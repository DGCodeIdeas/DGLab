<?php

namespace DGLab\Services\Superpowers\Lexer;

/**
 * Class Lexer
 *
 * Tokenizes SuperPHP template content.
 */
class Lexer
{
    private const P = [
        'SETUP' => '/^~setup\s*\{(.*?)\}/s',
        'MOUNT' => '/^~mount\s*\{(.*?)\}/s',
        'RENDERED' => '/^~rendered\s*\{(.*?)\}/s',
        'CLEANUP' => '/^~cleanup\s*\{(.*?)\}/s',
        'DIR' => '/^(@[a-zA-Z0-9]+(?:\s*(\((?:[^()]++|(?2))*\)))?)/s',
        'RAW' => '/^\{!!\s*(.*?)\s*!!\}/s',
        'ESC' => '/^\{\{\s*(.*?)\s*\}\}/s',
        'SELF' => '/^<s:([a-zA-Z0-9\-\.\:]+)\s*([^>]*?)\s*\/>/s',
        'OPEN' => '/^<s:([a-zA-Z0-9\-\.\:]+)\s*([^>]*?)>/s',
        'CLOSE' => '/^<\/s:([a-zA-Z0-9\-\.\:]+)\s*>/s',
        'REAC' => '/^<([a-zA-Z0-9]+)\s+[^>]*?(@|s-)[a-z0-9\.]+/is'
    ];

    private array $tokens = [];
    private string $input;
    private int $line = 1;

    public function tokenize(string $content): array
    {
        $this->input = $content;
        $this->tokens = [];
        $this->line = 1;

        while ($this->input !== '') {
            if ($this->matchLifecycle()) {
                continue;
            }
            if ($this->matchLegacyLifecycle()) {
                continue;
            }
            if ($this->matchExpressionRaw()) {
                continue;
            }
            if ($this->matchExpressionEscaped()) {
                continue;
            }
            if ($this->matchComponentSelfClosing()) {
                continue;
            }
            if ($this->matchComponentOpen()) {
                continue;
            }
            if ($this->matchComponentClose()) {
                continue;
            }
            if ($this->matchReactiveTag()) {
                continue;
            }
            if ($this->matchDirective()) {
                continue;
            }

            $this->matchText();
        }

        return $this->tokens;
    }

    private function matchLifecycle(): bool
    {
        foreach (['SETUP', 'MOUNT', 'RENDERED', 'CLEANUP'] as $key) {
            if (preg_match(self::P[$key], $this->input, $matches)) {
                $type = constant(Token::class . '::T_' . $key . '_BLOCK');
                $this->pushToken($type, $matches[1]);
                $this->consume($matches[0]);
                return true;
            }
        }
        return false;
    }

    private function matchLegacyLifecycle(): bool
    {
        foreach (['SETUP', 'MOUNT', 'RENDERED', 'CLEANUP'] as $key) {
             $pattern = '/^~' . strtolower($key) . '\s+(.*?)\s*~/s';
            if (preg_match($pattern, $this->input, $matches)) {
                $type = constant(Token::class . '::T_' . $key . '_BLOCK');
                $this->pushToken($type, $matches[1]);
                $this->consume($matches[0]);
                return true;
            }
        }
        return false;
    }

    private function matchDirective(): bool
    {
        if (preg_match(self::P['DIR'], $this->input, $matches)) {
            $this->pushToken(Token::T_DIRECTIVE, $matches[0]);
            $this->consume($matches[0]);
            return true;
        }
        return false;
    }

    private function matchExpressionRaw(): bool
    {
        if (preg_match(self::P['RAW'], $this->input, $matches)) {
            $this->pushToken(Token::T_EXPRESSION_RAW, $matches[1]);
            $this->consume($matches[0]);
            return true;
        }
        return false;
    }

    private function matchExpressionEscaped(): bool
    {
        if (preg_match(self::P['ESC'], $this->input, $matches)) {
            $this->pushToken(Token::T_EXPRESSION_ESCAPED, $matches[1]);
            $this->consume($matches[0]);
            return true;
        }
        return false;
    }

    private function matchComponentOpen(): bool
    {
        if (preg_match(self::P['OPEN'], $this->input, $matches)) {
            $this->pushToken(Token::T_COMPONENT_OPEN, $matches[0]);
            $this->consume($matches[0]);
            return true;
        }
        return false;
    }

    private function matchComponentClose(): bool
    {
        if (preg_match(self::P['CLOSE'], $this->input, $matches)) {
            $this->pushToken(Token::T_COMPONENT_CLOSE, $matches[0]);
            $this->consume($matches[0]);
            return true;
        }
        return false;
    }

    private function matchComponentSelfClosing(): bool
    {
        if (preg_match(self::P['SELF'], $this->input, $matches)) {
            $this->pushToken(Token::T_COMPONENT_SELF_CLOSING, $matches[0]);
            $this->consume($matches[0]);
            return true;
        }
        return false;
    }

    private function matchReactiveTag(): bool
    {
        if (preg_match(self::P['REAC'], $this->input, $matches)) {
            $tag = $matches[1];
            // Find the end of this tag
            $endPos = strpos($this->input, '>');
            if ($endPos !== false) {
                 $tagContent = substr($this->input, 0, $endPos + 1);
                 $this->pushToken(Token::T_REACTIVE_TAG, $tagContent);
                 $this->consume($tagContent);
                 return true;
            }
        }
        return false;
    }

    private function matchText(): void
    {
        $next = strpos($this->input, '@');
        $next2 = strpos($this->input, '{{');
        $next3 = strpos($this->input, '{!!');
        $next4 = strpos($this->input, '<s:');
        $next5 = strpos($this->input, '</s:');
        $next6 = strpos($this->input, '~');
        $next7 = strpos($this->input, '<'); // For reactive tags

        $pos = false;
        foreach ([$next, $next2, $next3, $next4, $next5, $next6, $next7] as $p) {
            if ($p !== false && ($pos === false || $p < $pos)) {
                $pos = $p;
            }
        }

        if ($pos === false) {
            $text = $this->input;
        } elseif ($pos === 0) {
             // If we are at a potential token start but none matched, take 1 char as text
             $text = substr($this->input, 0, 1);
        } else {
            $text = substr($this->input, 0, $pos);
        }

        $this->pushToken(Token::T_TEXT, $text);
        $this->consume($text);
    }

    private function pushToken(string $type, string $value): void
    {
        $this->tokens[] = new Token($type, $value, $this->line);
    }

    private function consume(string $content): void
    {
        $this->line += substr_count($content, "\n");
        $this->input = substr($this->input, strlen($content));
    }
}
