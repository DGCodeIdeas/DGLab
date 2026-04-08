<?php

namespace DGLab\Tests\Integration\Nexus;

use PHPUnit\Framework\TestCase;
use DGLab\Services\Nexus\NexusClient;
use DGLab\Core\Application;
use Psr\Log\LoggerInterface;
use DGLab\Core\GenericEvent;

class NexusPhaseTwoTest extends TestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        $this->app = new Application(dirname(dirname(dirname(__DIR__))));
        $this->app->boot();
    }

    public function testNexusClientPublishesToRedis()
    {
        // Skip real Redis test in CI if not available, or just focus on the logic.
        // For now, I will mock the internal Redis client of NexusClient to verify it calls publish.
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $config = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 0
        ];

        $client = new class($config, $logger) extends NexusClient {
             public function getRedis() { return $this->redis; }
             public function setRedis($redis) { $this->redis = $redis; }
        };

        $mockRedis = $this->getMockBuilder(\Predis\Client::class)
            ->addMethods(['publish'])
            ->getMock();

        $mockRedis->expects($this->once())
            ->method('publish')
            ->with('nexus_broadcast', $this->stringContains('test.topic'));

        $client->setRedis($mockRedis);

        $result = $client->publish('test.topic', ['foo' => 'bar']);
        $this->assertTrue($result);
    }

    public function testEventDispatcherBroadcasts()
    {
        $nexusClient = $this->getMockBuilder(NexusClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $nexusClient->expects($this->once())
            ->method('publish')
            ->with($this->equalTo('job.progress'), $this->callback(function($payload) {
                return $payload['jobId'] === '123';
            }))
            ->willReturn(true);

        $this->app->set(NexusClient::class, $nexusClient);

        $this->app->set(\DGLab\Core\EventDrivers\BroadcastDriver::class, function ($app) use ($nexusClient) {
            return new \DGLab\Core\EventDrivers\BroadcastDriver($nexusClient);
        });

        $event = new GenericEvent('job.progress', ['jobId' => '123', 'progress' => 0.5, 'message' => 'Test']);

        $this->app->get(\DGLab\Core\Contracts\DispatcherInterface::class)->dispatch($event);
    }
}
