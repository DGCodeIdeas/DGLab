<?php

declare(strict_types=1);

namespace SovereignStack\Orchestrator;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Gate that refuses to create a tag if the latest run of a given GitHub
 * Actions workflow on a given branch is not green (conclusion === 'success').
 *
 * Implements P1 gap 6: `--require-ci-green` on `loom tag:create`.
 *
 * Queries the GitHub Actions REST API:
 *   GET /repos/{owner}/{repo}/actions/workflows/{workflowFileName}/runs
 *       ?branch={branch}&per_page=1
 *
 * The response is `{workflow_runs: [{id, status, conclusion, ...}]}`.
 *   - status:      'queued' | 'in_progress' | 'completed'
 *   - conclusion:  null until status===completed, then one of:
 *                  'success' | 'failure' | 'cancelled' | 'timed_out' |
 *                  'skipped' | 'neutral' | 'action_required' | 'stale'
 *
 * The gate is satisfied only when status==='completed' AND
 * conclusion==='success'.
 */
class CiGate
{
    private string $repoOwner;

    private string $repoName;

    private ?ClientInterface $httpClient;

    private ?RequestFactoryInterface $requestFactory;

    private string $token = '';

    /**
     * @param string|null                   $repoOwner       GitHub repo owner (e.g. "DGCodeIdeas").
     *                                                       Defaults to GITHUB_REPOSITORY env var.
     * @param string|null                   $repoName        GitHub repo name (e.g. "DGLab").
     *                                                       Defaults to GITHUB_REPOSITORY env var.
     * @param ClientInterface|null          $httpClient      PSR-18 client. Auto-discovered if null.
     * @param RequestFactoryInterface|null  $requestFactory  PSR-17 request factory. Auto-discovered if null.
     * @param bool                          $autoDiscover    When false, do NOT invoke PSR-18/17 discovery
     *                                                       even if httpClient/requestFactory are null.
     *                                                       Used by tests to assert the no-client code path.
     */
    public function __construct(
        ?string $repoOwner = null,
        ?string $repoName = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        bool $autoDiscover = true,
    ) {
        // If owner/name not provided, try GITHUB_REPOSITORY env var ("owner/repo").
        if ($repoOwner === null || $repoName === null) {
            $githubRepo = \getenv('GITHUB_REPOSITORY') ?: '';
            $parts = \explode('/', $githubRepo);
            $repoOwner = $repoOwner ?? ($parts[0] ?? '');
            $repoName = $repoName ?? ($parts[1] ?? '');
        }
        $this->repoOwner = $repoOwner !== null ? $repoOwner : '';
        $this->repoName = $repoName !== null ? $repoName : '';

        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;

        if ($autoDiscover && ($this->httpClient === null || $this->requestFactory === null)) {
            try {
                $this->httpClient ??= Psr18ClientDiscovery::find();
            } catch (\RuntimeException) {
                // Discovery failed — leave httpClient as whatever was passed.
            }
            try {
                $this->requestFactory ??= Psr17FactoryDiscovery::findRequestFactory();
            } catch (\RuntimeException) {
                // Discovery failed — leave requestFactory as whatever was passed.
            }
        }
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getRepoOwner(): string
    {
        return $this->repoOwner;
    }

    public function getRepoName(): string
    {
        return $this->repoName;
    }

    /**
     * Query the latest run of $workflowFileName on $branch.
     *
     * @return array{runId: int|null, status: string|null, conclusion: string|null, details: string}
     *     - runId:       the numeric GitHub Actions run ID, or null if unknown.
     *     - status:      'queued' | 'in_progress' | 'completed', or null if unknown.
     *     - conclusion:  'success' | 'failure' | etc., or null until status===completed.
     *     - details:     human-readable diagnostic string.
     */
    public function getLatestWorkflowRun(string $workflowFileName, string $branch = 'main'): array
    {
        if ($this->httpClient === null || $this->requestFactory === null) {
            return [
                'runId' => null,
                'status' => null,
                'conclusion' => null,
                'details' => 'No PSR-18 HTTP client available — cannot query GitHub Actions API.',
            ];
        }

        if ($this->repoOwner === '' || $this->repoName === '') {
            return [
                'runId' => null,
                'status' => null,
                'conclusion' => null,
                'details' => 'Repository owner/name not configured (pass to constructor or set GITHUB_REPOSITORY env var).',
            ];
        }

        $url = \sprintf(
            'https://api.github.com/repos/%s/%s/actions/workflows/%s/runs?branch=%s&per_page=1',
            \urlencode($this->repoOwner),
            \urlencode($this->repoName),
            \urlencode($workflowFileName),
            \urlencode($branch)
        );

        $request = $this->requestFactory->createRequest('GET', $url);
        $request = $request
            ->withHeader('Accept', 'application/vnd.github+json')
            ->withHeader('User-Agent', 'loom-cigate/1.0');
        if ($this->token !== '') {
            $request = $request->withHeader('Authorization', 'Bearer ' . $this->token);
        }

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            return [
                'runId' => null,
                'status' => null,
                'conclusion' => null,
                'details' => 'HTTP request failed: ' . $e->getMessage(),
            ];
        }

        return $this->parseRunResponse($response);
    }

    /**
     * Assert that the latest run of $workflowFileName on $branch has
     * conclusion === 'success'.
     *
     * @param string $workflowFileName Workflow file name (e.g. "packages-ci.yml").
     * @param string $branch           Branch name (default: 'main').
     * @param int    $timeoutSeconds   If >0, poll up to this many seconds waiting
     *                                 for the run to complete. Polls every 5 seconds.
     *
     * @throws \RuntimeException if the run is missing, failed, in-progress past
     *                           timeout, or no HTTP client is configured.
     */
    public function assertGreen(string $workflowFileName, string $branch = 'main', int $timeoutSeconds = 0): void
    {
        $deadline = \time() + $timeoutSeconds;

        while (true) {
            $result = $this->getLatestWorkflowRun($workflowFileName, $branch);

            if ($result['status'] === null) {
                // Could not query — propagate as error.
                throw new \RuntimeException('CI gate could not query workflow runs: ' . $result['details']);
            }

            if ($result['status'] === 'completed') {
                if ($result['conclusion'] !== 'success') {
                    throw new \RuntimeException(\sprintf(
                        'CI gate failed: latest run of %s on %s (run %s) concluded with %s, not success.',
                        $workflowFileName,
                        $branch,
                        $result['runId'] !== null ? (string) $result['runId'] : '?',
                        $result['conclusion'] ?? 'null'
                    ));
                }
                return; // green
            }

            // Status is 'queued' or 'in_progress' — wait or fail.
            if (\time() >= $deadline) {
                throw new \RuntimeException(\sprintf(
                    'CI gate timed out after %d seconds: latest run of %s on %s is still %s.',
                    $timeoutSeconds,
                    $workflowFileName,
                    $branch,
                    $result['status']
                ));
            }
            \sleep(5);
        }
    }

    /**
     * @return array{runId: int|null, status: string|null, conclusion: string|null, details: string}
     */
    private function parseRunResponse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            return [
                'runId' => null,
                'status' => null,
                'conclusion' => null,
                'details' => 'GitHub API returned HTTP ' . $statusCode . ': ' . \substr($body, 0, 200),
            ];
        }

        $json = \json_decode($body, true);
        if (!\is_array($json) || !isset($json['workflow_runs']) || !\is_array($json['workflow_runs'])) {
            return [
                'runId' => null,
                'status' => null,
                'conclusion' => null,
                'details' => 'GitHub API returned malformed JSON or missing workflow_runs field.',
            ];
        }

        $runs = $json['workflow_runs'];
        if ($runs === []) {
            return [
                'runId' => null,
                'status' => null,
                'conclusion' => null,
                'details' => 'No workflow runs found for the requested branch.',
            ];
        }

        $run = $runs[0];
        if (!\is_array($run)) {
            return [
                'runId' => null,
                'status' => null,
                'conclusion' => null,
                'details' => 'GitHub API returned a malformed run entry.',
            ];
        }

        $rawId = $run['id'] ?? null;
        $runId = (\is_int($rawId) || (\is_string($rawId) && \ctype_digit($rawId))) ? (int) $rawId : null;
        $status = isset($run['status']) && \is_string($run['status']) ? $run['status'] : null;
        // Note: isset() returns false for null values, so a JSON-encoded null
        // conclusion (status=in_progress) correctly yields $conclusion=null.
        $conclusion = isset($run['conclusion']) && \is_string($run['conclusion'])
            ? $run['conclusion']
            : null;

        return [
            'runId' => $runId,
            'status' => $status,
            'conclusion' => $conclusion,
            'details' => 'Run ' . ($runId !== null ? (string) $runId : '?') . ': status=' . ($status ?? 'null') . ', conclusion=' . ($conclusion ?? 'null'),
        ];
    }
}
