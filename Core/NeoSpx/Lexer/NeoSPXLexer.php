<?php
declare(strict_types=1);

namespace SovereignStack\Core\NeoSpx\Lexer;

/**
 * NeoSPXLexer — stateful mode-stacked lexer for the NeoSPX template language.
 *
 * Per §3.1 of the NeoSPX EBNF Grammar Specification:
 * - STATE_DATA: default, accumulates raw HTML, detects NeoSPX triggers
 * - STATE_BLOCK: after @ + known directive keyword
 * - STATE_VAR: inside {{ }} or {!! !!}
 * - STATE_STRING: inside quoted strings
 * - STATE_INTERPOLATION: inside {{ }} within strings
 * - STATE_PHP: inside ~setup or @php blocks
 *
 * The lexer does NOT parse HTML structure. It only detects NeoSPX-specific
 * triggers (<s:, </s:, @ + known keyword, ~setup, {{, {!!) and accumulates
 * all other characters into T_HTML tokens.
 *
 * PHP expressions are tokenized using PHP's native tokenizer (token_get_all).
 * The template lexer handles boundary detection; PHP's tokenizer handles
 * content tokenization.
 */
final class NeoSPXLexer
{
    private string $source;
    private int $pos = 0;
    private int $line = 1;
    private int $column = 1;
    private LexerState $state = LexerState::STATE_DATA;
    /** @var list<Token> */
    private array $tokens = [];
    /** @var list<array{message: string, line: int, column: int, offset: int}> */
    private array $errors = [];

    /** @var array<string> Known directive keywords */
    private const DIRECTIVE_KEYWORDS = [
        'if', 'elseif', 'else', 'endif',
        'foreach', 'endforeach',
        'for', 'endfor',
        'while', 'endwhile',
        'switch', 'case', 'default', 'endswitch',
        'break', 'continue',
        'persist', 'global', 'include',
        'php', 'endphp',
    ];

    public function __construct(string $source)
    {
        $this->source = $source;
    }

    /**
     * Tokenize the source and return the token stream.
     *
     * @return Token[]
     */
    /** @return list<Token> */
    public function tokenize(): array
    {
        $this->pos = 0;
        $this->line = 1;
        $this->column = 1;
        $this->state = LexerState::STATE_DATA;
        $this->tokens = [];
        $this->errors = [];

        $len = strlen($this->source);

        while ($this->pos < $len) {
            match ($this->state) {
                LexerState::STATE_DATA => $this->scanData(),
                LexerState::STATE_BLOCK => $this->scanBlock(),
                LexerState::STATE_VAR => $this->scanVar(),
                LexerState::STATE_STRING => $this->scanString(),
                LexerState::STATE_INTERPOLATION => $this->scanInterpolation(),
                LexerState::STATE_PHP => $this->scanPhp(),
            };
        }

        // Emit EOF token
        $this->emitToken(TokenType::T_EOF, '', $this->line, $this->column, $this->pos, $this->pos);

        return $this->tokens;
    }

    /**
     * Get accumulated errors.
     *
     * @return array<array{message: string, line: int, column: int, offset: int}>
     */
    /** @return list<array{message: string, line: int, column: int, offset: int}> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the source location of the current position.
     */
    public function getSourceLocation(): SourceLocation
    {
        return new SourceLocation(
            line: $this->line,
            column: $this->column,
            offset: $this->pos,
            endLine: $this->line,
            endColumn: $this->column,
            endOffset: $this->pos,
        );
    }

    // --- STATE_DATA: accumulate raw HTML, detect NeoSPX triggers ---

    private function scanData(): void
    {
        $len = strlen($this->source);
        $start = $this->pos;
        $startLine = $this->line;
        $startCol = $this->column;
        $startOffset = $this->pos;

        $html = '';

        while ($this->pos < $len) {
            $char = $this->source[$this->pos];

            // Check for component tag open: <s:
            if ($char === '<' && $this->pos + 2 < $len && substr($this->source, $this->pos, 3) === '<s:') {
                if ($html !== '') {
                    $this->emitToken(TokenType::T_HTML, $html, $startLine, $startCol, $startOffset, $this->pos);
                }
                $this->state = LexerState::STATE_BLOCK; // transition to handle component
                return;
            }

            // Check for component close: </s:
            if ($char === '<' && $this->pos + 3 < $len && substr($this->source, $this->pos, 4) === '</s:') {
                if ($html !== '') {
                    $this->emitToken(TokenType::T_HTML, $html, $startLine, $startCol, $startOffset, $this->pos);
                }
                $this->state = LexerState::STATE_BLOCK;
                return;
            }

            // Check for output expression open: {{
            if ($char === '{' && $this->pos + 1 < $len && $this->source[$this->pos + 1] === '{') {
                if ($html !== '') {
                    $this->emitToken(TokenType::T_HTML, $html, $startLine, $startCol, $startOffset, $this->pos);
                }
                $this->emitToken(TokenType::T_OUTPUT_OPEN, '{{', $this->line, $this->column, $this->pos, $this->pos + 2);
                $this->advance(2);
                $this->state = LexerState::STATE_VAR;
                return;
            }

            // Check for raw output open: {!!
            if ($char === '{' && $this->pos + 2 < $len && substr($this->source, $this->pos, 3) === '{!!') {
                if ($html !== '') {
                    $this->emitToken(TokenType::T_HTML, $html, $startLine, $startCol, $startOffset, $this->pos);
                }
                $this->emitToken(TokenType::T_RAW_OPEN, '{!!', $this->line, $this->column, $this->pos, $this->pos + 3);
                $this->advance(3);
                $this->state = LexerState::STATE_VAR;
                return;
            }

            // Check for directive start: @ + known keyword
            if ($char === '@') {
                $keyword = $this->readDirectiveKeyword();
            if ($keyword !== null) {
                if ($html !== '') {
                    $this->emitToken(TokenType::T_HTML, $html, $startLine, $startCol, $startOffset, $this->pos);
                }
                $this->emitToken(TokenType::T_DIRECTIVE_START, '@', $this->line, $this->column, $this->pos, $this->pos + 1);
                $this->advance(1);
                // Consume the directive keyword
                $kwStart = $this->pos;
                while ($this->pos < $len && preg_match('/[a-zA-Z_]/', $this->source[$this->pos])) {
                    $this->advance(1);
                }
                $kw = substr($this->source, $kwStart, $this->pos - $kwStart);
                if ($kw === 'php') {
                    $this->state = LexerState::STATE_PHP;
                } elseif (in_array($kw, ['if', 'elseif', 'while', 'switch', 'case', 'persist', 'global'], true)) {
                    // Skip whitespace
                    while ($this->pos < $len && ctype_space($this->source[$this->pos])) {
                        $this->advance(1);
                    }
                    // If next char is '(', scan PHP expression (parenthesized or bare)
                    if ($this->pos < $len && ($this->source[$this->pos] === '(' || preg_match('/[a-zA-Z0-9_$]/', $this->source[$this->pos]))) {
                        $exprResult = $this->scanPhpExpression();
                        if ($exprResult !== null) {
                            $this->emitToken(TokenType::T_PHP_EXPR, $exprResult['expression'], $exprResult['line'], $exprResult['column'], $exprResult['start'], $exprResult['end']);
                        }
                    }
                    $this->state = LexerState::STATE_DATA;
                } elseif ($kw === 'foreach') {
                    // Skip whitespace
                    while ($this->pos < $len && ctype_space($this->source[$this->pos])) {
                        $this->advance(1);
                    }
                    if ($this->pos < $len && $this->source[$this->pos] === '(') {
                        $exprResult = $this->scanPhpExpression();
                        if ($exprResult !== null) {
                            $this->emitToken(TokenType::T_PHP_EXPR, $exprResult['expression'], $exprResult['line'], $exprResult['column'], $exprResult['start'], $exprResult['end']);
                        }
                    }
                    $this->state = LexerState::STATE_DATA;
                } elseif ($kw === 'for') {
                    // Skip whitespace
                    while ($this->pos < $len && ctype_space($this->source[$this->pos])) {
                        $this->advance(1);
                    }
                    if ($this->pos < $len && $this->source[$this->pos] === '(') {
                        $this->advance(1); // skip (
                        $headerStart = $this->pos;
                        $headerLine = $this->line;
                        $headerCol = $this->column;
                        $headerStartOffset = $this->pos;
                        $depth = 0;
                        $inString = false;
                        $stringChar = '';
                        while ($this->pos < $len) {
                            $c = $this->source[$this->pos];
                            if ($inString) {
                                if ($c === '\\') { $this->advance(2); continue; }
                                if ($c === $stringChar) { $inString = false; $stringChar = ''; }
                                $this->advance(1);
                                continue;
                            }
                            if ($c === '"' || $c === "'") {
                                $inString = true;
                                $stringChar = $c;
                                $this->advance(1);
                                continue;
                            }
                            if ($c === '(') { $depth++; $this->advance(1); continue; }
                            if ($c === ')') {
                                if ($depth === 0) break;
                                $depth--;
                                $this->advance(1);
                                continue;
                            }
                            $this->advance(1);
                        }
                        $header = trim(substr($this->source, $headerStart, $this->pos - $headerStart));
                        $this->emitToken(TokenType::T_PHP_EXPR, $header, $headerLine, $headerCol, $headerStartOffset, $this->pos);
                        if ($this->pos < $len) {
                            $this->advance(1); // skip )
                        }
                    }
                    $this->state = LexerState::STATE_DATA;
                } elseif ($kw === 'case' || $kw === 'default' || $kw === 'break' || $kw === 'continue' || $kw === 'persist' || $kw === 'global') {
                    // Skip whitespace
                    while ($this->pos < $len && ctype_space($this->source[$this->pos])) {
                        $this->advance(1);
                    }
                    // Check if followed by PHP expression
                    if ($this->pos < $len && $this->source[$this->pos] !== '>' && $this->source[$this->pos] !== '@' && $this->source[$this->pos] !== "\n") {
                        $exprResult = $this->scanPhpExpression();
                        if ($exprResult !== null) {
                            $this->emitToken(TokenType::T_PHP_EXPR, $exprResult['expression'], $exprResult['line'], $exprResult['column'], $exprResult['start'], $exprResult['end']);
                        }
                    }
                    $this->state = LexerState::STATE_DATA;
                } elseif ($kw === 'include') {
                    // Skip whitespace
                    while ($this->pos < $len && ctype_space($this->source[$this->pos])) {
                        $this->advance(1);
                    }
                    // Read string literal
                    if ($this->pos < $len && ($this->source[$this->pos] === '"' || $this->source[$this->pos] === "'")) {
                        $strStart = $this->pos;
                        $strLine = $this->line;
                        $strCol = $this->column;
                        $quote = $this->source[$this->pos];
                        $this->advance(1);
                        $strValue = $quote;
                        while ($this->pos < $len && $this->source[$this->pos] !== $quote) {
                            if ($this->source[$this->pos] === '\\') {
                                $strValue .= $this->source[$this->pos] . ($this->source[$this->pos + 1] ?? '');
                                $this->advance(2);
                            } else {
                                $strValue .= $this->source[$this->pos];
                                $this->advance(1);
                            }
                        }
                        if ($this->pos < $len) {
                            $strValue .= $quote;
                            $this->advance(1);
                        }
                        $this->emitToken(TokenType::T_STRING, $strValue, $strLine, $strCol, $strStart, $this->pos);
                    }
                    $this->state = LexerState::STATE_DATA;
                } else {
                    $this->state = LexerState::STATE_BLOCK;
                }
                return;
            }
            // Unknown directive — report error
            if ($this->pos + 1 < $len && preg_match('/[a-zA-Z_]/', $this->source[$this->pos + 1])) {
                if ($html !== '') {
                    $this->emitToken(TokenType::T_HTML, $html, $startLine, $startCol, $startOffset, $this->pos);
                }
                $kwStart = $this->pos + 1;
                $kw = '';
                $i = $kwStart;
                while ($i < $len && preg_match('/[a-zA-Z_]/', $this->source[$i])) {
                    $kw .= $this->source[$i];
                    $i++;
                }
                $this->errors[] = [
                    'message' => "Unknown directive @{$kw}",
                    'line' => $this->line,
                    'column' => $this->column,
                    'offset' => $this->pos,
                ];
                $this->advance(1 + strlen($kw));
                $this->state = LexerState::STATE_DATA;
                return;
            }
            }

            // Check for setup block: ~setup
            if ($char === '~' && $this->pos + 5 < $len && substr($this->source, $this->pos, 6) === '~setup') {
                if ($html !== '') {
                    $this->emitToken(TokenType::T_HTML, $html, $startLine, $startCol, $startOffset, $this->pos);
                }
                $this->emitToken(TokenType::T_SETUP_START, '~setup', $this->line, $this->column, $this->pos, $this->pos + 6);
                $this->advance(6);
                $this->state = LexerState::STATE_PHP;
                return;
            }

            // Accumulate raw HTML
            $html .= $char;
            $this->advance(1);
        }

        // Emit remaining HTML
        if ($html !== '') {
            $this->emitToken(TokenType::T_HTML, $html, $startLine, $startCol, $startOffset, $this->pos);
        }
    }

    // --- STATE_BLOCK: handle component tags and directive arguments ---

    private function scanBlock(): void
    {
        $len = strlen($this->source);
        $char = $this->source[$this->pos] ?? '';

        // Component open/close handled by scanData's trigger detection
        // This state is entered after trigger detection; handle specifics

        // Component close: </s:
        if ($char === '<' && $this->pos + 3 < $len && substr($this->source, $this->pos, 4) === '</s:') {
            $this->emitToken(TokenType::T_COMPONENT_CLOSE, '</s:', $this->line, $this->column, $this->pos, $this->pos + 4);
            $this->advance(4);
            // Read component name (consumed but not emitted as separate token)
            $this->readComponentName();
            return;
        }

        // Component open: <s:
        if ($char === '<' && $this->pos + 2 < $len && substr($this->source, $this->pos, 3) === '<s:') {
            $this->emitToken(TokenType::T_COMPONENT_OPEN, '<s:', $this->line, $this->column, $this->pos, $this->pos + 3);
            $this->advance(3);
            // Read component name (consumed but not emitted as separate token)
            $this->readComponentName();
            return;
        }

        // Self-close: />
        if ($char === '/' && $this->pos + 1 < $len && $this->source[$this->pos + 1] === '>') {
            $this->emitToken(TokenType::T_SELF_CLOSE, '/>', $this->line, $this->column, $this->pos, $this->pos + 2);
            $this->advance(2);
            $this->state = LexerState::STATE_DATA;
            return;
        }

        // Tag close: >
        if ($char === '>') {
            $this->emitToken(TokenType::T_TAG_CLOSE, '>', $this->line, $this->column, $this->pos, $this->pos + 1);
            $this->advance(1);
            $this->state = LexerState::STATE_DATA;
            return;
        }

        // Attribute name: IDENT
        if (preg_match('/[a-zA-Z_]/', $char)) {
            $attrStart = $this->pos;
            $attrLine = $this->line;
            $attrCol = $this->column;
            $name = '';
            while ($this->pos < $len && preg_match('/[a-zA-Z0-9_-]/', $this->source[$this->pos])) {
                $name .= $this->source[$this->pos];
                $this->advance(1);
            }
            if ($name !== '') {
                $this->emitToken(TokenType::T_ATTR_NAME, $name, $attrLine, $attrCol, $attrStart, $this->pos);
            }
            return;
        }

        // Attribute equals: =
        if ($char === '=') {
            $this->emitToken(TokenType::T_ATTR_EQUALS, '=', $this->line, $this->column, $this->pos, $this->pos + 1);
            $this->advance(1);
            // Check if next is a PHP expression (unquoted) or string literal
            if ($this->pos < $len && ($this->source[$this->pos] === '"' || $this->source[$this->pos] === "'")) {
                // String literal — stay in STATE_BLOCK, emit T_STRING
                $strStart = $this->pos;
                $strLine = $this->line;
                $strCol = $this->column;
                $quote = $this->source[$this->pos];
                $this->advance(1);
                $strValue = $quote;
                while ($this->pos < $len && $this->source[$this->pos] !== $quote) {
                    if ($this->source[$this->pos] === '\\') {
                        $strValue .= $this->source[$this->pos] . ($this->source[$this->pos + 1] ?? '');
                        $this->advance(2);
                    } else {
                        $strValue .= $this->source[$this->pos];
                        $this->advance(1);
                    }
                }
                if ($this->pos < $len) {
                    $strValue .= $quote;
                    $this->advance(1);
                }
                $this->emitToken(TokenType::T_STRING, $strValue, $strLine, $strCol, $strStart, $this->pos);
            } elseif ($this->pos < $len && !ctype_space($this->source[$this->pos]) && $this->source[$this->pos] !== '>' && $this->source[$this->pos] !== '/') {
                // PHP expression (unquoted) — use boundary scanning
                $exprResult = $this->scanPhpExpression();
                if ($exprResult !== null) {
                    $this->emitToken(TokenType::T_PHP_EXPR, $exprResult['expression'], $exprResult['line'], $exprResult['column'], $exprResult['start'], $exprResult['end']);
                }
            }
            return;
        }

        // String literal (standalone, for @include 'path')
        if ($char === '"' || $char === "'") {
            $strStart = $this->pos;
            $strLine = $this->line;
            $strCol = $this->column;
            $quote = $this->source[$this->pos];
            $this->advance(1);
            $strValue = $quote;
            while ($this->pos < $len && $this->source[$this->pos] !== $quote) {
                if ($this->source[$this->pos] === '\\') {
                    $strValue .= $this->source[$this->pos] . ($this->source[$this->pos + 1] ?? '');
                    $this->advance(2);
                } else {
                    $strValue .= $this->source[$this->pos];
                    $this->advance(1);
                }
            }
            if ($this->pos < $len) {
                $strValue .= $quote;
                $this->advance(1);
            }
            $this->emitToken(TokenType::T_STRING, $strValue, $strLine, $strCol, $strStart, $this->pos);
            $this->state = LexerState::STATE_DATA;
            return;
        }

        // Whitespace
        if (ctype_space($char)) {
            $this->advance(1);
            return;
        }

        // Unknown character in block context — emit as HTML and return to data
        $this->state = LexerState::STATE_DATA;
    }

    // --- STATE_VAR: inside {{ }} or {!! !!} ---

    private function scanVar(): void
    {
        $len = strlen($this->source);
        $char = $this->source[$this->pos] ?? '';

        // Check for output close: }}
        if ($char === '}' && $this->pos + 1 < $len && $this->source[$this->pos + 1] === '}') {
            $this->emitToken(TokenType::T_OUTPUT_CLOSE, '}}', $this->line, $this->column, $this->pos, $this->pos + 2);
            $this->advance(2);
            $this->state = LexerState::STATE_DATA;
            return;
        }

        // Check for raw close: !!}
        if ($char === '!' && $this->pos + 2 < $len && substr($this->source, $this->pos, 3) === '!!}') {
            $this->emitToken(TokenType::T_RAW_CLOSE, '!!}', $this->line, $this->column, $this->pos, $this->pos + 3);
            $this->advance(3);
            $this->state = LexerState::STATE_DATA;
            return;
        }

        // Accumulate PHP expression
        $exprStart = $this->pos;
        $exprLine = $this->line;
        $exprCol = $this->column;
        $expr = '';

        while ($this->pos < $len) {
            $c = $this->source[$this->pos];
            if ($c === '}' && $this->pos + 1 < $len && $this->source[$this->pos + 1] === '}') {
                break;
            }
            if ($c === '!' && $this->pos + 2 < $len && substr($this->source, $this->pos, 3) === '!!}') {
                break;
            }
            $expr .= $c;
            $this->advance(1);
        }

        if (trim($expr) !== '') {
            $this->emitToken(TokenType::T_PHP_EXPR, trim($expr), $exprLine, $exprCol, $exprStart, $this->pos);
        }
    }

    // --- STATE_STRING: inside quoted strings ---

    private function scanString(): void
    {
        // This state is handled inline in scanBlock for attribute values
        // and in scanData for HTML. For standalone strings, emit as T_STRING.
        $this->state = LexerState::STATE_DATA;
    }

    // --- STATE_INTERPOLATION: inside {{ }} within strings ---

    private function scanInterpolation(): void
    {
        // Not implemented in v1 — interpolation in strings is deferred
        $this->state = LexerState::STATE_DATA;
    }

    // --- STATE_PHP: inside ~setup or @php blocks ---

    private function scanPhp(): void
    {
        $len = strlen($this->source);
        $start = $this->pos;
        $startLine = $this->line;
        $startCol = $this->column;
        $startOffset = $this->pos;

        // Scan for closing marker
        $inString = false;
        $stringChar = '';
        $inHeredoc = false;
        $heredocLabel = '';
        $inComment = false;
        $commentType = '';
        $depth = 0;

        while ($this->pos < $len) {
            $char = $this->source[$this->pos];

            // Handle heredoc/nowdoc
            if ($inHeredoc) {
                if ($this->pos > 0 && $this->source[$this->pos - 1] === "\n") {
                    $labelLen = strlen($heredocLabel);
                    if (substr($this->source, $this->pos, $labelLen) === $heredocLabel) {
                        $afterLabel = $this->pos + $labelLen;
                        if ($afterLabel >= $len || $this->source[$afterLabel] === ';' || $this->source[$afterLabel] === "\n" || ctype_space($this->source[$afterLabel])) {
                            $inHeredoc = false;
                            $heredocLabel = '';
                            $this->advance($labelLen);
                            continue;
                        }
                    }
                }
                $this->advance(1);
                continue;
            }

            // Handle string state
            if ($inString) {
                if ($char === '\\') {
                    $this->advance(2);
                    continue;
                }
                if ($char === $stringChar) {
                    $inString = false;
                    $stringChar = '';
                }
                $this->advance(1);
                continue;
            }

            // Handle comment state
            if ($inComment) {
                if ($commentType === 'line') {
                    if ($char === "\n") {
                        $inComment = false;
                        $commentType = '';
                    }
                    $this->advance(1);
                    continue;
                }
                if ($commentType === 'block') {
                    if ($char === '*' && $this->pos + 1 < $len && $this->source[$this->pos + 1] === '/') {
                        $inComment = false;
                        $commentType = '';
                        $this->advance(2);
                        continue;
                    }
                    $this->advance(1);
                    continue;
                }
                $this->advance(1);
                continue;
            }

            // Handle heredoc/nowdoc opener
            if ($char === '<' && $this->pos + 2 < $len && substr($this->source, $this->pos, 3) === '<<<') {
                $this->advance(3);
                $nowdoc = false;
                if ($this->pos < $len && $this->source[$this->pos] === "'") {
                    $nowdoc = true;
                    $this->advance(1);
                } elseif ($this->pos < $len && $this->source[$this->pos] === '"') {
                    $this->advance(1);
                }
                $labelStart = $this->pos;
                while ($this->pos < $len && (ctype_alnum($this->source[$this->pos]) || $this->source[$this->pos] === '_')) {
                    $this->advance(1);
                }
                $heredocLabel = substr($this->source, $labelStart, $this->pos - $labelStart);
                if ($nowdoc && $this->pos < $len && $this->source[$this->pos] === "'") {
                    $this->advance(1);
                } elseif (!$nowdoc && $this->pos < $len && $this->source[$this->pos] === '"') {
                    $this->advance(1);
                }
                $inHeredoc = true;
                continue;
            }

            // Handle string openers
            if ($char === '"' || $char === "'") {
                $inString = true;
                $stringChar = $char;
                $this->advance(1);
                continue;
            }

            // Handle comment openers
            if ($char === '/' && $this->pos + 1 < $len) {
                if ($this->source[$this->pos + 1] === '/') {
                    $inComment = true;
                    $commentType = 'line';
                    $this->advance(2);
                    continue;
                }
                if ($this->source[$this->pos + 1] === '*') {
                    $inComment = true;
                    $commentType = 'block';
                    $this->advance(2);
                    continue;
                }
            }
            if ($char === '#') {
                $inComment = true;
                $commentType = 'line';
                $this->advance(1);
                continue;
            }

            // Handle delimiter depth
            if ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
                $this->advance(1);
                continue;
            }
            if ($char === ')' || $char === ']' || $char === '}') {
                if ($depth > 0) {
                    $depth--;
                }
                $this->advance(1);
                continue;
            }

            // Check for closing marker at PHP top-level
            if ($depth === 0) {
                // Check for ~setup (closing)
                if (substr($this->source, $this->pos, 6) === '~setup') {
                    $afterMarker = $this->pos + 6;
                    if ($afterMarker >= $len || ctype_space($this->source[$afterMarker]) || $this->source[$afterMarker] === "\n" || $this->source[$afterMarker] === "\r") {
                        // Emit accumulated PHP code
                        $code = substr($this->source, $start, $this->pos - $start);
                        if (trim($code) !== '') {
                            $this->emitToken(TokenType::T_PHP_CODE, $code, $startLine, $startCol, $startOffset, $this->pos);
                        }
                        // Emit closing marker
                        $this->emitToken(TokenType::T_SETUP_END, '~setup', $this->line, $this->column, $this->pos, $this->pos + 6);
                        $this->advance(6);
                        $this->state = LexerState::STATE_DATA;
                        return;
                    }
                }
                // Check for @endphp
                if (substr($this->source, $this->pos, 7) === '@endphp') {
                    $afterMarker = $this->pos + 7;
                    if ($afterMarker >= $len || ctype_space($this->source[$afterMarker]) || $this->source[$afterMarker] === "\n" || $this->source[$afterMarker] === "\r") {
                        $code = substr($this->source, $start, $this->pos - $start);
                        if (trim($code) !== '') {
                            $this->emitToken(TokenType::T_PHP_CODE, $code, $startLine, $startCol, $startOffset, $this->pos);
                        }
                        $this->advance(7);
                        $this->state = LexerState::STATE_DATA;
                        return;
                    }
                }
            }

            $this->advance(1);
        }

        // Unterminated block
        $code = substr($this->source, $start, $this->pos - $start);
        if (trim($code) !== '') {
            $this->emitToken(TokenType::T_PHP_CODE, $code, $startLine, $startCol, $startOffset, $this->pos);
        }
        $this->errors[] = [
            'message' => 'Unterminated block: closing marker not found',
            'line' => $startLine,
            'column' => $startCol,
            'offset' => $startOffset,
        ];
        $this->state = LexerState::STATE_DATA;
    }

    // --- Helper methods ---

    private function readDirectiveKeyword(): ?string
    {
        $len = strlen($this->source);
        if ($this->pos >= $len || $this->source[$this->pos] !== '@') {
            return null;
        }

        $start = $this->pos + 1;
        $keyword = '';
        $i = $start;
        while ($i < $len && preg_match('/[a-zA-Z_]/', $this->source[$i])) {
            $keyword .= $this->source[$i];
            $i++;
        }

        if ($keyword === '' || !in_array($keyword, self::DIRECTIVE_KEYWORDS, true)) {
            return null;
        }

        return $keyword;
    }

    private function readComponentName(): string
    {
        $len = strlen($this->source);
        $name = '';
        while ($this->pos < $len && (preg_match('/[a-zA-Z0-9_:]/', $this->source[$this->pos]))) {
            $name .= $this->source[$this->pos];
            $this->advance(1);
        }
        return $name;
    }

    /**
     * Scan a PHP expression starting at the current position.
     * Returns the expression string and its boundaries.
     */
    /** @return array{expression: string, line: int, column: int, start: int, end: int}|null */
    private function scanPhpExpression(): ?array
    {
        $len = strlen($this->source);
        $start = $this->pos;
        $startLine = $this->line;
        $startCol = $this->column;
        $startOffset = $this->pos;

        $depth = 0;
        $inString = false;
        $stringChar = '';
        $inHeredoc = false;
        $heredocLabel = '';
        $inComment = false;
        $commentType = '';

        while ($this->pos < $len) {
            $char = $this->source[$this->pos];

            // Handle heredoc/nowdoc
            if ($inHeredoc) {
                if ($this->pos > 0 && $this->source[$this->pos - 1] === "\n") {
                    $labelLen = strlen($heredocLabel);
                    if (substr($this->source, $this->pos, $labelLen) === $heredocLabel) {
                        $afterLabel = $this->pos + $labelLen;
                        if ($afterLabel >= $len || $this->source[$afterLabel] === ';' || $this->source[$afterLabel] === "\n" || ctype_space($this->source[$afterLabel])) {
                            $inHeredoc = false;
                            $heredocLabel = '';
                            $this->advance($labelLen);
                            continue;
                        }
                    }
                }
                $this->advance(1);
                continue;
            }

            // Handle string state
            if ($inString) {
                if ($char === '\\') {
                    $this->advance(2);
                    continue;
                }
                if ($char === $stringChar) {
                    $inString = false;
                    $stringChar = '';
                }
                $this->advance(1);
                continue;
            }

            // Handle comment state
            if ($inComment) {
                if ($commentType === 'line') {
                    if ($char === "\n") {
                        $inComment = false;
                        $commentType = '';
                    }
                    $this->advance(1);
                    continue;
                }
                if ($commentType === 'block') {
                    if ($char === '*' && $this->pos + 1 < $len && $this->source[$this->pos + 1] === '/') {
                        $inComment = false;
                        $commentType = '';
                        $this->advance(2);
                        continue;
                    }
                    $this->advance(1);
                    continue;
                }
                $this->advance(1);
                continue;
            }

            // Handle heredoc/nowdoc opener
            if ($char === '<' && $this->pos + 2 < $len && substr($this->source, $this->pos, 3) === '<<<') {
                $this->advance(3);
                $nowdoc = false;
                if ($this->pos < $len && $this->source[$this->pos] === "'") {
                    $nowdoc = true;
                    $this->advance(1);
                } elseif ($this->pos < $len && $this->source[$this->pos] === '"') {
                    $this->advance(1);
                }
                $labelStart = $this->pos;
                while ($this->pos < $len && (ctype_alnum($this->source[$this->pos]) || $this->source[$this->pos] === '_')) {
                    $this->advance(1);
                }
                $heredocLabel = substr($this->source, $labelStart, $this->pos - $labelStart);
                if ($nowdoc && $this->pos < $len && $this->source[$this->pos] === "'") {
                    $this->advance(1);
                } elseif (!$nowdoc && $this->pos < $len && $this->source[$this->pos] === '"') {
                    $this->advance(1);
                }
                $inHeredoc = true;
                continue;
            }

            // Handle string openers
            if ($char === '"' || $char === "'") {
                $inString = true;
                $stringChar = $char;
                $this->advance(1);
                continue;
            }

            // Handle comment openers
            if ($char === '/' && $this->pos + 1 < $len) {
                if ($this->source[$this->pos + 1] === '/') {
                    $inComment = true;
                    $commentType = 'line';
                    $this->advance(2);
                    continue;
                }
                if ($this->source[$this->pos + 1] === '*') {
                    $inComment = true;
                    $commentType = 'block';
                    $this->advance(2);
                    continue;
                }
            }
            if ($char === '#') {
                $inComment = true;
                $commentType = 'line';
                $this->advance(1);
                continue;
            }

            // Handle delimiter depth
            if ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
                $this->advance(1);
                continue;
            }
            if ($char === ')' || $char === ']' || $char === '}') {
                if ($depth > 0) {
                    $depth--;
                }
                $this->advance(1);
                continue;
            }

            // Check for template-level terminators at depth 0
            if ($depth === 0) {
                // Check for /> (self-close) first
                if ($this->pos + 1 < $len && $this->source[$this->pos] === '/' && $this->source[$this->pos + 1] === '>') {
                    break;
                }
                // Check for > (tag close) — but NOT -> or >= or =>
                if ($char === '>') {
                    // Check if preceded by - (-> operator)
                    if ($this->pos > 0 && $this->source[$this->pos - 1] === '-') {
                        $this->advance(1);
                        continue;
                    }
                    // Check if preceded by = (>= or => operator)
                    if ($this->pos > 0 && $this->source[$this->pos - 1] === '=') {
                        $this->advance(1);
                        continue;
                    }
                    // Check if followed by = (=> operator: > =)
                    if ($this->pos + 1 < $len && $this->source[$this->pos + 1] === '=') {
                        $this->advance(1);
                        continue;
                    }
                    // Standalone > — terminate
                    break;
                }
                // Check for next attribute: whitespace + IDENT + =
                if (ctype_space($char)) {
                    $lookahead = $this->pos;
                    while ($lookahead < $len && ctype_space($this->source[$lookahead])) {
                        $lookahead++;
                    }
                    if ($lookahead < $len && preg_match('/[a-zA-Z_]/', $this->source[$lookahead])) {
                        $attrStart = $lookahead;
                        while ($lookahead < $len && preg_match('/[a-zA-Z0-9_-]/', $this->source[$lookahead])) {
                            $lookahead++;
                        }
                        if ($lookahead < $len && $this->source[$lookahead] === '=') {
                            break;
                        }
                    }
                }
            }

            $this->advance(1);
        }

        $expression = trim(substr($this->source, $start, $this->pos - $start));

        if ($expression === '') {
            return null;
        }

        return [
            'expression' => $expression,
            'line' => $startLine,
            'column' => $startCol,
            'start' => $startOffset,
            'end' => $this->pos,
        ];
    }

    private function advance(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            if ($this->pos < strlen($this->source)) {
                if ($this->source[$this->pos] === "\n") {
                    $this->line++;
                    $this->column = 1;
                } else {
                    $this->column++;
                }
                $this->pos++;
            }
        }
    }

    private function emitToken(TokenType $type, string $value, int $line, int $column, int $startOffset, int $endOffset): void
    {
        $this->tokens[] = new Token(
            type: $type,
            value: $value,
            location: new SourceLocation(
                line: $line,
                column: $column,
                offset: $startOffset,
                endLine: $this->line,
                endColumn: $this->column,
                endOffset: $endOffset,
            ),
        );
    }
}
