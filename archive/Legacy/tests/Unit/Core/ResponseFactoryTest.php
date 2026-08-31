<?php

namespace DGLab\Tests\Unit\Core;

use DGLab\Core\ResponseFactory;
use DGLab\Core\Response;
use DGLab\Tests\TestCase;

class ResponseFactoryTest extends TestCase
{
    private ResponseFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new ResponseFactory();
    }

    public function testCreate()
    {
        $response = $this->factory->create('Hello', 201, ['X-Test' => 'Foo']);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('Hello', $response->getContent());
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('Foo', $response->getHeader('X-Test'));
    }

    public function testJson()
    {
        $response = $this->factory->json(['foo' => 'bar'], 202);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('application/json', $response->getHeader('Content-Type'));
        $this->assertEquals(json_encode(['foo' => 'bar']), $response->getContent());
        $this->assertEquals(202, $response->getStatusCode());
    }

    public function testRedirect()
    {
        $response = $this->factory->redirect('/home', 301);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('/home', $response->getHeader('Location'));
    }
}
