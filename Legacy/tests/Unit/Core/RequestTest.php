<?php

/**
 * Request Tests
 */

namespace DGLab\Tests\Unit\Core;

use DGLab\Core\Request;
use DGLab\Tests\TestCase;

class RequestTest extends \DGLab\Tests\TestCase
{
    public function testGetMethod(): void
    {
        $request = new Request([], [], [], ['REQUEST_METHOD' => 'POST']);
        $this->assertEquals('POST', $request->getMethod());
    }

    public function testGetPath(): void
    {
        $request = new Request([], [], [], ['REQUEST_URI' => '/test/path?foo=bar']);
        $this->assertEquals('/test/path', $request->getPath());
    }

    public function testQueryParameters(): void
    {
        $request = new Request(['foo' => 'bar', 'baz' => 'qux']);

        $this->assertEquals('bar', $request->query('foo'));
        $this->assertEquals('qux', $request->query('baz'));
        $this->assertNull($request->query('missing'));
        $this->assertEquals('default', $request->query('missing', 'default'));
    }

    public function testPostParameters(): void
    {
        $request = new Request([], ['name' => 'John', 'email' => 'john@example.com']);

        $this->assertEquals('John', $request->post('name'));
        $this->assertEquals('john@example.com', $request->post('email'));
    }

    public function testInputMethod(): void
    {
        $request = new Request(['get_param' => 'from_get'], ['post_param' => 'from_post']);

        $this->assertEquals('from_post', $request->input('post_param'));
        $this->assertEquals('from_get', $request->input('get_param'));
    }

    public function testHasAndFilled(): void
    {
        $request = new Request(['empty' => '', 'zero' => '0', 'filled' => 'value']);

        $this->assertTrue($request->has('filled'));
        $this->assertTrue($request->has('empty'));
        $this->assertFalse($request->has('missing'));

        $this->assertTrue($request->filled('filled'));
        $this->assertFalse($request->filled('empty'));
    }

    public function testIsAjax(): void
    {
        $ajaxRequest = new Request([], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $normalRequest = new Request();

        $this->assertTrue($ajaxRequest->isAjax());
        $this->assertFalse($normalRequest->isAjax());
    }

    public function testIsJson(): void
    {
        $jsonRequest = new Request([], [], [], ['CONTENT_TYPE' => 'application/json']);
        $normalRequest = new Request();

        $this->assertTrue($jsonRequest->isJson());
        $this->assertFalse($normalRequest->isJson());
    }

    public function testRouteParameters(): void
    {
        $request = new Request();
        $request = $request->withRouteParams(['id' => 123, 'slug' => 'test-post']);

        $this->assertEquals(123, $request->route('id'));
        $this->assertEquals('test-post', $request->route('slug'));
        $this->assertEquals('default', $request->route('missing', 'default'));
    }

    public function testOnlyAndExcept(): void
    {
        $request = new Request(['a' => '1', 'b' => '2', 'c' => '3']);

        $this->assertEquals(['a' => '1', 'b' => '2'], $request->only(['a', 'b']));
        $this->assertEquals(['a' => '1', 'c' => '3'], $request->except(['b']));
    }
}
