<?php

namespace DGLab\Services\Auth\Contracts;

/**
 * Interface for social authentication providers
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
     * Handle the callback from the provider.
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
