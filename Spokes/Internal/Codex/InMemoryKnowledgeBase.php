<?php

declare(strict_types=1);

namespace SovereignStack\Internal\Codex;

/**
 * In-memory KnowledgeBase implementation for depth-2 (pre-DBAL) usage.
 *
 * Stores documents in a PHP array with full version history. Each saveDocument()
 * call creates a new version. The public flag is set via markPublic() / markPrivate().
 *
 * When CORE-19 (DBAL) lands, replace with a DBAL-backed implementation —
 * the KnowledgeBaseInterface contract is unchanged.
 *
 * @package SovereignStack\Internal\Codex
 */
final class InMemoryKnowledgeBase implements KnowledgeBaseInterface
{
    /**
     * @var array<string, list<array{content: string, summary: string, staff_id: string, updated_at: string, is_public: bool}>>
     */
    private array $documents = [];

    public function getDocument(string $slug, ?int $version = null): array
    {
        if (!isset($this->documents[$slug])) {
            throw DocumentNotFoundException::forSlug($slug);
        }

        $versions = $this->documents[$slug];

        if ($version === null) {
            $version = count($versions);
        }

        if ($version < 1 || $version > count($versions)) {
            throw DocumentNotFoundException::forVersion($slug, $version);
        }

        $entry = $versions[$version - 1];

        return [
            'slug'        => $slug,
            'content'     => $entry['content'],
            'version'     => $version,
            'summary'     => $entry['summary'],
            'staff_id'    => $entry['staff_id'],
            'updated_at'  => $entry['updated_at'],
            'is_public'   => $entry['is_public'],
        ];
    }

    public function saveDocument(string $slug, string $content, string $staffId, string $summary): bool
    {
        $isPublic = false;

        // If the document already exists, inherit the public flag from the latest version.
        if (isset($this->documents[$slug]) && $this->documents[$slug] !== []) {
            $latest = $this->documents[$slug][count($this->documents[$slug]) - 1];
            $isPublic = $latest['is_public'];
        }

        $this->documents[$slug][] = [
            'content'     => $content,
            'summary'     => $summary,
            'staff_id'    => $staffId,
            'updated_at'  => date('c'),
            'is_public'   => $isPublic,
        ];

        return true;
    }

    public function isPublic(string $slug): bool
    {
        if (!isset($this->documents[$slug]) || $this->documents[$slug] === []) {
            return false;
        }

        $latest = $this->documents[$slug][count($this->documents[$slug]) - 1];
        return $latest['is_public'];
    }

    /**
     * Mark a document as public (latest version only).
     */
    public function markPublic(string $slug): void
    {
        $this->assertExists($slug);
        $latest = count($this->documents[$slug]) - 1;
        $this->documents[$slug][$latest]['is_public'] = true;
    }

    /**
     * Mark a document as private (latest version only).
     */
    public function markPrivate(string $slug): void
    {
        $this->assertExists($slug);
        $latest = count($this->documents[$slug]) - 1;
        $this->documents[$slug][$latest]['is_public'] = false;
    }

    /**
     * Get the version count for a document.
     */
    public function versionCount(string $slug): int
    {
        if (!isset($this->documents[$slug])) {
            return 0;
        }
        return count($this->documents[$slug]);
    }

    private function assertExists(string $slug): void
    {
        if (!isset($this->documents[$slug]) || $this->documents[$slug] === []) {
            throw DocumentNotFoundException::forSlug($slug);
        }
    }
}
