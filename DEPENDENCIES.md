# Dependencies

This document lists all external dependencies used across DGLab's packages and infrastructure.

## Runtime dependencies (per package)

### packages/core/container (CORE-02)
| Dependency | Version | Purpose |
|------------|---------|---------|
| `psr/container` | ^2.0 | PSR-11 container interface |

### packages/core/event-dispatcher (CORE-03)
| Dependency | Version | Purpose |
|------------|---------|---------|
| `psr/event-dispatcher` | ^1.0 | PSR-14 event dispatcher interface |
| `psr/container` | ^2.0 (suggest) | Lazy listener resolution |
| `psr/log` | ^3.0 (suggest) | Error logging during dispatch |

### packages/core/http-message (CORE-04)
| Dependency | Version | Purpose |
|------------|---------|---------|
| `psr/http-message` | ^2.0 | PSR-7 message interfaces |
| `psr/http-factory` | ^1.0 | PSR-17 factory interfaces |
| `ext-mbstring` | * | Multibyte header validation |
| `ext-fileinfo` | * | UploadedFile media type detection |

### packages/core/middleware (CORE-05)
| Dependency | Version | Purpose |
|------------|---------|---------|
| `psr/http-message` | ^2.0 | HTTP message types |
| `psr/http-server-handler` | ^1.0 | PSR-15 request handler interface |
| `psr/http-server-middleware` | ^1.0 | PSR-15 middleware interface |
| `psr/container` | ^2.0 | Class-string middleware resolution |
| `sovereign-stack/core-http-message` | ^1.0 | Response/Stream for FinalRequestHandler |

### packages/core/router (CORE-06)
| Dependency | Version | Purpose |
|------------|---------|---------|
| `psr/http-message` | ^2.0 | ServerRequestInterface |
| `sovereign-stack/core-http-message` | ^1.0 | ServerRequest + Uri for matching |
| `ext-mbstring` | * | Multibyte path handling |
| `ext-pcre` | * | PCRE-JIT for route matching |

### orchestrator (CORE-01: Loom)
| Dependency | Version | Purpose |
|------------|---------|---------|
| `psr/container` | ^2.0 | Container interface (for version tests) |
| `psr/log` | ^3.0 (suggest) | Diagnostic logging |

## Development dependencies (shared across packages)

| Dependency | Version | Purpose |
|------------|---------|---------|
| `phpunit/phpunit` | ^10.5 | Testing framework |
| `phpstan/phpstan` | ^2.2 | Static analysis (level 8) |
| `friendsofphp/php-cs-fixer` | ^3.48 | Code style enforcement |

## Infrastructure dependencies

### Anvil (deployment stack)
| Component | Version | Purpose |
|-----------|---------|---------|
| Caddy | 2.11.4 | Edge server (TLS, HTTP/3) |
| FrankenPHP | 1.12.7 | App server (PHP 8.3, Fiber workers) |
| Tengine | 3.2.0-rc5 | Internal LB (dyups, health checks) |
| Docker Engine | latest | Dev stack (MySQL, Redis, phpMyAdmin) |
| dnsmasq | latest | *.test local DNS |
| mkcert | latest | Local CA + per-project certs |
| dart-sass | latest | SCSS compilation |

### CI/CD
| Tool | Purpose |
|------|---------|
| GitHub Actions | CI + release automation |
| `pv` | Download progress bars |
| `jq` | JSON parsing in release workflow |
| `gh` CLI | GitHub Release creation |

## PHP extensions

| Extension | Required by | Purpose |
|-----------|-------------|---------|
| `ext-mbstring` | CORE-04, CORE-06 | Multibyte string handling |
| `ext-fileinfo` | CORE-04 | MIME type detection |
| `ext-pcre` | CORE-06 | PCRE regex for route matching |
| `ext-json` | All | JSON encoding/decoding |
| `ext-openssl` | (future) CORE-16 | Encryption |
| `ext-pdo` | (future) CORE-19 | Database abstraction |

## No external services required

DGLab's Core packages have zero runtime service dependencies. No database, cache, queue, or external API is needed to use `core/container`, `core/event-dispatcher`, `core/http-message`, `core/middleware`, or `core/router`. Service dependencies (MySQL, Redis) are introduced at the Hub tier and are configurable via CORE-10 (Config).
