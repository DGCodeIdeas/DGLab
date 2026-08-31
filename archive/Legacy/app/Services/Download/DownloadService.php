<?php

namespace DGLab\Services\Download;

use DGLab\Services\BaseService;

/**
 * Download Service Wrapper
 *
 * Provides compatibility with the ServiceInterface for the DownloadManager.
 */
class DownloadService extends BaseService
{
    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return 'download-service';
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'Download Service';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Core service for managing file downloads and storage.';
    }

    /**
     * @inheritDoc
     */
    public function getIcon(): string
    {
        return 'bi-download';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string'],
            ],
            'required' => ['path'],
        ];
    }

    /**
     * @inheritDoc
     */
    public function validate(array $input): array
    {
        return $input;
    }

    /**
     * @inheritDoc
     */
    public function process(array $input, ?callable $progressCallback = null): array
    {
        // Implementation for API-based downloads if needed
        return [
            'url' => DownloadManager::getInstance()->getUrl($input['path']),
        ];
    }

    /**
     * @inheritDoc
     */
    public function estimateTime(array $input): int
    {
        return 1;
    }

    /**
     * @inheritDoc
     */
    public function supportsChunking(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getConfig(): array
    {
        return [];
    }
}
