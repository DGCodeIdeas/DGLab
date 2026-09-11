<?php

declare(strict_types=1);

namespace SovereignStack\Core\ErrorHandler\Renderer;

use SovereignStack\Core\ErrorHandler\RendererInterface;
use Throwable;

/**
 * Renders throwables as plain text — suitable for CLI output or as a fallback.
 *
 * Debug mode: full stack trace, file, line, previous exceptions.
 * Production mode: one-line generic error message.
 */
final class PlainTextRenderer implements RendererInterface
{
    public function render(Throwable $throwable, bool $debug): string
    {
        if (!$debug) {
            return $this->genericMessage($throwable) . "\n";
        }

        $lines = [
            sprintf(
                '%s: %s in %s:%d',
                $throwable::class,
                $throwable->getMessage(),
                $throwable->getFile(),
                $throwable->getLine(),
            ),
            '',
            'Stack trace:',
            $throwable->getTraceAsString(),
        ];

        $previous = $throwable->getPrevious();
        while ($previous instanceof Throwable) {
            $lines[] = '';
            $lines[] = 'Caused by:';
            $lines[] = sprintf(
                '%s: %s in %s:%d',
                $previous::class,
                $previous->getMessage(),
                $previous->getFile(),
                $previous->getLine(),
            );
            $lines[] = $previous->getTraceAsString();
            $previous = $previous->getPrevious();
        }

        return implode("\n", $lines) . "\n";
    }

    public function contentType(): string
    {
        return 'text/plain; charset=utf-8';
    }

    private function genericMessage(Throwable $e): string
    {
        return match (true) {
            $e instanceof \InvalidArgumentException => 'Bad request',
            $e instanceof \DomainException => 'Bad request',
            $e instanceof \OutOfBoundsException => 'Not found',
            default => 'Internal server error',
        };
    }
}
