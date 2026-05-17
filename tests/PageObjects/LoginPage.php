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

    public function open(): void
    {
        $this->client->request('GET', '/auth/login');
    }

    public function fillCredentials(string $email, string $password): void
    {
        $crawler = $this->client->waitFor('input[name="email"]');
        $crawler->filter('input[name="email"]')->sendKeys($email);
        $crawler->filter('input[name="password"]')->sendKeys($password);
    }

    public function submit(): void
    {
        $this->client->getCrawler()->filter('button[type="submit"]')->click();
    }

    public function login(string $email, string $password): void
    {
        $this->open();
        $this->fillCredentials($email, $password);
        $this->submit();
    }

    public function isVisible(): bool
    {
        return $this->client->getCrawler()->filter('form')->count() > 0;
    }

    public function getErrorMessage(): ?string
    {
        $error = $this->client->getCrawler()->filter('.alert-danger');
        return $error->count() > 0 ? $error->text() : null;
    }
}
