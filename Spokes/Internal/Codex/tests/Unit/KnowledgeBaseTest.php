<?php

declare(strict_types=1);

namespace SovereignStack\Internal\Codex\Tests;

use PHPUnit\Framework\TestCase;
use SovereignStack\Internal\Codex\DocumentNotFoundException;
use SovereignStack\Internal\Codex\InMemoryKnowledgeBase;

/**
 * KnowledgeBase tests: save/get, version history, public marker, boundary enforcement.
 *
 * @package SovereignStack\Internal\Codex\Tests
 */
final class KnowledgeBaseTest extends TestCase
{
    private InMemoryKnowledgeBase $kb;

    protected function setUp(): void
    {
        $this->kb = new InMemoryKnowledgeBase();
    }

    public function testSaveAndGetDocument(): void
    {
        $this->kb->saveDocument('getting-started', '# Hello World', 'staff-001', 'Initial draft');

        $doc = $this->kb->getDocument('getting-started');
        self::assertSame('getting-started', $doc['slug']);
        self::assertSame('# Hello World', $doc['content']);
        self::assertSame(1, $doc['version']);
        self::assertSame('Initial draft', $doc['summary']);
        self::assertSame('staff-001', $doc['staff_id']);
        self::assertFalse($doc['is_public']);
    }

    public function testVersionHistoryIncrements(): void
    {
        $this->kb->saveDocument('sop', 'v1 content', 'staff-001', 'First');
        $this->kb->saveDocument('sop', 'v2 content', 'staff-002', 'Second');

        self::assertSame(2, $this->kb->versionCount('sop'));

        $v1 = $this->kb->getDocument('sop', 1);
        self::assertSame('v1 content', $v1['content']);
        self::assertSame(1, $v1['version']);

        $v2 = $this->kb->getDocument('sop', 2);
        self::assertSame('v2 content', $v2['content']);
        self::assertSame(2, $v2['version']);

        $latest = $this->kb->getDocument('sop');
        self::assertSame(2, $latest['version']);
    }

    public function testVersionIntegrityByteForByte(): void
    {
        $content = "# SOP Title\n\nStep 1: Do the thing.\nStep 2: Do more.\n";
        $this->kb->saveDocument('sop', $content, 'staff-001', 'Initial');
        $this->kb->saveDocument('sop', $content . 'Extra content', 'staff-002', 'Updated');

        $v1 = $this->kb->getDocument('sop', 1);
        self::assertSame($content, $v1['content'], 'Version 1 content should be byte-for-byte identical to what was saved.');
    }

    public function testDocumentNotFoundThrows(): void
    {
        $this->expectException(DocumentNotFoundException::class);
        $this->kb->getDocument('nonexistent');
    }

    public function testVersionNotFoundThrows(): void
    {
        $this->kb->saveDocument('sop', 'content', 'staff-001', 'First');

        $this->expectException(DocumentNotFoundException::class);
        $this->kb->getDocument('sop', 99);
    }

    public function testDocumentIsPrivateByDefault(): void
    {
        $this->kb->saveDocument('internal-sop', 'secret content', 'staff-001', 'Internal only');
        self::assertFalse($this->kb->isPublic('internal-sop'));
    }

    public function testMarkPublicThenIsPublic(): void
    {
        $this->kb->saveDocument('public-doc', 'content', 'staff-001', 'First');
        self::assertFalse($this->kb->isPublic('public-doc'));

        $this->kb->markPublic('public-doc');
        self::assertTrue($this->kb->isPublic('public-doc'));
    }

    public function testMarkPrivateAfterPublic(): void
    {
        $this->kb->saveDocument('doc', 'content', 'staff-001', 'First');
        $this->kb->markPublic('doc');
        self::assertTrue($this->kb->isPublic('doc'));

        $this->kb->markPrivate('doc');
        self::assertFalse($this->kb->isPublic('doc'));
    }

    public function testPublicFlagInheritedOnNewVersion(): void
    {
        $this->kb->saveDocument('doc', 'v1', 'staff-001', 'First');
        $this->kb->markPublic('doc');
        self::assertTrue($this->kb->isPublic('doc'));

        // Save a new version — should inherit the public flag.
        $this->kb->saveDocument('doc', 'v2', 'staff-002', 'Second');
        self::assertTrue($this->kb->isPublic('doc'));

        $v2 = $this->kb->getDocument('doc', 2);
        self::assertTrue($v2['is_public']);
    }

    public function testPublicInternalBoundaryEnforced(): void
    {
        // Internal document — should NOT be accessible from the public tier.
        $this->kb->saveDocument('internal-only', 'secret SOP', 'staff-001', 'Internal');
        self::assertFalse($this->kb->isPublic('internal-only'));

        // Public document — safe to serve via BRIDGE-01 to ESPOKE-01.
        $this->kb->saveDocument('public-guide', '# Welcome', 'staff-001', 'Public guide');
        $this->kb->markPublic('public-guide');
        self::assertTrue($this->kb->isPublic('public-guide'));

        // The boundary: isPublic() is the gate. BRIDGE-01 checks this before
        // forwarding to ESPOKE-01. An internal document returns false → BRIDGE-01
        // returns 403 (default-deny). This is the load-bearing security test
        // per ISPOKE-09 CI criterion 4.
    }

    public function testMarkPublicOnNonexistentThrows(): void
    {
        $this->expectException(DocumentNotFoundException::class);
        $this->kb->markPublic('nonexistent');
    }

    public function testMultipleDocuments(): void
    {
        $this->kb->saveDocument('doc-a', 'A content', 'staff-001', 'A');
        $this->kb->saveDocument('doc-b', 'B content', 'staff-002', 'B');
        $this->kb->saveDocument('doc-a', 'A v2', 'staff-001', 'A updated');

        self::assertSame(2, $this->kb->versionCount('doc-a'));
        self::assertSame(1, $this->kb->versionCount('doc-b'));

        $a = $this->kb->getDocument('doc-a');
        self::assertSame('A v2', $a['content']);

        $b = $this->kb->getDocument('doc-b');
        self::assertSame('B content', $b['content']);
    }
}
