<?php

declare(strict_types=1);

namespace SovereignStack\External\Canvas;

/**
 * Validates content metadata at publish time, before it reaches ESPOKE-01's
 * render path. Called from ISPOKE-09's content workflow, not from ESPOKE-01
 * itself.
 *
 * Frozen per SDLC-AGRD §2.1.
 *
 * @package SovereignStack\External\Canvas
 */
interface SeoValidationInterface
{
    /**
     * Validate content metadata for SEO compliance.
     *
     * @param ContentMetadata $metadata The metadata to validate.
     *
     * @return array<int, string> Validation errors; empty array means valid.
     */
    public function validate(ContentMetadata $metadata): array;
}
