<?php

namespace DGLab\Tests\PageObjects;

use Symfony\Component\Panther\Client;

class NavigationComponent
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function clickLink(string $linkText): void
    {
        $this->client->clickLink($linkText);
        // Wait for SPA fragment transition (assuming a transition class or indicator)
        $this->client->waitForInvisibility('.sp-transition-loading');
    }

    public function navigateTo(string $uri): void
    {
        $this->client->getCrawler()->filter('a[href="' . $uri . '"]')->click();
        $this->client->waitForInvisibility('.sp-transition-loading');
    }
}
