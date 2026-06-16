<?php

namespace DGLab\Tests\Unit\Services\Superpowers;

use DGLab\Services\Superpowers\Runtime\GlobalStateStoreInterface;
use DGLab\Services\Superpowers\Runtime\GlobalStateStore;
use DGLab\Tests\TestCase;

class GlobalStateStoreContractTest extends TestCase
{
    protected function createStore(): GlobalStateStoreInterface
    {
        return new GlobalStateStore();
    }

    public function testSetAndGet()
    {
        $store = $this->createStore();
        $store->set('foo', 'bar');
        $this->assertEquals('bar', $store->get('foo'));
    }

    public function testGetDefault()
    {
        $store = $this->createStore();
        $this->assertEquals('default', $store->get('nonexistent', 'default'));
    }

    public function testAll()
    {
        $store = $this->createStore();
        $store->set('a', 1);
        $store->set('b', 2);
        $this->assertEquals(['a' => 1, 'b' => 2], $store->all());
    }

    public function testForget()
    {
        $store = $this->createStore();
        $store->set('foo', 'bar');
        $store->forget('foo');
        $this->assertNull($store->get('foo'));
    }

    public function testClear()
    {
        $store = $this->createStore();
        $store->set('foo', 'bar');
        $store->clear();
        $this->assertEmpty($store->all());
    }

    public function testNonSerializableThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $store = $this->createStore();
        $store->set('closure', function() {});
    }
}
