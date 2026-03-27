<?php

namespace DGLab\Tests\PageObjects;

use Symfony\Component\Panther\Client;

class LoginPage
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function login(string $email, string $password): void
    {
        $crawler = $this->client->request('GET', '/login');

        $form = $crawler->filter('form')->form([
            'email' => $email,
            'password' => $password,
        ]);

        $this->client->submit($form);
    }
}
