<?php

declare(strict_types=1);

namespace SovereignStack\Internal\Codex;

/**
 * Knowledge base contract for the Sovereign Codex (ISPOKE-09).
 *
 * The load-bearing method is isPublic(): BRIDGE-01's DtoTransformerInterface
 * reads this to decide whether a document may be served to ESPOKE-01 (public
 * CMS). A document is NEVER public by default.
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * @package SovereignStack\Internal\Codex
 */
interface KnowledgeBaseInterface
{
    /**
     * Get a document by slug, optionally at a specific version.
     *
     * @param string   $slug    The document's URL-safe identifier.
     * @param int|null $version Version number (1-based). Null = latest.
     *
     * @return array{slug: string, content: string, version: int, summary: string, staff_id: string, updated_at: string, is_public: bool}
     *
     * @throws DocumentNotFoundException If the slug (or version) does not exist.
     */
    public function getDocument(string $slug, ?int $version = null): array;

    /**
     * Save a document (create or update). Creates a new version on each save.
     *
     * @param string $slug    The document's URL-safe identifier.
     * @param string $content Markdown content.
     * @param string $staffId ULID of the staff member making the edit.
     * @param string $summary Human-readable edit summary.
     *
     * @return bool True on success.
     */
    public function saveDocument(string $slug, string $content, string $staffId, string $summary): bool;

    /**
     * Whether the document (latest version) is marked public.
     *
     * This is the load-bearing method for the BRIDGE-01 security boundary:
     * only documents with isPublic() === true are ever offered to BRIDGE-01's
     * registered contract for ESPOKE-01. Internal-only SOPs are structurally
     * unreachable from the public tier, not just access-controlled.
     */
    public function isPublic(string $slug): bool;
}
