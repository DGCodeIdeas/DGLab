<?php

declare(strict_types=1);

namespace SovereignStack\Bridge\Tests;

use PHPUnit\Framework\TestCase;
use SovereignStack\Bridge\WafInspector;

/**
 * WAF inspector tests: SQLi/XSS/path-traversal detection + benign passthrough.
 *
 * @package SovereignStack\Bridge\Tests
 */
final class WafInspectorTest extends TestCase
{
    private WafInspector $waf;

    protected function setUp(): void
    {
        $this->waf = new WafInspector();
    }

    public function testSqlInjectionUnionSelect(): void
    {
        $hit = $this->waf->inspect('', '1 UNION SELECT * FROM users');
        self::assertNotNull($hit);
        self::assertSame('sqli.union_select', $hit);
    }

    public function testSqlInjectionOrOneEqualsOne(): void
    {
        $hit = $this->waf->inspect('', "admin' OR 1=1 --");
        self::assertNotNull($hit);
        self::assertSame('sqli.or_1_equals_1', $hit);
    }

    public function testSqlCommentMarkers(): void
    {
        $hit = $this->waf->inspect('', 'test--comment');
        self::assertNotNull($hit);
        self::assertSame('sqli.comment_markers', $hit);
    }

    public function testSqlStackedQueries(): void
    {
        $hit = $this->waf->inspect('', 'SELECT 1; DROP TABLE users');
        self::assertNotNull($hit);
        self::assertSame('sqli.stacked', $hit);
    }

    public function testXssScriptTag(): void
    {
        $hit = $this->waf->inspect('', '<script>alert(1)</script>');
        self::assertNotNull($hit);
        self::assertSame('xss.script_tag', $hit);
    }

    public function testXssJavascriptUri(): void
    {
        $hit = $this->waf->inspect('', '<a href="javascript:alert(1)">');
        self::assertNotNull($hit);
        self::assertSame('xss.javascript_uri', $hit);
    }

    public function testXssEventHandler(): void
    {
        $hit = $this->waf->inspect('', '<img onload=alert(1)>');
        self::assertNotNull($hit);
        self::assertSame('xss.event_handler', $hit);
    }

    public function testPathTraversal(): void
    {
        $hit = $this->waf->inspect('', '../../../etc/passwd');
        self::assertNotNull($hit);
        self::assertSame('path_traversal', $hit);
    }

    public function testPathTraversalEncoded(): void
    {
        $hit = $this->waf->inspect('', '..%2f..%2fetc');
        self::assertNotNull($hit);
        self::assertSame('path_traversal', $hit);
    }

    public function testBenignJsonPasses(): void
    {
        $hit = $this->waf->inspect('', json_encode(['name' => 'Alice', 'age' => 30]));
        self::assertNull($hit);
    }

    public function testBenignUrlPasses(): void
    {
        $hit = $this->waf->inspect('page=2&sort=name&dir=asc', '');
        self::assertNull($hit);
    }

    public function testBenignFormDataPasses(): void
    {
        $hit = $this->waf->inspect('', 'name=John&email=john@example.com&message=Hello');
        self::assertNull($hit);
    }

    public function testQueryStringIsScanned(): void
    {
        $hit = $this->waf->inspect('q=<script>alert(1)</script>', '');
        self::assertNotNull($hit);
        self::assertSame('xss.script_tag', $hit);
    }
}
