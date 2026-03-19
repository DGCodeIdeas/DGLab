<?php

namespace DGLab\Services\MangaScript;

use DGLab\Services\BaseService;
use DGLab\Services\Contracts\ChunkedServiceInterface;
use DGLab\Core\Application;
use DGLab\Core\AuditService;

/**
 * MangaScript Service
 *
 * Orchestrates the transformation of novels into detailed manga scripts.
 */
class MangaScriptService extends BaseService implements ChunkedServiceInterface
{
    public const SERVICE_ID = 'manga-script';

    protected AI\RoutingEngine $routing;
    protected AuditService $audit;

    public function __construct(Application $app, AI\RoutingEngine $routing, AuditService $audit)
    {
        parent::__construct($app);
        $this->routing = $routing;
        $this->audit = $audit;
    }

    /**
     * Process a novel into a manga script
     */
    public function process(array $input): array
    {
        $startTime = microtime(true);
        $this->audit->log('mangascript', 'mangascript.process.started', $input['title'] ?? 'Untitled');

        try {
            // Placeholder for core AI logic refactor using llm_unified.php
            $result = $this->routing->route($input)->execute();

            $latency = (int)((microtime(true) - $startTime) * 1000);
            $this->audit->log(
                'mangascript',
                'mangascript.process.success',
                $input['title'] ?? 'Untitled',
                [],
                200,
                $latency
            );

            event('mangascript.process.completed', ['title' => $input['title'] ?? 'Untitled', 'latency' => $latency]);

            return $result;
        } catch (\Exception $e) {
            $this->audit->log(
                'mangascript',
                'mangascript.process.failed',
                $input['title'] ?? 'Untitled',
                ['error' => $e->getMessage()]
            );
            event('mangascript.process.failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Asynchronously process a novel
     */
    public function processAsync(array $input): string
    {
        $jobId = uuid();
        event('mangascript.job.requested', ['job_id' => $jobId, 'input' => $input]);
        return $jobId;
    }

    public function getStatus(string $processId): array
    {
        return ['status' => 'pending', 'id' => $processId];
    }

    public function stream(array $input): iterable
    {
        return [];
    }

    public function processChunk(array $chunk): array
    {
        return $this->process($chunk);
    }
}
