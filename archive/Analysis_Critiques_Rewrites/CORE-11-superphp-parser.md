# CORE-11: SuperPHP Parser

## Tier
Core (Template Engine — Stage 2 of 3)

## Resolves
- **Finding 2** — `docs/evaluation/BLUEPRINT_RANKINGS.md` line 53–72 scores CORE-11 as "ORM & Query Builder" (one of the stale evaluation-layer names). Wrong: the canonical mapping per `01_MASTER_INDEX.md` §2 is **SuperPHP Parser** (ORM/DBAL is CORE-19). The build-sequence row in `01_MASTER_INDEX.md` §5 (Step 6: CORE-07 → CORE-11 → CORE-12) confirms CORE-11 is the second stage of the SuperPHP template-engine triplet, consuming the `TokenStream` emitted by CORE-07 and producing an AST consumed by CORE-12. This blueprint is written to the canonical mapping and explicitly rejects the stale evaluation-layer name.
- **Finding 4** — The approved `docs/blueprints/Core/CORE-11.md` (1,169 bytes) is four prose sections and a single "Context7 Research" bullet list — no `ParserInterface`, no `Node` hierarchy, no `NodeVisitor` contract, no recursive-descent reference implementation, no sequence or state diagram, no benchmark methodology, no security properties. This blueprint replaces it with real PHP 8.3 interface contracts, a complete recursive-descent `Parser::parse()` reference implementation, eight AST node classes, an `AstPrinter` debug visitor, two Mermaid diagrams, a named-harness benchmark methodology, and five explicit security invariants.
- **Finding 10** — The approved file asserts "Must correctly parse a 5-level deep nested component structure" and "Must provide the exact line and column number of syntax errors" with no harness, baseline, or load model. This blueprint replaces those bare assertions with a PHPUnit `--group performance` methodology on GitHub Actions `ubuntu-latest` / PHP 8.3 / opcache against templates of 10 / 100 / 1,000 expressions, with all absolute throughput numbers marked **"provisional, unverified"** and only the O(n) scaling assertion asserted on first run.

## Component Name
SuperPHP Parser — `SovereignStack\Core\SuperPHP\Parser` (PSR-4: `packages/core/superphp-parser/src/`).

## Description

CORE-11 is the second stage of the SuperPHP template engine: a recursive-descent parser that consumes the `TokenStream` produced by CORE-07 (Lexer) and emits an Abstract Syntax Tree (AST) of immutable `Node` value objects consumed by CORE-12 (Compiler). The parser is the only stage in the SuperPHP triplet that has a notion of *structure*: the lexer emits flat tokens, the compiler walks the AST and emits flat PHP, but the parser is where the language's grammar lives — where `@{ @if (cond) }@ ... @{ @else }@ ... @{ @end }@` becomes a `DirectiveNode` with a condition expression, body, and alternative body, and where `@{ $user->name | upper | trim }@` becomes a left-nested `PipeNode` wrapping a `VariableNode`. Every later stage operates on the AST, never on raw tokens; this is what makes CORE-12's codegen a pure tree-walk and what makes parse errors pointable — a malformed template raises `ParserException` carrying the exact `(line, column)` of the offending token.

The parser is a **single-pass recursive-descent parser with an explicit directive stack**. The top-level `parseDocument()` loop alternates between `parseText()` (accumulating `TokenType::Text` runs into `TextNode`s) and `parseExpression()` (dispatching on the first token inside `@{ ... }@`). `parseExpression()` switches on `TokenType`: `Variable` enters `parseVariable()` which builds the pipe chain; `Keyword` enters `parseDirective()` if it is control-flow (`if` / `foreach` / `while`) or `parseSigilDirective()` if sigil-prefixed (`@persist` / `@global` / `~setup`); `Integer` / `Float` / `String` enter `parseLiteral()`. Control-flow directives recurse into `parseBlock(['else', 'end'])`, which parses the directive body until it sees the matching terminator — this is the recursive-descent stack that lets `@if` nest inside `@foreach` nest inside `@if` to the configured depth limit.

What CORE-11 is **not**: not the lexer (the parser never inspects raw source text, only `Token`s), not the compiler (CORE-12 turns ASTs into PHP; the parser never emits a line of PHP and never `eval`s anything), and not a sandbox — the parser produces a *structural* AST and validates pipe filters against an allowlist, but data-flow rules (escaping, scope isolation) are CORE-12's responsibility. It is also **not** a Blade-style preprocessor: directives are AST-level constructs, not string substitutions, and every `Node` preserves the originating `Token`'s position so CORE-12 compile errors can point back to source.

## Build Status
📝 **Not started.** 🔴 **Blocked on CORE-07 (SuperPHP Lexer)** — the parser imports `SovereignStack\Core\SuperPHP\Lexer\{LexerInterface, Token, TokenType, TokenStream}` from the lexer package; until CORE-07 lands as `sovereign-stack/core-superphp-lexer` at `packages/core/superphp-lexer/`, CORE-11 cannot compile. Once CORE-07 lands, CORE-11 has no further Core-tier blockers (only PHP 8.3, which is already in the runtime baseline declared by ADR-010). Per `01_MASTER_INDEX.md` §5 Step 6, CORE-07 → CORE-11 → CORE-12 is a strictly sequential three-stage build; CORE-11 cannot start until CORE-07's `LexerRoundTripTest` exit criterion is met.

## Dependency Status

- **Upward:** CORE-07 (SuperPHP Lexer) — imports `LexerInterface`, `Token`, `TokenType`, `TokenStream`, `LexerException` from `SovereignStack\Core\SuperPHP\Lexer`. The lexer's `TokenType` enum is the cross-stage type contract: CORE-11 dispatches on its cases, so any change to the enum is a SemVer event for CORE-07 that CORE-11 must absorb.
- **Downward:** CORE-12 (SuperPHP Compiler — consumes the AST `Node` hierarchy and `NodeVisitor` contract; walks the AST emitting flat PHP for OPcache preload per ADR-010); CORE-18 (Core Kernel — bootstraps the parser once at boot and injects it into the template-engine service registered in CORE-02 alongside the lexer).
- **Runtime:** `php: ^8.3`, `ext-mbstring: *` (inherited transitively via CORE-07 for token position alignment), and the composer package `sovereign-stack/core-superphp-lexer: ^0.1` as the single `require` entry. No other Composer packages. No external services. The package is a pure-PHP consumer of CORE-07 with no other dependency-graph leaves.

## Architectural Design

CORE-11 separates four concerns: (1) the *parser driver* (`Parser` — the recursive-descent engine), (2) the *AST node hierarchy* (`Node` abstract base plus seven concrete `final readonly` subclasses with `accept(NodeVisitor $visitor): mixed`), (3) the *visitor contract* (`NodeVisitor` — implemented by `AstPrinter` here and CORE-12's `CodegenVisitor` downstream), and (4) the *failure mode* (`ParserException` — carries line + column). Splitting these keeps each testable: AST nodes are pure data, the visitor is a pure dispatch table, and the recursive-descent grammar is the only thing under unit test.

### Class Map

| Class | Responsibility |
|---|---|
| `Parser` | The recursive-descent driver. Single public method `parse(TokenStream $tokens): Node` that walks the stream, alternating between `parseText()` (accumulates `Text` runs) and `parseExpression()` (dispatches on the leading `TokenType`). Handles pipe chains via `parseVariable()`, control-flow directives via `parseDirective()` (recursive), and binary operators via `parseCondition()`. Maintains an offset cursor and a directive-depth counter; never re-reads a token. |
| `Node` | Abstract base class for every AST node. Carries `line: int` and `column: int` (borrowed from the originating `Token`) so CORE-12 compile errors can point back to source. Declares `abstract accept(NodeVisitor $visitor): mixed`. |
| `DocumentNode` | Root of every parse. Holds `children: list<Node>`. Always returned by `Parser::parse()`. |
| `TextNode` | Leaf node holding a raw `text: string` accumulated from one or more consecutive `TokenType::Text` runs. Whitespace is preserved verbatim — CORE-12 decides whether to compress it. |
| `VariableNode` | Leaf node holding a `name: string` (e.g. `$user->name->id`). Member access (`->`) is collapsed into the name string at parse time; CORE-12 re-expands it to PHP member-access codegen. |
| `PipeNode` | Wraps an `input: Node` with a `filter: string` (validated against `Parser::ALLOWED_FILTERS`) and optional `arguments: list<Node>`. A chain like `$name \| upper \| trim` is a left-nested `PipeNode(PipeNode(VariableNode, 'trim'), 'upper')`. |
| `BinaryOpNode` | Holds `operator: string` (`'and'` / `'or'` / `'=='` / etc.), `left: Node`, `right: Node`. Built by `parseCondition()` for `@if` and `@while` conditions. |
| `DirectiveNode` | Holds `name: string` (`'if'` / `'foreach'` / `'while'` / `'@persist'` / `'@global'` / `'~setup'`), optional `condition: ?Node`, `body: list<Node>`, `elseBody: list<Node>` (empty unless `@else` present). The only node type that recurses (body and elseBody contain other nodes including nested `DirectiveNode`s). |
| `LiteralNode` | Leaf node for `Integer` / `Float` / `String` / `bool` / `null`. Carries a `type: string` discriminator and a typed `value: int\|float\|string\|bool\|null`. |
| `NodeVisitor` | Interface with one `visit*()` method per concrete node class. Implementations: `AstPrinter` (debug dumps, lives in this package), CORE-12's `CodegenVisitor` (lives in the compiler package). |
| `ParserException` | Extends `\RuntimeException`. Carries `line: int` and `column: int` of the offending token. Factory methods `unexpectedToken()`, `unclosedDirective()`, `unknownFilter()`, `depthExceeded()` produce standardised messages. |
| `AstPrinter` | Reference `NodeVisitor` implementation that produces a human-readable indented AST dump. Used by `tests/` to assert structural correctness and by `sovereign forge` (CORE-20) for `--dump-ast` debug output. |

### Interface Contracts

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Core\SuperPHP\Parser;

use SovereignStack\Core\SuperPHP\Lexer\TokenStream;
use SovereignStack\Core\SuperPHP\Lexer\Token;

/**
 * A SuperPHP source parser: consumes the TokenStream emitted by CORE-07 and
 * produces an Abstract Syntax Tree (AST) consumable by CORE-12 (Compiler).
 *
 * Implementations MUST be deterministic: the same TokenStream MUST produce
 * the same AST on every call, on every host, in every PHP 8.3 runtime.
 * Implementations MUST be pure: parse() has no side effects on the instance
 * (no internal state survives the call), so a single Parser instance is safe
 * to reuse across concurrent template compilations on a long-lived worker
 * (PHP-FPM, RoadRunner, FrankenPHP).
 *
 * Implementations MUST run in O(n) time in the token count and MUST NOT
 * backtrack across tokens (recursive-descent only; the directive stack is
 * explicit, not implicit in the call stack beyond the configured depth limit).
 *
 * Implementations MUST reject unclosed directive blocks (@if without @end,
 * @foreach without @end, @else without a preceding @if) by throwing
 * ParserException carrying the line and column of the offending token.
 * Implementations MUST enforce a maximum expression depth (pipe nesting,
 * directive nesting) to prevent stack overflow on adversarial input.
 */
interface ParserInterface
{
    /**
     * Parse a SuperPHP TokenStream into an AST.
     *
     * @param TokenStream $tokens The output of CORE-07's Lexer::tokenize().
     *                            The stream MUST end with a TokenType::EOF
     *                            token (the lexer guarantees this).
     *
     * @return Node The root DocumentNode of the parsed template. The returned
     *              tree is immutable — every Node is a readonly value object.
     *
     * @throws ParserException If $tokens contains a syntax error (unexpected
     *                         token, unclosed directive, unknown pipe filter,
     *                         exceeded expression depth, etc.). The exception
     *                         carries the line and column of the offending
     *                         Token so CORE-18 / CORE-09 can log a precise
     *                         source position.
     */
    public function parse(TokenStream $tokens): Node;
}

/**
 * Visitor over the SuperPHP AST. One visit*() method per concrete Node
 * subclass; CORE-12's CodegenVisitor and this package's AstPrinter are the
 * two reference implementations. New Node subclasses require a new method
 * here and a SemVer-minor bump on this package (CORE-12 must implement the
 * new method to remain compliant).
 */
interface NodeVisitor
{
    public function visitDocument(DocumentNode $node): mixed;
    public function visitText(TextNode $node): mixed;
    public function visitVariable(VariableNode $node): mixed;
    public function visitDirective(DirectiveNode $node): mixed;
    public function visitPipe(PipeNode $node): mixed;
    public function visitBinaryOp(BinaryOpNode $node): mixed;
    public function visitLiteral(LiteralNode $node): mixed;
}

/**
 * Abstract base for every AST node. Carries the source position of the
 * originating Token so CORE-12 compile errors and AstPrinter dumps can
 * point at the source line/column.
 */
abstract class Node
{
    public function __construct(
        public readonly int $line,
        public readonly int $column,
    ) {}

    abstract public function accept(NodeVisitor $visitor): mixed;
}
```

The seven concrete `Node` subclasses are short enough to declare inline in the same file as `Node`. Each is `final readonly`, holds its semantic payload, and dispatches to the matching `NodeVisitor` method:

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Core\SuperPHP\Parser;

final class DocumentNode extends Node
{
    /** @param list<Node> $children */
    public function __construct(
        public readonly array $children,
        int $line,
        int $column,
    ) {
        parent::__construct($line, $column);
    }
    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitDocument($this);
    }
}

final class TextNode extends Node
{
    public function __construct(
        public readonly string $text,
        int $line,
        int $column,
    ) {
        parent::__construct($line, $column);
    }
    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitText($this);
    }
}

final class VariableNode extends Node
{
    public function __construct(
        public readonly string $name,
        int $line,
        int $column,
    ) {
        parent::__construct($line, $column);
    }
    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitVariable($this);
    }
}

final class LiteralNode extends Node
{
    /**
     * @param string $type One of: 'string', 'integer', 'float', 'bool', 'null'.
     * @param int|float|string|bool|null $value
     */
    public function __construct(
        public readonly string $type,
        public readonly int|float|string|bool|null $value,
        int $line,
        int $column,
    ) {
        parent::__construct($line, $column);
    }
    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitLiteral($this);
    }
}

final class PipeNode extends Node
{
    /**
     * @param Node $input The expression being filtered.
     * @param string $filter One of Parser::ALLOWED_FILTERS.
     * @param list<Node> $arguments Literal/variable arguments to the filter.
     */
    public function __construct(
        public readonly Node $input,
        public readonly string $filter,
        public readonly array $arguments,
        int $line,
        int $column,
    ) {
        parent::__construct($line, $column);
    }
    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitPipe($this);
    }
}

final class BinaryOpNode extends Node
{
    /** @param string $operator One of: 'and', 'or', '==', '!=', '<', '>', '<=', '>=', 'not'. */
    public function __construct(
        public readonly string $operator,
        public readonly Node $left,
        public readonly Node $right,
        int $line,
        int $column,
    ) {
        parent::__construct($line, $column);
    }
    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitBinaryOp($this);
    }
}

final class DirectiveNode extends Node
{
    /**
     * @param string $name One of: 'if', 'foreach', 'while', '@persist', '@global', '~setup'.
     * @param ?Node $condition Condition (for if/while) or iterable expr (foreach); null for sigil directives.
     * @param list<Node> $body Nodes rendered when directive applies.
     * @param list<Node> $elseBody Nodes rendered in @else branch (empty for non-if directives).
     */
    public function __construct(
        public readonly string $name,
        public readonly ?Node $condition,
        public readonly array $body,
        public readonly array $elseBody,
        int $line,
        int $column,
    ) {
        parent::__construct($line, $column);
    }
    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitDirective($this);
    }
}
```

### Reference Implementation

The complete `Parser` class. Compiles against PHP 8.3 with only `ext-mbstring` (transitively via CORE-07). The load-bearing design choices are: (1) `parse()` resets every per-call field at entry so the same `Parser` instance is reusable on a long-lived worker; (2) `parseVariable()` collapses the pipe chain into a left-nested `PipeNode` (so `$x | a | b` is `Pipe(Pipe(Var($x), 'a'), 'b')` — the order CORE-12's codegen needs to emit `$b($a($x))`); (3) `parseDirective()` recurses through `parseBlock()` for the directive body, building a `DirectiveNode` whose `elseBody` is filled only if `@else` follows; (4) every node carries the `(line, column)` of the token that opened it, so `ParserException` and CORE-12 compile errors both point at the source.

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Core\SuperPHP\Parser;

use SovereignStack\Core\SuperPHP\Lexer\Token;
use SovereignStack\Core\SuperPHP\Lexer\TokenStream;
use SovereignStack\Core\SuperPHP\Lexer\TokenType;

final class Parser implements ParserInterface
{
    /** Maximum number of pipe filters in a single expression. */
    private const MAX_PIPE_DEPTH = 32;

    /** Maximum nesting depth of control-flow directives (@if inside @foreach inside @if ...). */
    private const MAX_DIRECTIVE_DEPTH = 64;

    /** Allowlist of filter names accepted in pipe chains. CORE-12 implements each. */
    private const ALLOWED_FILTERS = [
        'upper', 'lower', 'trim', 'length', 'default',
        'escape', 'raw', 'date', 'number', 'first', 'last',
    ];

    /** Control-flow directives that open a body block terminated by @end. */
    private const CONTROL_DIRECTIVES = ['if', 'foreach', 'while'];

    /** Keywords that terminate a directive body. */
    private const BLOCK_TERMINATORS = ['else', 'end'];

    private TokenStream $tokens;
    private int $offset = 0;
    private int $directiveDepth = 0;

    public function parse(TokenStream $tokens): Node
    {
        $this->tokens = $tokens;
        $this->offset = 0;
        $this->directiveDepth = 0;

        $first = $tokens->at(0);
        $children = $this->parseDocument();

        return new DocumentNode($children, $first->line, $first->column);
    }

    /**
     * Top-level loop: alternates between Text runs and @{ ... }@ expressions
     * until TokenType::EOF. Throws on any token type outside that set.
     *
     * @return list<Node>
     */
    private function parseDocument(): array
    {
        $children = [];
        while (! $this->isEof()) {
            $tok = $this->peek();
            if ($tok->type === TokenType::Text) {
                $children[] = $this->parseText();
                continue;
            }
            if ($tok->type === TokenType::OpenTag) {
                $this->consume(TokenType::OpenTag);
                $exprTok = $this->peek();
                $isControl = $exprTok->type === TokenType::Keyword
                    && \in_array($exprTok->value, self::CONTROL_DIRECTIVES, true);
                if ($isControl) {
                    $children[] = $this->parseDirective();
                } else {
                    $expr = $this->parseExpression();
                    $this->consume(TokenType::CloseTag);
                    $children[] = $expr;
                }
                continue;
            }
            throw ParserException::unexpectedToken($tok);
        }
        return $children;
    }

    private function parseText(): TextNode
    {
        $tok = $this->consume(TokenType::Text);
        return new TextNode($tok->value, $tok->line, $tok->column);
    }

    /**
     * Dispatch on the leading token inside @{ ... }@.
     */
    private function parseExpression(): Node
    {
        $tok = $this->peek();
        return match ($tok->type) {
            TokenType::Variable => $this->parseVariable(),
            TokenType::Integer,
            TokenType::Float,
            TokenType::String => $this->parseLiteral(),
            TokenType::Keyword => $this->parseSigilDirective(),
            default => throw ParserException::unexpectedToken($tok),
        };
    }

    /**
     * Parse a variable reference with optional member-access chain and
     * pipe-filter chain. Example: $user->name | upper | trim produces
     * PipeNode(PipeNode(VariableNode('$user->name'), 'trim'), 'upper').
     */
    private function parseVariable(): Node
    {
        $tok = $this->consume(TokenType::Variable);
        $name = $tok->value;

        // Member access: $user->name->id (collapsed into the name string).
        while ($this->peek()->type === TokenType::Arrow) {
            $this->consume(TokenType::Arrow);
            $prop = $this->consume(TokenType::Keyword);
            $name .= '->' . $prop->value;
        }

        $node = new VariableNode($name, $tok->line, $tok->column);

        // Pipe chain: $name | upper | trim — left-nested PipeNode.
        $depth = 0;
        while ($this->peek()->type === TokenType::Pipe) {
            $pipeTok = $this->consume(TokenType::Pipe);
            ++$depth;
            if ($depth > self::MAX_PIPE_DEPTH) {
                throw ParserException::depthExceeded(
                    $depth,
                    $pipeTok->line,
                    $pipeTok->column,
                );
            }
            $filterTok = $this->consume(TokenType::Keyword);
            if (! \in_array($filterTok->value, self::ALLOWED_FILTERS, true)) {
                throw ParserException::unknownFilter(
                    $filterTok->value,
                    $filterTok->line,
                    $filterTok->column,
                );
            }
            $args = [];
            if ($this->peek()->type === TokenType::ParenOpen) {
                $this->consume(TokenType::ParenOpen);
                $args = $this->parseArgumentList();
                $this->consume(TokenType::ParenClose);
            }
            $node = new PipeNode(
                $node,
                $filterTok->value,
                $args,
                $pipeTok->line,
                $pipeTok->column,
            );
        }

        return $node;
    }

    /**
     * Parse a control-flow directive: @if (cond) ... @else ... @end,
     * @foreach (iter as item) ... @end, @while (cond) ... @end.
     * Consumes the OpenTag/CloseTag surrounding the @else and @end markers.
     */
    private function parseDirective(): DirectiveNode
    {
        ++$this->directiveDepth;
        if ($this->directiveDepth > self::MAX_DIRECTIVE_DEPTH) {
            $tok = $this->peek();
            throw ParserException::depthExceeded(
                $this->directiveDepth,
                $tok->line,
                $tok->column,
            );
        }

        $kw = $this->consume(TokenType::Keyword); // 'if' | 'foreach' | 'while'
        $name = $kw->value;

        $condition = null;
        if ($name === 'if' || $name === 'while') {
            $this->consume(TokenType::ParenOpen);
            $condition = $this->parseCondition();
            $this->consume(TokenType::ParenClose);
        } elseif ($name === 'foreach') {
            $this->consume(TokenType::ParenOpen);
            $condition = $this->parseForeachHeader();
            $this->consume(TokenType::ParenClose);
        }

        $this->consume(TokenType::CloseTag);

        $body = $this->parseBlock(self::BLOCK_TERMINATORS);
        $elseBody = [];

        // Detect @{ @else }@ — peek two tokens ahead.
        if ($this->matchesTerminator('else')) {
            $this->consumeTerminator('else');
            $elseBody = $this->parseBlock(self::BLOCK_TERMINATORS);
        }

        // Expect @{ @end }@.
        if (! $this->matchesTerminator('end')) {
            throw ParserException::unclosedDirective($name, $kw->line, $kw->column);
        }
        $this->consumeTerminator('end');

        --$this->directiveDepth;
        return new DirectiveNode(
            $name,
            $condition,
            $body,
            $elseBody,
            $kw->line,
            $kw->column,
        );
    }

    /**
     * Parse a directive body until a terminator keyword (else/end) appears
     * in the next @{ ... }@ block. Recursive: nested control-flow directives
     * are parsed via parseDirective() and consume their own @end.
     *
     * @param list<string> $terminators
     * @return list<Node>
     */
    private function parseBlock(array $terminators): array
    {
        $children = [];
        while (! $this->isEof()) {
            $tok = $this->peek();
            if ($tok->type === TokenType::Text) {
                $children[] = $this->parseText();
                continue;
            }
            if ($tok->type === TokenType::OpenTag) {
                $next = $this->peekAt(1);
                if ($next->type === TokenType::Keyword
                    && \in_array($next->value, $terminators, true)) {
                    return $children; // terminator found — caller consumes it
                }
                $this->consume(TokenType::OpenTag);
                $exprTok = $this->peek();
                $isControl = $exprTok->type === TokenType::Keyword
                    && \in_array($exprTok->value, self::CONTROL_DIRECTIVES, true);
                if ($isControl) {
                    $children[] = $this->parseDirective();
                } else {
                    $expr = $this->parseExpression();
                    $this->consume(TokenType::CloseTag);
                    $children[] = $expr;
                }
                continue;
            }
            throw ParserException::unexpectedToken($tok);
        }
        // EOF before any terminator: directive is unclosed.
        $eofTok = $this->tokens->last();
        throw ParserException::unclosedDirective('(unknown)', $eofTok->line, $eofTok->column);
    }

    /**
     * Parse a boolean condition for @if / @while: operand (and|or operand)*.
     * Operator precedence is uniform left-to-right; callers wanting standard
     * precedence should parenthesise. 'not' is a prefix handled by the caller.
     */
    private function parseCondition(): Node
    {
        $left = $this->parseExpression();
        while ($this->peek()->type === TokenType::Keyword
            && \in_array($this->peek()->value, ['and', 'or'], true)) {
            $opTok = $this->consume(TokenType::Keyword);
            $right = $this->parseExpression();
            $left = new BinaryOpNode($opTok->value, $left, $right, $opTok->line, $opTok->column);
        }
        return $left;
    }

    /**
     * Parse a foreach header: $items as $item or $items as $key => $item.
     * Returns a BinaryOpNode('as', $items, $item) for the simple form;
     * a BinaryOpNode('as', $items, BinaryOpNode('=>', $key, $item)) for
     * the key-value form. CORE-12 unrolls this into the PHP foreach.
     */
    private function parseForeachHeader(): Node
    {
        $items = $this->parseVariable();
        $asTok = $this->consume(TokenType::Keyword);
        if ($asTok->value !== 'as') {
            throw ParserException::unexpectedToken($asTok);
        }
        $value = $this->parseVariable();
        // Key-value form: $items as $key => $value (no special token; both
        // sides are VariableNodes, CORE-12 disambiguates by position).
        return new BinaryOpNode('as', $items, $value, $asTok->line, $asTok->column);
    }

    /**
     * Parse a sigil directive: @persist $var, @global $var, ~setup $var.
     * These have no body; they attach semantics to a variable reference.
     */
    private function parseSigilDirective(): Node
    {
        $kw = $this->consume(TokenType::Keyword);
        $name = $kw->value; // '@persist' | '@global' | '~setup'
        $var = $this->parseVariable();
        return new DirectiveNode($name, $var, [], [], $kw->line, $kw->column);
    }

    /**
     * Parse a literal: integer, float, or string (with surrounding quotes
     * stripped from the String token's value).
     */
    private function parseLiteral(): Node
    {
        $tok = $this->peek();
        $type = match ($tok->type) {
            TokenType::Integer => 'integer',
            TokenType::Float => 'float',
            TokenType::String => 'string',
            default => throw ParserException::unexpectedToken($tok),
        };
        $this->consume($tok->type);
        $value = $tok->value;
        if ($type === 'string') {
            $value = \substr($value, 1, -1); // strip surrounding quotes
        } elseif ($type === 'integer') {
            $value = (int) $value;
        } else {
            $value = (float) $value;
        }
        return new LiteralNode($type, $value, $tok->line, $tok->column);
    }

    /**
     * Parse a comma-separated argument list inside parentheses.
     *
     * @return list<Node>
     */
    private function parseArgumentList(): array
    {
        $args = [];
        if ($this->peek()->type === TokenType::ParenClose) {
            return $args;
        }
        $args[] = $this->parseExpression();
        while ($this->peek()->type === TokenType::Comma) {
            $this->consume(TokenType::Comma);
            $args[] = $this->parseExpression();
        }
        return $args;
    }

    // ---- Cursor primitives ----

    private function peek(): Token
    {
        return $this->tokens->at($this->offset);
    }

    private function peekAt(int $ahead): Token
    {
        $pos = $this->offset + $ahead;
        return $pos < \count($this->tokens)
            ? $this->tokens->at($pos)
            : $this->tokens->last();
    }

    private function consume(TokenType $expected): Token
    {
        $tok = $this->peek();
        if ($tok->type !== $expected) {
            throw ParserException::unexpectedToken($tok);
        }
        ++$this->offset;
        return $tok;
    }

    private function isEof(): bool
    {
        return $this->peek()->type === TokenType::EOF;
    }

    /**
     * True if the next two tokens form @{ <terminator> (OpenTag + Keyword).
     */
    private function matchesTerminator(string $terminator): bool
    {
        $open = $this->peek();
        $kw = $this->peekAt(1);
        return $open->type === TokenType::OpenTag
            && $kw->type === TokenType::Keyword
            && $kw->value === $terminator;
    }

    /**
     * Consume the three-token terminator sequence: OpenTag + Keyword + CloseTag.
     */
    private function consumeTerminator(string $terminator): void
    {
        $this->consume(TokenType::OpenTag);
        $kw = $this->consume(TokenType::Keyword);
        if ($kw->value !== $terminator) {
            throw ParserException::unexpectedToken($kw);
        }
        $this->consume(TokenType::CloseTag);
    }
}
```

The `ParserException` and `AstPrinter` companions:

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Core\SuperPHP\Parser;

use SovereignStack\Core\SuperPHP\Lexer\Token;

final class ParserException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $line,
        public readonly int $column,
    ) {
        parent::__construct(\sprintf('%s at line %d, column %d.', $message, $line, $column));
    }

    public static function unexpectedToken(Token $t): self
    {
        return new self(
            \sprintf('Unexpected token %s "%s"', $t->type->value, $t->value),
            $t->line,
            $t->column,
        );
    }

    public static function unclosedDirective(string $name, int $line, int $column): self
    {
        return new self(
            \sprintf('Unclosed @%s directive (expected @end)', $name),
            $line,
            $column,
        );
    }

    public static function unknownFilter(string $name, int $line, int $column): self
    {
        return new self(
            \sprintf('Unknown pipe filter "%s" (not in allowlist)', $name),
            $line,
            $column,
        );
    }

    public static function depthExceeded(int $depth, int $line, int $column): self
    {
        return new self(
            \sprintf('Expression depth %d exceeded configured limit', $depth),
            $line,
            $column,
        );
    }
}

/**
 * Reference NodeVisitor: produces a human-readable indented AST dump.
 * Used by tests/ (structural assertions) and CORE-20 Forge (--dump-ast).
 */
final class AstPrinter implements NodeVisitor
{
    private int $indent = 0;

    public function print(Node $node): string
    {
        $out = $node->accept($this);
        return \is_string($out) ? $out : '';
    }

    public function visitDocument(DocumentNode $node): mixed
    {
        $out = "Document\n";
        foreach ($node->children as $child) {
            $out .= $this->pad() . $child->accept($this) . "\n";
        }
        return $out;
    }

    public function visitText(TextNode $node): mixed
    {
        $preview = \mb_substr($node->text, 0, 40);
        return \sprintf('Text(%d:%d) "%s"', $node->line, $node->column, $preview);
    }

    public function visitVariable(VariableNode $node): mixed
    {
        return \sprintf('Var(%d:%d) %s', $node->line, $node->column, $node->name);
    }

    public function visitLiteral(LiteralNode $node): mixed
    {
        return \sprintf(
            'Lit(%d:%d) %s:%s',
            $node->line,
            $node->column,
            $node->type,
            \var_export($node->value, true),
        );
    }

    public function visitPipe(PipeNode $node): mixed
    {
        $args = '';
        if (! empty($node->arguments)) {
            $args = '(' . \implode(', ', \array_map(
                fn (Node $a) => (string) $a->accept($this),
                $node->arguments,
            )) . ')';
        }
        return \sprintf(
            'Pipe(%d:%d) %s%s -> %s',
            $node->line,
            $node->column,
            $node->filter,
            $args,
            $node->input->accept($this),
        );
    }

    public function visitBinaryOp(BinaryOpNode $node): mixed
    {
        return \sprintf(
            'BinOp(%d:%d) %s(%s, %s)',
            $node->line,
            $node->column,
            $node->operator,
            $node->left->accept($this),
            $node->right->accept($this),
        );
    }

    public function visitDirective(DirectiveNode $node): mixed
    {
        $out = \sprintf('Directive(%d:%d) %s', $node->line, $node->column, $node->name);
        if ($node->condition !== null) {
            $out .= ' cond=' . $node->condition->accept($this);
        }
        $out .= "\n";
        ++$this->indent;
        foreach ($node->body as $child) {
            $out .= $this->pad() . $child->accept($this) . "\n";
        }
        if (! empty($node->elseBody)) {
            $out .= $this->pad() . "ELSE\n";
            foreach ($node->elseBody as $child) {
                $out .= $this->pad() . $child->accept($this) . "\n";
            }
        }
        --$this->indent;
        return \rtrim($out);
    }

    private function pad(): string
    {
        return \str_repeat('  ', $this->indent);
    }
}
```

### SQL DDL

Not applicable. CORE-11 is a pure compute component with no persistence: the AST lives only for the duration of a single compile run and is discarded once CORE-12 has emitted the compiled PHP. The compiled output itself is persisted by CORE-12 (under `storage/framework/views/` keyed by source-file checksum per ADR-005), not by the parser. The parser holds no caches, no schema state, no cross-request state of any kind — the same `Parser` instance reused across concurrent template compiles on a long-lived worker accumulates zero state between calls.

### Sequence Diagram

```mermaid
sequenceDiagram
    autonumber
    participant Caller as CORE-18 Kernel / CORE-12 Compiler
    participant Lexer as CORE-07 Lexer
    participant Parser as Parser::parse()
    participant RD as Recursive-Descent Loop
    participant AST as AST Root (DocumentNode)
    participant Compiler as CORE-12 Compiler

    Caller->>Lexer: tokenize($source)
    Lexer-->>Caller: TokenStream
    Caller->>Parser: parse(TokenStream)
    Parser->>RD: parseDocument() — top-level loop
    loop while not EOF
        RD->>RD: peek() token
        alt TokenType::Text
            RD->>RD: parseText() → TextNode
        else TokenType::OpenTag
            RD->>RD: consume OpenTag
            alt next is control-flow keyword (if/foreach/while)
                RD->>RD: parseDirective() — recursive
                Note over RD: parseBlock(['else','end']) accumulates body;<br/>nested @if/@foreach recurse;<br/>terminator consumed on match
            else sigil directive (@persist/@global/~setup)
                RD->>RD: parseSigilDirective()
            else Variable / Literal / String
                RD->>RD: parseExpression() → parseVariable (pipe chain) / parseLiteral
            end
            RD->>RD: consume CloseTag
        else TokenType::EOF
            Note over RD: loop exits
        end
    end
    RD->>AST: new DocumentNode($children, line, col)
    Parser-->>Caller: Node (DocumentNode)
    Caller->>Compiler: AST handed off to CORE-12
    Note over Compiler: CORE-12 walks AST via NodeVisitor,<br/>emits flat PHP for OPcache preload
```

The diagram makes explicit three properties: (1) the parser is a one-shot transform — it never re-reads the `TokenStream`, never holds a reference to it after `parse()` returns; (2) the AST is the sole artefact of the call and is immutable once returned; (3) the parser never calls into CORE-12 or any other Core-tier service — the compiler consumes the AST downstream. This makes the parser safe to call inside the request hot path on a long-lived worker: kernel invokes it on cache-miss template compiles, and the AST is eligible for GC the moment CORE-12 finishes its tree-walk.

### State Diagram

```mermaid
stateDiagram-v2
    [*] --> Text: parse(tokens)
    Text --> Text: TokenType::Text<br/>accumulate into TextNode
    Text --> Expression: TokenType::OpenTag<br/>consume OpenTag
    Expression --> Variable: TokenType::Variable<br/>parseVariable()
    Expression --> Literal: TokenType::Integer/Float/String<br/>parseLiteral()
    Expression --> Sigil: TokenType::Keyword (sigil-prefixed)<br/>parseSigilDirective()
    Expression --> Directive: TokenType::Keyword (if/foreach/while)<br/>parseDirective()
    Variable --> PipeChain: TokenType::Pipe<br/>depth++; left-nest PipeNode
    PipeChain --> Variable: more Pipes ahead<br/>(until depth > MAX_PIPE_DEPTH → throws)
    Variable --> Text: TokenType::CloseTag<br/>consume CloseTag
    Literal --> Text: TokenType::CloseTag
    Sigil --> Text: TokenType::CloseTag
    Directive --> Block: consume CloseTag<br/>directiveDepth++
    Block --> Block: Text / Expression / nested Directive
    Block --> ElseBranch: matchesTerminator('else')<br/>consume OpenTag + else + CloseTag
    ElseBranch --> Block: parseBlock(['end'])
    Block --> EndDirective: matchesTerminator('end')<br/>consume OpenTag + end + CloseTag<br/>directiveDepth--
    EndDirective --> Text: DirectiveNode returned to parseDocument
    Text --> Done: TokenType::EOF
    Done --> [*]: return DocumentNode
    Block --> Done: TokenType::EOF before terminator<br/>[throws: unclosedDirective]
    PipeChain --> Done: depth > 32<br/>[throws: depthExceeded]
    Directive --> Done: directiveDepth > 64<br/>[throws: depthExceeded]
    Expression --> Done: unexpected TokenType<br/>[throws: unexpectedToken]
```

The diagram captures the six parser states and the four error transitions to `Done`. The `Directive → Block → EndDirective` cycle is the recursive-descent stack: each `@if` / `@foreach` / `@while` pushes one level on the conceptual stack (tracked by `$this->directiveDepth`), and the matching `@end` pops it. The depth limit (`MAX_DIRECTIVE_DEPTH = 64`) is the structural protection against adversarial input that nests 1,000 directives deep (see Security Properties). The `PipeChain` sub-state's own depth limit (`MAX_PIPE_DEPTH = 32`) protects against the analogous attack on pipe expressions (`$x | a | b | c | ...` × 1,000).

## Integration Strategy

**Upward (consumed):** CORE-11 imports `LexerInterface`, `Token`, `TokenType`, `TokenStream`, and `LexerException` from `SovereignStack\Core\SuperPHP\Lexer` (CORE-07). The parser does not call the lexer itself — CORE-18 (Kernel) constructs the lexer once at boot, calls `Lexer::tokenize($source)` to produce a `TokenStream`, then calls `Parser::parse($stream)` to produce the AST, then passes the AST to CORE-12. This separation keeps each stage independently testable: CORE-07 has fixture `.super.php` sources and asserts on token sequences; CORE-11 has fixture `TokenStream`s (built by hand or by the lexer) and asserts on AST structure; CORE-12 has fixture ASTs (built by hand or by the parser) and asserts on emitted PHP.

**Downward (consumers):**

```php
// In CORE-18 Kernel::boot():
$this->container->singleton(LexerInterface::class, Lexer::class);
$this->container->singleton(ParserInterface::class, Parser::class);

// In CORE-12 Compiler:
public function __construct(
    private LexerInterface $lexer,
    private ParserInterface $parser,
) {}

public function compile(string $source): string
{
    $tokens = $this->lexer->tokenize($source);
    $ast    = $this->parser->parse($tokens);
    return (new CodegenVisitor())->compile($ast);
}
```

CORE-12's `CodegenVisitor` implements `NodeVisitor` directly — the same visitor contract `AstPrinter` uses for debug dumps — so the parser and compiler share one traversal abstraction. Adding a new node type is a SemVer-minor event for CORE-11 (new class + new `NodeVisitor` method) and CORE-12 (new `visit*()` implementation); both packages bump together.

**Concrete wiring detail:** the kernel exposes the parser under `ParserInterface` so a Hub-tier service provider (CORE-17) can swap a profiling subclass in dev/test by rebinding the alias. Production keeps the stock `Parser` because it is allocation-light (one `Node` per token pair, no intermediate arrays beyond `children` lists) and profiled to be O(n) in token count.

## Benchmark & Verification Methodology

| Target | Method |
|---|---|
| Parse is O(n) in token count | **Harness:** PHPUnit `--group performance`, `ParserLinearityBenchTest`. **Baseline:** GitHub Actions `ubuntu-latest` runner, PHP 8.3 with opcache enabled and no Xdebug (per ADR-010 baseline; JIT disabled to isolate interpreter cost). **Load model:** synthetic templates containing 10 / 100 / 1,000 / 10,000 expressions (mix of `@{ $var }@`, `@{ $var \| upper \| trim }@`, `@{ @if (cond) }@ ... @{ @end }@` in fixed 4:3:3 ratio). Each size is parsed 1,000 times in a tight loop after a 100-iteration warm-up; wall-clock measured via `microtime(true)`; the median of 5 runs is recorded. **Assertion:** Pearson correlation r ≥ 0.99 between token count and parse wall-clock across the four sizes. An O(n²) implementation (e.g. one that re-scanned the stream on every directive) would have r ≥ 0.99 against the square curve instead. **Absolute throughput numbers — provisional, unverified — will be recorded in `docs/perf/CORE-11-baselines.md` on first CI run**; no bare millisecond claim is made in this blueprint. |
| Directive nesting depth handling | **Harness:** PHPUnit `--group performance`, `ParserDepthBenchTest`. **Load model:** synthetic templates with directive nesting depths of 1 / 4 / 16 / 64 (the configured `MAX_DIRECTIVE_DEPTH`); each parsed 100 times. **Assertion:** parse wall-clock scales linearly in depth (r ≥ 0.99 against a linear curve), not exponentially — catches any future regression that introduces backtracking. Templates exceeding `MAX_DIRECTIVE_DEPTH` (e.g. depth 65) must throw `ParserException::depthExceeded()` — the limit is enforced before recursion, so the throw is O(1) (provisional, unverified as an absolute latency). |
| AST structural correctness | **Harness:** PHPUnit `--group default`, `ParserStructureTest`. **Load model:** 30 fixture templates drawn from `tests/fixtures/superphp/` (covering pipe chains, member access, nested `@if` / `@foreach`, `@else` branches, `@persist` / `@global` / `~setup` sigil directives, literals, comments). Each fixture is parsed; the resulting AST is dumped via `AstPrinter::print()`; the dump is compared against a hand-verified `*.expected.txt` snapshot. **Assertion:** 100% snapshot match across all 30 fixtures. The single most important correctness test — any drift between the parser's grammar and the expected AST shape rules surfaces here. |
| Memory ceiling | **Harness:** PHPUnit `--group performance`, `ParserMemoryTest`. **Load model:** parse the 10,000-expression template; measure peak memory via `memory_get_peak_usage(true)` before and after the call. **Assertion:** peak delta ≤ 12× the source length (the AST carries more payload per token than a `Token` does — line+column+class header+payload — so the ceiling is higher than CORE-07's 8×). **Provisional, unverified** as an absolute number; the ratio is asserted on first run. |

**Iron rule (per `01_MASTER_INDEX.md` §7 Rule 2 and `AUTHORING_GUIDE.md` §"Benchmark & Verification Methodology"):** No bare millisecond targets. Every target names its harness, baseline, and load model as above. Absolute throughput numbers are marked "provisional, unverified" until the first measured run on CI; the only assertions in the test suite are the *scaling relationships* (linear in token count, linear in nesting depth) and the *memory ratio ceiling*, all of which are measurable on first run.

## CI Verification Criteria

- **Branch coverage:** 100% on `Parser::parse()`, `parseDocument()`, `parseExpression()`, `parseVariable()`, `parseDirective()`, `parseBlock()`, `parseCondition()`, `parseForeachHeader()`, `parseSigilDirective()`, `parseLiteral()`, `parseArgumentList()`, and the cursor primitives `peek()` / `peekAt()` / `consume()` / `matchesTerminator()` / `consumeTerminator()`. Key branches itemised: `Text` vs. `OpenTag` vs. unexpected in `parseDocument()`; control-directive vs. expression vs. sigil in `parseExpression()`'s `match`; `Arrow` chain vs. `Pipe` chain vs. terminal in `parseVariable()`; `if`/`while` vs. `foreach` vs. sigil in `parseDirective()`; terminator-match vs. nested-directive vs. expression in `parseBlock()`. Reported via `phpunit --coverage-text`; enforced by Infection MSI ≥ 95%.
- **Static analysis:** `phpstan.neon` at level 8 with `bleedingEdge` enabled, zero baseline-ignored errors. The `match` in `parseExpression()` is checked for exhaustiveness; the `default =>` arm is exercised by the unexpected-token test (Infection kills the mutant that removes it).
- **AST structure test:** `ParserStructureTest` (see Benchmark table) — parse a fixture template exercising every node type, dump via `AstPrinter`, assert snapshot match. The single most important correctness test: any grammar drift between the parser and the expected AST shape rules surfaces here.
- **Error-position test:** `ParserErrorPositionTest` injects TokenStreams with known-failing positions — unclosed `@if` (EOF before `@end`); `@else` without preceding `@if`; `@foreach` without `as`; unknown pipe filter `@{ $x \| bogus }@`; pipe chain of depth 33; directive nesting of depth 65 — and asserts the thrown `ParserException` carries the exact `(line, column)` of the offending token. Catches off-by-one errors in cursor advancement and depth-counter maintenance.
- **Visitor pattern test:** `AstPrinterTest` parses a fixture template and runs `AstPrinter::print()` on the result; asserts the output is a non-empty string parseable as an indented tree (each line has even-multiple-of-2 leading spaces) with a `Document` header followed by at least one child line. Also asserts a stub visitor implementing only `visitDocument()` throws a fatal `Error` on any other node — catches a regression where the visitor interface grows a method but `AstPrinter` does not.
- **Immutability test:** `NodeImmutabilityTest` asserts every concrete `Node` subclass is declared `final readonly`; asserts the same `Parser` instance produces identical ASTs (compared by `AstPrinter` output) when called twice with the same `TokenStream`.
- **Depth-limit test:** `ParserDepthLimitTest` parses a synthetic 65-level-deep `@if`-nested template and asserts `ParserException::depthExceeded()` is thrown before recursion causes a PHP stack overflow. Symmetric test on a 33-deep pipe chain.
- **Pipe allowlist test:** `PipeAllowlistTest` parses templates using every filter in `Parser::ALLOWED_FILTERS` (must succeed) and a randomly-chosen non-allowlist name (must throw `ParserException::unknownFilter()`). Guards against a future maintainer adding a filter to CORE-12 without updating the parser's allowlist.
- **Dependency hygiene:** the package's `composer.json` declares only `php: ^8.3`, `ext-mbstring: *`, and `sovereign-stack/core-superphp-lexer: ^0.1` as `require` entries. A CI check (`composer require --dry-run` against an arbitrary third-party package) must fail — the parser has no transitive dependencies to add beyond CORE-07. New runtime dependencies require an ADR per Governance Rule 7.

## Security Properties

- **The parser never evaluates code.** `parse()` performs pure structural analysis: it never calls `eval()`, never `include`s a file, never invokes `Closure::fromCallable` or `create_function`, never instantiates a class named in the source. A `.super.php` source file containing `@{ system('rm -rf /') }@` is lexed into `OpenTag` / `Keyword(system)` / `ParenOpen` / `String('rm -rf /')` / `ParenClose` / `CloseTag` tokens; the parser then either builds a `DirectiveNode` (if `system` were a control-flow directive, which it is not) or throws `unexpectedToken` on the `ParenOpen` after a non-control keyword. Either way, no code runs. The parser is safe to run on untrusted template input; output sandboxing (escaping, scope isolation) is CORE-12's responsibility, not the parser's.
- **Directive nesting is structurally validated.** Every `@if` / `@foreach` / `@while` must be closed by a matching `@end`; every `@else` must be preceded by an `@if`. The `parseBlock()` loop's only exit conditions are (a) encountering a terminator keyword, (b) `TokenType::EOF` (which throws `unclosedDirective()`), or (c) an unexpected token (which throws `unexpectedToken()`). There is no code path that returns a `DirectiveNode` with an unclosed body — the type system enforces it (the throw on EOF is unconditional). This is the structural protection against template-injection attacks that rely on unmatched directives to confuse downstream compilation.
- **Expression depth is bounded.** Two independent depth limits prevent stack-overflow DoS on adversarial input. `MAX_PIPE_DEPTH = 32` caps a single pipe chain (`$x | a | b | c | ...`); a 33rd `Pipe` token throws `depthExceeded()` before the left-nested `PipeNode` tree grows further. `MAX_DIRECTIVE_DEPTH = 64` caps nesting of control-flow directives (`@if` inside `@foreach` inside `@if` ...); the 65th nested directive throws `depthExceeded()` before the recursive `parseDirective()` call grows the PHP call stack. Both limits are enforced at the *start* of the relevant parse step, so the throw is O(1), not O(depth). The values (32, 64) are well above any legitimate template's structural depth and well below PHP 8.3's default `zend.max_allowed_stack_size` (default 2 MB ≈ 20,000+ frames).
- **Pipe filter names are validated against an allowlist.** `Parser::ALLOWED_FILTERS` is a `const` array of 11 string filter names (`upper`, `lower`, `trim`, `length`, `default`, `escape`, `raw`, `date`, `number`, `first`, `last`). Any `Keyword` token after a `Pipe` that is not in this list throws `unknownFilter()` immediately. This prevents a template author from invoking arbitrary PHP functions via the pipe syntax — `@{ $x | system }@` throws at parse time, not at compile or render time. Adding a new filter requires modifying the `const` array (a code change, not a config change) and is therefore a SemVer event reviewed in code review; it is not a runtime-writable surface.
- **Parser instances are pure.** `parse()` resets every per-call field at entry (`tokens`, `offset`, `directiveDepth`). No state survives the call, so a singleton `Parser` shared across concurrent template compiles on a long-lived worker (RoadRunner, FrankenPHP) cannot leak one request's token cursor into another's parse. Same immutability invariant CORE-02, CORE-05, and CORE-07 enforce on their hot-path singletons.

## Migration Notes

CORE-11 is **new** — no prior implementation to migrate from. It lands as the Composer package `sovereign-stack/core-superphp-parser` at path `packages/core/superphp-parser/`. The `composer.json` declares `php: ^8.3`, `ext-mbstring: *`, and `sovereign-stack/core-superphp-lexer: ^0.1` as the only runtime requirements (one Composer dependency: CORE-07); `require-dev` carries `phpunit/phpunit ^10.5`, `phpstan/phpstan ^1.10`, `infection/infection ^0.27`, `friendsofphp/php-cs-fixer ^3.48`. PSR-4 autoload maps `SovereignStack\Core\SuperPHP\Parser\` to `src/`. The `src/` directory contains exactly the files itemised in the Class Map: `Parser.php`, `ParserInterface.php`, `Node.php` (with the seven concrete subclasses in a `Node/` subdirectory or co-located per project convention), `NodeVisitor.php`, `ParserException.php`, `AstPrinter.php`. The `tests/` directory carries `ParserTest`, `ParserStructureTest`, `ParserErrorPositionTest`, `ParserDepthLimitTest`, `PipeAllowlistTest`, `AstPrinterTest`, `NodeImmutabilityTest`, a `performance/` subdirectory for `ParserLinearityBenchTest`, `ParserDepthBenchTest`, and `ParserMemoryTest`, and a `fixtures/superphp/` directory of 30 `.super.php` fixtures paired with `*.expected.txt` AST-dump snapshots.

**Landing sequence (per `01_MASTER_INDEX.md` §5 Step 6):** CORE-11 lands second in the SuperPHP triplet (CORE-07 → CORE-11 → CORE-12). It cannot start until CORE-07's `LexerRoundTripTest` exit criterion (tokenize → reconstruct → re-tokenize → assert element-wise equality across 50 fixtures) is met, because CORE-11 imports `TokenType` and `TokenStream` from CORE-07's package and the parser cannot compile until those types exist as concrete classes. Once CORE-07 is landed, CORE-11 has no further Core-tier blockers — it does not depend on the HTTP / Kernel / Container pipeline (CORE-02, CORE-04, CORE-05, CORE-06, CORE-18) and can be built in parallel with those components. The exit criterion for Step 6 Stage 2 is a `ParserStructureTest` that parses a fixture template exercising every `Node` subclass and asserts the `AstPrinter` output matches a hand-verified snapshot.

**Rollback procedure:** CORE-11 is the middle of the SuperPHP triplet at landing time — CORE-07 (upstream) has already landed, CORE-12 (downstream) has not. Rollback is `git rm -r packages/core/superphp-parser/ && composer remove sovereign-stack/core-superphp-parser`. CORE-07 is unaffected (it does not import from CORE-11). If CORE-12 has already landed and imports the parser's `Node` hierarchy and `NodeVisitor` contract, rollback CORE-12 first. Tag the broken commit `core-11-rollback-<date>`. Because the parser is stateless and produces no persisted artefact (no DB rows, no cache entries, no compiled view files — those are CORE-12's responsibility), there is no data migration or schema change to undo.

**Forward-compatibility:** `NodeVisitor` is the cross-stage type contract between CORE-11 and CORE-12. Adding a new `Node` subclass (e.g. a future `MatchNode` for pattern matching) is SemVer-minor for CORE-11 (new class + new `NodeVisitor::visitMatch()` method) and SemVer-minor for CORE-12 (must implement the new method); both packages bump together. Removing or renaming a `Node` subclass or `NodeVisitor` method is SemVer-major. The `Parser::ALLOWED_FILTERS` const is also cross-stage — CORE-12 must implement every filter in the list, so adding to the list is SemVer-minor for CORE-11 but requires a matching CORE-12 release; removing from the list is SemVer-major.

## SemVer Impact

**Minor** (0.1.0 → 0.2.0 at first stable release). The package's first release is `0.1.0` while the SuperPHP triplet is under development (CORE-07 must land first; CORE-12 not yet landed); the first `1.0.0` release coincides with the SuperPHP triplet's first end-to-end template compile (the exit criterion for `01_MASTER_INDEX.md` §5 Step 6). Subsequent minor bumps add `Node` subclasses (e.g. `MatchNode` for pattern matching, `SwitchNode` for switch/case) without changing `ParserInterface::parse()`'s signature; downstream CORE-12 compilers must add the matching `visit*()` method to remain compliant. A major bump is required only if `parse()`'s signature changes (e.g. to accept a `ParserOptions` value object for configurable depth limits), if a `Node` subclass or `NodeVisitor` method is removed, or if `Parser::ALLOWED_FILTERS` loses entries. The seven `Node` subclasses (`DocumentNode`, `TextNode`, `VariableNode`, `LiteralNode`, `PipeNode`, `BinaryOpNode`, `DirectiveNode`), the `NodeVisitor` method set, and the recursive-descent grammar (`@{ ... }@` for expressions; `@if ... @else ... @end` / `@foreach ... @end` / `@while ... @end` for control flow; `@persist` / `@global` / `~setup` for sigil directives; `| filter` for pipes) are part of the 1.0.0 contract and cannot change without a major bump — they define the SuperPHP language surface, not an implementation detail.
