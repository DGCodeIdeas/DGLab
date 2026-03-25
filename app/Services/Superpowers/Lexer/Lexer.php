<?php

namespace DGLab\Services\Superpowers\Lexer;

class Lexer
{
    private const P = [
        'SETUP' => '/^~setup\s*(\{(?:[^{}]|(?R))*\})(?:\s*~)?\s*/s',
        'MOUNT' => '/^~mount\s*(\{(?:[^{}]|(?R))*\})(?:\s*~)?\s*/s',
        'RENDERED' => '/^~rendered\s*(\{(?:[^{}]|(?R))*\})(?:\s*~)?\s*/s',
        'CLEANUP' => '/^~cleanup\s*(\{(?:[^{}]|(?R))*\})(?:\s*~)?\s*/s',
        'DIR' => '/^(@[a-zA-Z0-9]+(?:\s*(\((?:[^()]++|(?2))*\)))?)/s',
        'RAW' => '/^\{!!\s*(.*?)\s*!!\}/s',
        'ESC' => '/^\{\{\s*(.*?)\s*\}\}/s',
        'SELF' => '/^<s:([a-zA-Z0-9\-\.\:_]+)\s*([^>]*?)\s*\/>/s',
        'OPEN' => '/^<s:([a-zA-Z0-9\-\.\:_]+)\s*([^>]*?)>/s',
        'CLOSE' => '/^<\/s:([a-zA-Z0-9\-\.\:_]+)\s*>/s',
        'REAC' => '/^<([a-zA-Z0-9]+)\s+[^>]*?(@|s-)[a-z0-9\.]+/is',
        'TAG_CLOSE' => '/^<\/([a-zA-Z0-9]+)>/s'
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
            if ($this->matchGenericClose()) {
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
                $code = $matches[1];
                if (str_starts_with($code, '{') && str_ends_with($code, '}')) {
                    $code = substr($code, 1, -1);
                }
                $this->pushToken(constant(Token::class . '::T_' . $key . '_BLOCK'), $code);
                $this->consume($matches[0]);
                return true;
            }
        }
        return false;
    }

    private function matchLegacyLifecycle(): bool
    {
        foreach (['SETUP', 'MOUNT', 'RENDERED', 'CLEANUP'] as $key) {
            if (preg_match('/^~' . strtolower($key) . '\s+(.*?)\s*~/s', $this->input, $matches)) {
                $this->pushToken(constant(Token::class . '::T_' . $key . '_BLOCK'), $matches[1]);
                $this->consume($matches[0]);
                return true;
            }
        }
        return false;
    }

    private function matchGenericClose(): bool
    {
        if (preg_match(self::P['TAG_CLOSE'], $this->input, $matches)) {
            $this->pushToken(Token::T_TAG_CLOSE, $matches[0]);
            $this->consume($matches[0]);
            return true;
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
        return $this->m(self::P['OPEN'], Token::T_COMPONENT_OPEN);
    }
    private function matchComponentClose(): bool
    {
        return $this->m(self::P['CLOSE'], Token::T_COMPONENT_CLOSE);
    }
    private function matchComponentSelfClosing(): bool
    {
        return $this->m(self::P['SELF'], Token::T_COMPONENT_SELF_CLOSING);
    }

    private function m($p, $t): bool
    {
        if (preg_match($p, $this->input, $matches)) {
            $this->pushToken($t, $matches[0]);
            $this->consume($matches[0]);
            return true;
        }
        return false;
    }

    private function matchReactiveTag(): bool
    {
        if (preg_match(self::P['REAC'], $this->input, $matches)) {
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
        $stops = ['@', '{{', '{!!', '<s:', '</s:', '~', '</', '<'];
        $pos = false;
        foreach ($stops as $s) {
            $p = strpos($this->input, $s);
            if ($p !== false && ($pos === false || $p < $pos)) {
                $pos = $p;
            }
        }
        if ($pos === false) {
            $text = $this->input;
        } elseif ($pos === 0) {
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
