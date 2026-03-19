<?php

namespace DGLab\Services\Superpowers\Transpiler;

/**
 * Class ExpressionTranspiler
 *
 * Transpiles SuperPHP expressions into valid PHP.
 */
class ExpressionTranspiler
{
    /**
     * Transpile a SuperPHP expression.
     *
     * @param string $expression
     * @return string
     */
    public function transpile(string $expression): string
    {
        // Support for null-safe dot notation: $user?.profile?.name
        $patternNullSafe = '/\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)(\?\.[a-zA-Z0-9_\-\.]+)+/';
        $expression = preg_replace_callback($patternNullSafe, function ($matches) {
            $base = '$' . $matches[1];
            $remaining = substr($matches[0], strlen($base) + 2);
            $parts = explode('?.', $remaining);
            $result = $base;
            foreach ($parts as $part) {
                $result = "\\DGLab\\Services\\Superpowers\\Runtime\\Runtime::access({$result}, '{$part}', true)";
            }
            return $result;
        }, $expression);

        // Support for standard dot notation: $user.profile.name
        $pattern = '/\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)(\.[a-zA-Z0-9_\-\.]+)+/';
        $expression = preg_replace_callback($pattern, function ($matches) {
            $base = '$' . $matches[1];
            $remaining = substr($matches[0], strlen($base) + 1);
            $parts = explode('.', $remaining);
            $result = $base;
            foreach ($parts as $part) {
                $result = "\\DGLab\\Services\\Superpowers\\Runtime\\Runtime::access({$result}, '{$part}', false)";
            }
            return $result;
        }, $expression);

        return $expression;
    }

    /**
     * Validate a SuperPHP expression.
     *
     * @param string $expression
     * @return bool
     * @throws \RuntimeException
     */
    public function validate(string $expression): bool
    {
        $code = "return {$this->transpile($expression)};";
        $tokens = @token_get_all("<?php " . $code);
        if ($tokens === false) {
             throw new \RuntimeException("Syntax error in expression: {$expression}");
        }
        return true;
    }
}
