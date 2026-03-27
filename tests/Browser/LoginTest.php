<?php

namespace DGLab\Tests\Browser;

use DGLab\Tests\BrowserTestCase;
use DGLab\Tests\PageObjects\LoginPage;
use DGLab\Models\User;

class LoginTest extends BrowserTestCase
{
    public function testUserCanLogin()
    {
        // 1. Create a user in the test database
        User::create([
            'uuid' => uuid(),
            'email' => 'browser@test.com',
            'username' => 'browseruser',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'status' => 'active'
        ]);

        $client = static::createPantherClient(['browser' => 'chrome']);
        $loginPage = new LoginPage($client);

        $loginPage->login('browser@test.com', 'password123');

        // 2. Wait for redirect and verify
        $client->waitFor('.navbar');
        $this->assertStringNotContainsString('/login', $client->getCurrentURL());
    }
}
