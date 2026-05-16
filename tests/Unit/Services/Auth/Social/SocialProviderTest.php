<?php

namespace DGLab\Tests\Unit\Services\Auth\Social;

use DGLab\Services\Auth\Social\GoogleSocialProvider;
use DGLab\Services\Auth\Contracts\SocialProviderInterface;
use PHPUnit\Framework\TestCase;

class SocialProviderTest extends TestCase
{
    public function test_google_provider_implements_interface()
    {
        $config = [
            'client_id' => 'test-id',
            'redirect' => 'http://localhost/callback',
        ];
        $provider = new GoogleSocialProvider($config);

        $this->assertInstanceOf(SocialProviderInterface::class, $provider);
    }

    public function test_google_provider_methods()
    {
        $config = [
            'client_id' => 'test-id',
            'redirect' => 'http://localhost/callback',
        ];
        $provider = new GoogleSocialProvider($config);

        $this->assertEquals('google', $provider->getName());
        $this->assertStringContainsString('client_id=test-id', $provider->getAuthUrl());

        $callbackData = $provider->handleCallback(['code' => 'test-code']);
        $this->assertArrayHasKey('email', $callbackData);
    }
}
