<?php

namespace DGLab\Tests\Unit\Nexus;

use PHPUnit\Framework\TestCase;
use DGLab\Services\Nexus\RedisBroker;
use Psr\Log\LoggerInterface;

class RedisBrokerTest extends TestCase
{
    public function testRedisBrokerInitializesCorrectly()
    {
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $broker = new RedisBroker('localhost', 6379, 'password', 1, $logger);

        $this->assertInstanceOf(RedisBroker::class, $broker);
    }
}
