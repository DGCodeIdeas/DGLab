<?php

namespace DGLab\Services\Auth\Contracts;

/**
 * Interface for Social Authentication Providers
 */
interface SocialProviderInterface
{
    /**
     * Get the authentication URL for the provider.
     *
     * @return string
     */
    public function getAuthUrl(): string;

    /**
     * Handle the callback from the provider and return user data.
     *
     * @param array $params
     * @return array
     */
    public function handleCallback(array $params): array;

    /**
     * Get the name of the provider.
     *
     * @return string
     */
    public function getName(): string;
}
