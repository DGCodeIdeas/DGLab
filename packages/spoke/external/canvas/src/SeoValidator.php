<?php

declare(strict_types=1);

namespace SovereignStack\External\Canvas;

/**
 * Validates ContentMetadata for SEO compliance.
 *
 * Checks: title length (10-60), description length (50-160), canonical URL
 * validity, keyword count (max 10). Returns an array of error messages —
 * empty means valid.
 *
 * @package SovereignStack\External\Canvas
 */
final class SeoValidator implements SeoValidationInterface
{
    private const TITLE_MIN = 10;
    private const TITLE_MAX = 60;
    private const DESC_MIN  = 50;
    private const DESC_MAX  = 160;
    private const KEYWORD_MAX = 10;

    public function validate(ContentMetadata $metadata): array
    {
        $errors = [];

        $titleLen = strlen($metadata->title);
        if ($titleLen < self::TITLE_MIN) {
            $errors[] = sprintf('Title is too short (%d chars, minimum %d).', $titleLen, self::TITLE_MIN);
        }
        if ($titleLen > self::TITLE_MAX) {
            $errors[] = sprintf('Title is too long (%d chars, maximum %d).', $titleLen, self::TITLE_MAX);
        }

        $descLen = strlen($metadata->description);
        if ($descLen < self::DESC_MIN) {
            $errors[] = sprintf('Description is too short (%d chars, minimum %d).', $descLen, self::DESC_MIN);
        }
        if ($descLen > self::DESC_MAX) {
            $errors[] = sprintf('Description is too long (%d chars, maximum %d).', $descLen, self::DESC_MAX);
        }

        if (filter_var($metadata->canonicalUrl, FILTER_VALIDATE_URL) === false) {
            $errors[] = sprintf('Canonical URL [%s] is not a valid URL.', $metadata->canonicalUrl);
        }

        if (count($metadata->keywords) > self::KEYWORD_MAX) {
            $errors[] = sprintf('Too many keywords (%d, maximum %d).', count($metadata->keywords), self::KEYWORD_MAX);
        }

        return $errors;
    }
}
