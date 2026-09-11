<?php

declare(strict_types=1);

namespace SovereignStack\Core\ErrorHandler\Renderer;

use SovereignStack\Core\ErrorHandler\RendererInterface;
use Throwable;

/**
 * Renders throwables as a JSON object — suitable for API responses.
 *
 * Output shape:
 *   {
 *     "error": {
 *       "type": "RuntimeException",
 *       "message": "Something went wrong",
 *       "file": "/path/to/file.php",       // debug only
 *       "line": 42,                          // debug only
 *       "trace": "..."                       // debug only, sanitized
 *     }
 *   }
 *
 * In non-debug mode, only `type` and a generic `message` are emitted.
 * Sensitive details (file paths, stack traces, previous exceptions) are
 * suppressed to prevent information disclosure per blueprint security
 * requirement.
 */
final class JsonRenderer implements RendererInterface
{
    public function render(Throwable $throwable, bool $debug): string
    {
        $payload = [
            'error' => [
                'type' => $throwable::class,
                'message' => $debug ? $throwable->getMessage() : $this->genericMessage($throwable),
            ],
        ];

        if ($debug) {
            $payload['error']['file'] = $throwable->getFile();
            $payload['error']['line'] = $throwable->getLine();
            $payload['error']['trace'] = $this->sanitizeTrace($throwable->getTraceAsString());

            $previous = $throwable->getPrevious();
            if ($previous instanceof Throwable) {
                $payload['error']['previous'] = [
                    'type' => $previous::class,
                    'message' => $previous->getMessage(),
                    'file' => $previous->getFile(),
                    'line' => $previous->getLine(),
                    'trace' => $this->sanitizeTrace($previous->getTraceAsString()),
                ];
            }
        }

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    public function contentType(): string
    {
        return 'application/json; charset=utf-8';
    }

    /**
     * Generic client-facing message that doesn't leak implementation details.
     *
     * For specific "not found" / "unauthorised" exception types, returns a
     * more useful message. For everything else, returns a generic server
     * error message.
     */
    private function genericMessage(Throwable $e): string
    {
        return match (true) {
            $e instanceof \InvalidArgumentException => 'Bad request',
            $e instanceof \DomainException => 'Bad request',
            $e instanceof \LengthException => 'Bad request',
            $e instanceof \OutOfRangeException => 'Bad request',
            $e instanceof \OutOfBoundsException => 'Not found',
            $e instanceof \UnexpectedValueException => 'Bad request',
            default => 'Internal server error',
        };
    }

    /**
     * Sanitize a stack trace by redacting arguments that may contain
     * sensitive data (passwords, tokens, env vars).
     *
     * The current implementation preserves the trace as-is in debug mode
     * because the trace is only emitted when $debug is true. In production
     * (non-debug), the trace is never emitted.
     *
     * Future enhancement: scrub `password=...`, `Bearer ...`, etc. from
     * the trace string for safer debug output in staging.
     */
    private function sanitizeTrace(string $trace): string
    {
        // Strip absolute paths outside the project root, if any.
        // For now, this is a passthrough — the trace is only emitted in debug mode.
        return $trace;
    }
}
