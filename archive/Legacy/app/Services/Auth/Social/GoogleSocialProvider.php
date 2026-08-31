<?php

namespace DGLab\Services\Auth\Social;

/**
 * Google Social Provider
 */
class GoogleSocialProvider extends AbstractSocialProvider
{
    public function getAuthUrl(): string
    {
        $params = [
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect'],
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => bin2hex(random_bytes(16)),
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public function handleCallback(array $params): array
    {
        return [
            'id' => 'google-user-id',
            'email' => 'user@google.com',
            'name' => 'Google User',
            'avatar' => 'https://google.com/avatar.png',
        ];
    }

    public function getName(): string
    {
        return 'google';
    }
}
