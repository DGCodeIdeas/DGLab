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
     * @param string $contextVar The name of the context array variable (e.g. $__ctx)
     * @return string
     */
    public function transpile(string $expression, string $contextVar = '$__ctx'): string
    {
        // 1. Handle null-safe dot notation: $user?.profile?.name
        $patternNullSafe = '/\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)(\?\.[a-zA-Z0-9_\-\.]+)+/';
        $expression = preg_replace_callback($patternNullSafe, function ($matches) use ($contextVar) {
            $varName = $matches[1];
            $result = in_array($varName, ['this', '_SERVER', '_SESSION', '_GET', '_POST', 'GLOBALS'])
                ? '$' . $varName
                : "({$contextVar}['{$varName}'] ?? null)";

            $parts = explode('?.', substr($matches[0], strlen($varName) + 2));
            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }
                $result = "\\DGLab\\Services\\Superpowers\\Runtime\\Runtime::access({$result}, '{$part}', true)";
            }
            return $result;
        }, $expression);

        // 2. Handle standard dot notation: $user.profile.name
        $pattern = '/\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)(\.[a-zA-Z0-9_\-\.]+)+/';
        $expression = preg_replace_callback($pattern, function ($matches) use ($contextVar) {
            $varName = $matches[1];
            $result = in_array($varName, ['this', '_SERVER', '_SESSION', '_GET', '_POST', 'GLOBALS'])
                ? '$' . $varName
                : "({$contextVar}['{$varName}'] ?? null)";

            $parts = explode('.', substr($matches[0], strlen($varName) + 2));
            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }
                $result = "\\DGLab\\Services\\Superpowers\\Runtime\\Runtime::access({$result}, '{$part}', false)";
            }
            return $result;
        }, $expression);

        // 3. Simple variable access: $user -> $__ctx['user']
        $expression = preg_replace_callback('/(?<![a-zA-Z0-9_\\\\\$\\\'])(\$)([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/', function ($matches) use ($contextVar) {
            $varName = $matches[2];
            if ($varName === '__ctx' || $varName === '__persisted' || $varName === '__g') {
                return '$' . $varName;
            }
            if (in_array($varName, ['this', '_SERVER', '_SESSION', '_GET', '_POST', 'GLOBALS'])) {
                return '$' . $varName;
            }
            return "({$contextVar}['{$varName}'] ?? null)";
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
