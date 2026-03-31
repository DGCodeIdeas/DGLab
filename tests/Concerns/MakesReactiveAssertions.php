<?php

namespace DGLab\Tests\Concerns;

use DGLab\Core\Application;
use DGLab\Services\Superpowers\Runtime\GlobalStateStoreInterface;
use DOMDocument;
use DOMXPath;

trait MakesReactiveAssertions
{
    protected function assertResponseIsFragment(string $sectionId, $response = null): void
    {
        $response = $response ?? $this->lastResponse;
        if (!$response) {
            $this->fail('No response to assert against.');
        }

        $this->assertEquals(
            $sectionId,
            $response->getHeader('X-Superpowers-Fragment'),
            'Response is missing X-Superpowers-Fragment header or has wrong value.'
        );

        $content = $response->getContent();
        $this->assertStringNotContainsString(
            '<html',
            $content,
            'Response contains <html> tag, it might not be a fragment.'
        );
        $this->assertStringNotContainsString(
            '</body>',
            $content,
            'Response contains </body> tag, it might not be a fragment.'
        );
    }

    protected function assertFragmentContains(string $sectionId, string $text, $response = null): void
    {
        $response = $response ?? $this->lastResponse;
        if (!$response) {
            $this->fail('No response to assert against.');
        }

        $content = $response->getContent();
        $dom = new DOMDocument();
        @$dom->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $content . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        $xpath = new DOMXPath($dom);

        // Try to find by id or data-fragment
        $nodes = $xpath->query("//*[@id='{$sectionId}'] | //*[@data-fragment='{$sectionId}']");

        if ($nodes->length === 0) {
            if (strpos($content, $text) !== false) {
                $this->assertTrue(true);
                return;
            }
            $this->fail("Fragment with ID or data-fragment '{$sectionId}' not found in response.");
        }

        $found = false;
        foreach ($nodes as $node) {
            if (strpos($node->textContent, $text) !== false) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, "Fragment '{$sectionId}' does not contain expected text: '{$text}'");
    }

    protected function assertPersistedStateHas(string $key, $expectedValue): void
    {
        $store = Application::getInstance()->get(GlobalStateStoreInterface::class);
        $actualValue = $store->get($key, '__ABSENT__');

        if ($actualValue === '__ABSENT__') {
            $this->fail("Key '{$key}' not found in GlobalStateStore.");
        }

        $this->assertEquals(
            $expectedValue,
            $actualValue,
            "Persisted state for '{$key}' does not match expected value."
        );
    }

    protected function assertGlobalStateInjected(string $key, $expectedValue, $response = null): void
    {
        $response = $response ?? $this->lastResponse;
        if (!$response) {
            $this->fail('No response to assert against.');
        }

        $content = $response->getContent();
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);

        $nodes = $xpath->query("//*[@s-data]");
        if ($nodes->length === 0) {
            $this->fail("No reactive component (s-data) found in response.");
        }

        $found = false;
        foreach ($nodes as $node) {
            $sData = $node->getAttribute('s-data');
            $state = json_decode(base64_decode($sData), true);
            if (isset($state[$key]) && $state[$key] === $expectedValue) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            "Global state '{$key}' with value '" . var_export($expectedValue, true) . "' not found."
        );
    }
}
