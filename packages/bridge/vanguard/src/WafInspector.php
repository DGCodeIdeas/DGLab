<?php

declare(strict_types=1);

namespace SovereignStack\Bridge;

/**
 * WAF inspector — pure-logic regex scan for common attack patterns.
 *
 * Scans query string, parsed body, and raw body. On hit, returns the pattern
 * name (never the matched payload). At depth 2, this is the real implementation
 * (no external dependencies — just PCRE).
 *
 * @package SovereignStack\Bridge
 */
final class WafInspector
{
    /**
     * @return array<string, string> Pattern name → regex
     */
    private function patterns(): array
    {
        return [
            'sqli.union_select'   => '/\bunion\s+select\b/i',
            'sqli.or_1_equals_1'  => '/\bor\s+1\s*=\s*1\b/i',
            'sqli.comment_markers' => '/(--|\/\*|\*\/|#)/',
            'sqli.stacked'        => '/;\s*(drop|insert|update|delete|select)\b/i',
            'xss.script_tag'      => '/<script\b/i',
            'xss.javascript_uri'  => '/javascript:/i',
            'xss.event_handler'   => '/\bon\w+\s*=/i',
            'path_traversal'      => '/\.\.[\/\\\\]|\.\.%2f|\.\.%5c/i',
        ];
    }

    /**
     * Scan the request for attack patterns.
     *
     * @param string $queryString  The raw query string.
     * @param string $rawBody      The raw request body.
     * @return string|null Pattern name on hit, null on clean.
     */
    public function inspect(string $queryString, string $rawBody): ?string
    {
        $haystack = $queryString . "\n" . $rawBody;

        foreach ($this->patterns() as $name => $regex) {
            if (preg_match($regex, $haystack) === 1) {
                return $name;
            }
        }

        return null;
    }
}
