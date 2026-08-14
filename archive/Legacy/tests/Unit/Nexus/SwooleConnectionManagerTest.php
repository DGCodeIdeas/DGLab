<?php

namespace DGLab\Tests\Unit\Nexus;

use PHPUnit\Framework\TestCase;
use DGLab\Services\Nexus\SwooleConnectionManager;

class SwooleConnectionManagerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists('Swoole\Table')) {
            $this->markTestSkipped('Swoole extension is required for SwooleConnectionManager tests.');
        }
    }

    public function testAddAndGet()
    {
        $manager = new SwooleConnectionManager();
        $manager->add(1, ['user_id' => 10, 'tenant_id' => 5]);

        $data = $manager->get(1);
        $this->assertEquals(10, $data['user_id']);
        $this->assertEquals(5, $data['tenant_id']);
    }
}
