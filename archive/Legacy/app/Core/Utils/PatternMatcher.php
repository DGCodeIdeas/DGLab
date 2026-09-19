<?php

namespace DGLab\Core\Utils;

/**
 * Class PatternMatcher
 *
 * Utility for matching dot-notation strings against wildcard patterns.
 * Supports:
 * - '*' : matches exactly one segment (e.g., 'user.*' matches 'user.login' but not 'user.profile.update')
 * - '**' : matches one or more segments (e.g., 'user.**' matches both 'user.login' and 'user.profile.update')
 */
class PatternMatcher
{
    /**
     * Check if a string matches a given pattern.
     *
     * @param string $pattern
     * @param string $value
     * @return bool
     */
    public static function matches(string $pattern, string $value): bool
    {
        if ($pattern === $value || $pattern === '**') {
            return true;
        }

        // Convert wildcard pattern to a regular expression
        $regex = self::patternToRegex($pattern);

        return (bool) preg_match($regex, $value);
    }

    /**
     * Convert a wildcard pattern to a regular expression.
     *
     * @param string $pattern
     * @return string
     */
    protected static function patternToRegex(string $pattern): string
    {
        // Escape special characters except our wildcards
        $quoted = preg_quote($pattern, '#');

        // Replace '**' with a multi-segment match (including dots)
        // We use a non-greedy match to avoid over-matching if multiple ** exist
        $regex = str_replace('\*\*', '.*', $quoted);

        // Replace '*' with a single-segment match (excluding dots)
        $regex = str_replace('\*', '[^.]*', $regex);

        return '#^' . $regex . '$#';
    }
}
