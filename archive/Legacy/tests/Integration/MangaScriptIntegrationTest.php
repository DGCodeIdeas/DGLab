<?php

namespace DGLab\Tests\Integration;

use DGLab\Tests\IntegrationTestCase;
use DGLab\Services\MangaScript\MangaScriptService;
use DGLab\Services\MangaScript\AI\RoutingEngine;
use DGLab\Core\AuditService;

class MangaScriptIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->setConfig('llm_unified.providers', [
            'test_provider' => [
                'category' => 'A',
                'models' => [
                    'test_model' => ['context_tier' => 'medium']
                ]
            ]
        ]);

        $this->app->singleton(RoutingEngine::class, function($app) {
            return new RoutingEngine($app);
        });

        $this->app->singleton(MangaScriptService::class, function($app) {
            return new MangaScriptService(
                $app,
                $app->get(RoutingEngine::class),
                $app->get(AuditService::class)
            );
        });
    }

    public function testMangaScriptSyncProcessFlow()
    {
        // 1. Manually resolve the service from the container
        // to ensure it uses our Faked dispatcher if we call fakeEvents() AFTER
        $service = $this->app->get(MangaScriptService::class);

        $this->fakeEvents();

        $input = [
            'title' => 'The Lone Alchemist',
            'category' => 'A',
            'tier' => 'medium'
        ];

        $result = $service->process($input);

        $this->assertEquals('The Lone Alchemist', $result['title']);

        // 2. Verify Event using our fake
        $this->assertEventDispatched('mangascript.process.completed');

        // 3. Verify Audit
        $this->assertAuditLogged('mangascript.process.success', ['identifier' => 'The Lone Alchemist']);
    }

    public function testMangaScriptAsyncJobFlow()
    {
        $service = $this->app->get(MangaScriptService::class);
        $this->fakeEvents();

        $jobId = $service->processAsync(['title' => 'Async Story']);

        $this->assertIsString($jobId);
        $this->assertEventDispatched('mangascript.job.requested');
    }
}
