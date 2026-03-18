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
        // Support for dot notation: $user.profile.name
        // Becomes: \DGLab\Services\Superpowers\Runtime\Runtime::access(\DGLab\Services\Superpowers\Runtime\Runtime::access($user, 'profile'), 'name')

        // Match variables followed by dots and more dots/words
        $pattern = '/\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)(\.[a-zA-Z0-9_\-\.]+)+/';

        return preg_replace_callback($pattern, function($matches) {
            $base = '$' . $matches[1];
            // Split by dot but exclude the first dot attached to base
            $remaining = substr($matches[0], strlen($base) + 1);
            $parts = explode('.', $remaining);

            $result = $base;
            foreach ($parts as $part) {
                $result = "\\DGLab\\Services\\Superpowers\\Runtime\\Runtime::access({$result}, '{$part}')";
            }

            return $result;
        }, $expression);
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

        // Basic token check
        $tokens = @token_get_all("<?php " . $code);
        if ($tokens === false) {
             throw new \RuntimeException("Syntax error in expression: {$expression}");
        }

        return true;
    }
}
