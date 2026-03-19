<?php

namespace DGLab\Services\Superpowers\Lexer;

/**
 * Class Lexer
 *
 * Scans .super.php files and produces a stream of tokens.
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

    private function matchDirective(): bool
    {
        if (preg_match(self::P['DIR'], $this->input, $matches)) {
            $this->pushToken(Token::T_DIRECTIVE, $matches[1]);
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

    private function matchReactiveTag(): bool
    {
        if (preg_match(self::P['REAC'], $this->input)) {
             $content = $this->input;
             $inDoubleQuote = false;
             $inSingleQuote = false;
             $len = strlen($content);

            for ($i = 0; $i < $len; $i++) {
                 $char = $content[$i];
                if ($char === '"' && !$inSingleQuote) {
                    $inDoubleQuote = !$inDoubleQuote;
                }
                if ($char === "'" && !$inDoubleQuote) {
                    $inSingleQuote = !$inSingleQuote;
                }
                if ($char === '>' && !$inDoubleQuote && !$inSingleQuote) {
                     $tag = substr($content, 0, $i + 1);
                     $this->pushToken(Token::T_REACTIVE_TAG, $tag);
                     $this->consume($tag);
                     return true;
                }
            }
        }
        return false;
    }

    private function matchText(): void
    {
        $specials = ['~setup', '~mount', '~rendered', '~cleanup', '{{', '{!!', '<s:', '</s:', '@', '<'];
        $closestPos = strlen($this->input);

        foreach ($specials as $special) {
            $pos = strpos($this->input, $special);
            if ($pos !== false && $pos < $closestPos) {
                if ($special === '@') {
                    $remaining = substr($this->input, $pos);
                    if (preg_match('/^@[a-zA-Z]+/', $remaining)) {
                        $closestPos = $pos;
                    }
                } elseif ($special === '<') {
                     $remaining = substr($this->input, $pos);
                    if (preg_match(self::P['REAC'], $remaining) && !preg_match('/^<s:/', $remaining)) {
                         $closestPos = $pos;
                    }
                } else {
                    $closestPos = $pos;
                }
            }
        }

        if ($closestPos > 0) {
            $text = substr($this->input, 0, $closestPos);
            $this->pushToken(Token::T_TEXT, $text);
            $this->consume($text);
        } elseif ($closestPos === 0 && $this->input !== '') {
            $char = $this->input[0];
            $this->pushToken(Token::T_TEXT, $char);
            $this->consume($char);
        }
    }

    private function consume(string $text): void
    {
        $this->line += substr_count($text, "\n");
        $this->input = substr($this->input, strlen($text));
    }

    private function pushToken(string $type, string $value): void
    {
        $this->tokens[] = new Token($type, $value, $this->line);
    }
}
