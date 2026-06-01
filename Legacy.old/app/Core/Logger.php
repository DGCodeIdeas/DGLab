<?php

/**
 * DGLab Logger
 *
 * PSR-3 compliant logging with file and database support.
 *
 * @package DGLab\Core
 */

namespace DGLab\Core;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class Logger
 *
 * Provides logging functionality with:
 * - PSR-3 compliance
 * - Multiple handlers (file, database)
 * - Log rotation
 * - Context enrichment
 * - Error tracking
 */
class Logger implements LoggerInterface
{
    /**
     * Log levels
     */
    private const LEVELS = [
        LogLevel::DEBUG => 100,
        LogLevel::INFO => 200,
        LogLevel::NOTICE => 250,
        LogLevel::WARNING => 300,
        LogLevel::ERROR => 400,
        LogLevel::CRITICAL => 500,
        LogLevel::ALERT => 550,
        LogLevel::EMERGENCY => 600,
    ];

    /**
     * Logs directory
     */
    private string $logPath;

    /**
     * Minimum log level
     */
    private int $minLevel;

    /**
     * Channel name
     */
    private string $channel;

    /**
     * Database connection for structured logging
     */
    private ?\DGLab\Database\Connection $db = null;

    /**
     * Whether to log to database
     */
    private bool $useDatabase = false;

    /**
     * Error handler registered
     */
    private bool $errorHandlerRegistered = false;

    /**
     * Constructor
     */
    public function __construct(
        ?string $logPath = null,
        string $minLevel = LogLevel::DEBUG,
        string $channel = 'app'
    ) {
        $this->logPath = $logPath ?? Application::getInstance()->getBasePath() . '/storage/logs';
        $this->minLevel = self::LEVELS[$minLevel] ?? 100;
        $this->channel = $channel;

        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    /**
     * Enable database logging
     */
    public function enableDatabaseLogging(\DGLab\Database\Connection $db): void
    {
        $this->db = $db;
        $this->useDatabase = true;
    }

    /**
     * Log a message
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $levelValue = self::LEVELS[$level] ?? 100;

        if ($levelValue < $this->minLevel) {
            return;
        }

        $record = $this->createRecord($level, (string) $message, $context);

        // Write to file
        $this->writeToFile($record);

        // Write to database if enabled
        if ($this->useDatabase && $levelValue >= self::LEVELS[LogLevel::WARNING]) {
            $this->writeToDatabase($record);
        }
    }

    /**
     * System is unusable
     */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Create log record
     */
    private function createRecord(string $level, string $message, array $context): array
    {
        // Interpolate message
        $message = $this->interpolate($message, $context);

        return [
            'datetime' => new \DateTimeImmutable(),
            'channel' => $this->channel,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'extra' => [
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'request_id' => $context['request_id'] ?? uniqid(),
            ],
        ];
    }

    /**
     * Interpolate message with context
     */
    private function interpolate(string $message, array $context): string
    {
        $replacements = [];

        foreach ($context as $key => $val) {
            if (is_null($val) || is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replacements['{' . $key . '}'] = $val;
            } elseif (is_object($val)) {
                $replacements['{' . $key . '}'] = '[object ' . get_class($val) . ']';
            } else {
                $replacements['{' . $key . '}'] = '[' . gettype($val) . ']';
            }
        }

        return strtr($message, $replacements);
    }

    /**
     * Write to file
     */
    private function writeToFile(array $record): void
    {
        $filename = $this->logPath . '/' . $this->channel . '-' . date('Y-m-d') . '.log';

        $line = sprintf(
            "[%s] %s.%s: %s %s\n",
            $record['datetime']->format('Y-m-d H:i:s.u'),
            $record['channel'],
            strtoupper($record['level']),
            $record['message'],
            !empty($record['context']) ? json_encode($record['context']) : ''
        );

        file_put_contents($filename, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Write to database
     */
    private function writeToDatabase(array $record): void
    {
        if ($this->db === null) {
            return;
        }

        try {
            $this->db->insert(
                "INSERT INTO service_logs (service_id, level, message, context, created_at) VALUES (?, ?, ?, ?, ?)",
                [
                    $record['context']['service'] ?? 'app',
                    $record['level'],
                    $record['message'],
                    json_encode($record['context']),
                    $record['datetime']->format('Y-m-d H:i:s'),
                ]
            );
        } catch (\Exception $e) {
            // Fail silently to avoid infinite loops
        }
    }

    /**
     * Register error handler
     */
    public function registerErrorHandler(): void
    {
        if ($this->errorHandlerRegistered) {
            return;
        }

        set_error_handler(function ($level, $message, $file, $line) {
            $levelMap = [
                E_WARNING => LogLevel::WARNING,
                E_NOTICE => LogLevel::NOTICE,
                E_DEPRECATED => LogLevel::DEBUG,
                E_USER_ERROR => LogLevel::ERROR,
                E_USER_WARNING => LogLevel::WARNING,
                E_USER_NOTICE => LogLevel::NOTICE,
            ];

            $logLevel = $levelMap[$level] ?? LogLevel::ERROR;

            $this->log($logLevel, $message, [
                'file' => $file,
                'line' => $line,
            ]);

            return false;
        });

        set_exception_handler(function (\Throwable $e) {
            $this->critical($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        });

        $this->errorHandlerRegistered = true;
    }

    /**
     * Get recent logs
     */
    public function getRecent(int $lines = 100): array
    {
        $filename = $this->logPath . '/' . $this->channel . '-' . date('Y-m-d') . '.log';

        if (!file_exists($filename)) {
            return [];
        }

        $file = new \SplFileObject($filename, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $lines);
        $file->seek($startLine);

        $logs = [];
        while (!$file->eof()) {
            $logs[] = $file->current();
            $file->next();
        }

        return $logs;
    }

    /**
     * Rotate logs
     */
    public function rotate(int $maxDays = 7): int
    {
        $count = 0;
        $files = glob($this->logPath . '/*.log');

        foreach ($files as $file) {
            $mtime = filemtime($file);
            $age = (time() - $mtime) / 86400;

            if ($age > $maxDays) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get log statistics
     */
    public function stats(): array
    {
        $files = glob($this->logPath . '/*.log');
        $totalSize = 0;

        foreach ($files as $file) {
            $totalSize += filesize($file);
        }

        return [
            'files' => count($files),
            'size_bytes' => $totalSize,
            'size_human' => $this->formatBytes($totalSize),
        ];
    }

    /**
     * Format bytes
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes > 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}
