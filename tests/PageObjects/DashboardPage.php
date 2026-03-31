<?php

namespace DGLab\Tests\PageObjects;

use Symfony\Component\Panther\Client;
use Symfony\Component\DomCrawler\Crawler;

class DashboardPage
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function open(): Crawler
    {
        return $this->client->request('GET', '/');
    }

    public function isVisible(): bool
    {
        return $this->client->getCrawler()->filter('h1')->text() === 'Digital Lab Tools';
    }

    public function toggleDarkMode(): void
    {
        // Assuming there is a dark mode toggle button in the navbar or settings
        $this->client->getCrawler()->filter('#dark-mode-toggle')->click();
    }

    public function isDarkModeEnabled(): bool
    {
        return $this->client->getCrawler()->filter('body')->getAttribute('class') === 'dark-mode';
    }

    public function logout(): void
    {
        $this->client->getCrawler()->filter('a[href="/logout"]')->click();
    }
}
