<?php

namespace DGLab\Services\Auth\Contracts;

interface SocialProviderInterface
{
    public function getAuthUrl(): string;

    public function handleCallback(array $params): array;

    public function getName(): string;
}
