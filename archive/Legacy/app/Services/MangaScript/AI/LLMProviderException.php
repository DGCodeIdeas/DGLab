<?php

/**
 * DGLab LLM Provider Exception
 *
 * Exception thrown by LLM providers with detailed context.
 *
 * @package DGLab\Services\MangaScript\AI
 */

namespace DGLab\Services\MangaScript\AI;

/**
 * Class LLMProviderException
 *
 * Represents errors from LLM providers with retry and fallback context.
 */
class LLMProviderException extends \Exception
{
    /**
     * Error type constants
     */
    public const TYPE_RATE_LIMIT = 'rate_limit';
    public const TYPE_AUTH = 'authentication';
    public const TYPE_QUOTA = 'quota_exceeded';
    public const TYPE_TIMEOUT = 'timeout';
    public const TYPE_NETWORK = 'network';
    public const TYPE_CONTENT_FILTER = 'content_filter';
    public const TYPE_INVALID_REQUEST = 'invalid_request';
    public const TYPE_SERVER_ERROR = 'server_error';
    public const TYPE_MODEL_NOT_FOUND = 'model_not_found';
    public const TYPE_CONTEXT_LENGTH = 'context_length_exceeded';
    public const TYPE_UNKNOWN = 'unknown';

    /**
     * Constructor
     */
    public function __construct(
        string $message,
        public readonly string $errorType = self::TYPE_UNKNOWN,
        public readonly string $provider = 'unknown',
        public readonly ?string $model = null,
        public readonly bool $isRetryable = false,
        public readonly ?int $retryAfterSeconds = null,
        public readonly array $context = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create from HTTP status code
     */
    public static function fromHttpStatus(
        int $status,
        string $message,
        string $provider,
        ?string $model = null,
        array $context = []
    ): self {
        return match ($status) {
            401, 403 => new self(
                message: $message,
                errorType: self::TYPE_AUTH,
                provider: $provider,
                model: $model,
                isRetryable: false,
                context: $context,
                code: $status
            ),
            429 => new self(
                message: $message,
                errorType: self::TYPE_RATE_LIMIT,
                provider: $provider,
                model: $model,
                isRetryable: true,
                retryAfterSeconds: $context['retry_after'] ?? 60,
                context: $context,
                code: $status
            ),
            500, 502, 503 => new self(
                message: $message,
                errorType: self::TYPE_SERVER_ERROR,
                provider: $provider,
                model: $model,
                isRetryable: true,
                retryAfterSeconds: 5,
                context: $context,
                code: $status
            ),
            408, 504 => new self(
                message: $message,
                errorType: self::TYPE_TIMEOUT,
                provider: $provider,
                model: $model,
                isRetryable: true,
                retryAfterSeconds: 1,
                context: $context,
                code: $status
            ),
            default => new self(
                message: $message,
                errorType: self::TYPE_UNKNOWN,
                provider: $provider,
                model: $model,
                isRetryable: false,
                context: $context,
                code: $status
            )
        };
    }

    /**
     * Create rate limit exception
     */
    public static function rateLimited(
        string $provider,
        ?string $model = null,
        int $retryAfter = 60
    ): self {
        return new self(
            message: "Rate limited by {$provider}",
            errorType: self::TYPE_RATE_LIMIT,
            provider: $provider,
            model: $model,
            isRetryable: true,
            retryAfterSeconds: $retryAfter
        );
    }

    /**
     * Create content filtered exception
     */
    public static function contentFiltered(
        string $provider,
        ?string $model = null,
        string $reason = 'Content blocked by safety filters'
    ): self {
        return new self(
            message: $reason,
            errorType: self::TYPE_CONTENT_FILTER,
            provider: $provider,
            model: $model,
            isRetryable: false
        );
    }

    /**
     * Create context length exceeded exception
     */
    public static function contextLengthExceeded(
        string $provider,
        ?string $model = null,
        int $requested = 0,
        int $maximum = 0
    ): self {
        return new self(
            message: "Context length exceeded: requested {$requested}, maximum {$maximum}",
            errorType: self::TYPE_CONTEXT_LENGTH,
            provider: $provider,
            model: $model,
            isRetryable: false,
            context: [
                'requested_tokens' => $requested,
                'maximum_tokens' => $maximum,
            ]
        );
    }

    /**
     * Check if should fallback to another provider
     */
    public function shouldFallback(): bool
    {
        return in_array($this->errorType, [
            self::TYPE_RATE_LIMIT,
            self::TYPE_QUOTA,
            self::TYPE_SERVER_ERROR,
            self::TYPE_TIMEOUT,
            self::TYPE_NETWORK,
        ]);
    }

    /**
     * Check if is content-related error
     */
    public function isContentError(): bool
    {
        return $this->errorType === self::TYPE_CONTENT_FILTER;
    }

    /**
     * Get full context for logging
     */
    public function getFullContext(): array
    {
        return array_merge($this->context, [
            'error_type' => $this->errorType,
            'provider' => $this->provider,
            'model' => $this->model,
            'is_retryable' => $this->isRetryable,
            'retry_after_seconds' => $this->retryAfterSeconds,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ]);
    }
}
