<?php

declare(strict_types=1);

namespace SovereignStack\Orchestrator;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

class CIMonitor
{
    /** @var array<int, array{name: string, ci_url: string, ci_token: string}> */
    private array $repos = [];

    private ?ClientInterface $httpClient = null;

    private ?RequestFactoryInterface $requestFactory = null;

    public function __construct()
    {
        try {
            $this->httpClient = Psr18ClientDiscovery::find();
            $this->requestFactory = Psr17FactoryDiscovery::findRequestFactory();
        } catch (\RuntimeException) {
            // No HTTP client available; will fall back to local execution
        }
    }

    public function registerRepo(string $name, string $ciUrl, string $ciToken = ''): void
    {
        $this->repos[] = [
            'name' => $name,
            'ci_url' => $ciUrl,
            'ci_token' => $ciToken,
        ];
    }

    /**
     * @return array{status: string, details: string}
     */
    public function check(string $name): array
    {
        $repo = $this->findRepo($name);

        if ($repo === null) {
            return [
                'status' => 'unknown',
                'details' => "Repository '{$name}' is not registered.",
            ];
        }

        if ($this->httpClient !== null && $this->requestFactory !== null) {
            return $this->checkViaHttp($repo, $this->httpClient, $this->requestFactory);
        }

        return $this->checkViaLocalExecution($repo);
    }

    /**
     * @return array<string, array{status: string, details: string}>
     */
    public function checkAll(): array
    {
        $results = [];

        foreach ($this->repos as $repo) {
            $results[$repo['name']] = $this->check($repo['name']);
        }

        return $results;
    }

    /**
     * @return array<int, string>
     */
    public function getRegisteredRepos(): array
    {
        return \array_map(function (array $repo): string {
            return $repo['name'];
        }, $this->repos);
    }

    /**
     * @return array{name: string, ci_url: string, ci_token: string}|null
     */
    private function findRepo(string $name): ?array
    {
        foreach ($this->repos as $repo) {
            if ($repo['name'] === $name) {
                return $repo;
            }
        }

        return null;
    }

    /**
     * @param array{name: string, ci_url: string, ci_token: string} $repo
     * @return array{status: string, details: string}
     */
    private function checkViaHttp(array $repo, ClientInterface $client, RequestFactoryInterface $factory): array
    {
        $request = $factory->createRequest('GET', $repo['ci_url']);

        if ($repo['ci_token'] !== '') {
            $request = $request->withHeader('Authorization', 'Bearer ' . $repo['ci_token']);
        }

        try {
            $response = $client->sendRequest($request);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                return [
                    'status' => 'pass',
                    'details' => "HTTP {$statusCode}: CI endpoint responded successfully.",
                ];
            }

            if ($statusCode >= 400 && $statusCode < 500) {
                return [
                    'status' => 'fail',
                    'details' => "HTTP {$statusCode}: CI endpoint returned client error.",
                ];
            }

            return [
                'status' => 'pending',
                'details' => "HTTP {$statusCode}: CI endpoint returned server status.",
            ];
        } catch (ClientExceptionInterface $e) {
            return [
                'status' => 'fail',
                'details' => 'HTTP request failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @param array{name: string, ci_url: string, ci_token: string} $repo
     * @return array{status: string, details: string}
     */
    private function checkViaLocalExecution(array $repo): array
    {
        $repoDir = $repo['ci_url'];
        $ciScript = $repoDir . '/ci/run.php';

        if (!\is_dir($repoDir)) {
            return [
                'status' => 'unknown',
                'details' => "Local repo directory not found: {$repoDir}",
            ];
        }

        if (!\file_exists($ciScript)) {
            return [
                'status' => 'unknown',
                'details' => "CI script not found at: {$ciScript}",
            ];
        }

        $command = \sprintf('php %s', \escapeshellarg($ciScript));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = \proc_open($command, $descriptors, $pipes, $repoDir);

        if (!\is_resource($process)) {
            return [
                'status' => 'fail',
                'details' => 'Failed to launch CI process.',
            ];
        }

        \fclose($pipes[0]);
        $stdout = \stream_get_contents($pipes[1]);
        \fclose($pipes[1]);
        $stderr = \stream_get_contents($pipes[2]);
        \fclose($pipes[2]);

        $exitCode = \proc_close($process);

        if ($exitCode === 0) {
            return [
                'status' => 'pass',
                'details' => 'CI script executed successfully (exit code 0).',
            ];
        }

        $details = \sprintf(
            'CI script failed (exit code %d).',
            $exitCode
        );

        if ($stderr !== false && $stderr !== '') {
            $details .= ' STDERR: ' . \substr($stderr, 0, 500);
        } elseif ($stdout !== false && $stdout !== '') {
            $details .= ' STDOUT: ' . \substr($stdout, 0, 500);
        }

        return [
            'status' => 'fail',
            'details' => $details,
        ];
    }
}
