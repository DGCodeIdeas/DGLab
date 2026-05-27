<?php

namespace DGLab\Services\Auth\Social;

use DGLab\Services\Auth\Contracts\SocialProviderInterface;

abstract class AbstractSocialProvider implements SocialProviderInterface
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    abstract public function getAuthUrl(): string;
    abstract public function handleCallback(array $params): array;
    abstract public function getName(): string;
}
