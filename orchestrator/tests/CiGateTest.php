<?php

declare(strict_types=1);

namespace SovereignStack\Orchestrator\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SovereignStack\Orchestrator\CiGate;

final class CiGateTest extends TestCase
{
    // ─── assertGreen — happy + sad paths ─────────────────────────────────────

    public function testAssertGreenPassesOnSuccessfulWorkflow(): void
    {
        $gate = $this->buildGate($this->makeRunResponse(123456, 'completed', 'success'));
        // Should not throw.
        $gate->assertGreen('packages-ci.yml', 'main');
        $this->addToAssertionCount(1);
    }

    public function testAssertGreenFailsOnFailedWorkflow(): void
    {
        $gate = $this->buildGate($this->makeRunResponse(123456, 'completed', 'failure'));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('concluded with failure');
        $gate->assertGreen('packages-ci.yml', 'main');
    }

    public function testAssertGreenFailsOnCancelledWorkflow(): void
    {
        $gate = $this->buildGate($this->makeRunResponse(123456, 'completed', 'cancelled'));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cancelled');
        $gate->assertGreen('packages-ci.yml', 'main');
    }

    public function testAssertGreenFailsOnInProgressRunNoTimeout(): void
    {
        $gate = $this->buildGate($this->makeRunResponse(123456, 'in_progress', null));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('timed out after 0');
        $gate->assertGreen('packages-ci.yml', 'main', 0);
    }

    public function testAssertGreenFailsWhenNoRunsExist(): void
    {
        $gate = $this->buildGate($this->makeEmptyRunsResponse());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('could not query');
        $gate->assertGreen('packages-ci.yml', 'main');
    }

    public function testAssertGreenFailsWhenRepoNotConfigured(): void
    {
        $gate = $this->buildGate($this->makeRunResponse(1, 'completed', 'success'), '', '');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not configured');
        $gate->assertGreen('packages-ci.yml', 'main');
    }

    public function testAssertGreenFailsOnHttpClientException(): void
    {
        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willThrowException(
            new class ('connection refused') extends \Exception implements ClientExceptionInterface {
            }
        );
        $factory = $this->makeRequestFactory();
        $gate = new CiGate('owner', 'repo', $client, $factory);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP request failed');
        $gate->assertGreen('packages-ci.yml', 'main');
    }

    public function testAssertGreenFailsOnNon200Response(): void
    {
        $gate = $this->buildGate($this->makeRawResponse('{"message": "Not Found"}', 404));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 404');
        $gate->assertGreen('packages-ci.yml', 'main');
    }

    public function testAssertGreenFailsOnMalformedJson(): void
    {
        $gate = $this->buildGate($this->makeRawResponse('not json', 200));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('malformed JSON');
        $gate->assertGreen('packages-ci.yml', 'main');
    }

    public function testAssertGreenFailsWhenWorkflowRunsMissing(): void
    {
        // 200 + valid JSON but no workflow_runs field.
        $gate = $this->buildGate($this->makeRawResponse('{"message": "ok"}', 200));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing workflow_runs');
        $gate->assertGreen('packages-ci.yml', 'main');
    }

    public function testAssertGreenWithNoHttpClientAndAutoDiscoverOff(): void
    {
        // No client + no factory + autoDiscover=false → gate cannot query.
        $gate = new CiGate('owner', 'repo', null, null, false);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No PSR-18 HTTP client');
        $gate->assertGreen('packages-ci.yml', 'main');
    }

    // ─── getLatestWorkflowRun — return value shape ──────────────────────────

    public function testGetLatestWorkflowRunReturnsExpectedFields(): void
    {
        $gate = $this->buildGate($this->makeRunResponse(987654, 'completed', 'success'));
        $result = $gate->getLatestWorkflowRun('packages-ci.yml', 'main');

        self::assertSame(987654, $result['runId']);
        self::assertSame('completed', $result['status']);
        self::assertSame('success', $result['conclusion']);
        self::assertStringContainsString('987654', $result['details']);
    }

    public function testGetLatestWorkflowRunFailsWhenRepoNotConfigured(): void
    {
        $gate = $this->buildGate($this->makeRunResponse(1, 'completed', 'success'), '', '');
        $result = $gate->getLatestWorkflowRun('packages-ci.yml', 'main');

        self::assertNull($result['runId']);
        self::assertNull($result['status']);
        self::assertNull($result['conclusion']);
        self::assertStringContainsString('not configured', $result['details']);
    }

    public function testGetLatestWorkflowRunFailsOnNon200(): void
    {
        $gate = $this->buildGate($this->makeRawResponse('{"message":"Unauthorized"}', 401));
        $result = $gate->getLatestWorkflowRun('packages-ci.yml', 'main');

        self::assertNull($result['runId']);
        self::assertNull($result['status']);
        self::assertNull($result['conclusion']);
        self::assertStringContainsString('HTTP 401', $result['details']);
    }

    public function testGetLatestWorkflowRunHandlesNullConclusion(): void
    {
        // in_progress runs have conclusion=null.
        $gate = $this->buildGate($this->makeRunResponse(123, 'in_progress', null));
        $result = $gate->getLatestWorkflowRun('packages-ci.yml', 'main');

        self::assertSame(123, $result['runId']);
        self::assertSame('in_progress', $result['status']);
        self::assertNull($result['conclusion']);
    }

    public function testGetLatestWorkflowRunHandlesNoRuns(): void
    {
        $gate = $this->buildGate($this->makeEmptyRunsResponse());
        $result = $gate->getLatestWorkflowRun('packages-ci.yml', 'main');

        self::assertNull($result['runId']);
        self::assertNull($result['status']);
        self::assertNull($result['conclusion']);
        self::assertStringContainsString('No workflow runs', $result['details']);
    }

    // ─── constructor + token ────────────────────────────────────────────────

    public function testConstructorReadsGithubRepositoryEnvVar(): void
    {
        $original = \getenv('GITHUB_REPOSITORY');
        try {
            \putenv('GITHUB_REPOSITORY=DGCodeIdeas/DGLab');
            $gate = new CiGate(null, null, null, null, false);
            self::assertSame('DGCodeIdeas', $gate->getRepoOwner());
            self::assertSame('DGLab', $gate->getRepoName());
        } finally {
            if ($original === false) {
                \putenv('GITHUB_REPOSITORY');
            } else {
                \putenv('GITHUB_REPOSITORY=' . $original);
            }
        }
    }

    public function testConstructorHandlesMissingGithubRepositoryEnvVar(): void
    {
        $original = \getenv('GITHUB_REPOSITORY');
        try {
            \putenv('GITHUB_REPOSITORY=');
            $gate = new CiGate(null, null, null, null, false);
            self::assertSame('', $gate->getRepoOwner());
            self::assertSame('', $gate->getRepoName());
        } finally {
            if ($original === false) {
                \putenv('GITHUB_REPOSITORY');
            } else {
                \putenv('GITHUB_REPOSITORY=' . $original);
            }
        }
    }

    public function testExplicitOwnerNameOverridesEnvVar(): void
    {
        $original = \getenv('GITHUB_REPOSITORY');
        try {
            \putenv('GITHUB_REPOSITORY=envowner/envrepo');
            $gate = new CiGate('explicit-owner', 'explicit-repo', null, null, false);
            self::assertSame('explicit-owner', $gate->getRepoOwner());
            self::assertSame('explicit-repo', $gate->getRepoName());
        } finally {
            if ($original === false) {
                \putenv('GITHUB_REPOSITORY');
            } else {
                \putenv('GITHUB_REPOSITORY=' . $original);
            }
        }
    }

    public function testSetTokenDoesNotBreakSubsequentQueries(): void
    {
        // We cannot easily verify the Authorization header is sent (PHPUnit
        // stubs return null from withHeader by default), but we can verify
        // that setToken doesn't prevent the query from succeeding.
        $gate = $this->buildGate($this->makeRunResponse(1, 'completed', 'success'));
        $gate->setToken('fake-token-abc');
        $gate->assertGreen('packages-ci.yml', 'main');
        $this->addToAssertionCount(1);
    }

    // ─── test helpers ───────────────────────────────────────────────────────

    /**
     * Build a CiGate wired to a stub HTTP client that always returns $response.
     */
    private function buildGate(
        ResponseInterface $response,
        string $owner = 'owner',
        string $repo = 'repo',
    ): CiGate {
        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);
        $factory = $this->makeRequestFactory();
        return new CiGate($owner, $repo, $client, $factory);
    }

    private function makeRequestFactory(): RequestFactoryInterface
    {
        $request = $this->createStub(RequestInterface::class);
        // withHeader returns self so the call chain works.
        $request->method('withHeader')->willReturnSelf();

        $factory = $this->createStub(RequestFactoryInterface::class);
        $factory->method('createRequest')->willReturn($request);
        return $factory;
    }

    private function makeRunResponse(int $runId, string $status, ?string $conclusion): ResponseInterface
    {
        $conclusionJson = $conclusion === null ? 'null' : \json_encode($conclusion);
        $body = \sprintf(
            '{"workflow_runs":[{"id":%d,"status":%s,"conclusion":%s}]}',
            $runId,
            \json_encode($status),
            $conclusionJson
        );
        return $this->makeRawResponse($body, 200);
    }

    private function makeEmptyRunsResponse(): ResponseInterface
    {
        return $this->makeRawResponse('{"workflow_runs":[]}', 200);
    }

    private function makeRawResponse(string $body, int $status): ResponseInterface
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn($body);
        $stream->method('getContents')->willReturn($body);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn($stream);
        return $response;
    }
}
