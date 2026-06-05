# Core Implementation Instructions

**Target**: Sovereign Stack — Core Tier Polyrepo Implementation  
**PHP Version**: 8.3  
**Execution Mode**: Sequential, gated, fully automated  
**Audience**: AI coding agent executing these instructions verbatim

---

## Orientation

Do not write a single line of implementation code until you have read **every file** listed below in full. These files define the architecture, infrastructure, and environment contracts that every implementation decision must obey.

Read the following files **before taking any action**:

- [`../blueprints/Core/CORE-01.md`](../blueprints/Core/CORE-01.md)
- [`../blueprints/Core/CORE-02.md`](../blueprints/Core/CORE-02.md)
- [`../blueprints/Core/CORE-03.md`](../blueprints/Core/CORE-03.md)
- [`../blueprints/Core/CORE-04.md`](../blueprints/Core/CORE-04.md)
- [`../blueprints/Core/CORE-05.md`](../blueprints/Core/CORE-05.md)
- [`../blueprints/Core/CORE-06.md`](../blueprints/Core/CORE-06.md)
- [`../blueprints/Core/CORE-07.md`](../blueprints/Core/CORE-07.md)
- [`../blueprints/Core/CORE-08.md`](../blueprints/Core/CORE-08.md)
- [`../blueprints/Core/CORE-09.md`](../blueprints/Core/CORE-09.md)
- [`../blueprints/Core/CORE-10.md`](../blueprints/Core/CORE-10.md)
- [`render.yaml`](render.yaml)
- [`Dockerfile`](Dockerfile)
- [`docker-compose.yml`](docker-compose.yml)
- [`.env.example`](.env.example)

Only after reading all of the above may you proceed to implementation.

---

## Polyrepo Scaffold Standard

Every component repository created during this session **must** adhere to the following scaffold standard without exception.

### Directory Layout

- Every component lives under `repos/` as its own isolated directory.
- The **Orchestrator** is the single exception — it lives in `orchestrator/` at the project root, not under `repos/`.

### Required Files Per Component

Each component directory under `repos/` **must** contain the following files:

#### `composer.json`

- Define the correct PSR-4 autoload namespace as specified in the corresponding blueprint.
- Include `phpstan/phpstan`, `phpunit/phpunit`, and `friendsofphp/php-cs-fixer` as `require-dev` dependencies.
- Set `minimum-stability` to `stable` and `prefer-stable` to `true`.

#### `README.md`

- Begin with the component name and Phase ID (e.g., `CORE-01`).
- Include a one-paragraph description of the component's purpose.
- Reference the blueprint by Phase ID with a link to `../blueprints/Core/CORE-XX.md`.

#### `.gitignore`

- Ignore at minimum: `vendor/`, `.phpunit.result.cache`, `ci/cache/`, `*.log`, `.env`.

#### `phpstan.neon`

- Set `level: max` (maximum analysis level).
- Set `checkGenericClassInNonGenericObjectType: false` if needed for PSR interoperability.
- Configure paths to scan the `src/` directory.

#### `ci/run.php`

Create a single PHP script at `ci/run.php` that executes the following tools **in order**:

1. **PHPStan** — `vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress --error-format=raw`
2. **PHPUnit** — `vendor/bin/phpunit --configuration=phpunit.xml.dist --no-coverage`
3. **PHP CS Fixer** — `vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no`
4. **Composer validate** — `composer validate --no-check-all --strict`

The script must:

- Use `proc_open` or `exec` with the exit code captured for each step.
- Print a clear pass/fail banner for each tool.
- Exit with a **non-zero code** if any single tool fails.
- Not require any arguments or configuration beyond the files present in the component directory.

---

## Implementation Phases

Execute the following ten phases in strict sequential order. Do **not** begin a new phase until the current phase has:

1. A **passing CI run** — execute `php ci/run.php` from the component's root directory and confirm exit code 0.
2. A **committed git state** using the Conventional Commits format (see Git Discipline section).

### Phase CORE-01 — Polyrepo Orchestrator

| Field | Value |
|---|---|
| **Target directory** | `orchestrator/` (project root, not under `repos/`) |
| **PHPStan level** | `max` |
| **Namespace** | `SovereignStack\Orchestrator` |
| **Dependencies** | `czproject/git-php`, `php-http/discovery` |

**Key deliverables:**

- [`RepoManager`](orchestrator/src/RepoManager.php) — Handles git cloning, branching, and tagging. Must use `czproject/git-php` or raw `proc_open` for git operations.
- [`CIMonitor`](orchestrator/src/CIMonitor.php) — Interfaces with GitHub Actions / GitLab CI APIs to verify build health per registered repo. Must make HTTP calls and parse CI status responses.
- [`DependencyGraph`](orchestrator/src/DependencyGraph.php) — Resolves the Core → Hub → Spoke tier hierarchy. Must enforce that Core repos pass before Hub repos are evaluated.
- [`VersionBumpEngine`](orchestrator/src/VersionBumpEngine.php) — Analyzes Conventional Commit messages since the last tag to determine Major/Minor/Patch increments. Must implement SemVer 2.0.0 logic exactly.
- [`orchestrator/bin/loom`](orchestrator/bin/loom) — A PHP CLI entry point that instantiates the Orchestrator and runs a full lifecycle cycle.

**Interfaces defined by blueprint:**

- `RepoManager` class
- `CIMonitor` class  
- `DependencyGraph` class
- `VersionBumpEngine` class

**Verification command:** `php ci/run.php` from `orchestrator/`

---

### Phase CORE-02 — Dependency Injection Container

| Field | Value |
|---|---|
| **Target directory** | `repos/di-container` |
| **PHPStan level** | `max` |
| **Namespace** | `SovereignStack\DI` |
| **Dependencies** | `psr/container` |

**Key deliverables:**

- [`ContainerInterface`](repos/di-container/src/ContainerInterface.php) — Must extend `Psr\Container\ContainerInterface`. Add `bind(string $id, mixed $concrete = null, bool $singleton = false): void` and `make(string $id, array $parameters = []): mixed`.
- [`Container`](repos/di-container/src/Container.php) — Concrete implementation of `ContainerInterface`. Must support recursive reflection-based auto-wiring, singleton/shared instances, and explicit binding via constructor.
- [`DefinitionResolver`](repos/di-container/src/DefinitionResolver.php) — Uses `ReflectionClass` to introspect constructor parameters and resolve dependencies recursively.
- [`CompilerPass`](repos/di-container/src/CompilerPass.php) — Optional but required: analyzes the service graph and generates an optimized flat PHP array dump for production use.

**Interfaces defined by blueprint:**

- `ContainerInterface` (extends `Psr\Container\ContainerInterface`)
- `DefinitionResolver`
- `CompilerPass`

**Verification command:** `php ci/run.php` from `repos/di-container/`

---

### Phase CORE-03 — Event Dispatcher

| Field | Value |
|---|---|
| **Target directory** | `repos/event-dispatcher` |
| **PHPStan level** | `max` |
| **Namespace** | `SovereignStack\Events` |
| **Dependencies** | `psr/event-dispatcher` |

**Key deliverables:**

- [`EventDispatcher`](repos/event-dispatcher/src/EventDispatcher.php) — Implements `Psr\EventDispatcher\EventDispatcherInterface`. Serves as the central hub for emitting events. Must support haltable event propagation.
- [`ListenerProvider`](repos/event-dispatcher/src/ListenerProvider.php) — Implements `Psr\EventDispatcher\ListenerProviderInterface`. Maps event class names to their listener callables based on type-hinting. Must support priority ordering.
- [`StoppableEvent`](repos/event-dispatcher/src/StoppableEvent.php) — Implements `Psr\EventDispatcher\StoppableEventInterface`. Allows listeners to call `stopPropagation()` to halt the pipeline.
- [`Event`](repos/event-dispatcher/src/Event.php) — Base event class that all domain events extend. Must carry a `stamp()` method for immutability tracing.

**Interfaces defined by blueprint:**

- `EventDispatcher` (implements `Psr\EventDispatcher\EventDispatcherInterface`)
- `ListenerProvider` (implements `Psr\EventDispatcher\ListenerProviderInterface`)
- `StoppableEvent` (implements `Psr\EventDispatcher\StoppableEventInterface`)

**Verification command:** `php ci/run.php` from `repos/event-dispatcher/`

---

### Phase CORE-04 — HTTP Message & Factory

| Field | Value |
|---|---|
| **Target directory** | `repos/http-message` |
| **PHPStan level** | `max` |
| **Namespace** | `SovereignStack\Http` |
| **Dependencies** | `psr/http-message`, `psr/http-factory` |

**Key deliverables:**

- [`Request`](repos/http-message/src/Request.php) — Implements `Psr\Http\Message\ServerRequestInterface`. Must enforce immutability (every `with*` method returns a new instance).
- [`Response`](repos/http-message/src/Response.php) — Implements `Psr\Http\Message\ResponseInterface`. Must support a reason phrase map for all standard HTTP status codes.
- [`Stream`](repos/http-message/src/Stream.php) — Implements `Psr\Http\Message\StreamInterface`. Must use `php://temp` for memory-efficient body handling. Must support `__toString()`.
- [`UploadedFile`](repos/http-message/src/UploadedFile.php) — Implements `Psr\Http\Message\UploadedFileInterface`. Must handle file uploads with standardized metadata access without loading entire files into RAM.
- [`RequestFactory`](repos/http-message/src/RequestFactory.php) — Implements `Psr\Http\Message\RequestFactoryInterface`.
- [`ResponseFactory`](repos/http-message/src/ResponseFactory.php) — Implements `Psr\Http\Message\ResponseFactoryInterface`.
- [`StreamFactory`](repos/http-message/src/StreamFactory.php) — Implements `Psr\Http\Message\StreamFactoryInterface`.
- [`UriFactory`](repos/http-message/src/UriFactory.php) — Implements `Psr\Http\Message\UriFactoryInterface`.
- [`ServerRequestFactory`](repos/http-message/src/ServerRequestFactory.php) — Implements `Psr\Http\Message\ServerRequestFactoryInterface`.
- [`UploadedFileFactory`](repos/http-message/src/UploadedFileFactory.php) — Implements `Psr\Http\Message\UploadedFileFactoryInterface`.

**Interfaces defined by blueprint:**

- `Request` (implements `ServerRequestInterface`)
- `Response` (implements `ResponseInterface`)
- `Stream` (implements `StreamInterface`)
- `UploadedFile` (implements `UploadedFileInterface`)
- All six PSR-17 factory interfaces

**Verification command:** `php ci/run.php` from `repos/http-message/`

---

### Phase CORE-05 — HTTP Middleware & Request Handler

| Field | Value |
|---|---|
| **Target directory** | `repos/http-middleware` |
| **PHPStan level** | `max` |
| **Namespace** | `SovereignStack\Middleware` |
| **Dependencies** | `psr/http-server-middleware`, `psr/http-server-handler` |

**Key deliverables:**

- [`MiddlewareInterface`](repos/http-middleware/src/MiddlewareInterface.php) — Must match `Psr\Http\Server\MiddlewareInterface` exactly. Define `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`.
- [`RequestHandler`](repos/http-middleware/src/RequestHandler.php) — Implements `Psr\Http\Server\RequestHandlerInterface`. Coordinates the middleware stack, passing the request through each layer in an onion/ring pattern.
- [`MiddlewareStack`](repos/http-middleware/src/MiddlewareStack.php) — A composable stack that allows middleware to be added, removed, and reordered at runtime.

**Interfaces defined by blueprint:**

- `MiddlewareInterface` (implements `Psr\Http\Server\MiddlewareInterface`)
- `RequestHandler` (implements `Psr\Http\Server\RequestHandlerInterface`)

**Verification command:** `php ci/run.php` from `repos/http-middleware/`

---

### Phase CORE-06 — Attribute-Based Router

| Field | Value |
|---|---|
| **Target directory** | `repos/router` |
| **PHPStan level** | `max` |
| **Namespace** | `SovereignStack\Router` |
| **Dependencies** | `psr/http-message`, `psr/container` |

**Key deliverables:**

- [`RouteAttribute`](repos/router/src/RouteAttribute.php) — A PHP 8.3 Attribute class: `#[Route('/path', method: 'GET', name: 'route.name')]`. Must support `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `HEAD`, `OPTIONS`.
- [`RouteCollector`](repos/router/src/RouteCollector.php) — Scans directories for classes annotated with `#[Route]` attributes and builds the route table. Must support recursive directory scanning.
- [`Dispatcher`](repos/router/src/Dispatcher.php) — Matches a PSR-7 `ServerRequestInterface` against the compiled route table and returns the matched handler. Must use Trie-based prefix matching for sub-5ms resolution with 500+ routes.
- [`UrlGenerator`](repos/router/src/UrlGenerator.php) — Reverse-engineers URLs from named routes and their parameter placeholders.
- [`RouteCompiler`](repos/router/src/RouteCompiler.php) — Dumps the route table to a native PHP file for production use. Must support cache warming.

**Interfaces defined by blueprint:**

- `RouteAttribute` (PHP 8.3 Attribute class)
- `RouteCollector`
- `Dispatcher`
- `UrlGenerator`

**Verification command:** `php ci/run.php` from `repos/router/`

---

### Phase CORE-07 — SuperPHP Lexer

| Field | Value |
|---|---|
| **Target directory** | `repos/superphp-lexer` |
| **PHPStan level** | `max` |
| **Namespace** | `SovereignStack\SuperPHP\Lexer` |
| **Dependencies** | None (standalone) |

**Key deliverables:**

- [`Lexer`](repos/superphp-lexer/src/Lexer.php) — Iterates through a `.super.php` source string and produces a stream of `Token` objects. Must use optimized PCRE patterns for single-pass tokenization. Must lex 1 MB of template source in under 10 ms.
- [`Token`](repos/superphp-lexer/src/Token.php) — An immutable value object carrying `type`, `value`, `line`, and `column`. Must implement `__toString()`.
- [`TokenType`](repos/superphp-lexer/src/TokenType.php) — A PHP 8.3 `enum` defining `T_DIRECTIVE`, `T_COMPONENT_OPEN`, `T_SETUP_BLOCK`, `T_HTML`, `T_PHP`, `T_EXPRESSION`, `T_SLOT`, `T_YIELD`, and any other token types required by the SuperPHP syntax.
- [`PositionTracker`](repos/superphp-lexer/src/PositionTracker.php) — Maintains line and column numbers as the lexer advances through the source for precise error reporting.
- [`TokenStream`](repos/superphp-lexer/src/TokenStream.php) — A traversable collection of `Token` objects consumed by the Parser (future phase). Must support `current()`, `next()`, `peek()`, and `expect()` operations.

**Interfaces defined by blueprint:**

- `Lexer`
- `TokenType` (enum)
- `Token`
- `PositionTracker`
- `TokenStream`

**Verification command:** `php ci/run.php` from `repos/superphp-lexer/`

---

### Phase CORE-08 — Global Error & Exception Handler

| Field | Value |
|---|---|
| **Target directory** | `repos/error-handler` |
| **PHPStan level** | `max` |
| **Namespace** | `SovereignStack\Error` |
| **Dependencies** | `psr/log` |

**Key deliverables:**

- [`ExceptionHandler`](repos/error-handler/src/ExceptionHandler.php) — Registered via `set_exception_handler`. Must capture all uncaught `Throwable` instances. In production mode must force `display_errors` to `0` and render a generic "Server Error" response.
- [`ErrorHandler`](repos/error-handler/src/ErrorHandler.php) — Registered via `set_error_handler`. Must convert all PHP errors (Warnings, Notices, Deprecations) into `ErrorException` instances.
- [`RendererInterface`](repos/error-handler/src/RendererInterface.php) — Defines `render(Throwable $e): string`. Must support strategy implementations for CLI (plain text), SuperPHP (HTML error pages), and JSON (API error responses).
- [`CliRenderer`](repos/error-handler/src/CliRenderer.php) — Renders errors with color-coded output for terminal consumption.
- [`JsonRenderer`](repos/error-handler/src/JsonRenderer.php) — Renders errors as structured JSON with `error`, `code`, `message`, and `trace` keys (trace omitted in production).
- [`AuditBridge`](repos/error-handler/src/AuditBridge.php) — Automatically dispatches a `security.error` event via the CORE-03 Event Dispatcher when a critical error is caught. Must not throw if the dispatcher is unavailable.

**Interfaces defined by blueprint:**

- `ExceptionHandler`
- `ErrorHandler`
- `RendererInterface`
- `AuditBridge`

**Verification command:** `php ci/run.php` from `repos/error-handler/`

---

### Phase CORE-09 — PSR-3 Logging Service

| Field | Value |
|---|---|
| **Target directory** | `repos/logger` |
| **PHPStan level** | `max` |
| **Namespace** | `SovereignStack\Log` |
| **Dependencies** | `psr/log` |

**Key deliverables:**

- [`Logger`](repos/logger/src/Logger.php) — Implements `Psr\Log\LoggerInterface`. Must support all eight RFC 5424 log levels: `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug`.
- [`HandlerStack`](repos/logger/src/HandlerStack.php) — Allows multiple handlers to process a single log record. Must support priority ordering and push/pop operations.
- [`HandlerInterface`](repos/logger/src/HandlerInterface.php) — Defines `handle(array $record): bool`. All handlers must implement this.
- [`FileHandler`](repos/logger/src/FileHandler.php) — Writes log records to a file. Must use `flock` for concurrent write safety. Must support log rotation.
- [`NullHandler`](repos/logger/src/NullHandler.php) — Discards all log records. Useful for testing.
- [`FormatterInterface`](repos/logger/src/FormatterInterface.php) — Defines `format(array $record): string`.
- [`JsonFormatter`](repos/logger/src/JsonFormatter.php) — Converts log arrays into JSON strings for structured logging.
- [`LineFormatter`](repos/logger/src/LineFormatter.php) — Converts log arrays into plain-text lines with a configurable template pattern.

**Interfaces defined by blueprint:**

- `Logger` (implements `Psr\Log\LoggerInterface`)
- `HandlerStack`
- `HandlerInterface`
- `FormatterInterface`

**Verification command:** `php ci/run.php` from `repos/logger/`

---

### Phase CORE-10 — Configuration & Environment Loader

| Field | Value |
|---|---|
| **Target directory** | `repos/config-loader` |
| **PHPStan level** | `max` |
| **Namespace** | `SovereignStack\Config` |
| **Dependencies** | None (standalone — uses `$_ENV` superglobal) |

**Key deliverables:**

- [`ConfigRepository`](repos/config-loader/src/ConfigRepository.php) — An immutable key-value store with dot-notation access: `get('app.name', $default)`. Must support `has()`, `get()`, and `all()`. Must compile disparate config files into a single PHP array for ultra-fast production access.
- [`EnvLoader`](repos/config-loader/src/EnvLoader.php) — Parses `.env` files and populates `$_ENV`. Must handle `#` comments, quoted values, and variable interpolation (`APP_URL=${BASE_URL}/api`). Must fail with a `RuntimeException` if a required variable is missing.
- [`Processor`](repos/config-loader/src/Processor.php) — Handles value interpolation and type-casting (string to int, bool, array). Must support recursive interpolation.
- [`ConfigCache`](repos/config-loader/src/ConfigCache.php) — Compiles the resolved configuration into a single PHP file that can be `require`'d for sub-0.01ms key resolution.

**Interfaces defined by blueprint:**

- `ConfigRepository`
- `EnvLoader`
- `Processor`
- `ConfigCache`

**Verification command:** `php ci/run.php` from `repos/config-loader/`

---

## Git Discipline

Every repository (including the Orchestrator at `orchestrator/`) follows the same strict commit discipline.

### Commit Sequence

Exactly **three commits** per repository, applied in the following order:

| Order | Type | Message Format | Content |
|---|---|---|---|
| 1 | Scaffold | `feat(scope): scaffold {ComponentName} repository` | `composer.json`, `README.md`, `.gitignore`, `phpstan.neon`, `ci/run.php`, directory structure with empty `src/` and `tests/` directories |
| 2 | Implementation | `feat(scope): implement {ComponentName} core logic` | All source files under `src/`, all interfaces and classes fully implemented |
| 3 | Test | `test(scope): add {ComponentName} unit tests` | All PHPUnit test files under `tests/` covering every public method |

### Hard Rules

- Each commit happens **only after** `php ci/run.php` exits with code 0.
- Commit messages **must** follow the Conventional Commits format: `type(scope): description`.
- Allowed types: `feat`, `fix`, `test`, `chore`, `docs`, `refactor`.
- Scope **must** match the component name or Phase ID in kebab-case (e.g., `di-container`, `event-dispatcher`, `core-01`).
- Do **not** amend or squash commits. Push all three as distinct commits.
- Do **not** commit any file that would cause `ci/run.php` to fail.

---

## Quality Gates

The following standards are **non-negotiable**. Every file you produce must pass every gate before you move to the next phase.

### PHP 8.3 Strict Typing

- Every function and method **must** have a declared return type. No missing return types.
- Every parameter **must** have a declared type.
- The `mixed` type is **forbidden**. Use `string|int|float|bool|array|null` union types or specific class/interface types instead.
- Use PHP 8.3 features where appropriate: constructor promotion, `readonly` properties, `enum`, intersection types, `#[Override]` attribute.

### PHPStan at Maximum Level

- PHPStan level must be set to `max` in `phpstan.neon`.
- Zero errors. Zero ignored errors. Zero baseline exceptions.
- Run `vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress --error-format=raw` and confirm exit code 0.

### PHPUnit Coverage

- Every **public method** must be covered by at least one PHPUnit test.
- Test classes must be named `{ClassName}Test` and extend `PHPUnit\Framework\TestCase`.
- Tests must use PHP 8.3 syntax and type declarations.
- Use data providers for parameterized test cases where appropriate.
- Run `vendor/bin/phpunit --configuration=phpunit.xml.dist --no-coverage` and confirm exit code 0.

### PSR-12 Compliance

- All PHP files must comply with PSR-12 coding style.
- Run `vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no` and confirm exit code 0.
- No trailing whitespace. No short open tags. Correct namespace and use statement ordering.

---

## Session Completion Checklist

Once all ten implementation phases are complete and each has passed its CI run and committed state, execute the following final steps.

### Step 1: Run the Orchestrator CI Monitor

Execute the Orchestrator's CI monitor against all ten component repositories.

```bash
php orchestrator/bin/loom ci:monitor --all
```

This must:

- Check the CI status of every repo (`orchestrator/`, `repos/di-container/`, `repos/event-dispatcher/`, `repos/http-message/`, `repos/http-middleware/`, `repos/router/`, `repos/superphp-lexer/`, `repos/error-handler/`, `repos/logger/`, `repos/config-loader/`).
- For each repo, re-run `php ci/run.php` and capture the exit code.
- Calculate a SemVer bump for each repo based on the commit history since the initial scaffold.

### Step 2: Output the Consolidated Report

Write the complete consolidated report to [`orchestrator/reports/session-01.json`](orchestrator/reports/session-01.json).

The JSON **must** contain the following structure:

```json
{
    "session": "01",
    "timestamp": "ISO-8601-DATETIME",
    "repos": [
        {
            "name": "orchestrator",
            "phase": "CORE-01",
            "ci_status": "pass",
            "current_version": "0.1.0",
            "errors": []
        }
    ],
    "summary": {
        "total_repos": 10,
        "passing": 10,
        "failing": 0
    }
}
```

### Step 3: Print Terminal Summary

Print a summary table to the terminal in the following format:

```
+---------------------------+--------+---------+
| Repo                      | CI     | Version |
+---------------------------+--------+---------+
| orchestrator              | PASS   | 0.1.0   |
| di-container              | PASS   | 0.1.0   |
| event-dispatcher          | PASS   | 0.1.0   |
| http-message              | PASS   | 0.1.0   |
| http-middleware           | PASS   | 0.1.0   |
| router                    | PASS   | 0.1.0   |
| superphp-lexer            | PASS   | 0.1.0   |
| error-handler             | PASS   | 0.1.0   |
| logger                    | PASS   | 0.1.0   |
| config-loader             | PASS   | 0.1.0   |
+---------------------------+--------+---------+
| Total: 10 | Passing: 10 | Failing: 0       |
+---------------------------+--------+---------+
```

If any repo has a failing CI status, **do not** write the report file. Print the failures to stderr and abort.