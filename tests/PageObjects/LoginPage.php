<?php

namespace DGLab\Tests\PageObjects;

use Symfony\Component\Panther\Client;
use Symfony\Component\DomCrawler\Crawler;

class LoginPage
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function open(): Crawler
    {
        return $this->client->request('GET', '/login');
    }

    public function fillCredentials(string $email, string $password): void
    {
        $this->client->getCrawler()->filter('input[name="email"]')->sendKeys($email);
        $this->client->getCrawler()->filter('input[name="password"]')->sendKeys($password);
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

    public function getErrorMessage(): ?string
    {
        $error = $this->client->getCrawler()->filter('.alert-danger');
        return $error->count() > 0 ? $error->text() : null;
    }
}
