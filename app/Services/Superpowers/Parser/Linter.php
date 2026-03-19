<?php

namespace DGLab\Services\Superpowers\Parser;

use DGLab\Services\Superpowers\Lexer\Lexer;
use DGLab\Services\Superpowers\Lexer\Token;
use DGLab\Services\Superpowers\Exceptions\SyntaxException;

class Linter
{
    public function lint(string $content, ?string $path = null): bool
    {
        $tokens = (new Lexer())->tokenize($content);
        $stack = [];

        foreach ($tokens as $token) {
            if ($token->type === Token::T_DIRECTIVE) {
                preg_match('/^@([a-zA-Z0-9]+)/', $token->value, $matches);
                $name = $matches[1] ?? '';
                if (in_array($name, ['if', 'foreach', 'auth', 'guest', 'section'])) {
                    $stack[] = ['name' => $name, 'line' => $token->line];
                } elseif (str_starts_with($name, 'end') && $name !== 'end') {
                    if (empty($stack)) {
                        throw new SyntaxException("Unexpected closing directive: @{$name}", $path, $token->line);
                    }
                    $last = array_pop($stack);
                    $expected = 'end' . $last['name'];
                    if ($name !== $expected) {
                        throw new SyntaxException("Mismatched closing directive: expected @{$expected}, got @{$name}", $path, $token->line);
                    }
                }
            } elseif ($token->type === Token::T_COMPONENT_OPEN) {
                preg_match('/^<s:([a-zA-Z0-9\-\.]+)/', $token->value, $matches);
                $stack[] = ['name' => 's:' . ($matches[1] ?? ''), 'line' => $token->line];
            } elseif ($token->type === Token::T_COMPONENT_CLOSE) {
                preg_match('/^<\/s:([a-zA-Z0-9\-\.]+)/', $token->value, $matches);
                $name = 's:' . ($matches[1] ?? '');
                if (empty($stack)) {
                    throw new SyntaxException("Unexpected closing component tag: </{$name}>", $path, $token->line);
                }
                $last = array_pop($stack);
                if ($last['name'] !== $name) {
                    throw new SyntaxException("Mismatched closing component: expected </{$last['name']}>, got </{$name}>", $path, $token->line);
                }
            }
        }

        if (!empty($stack)) {
            $last = end($stack);
            $msg = str_starts_with($last['name'], 's:') ? "Unclosed component: <{$last['name']}>" : "Unclosed directive: @{$last['name']}";
            throw new SyntaxException($msg, $path, $last['line']);
        }

        return true;
    }
}
