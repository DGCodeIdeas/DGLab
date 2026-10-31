<?php

declare(strict_types=1);

namespace SovereignStack\Core\Config\Tests\Security;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Config\ConfigBuilder;

/**
 * Security tests: configuration values that originate from $_ENV or .env
 * files are attacker-controlled in many deployment scenarios. Verify that
 * values cannot break out of the dot-notation traversal or inject control
 * characters into config keys.
 */
final class ConfigSecurityTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (array_keys($_ENV) as $key) {
            if (str_starts_with($key, 'TEST_')) {
                unset($_ENV[$key]);
            }
        }
    }

    public function testEnvKeyWithDotDoesNotEscapeNamespace(): void
    {
        // An env var literally named "TEST.APP.NAME" is unusual but legal
        // in some shells. We verify the builder does NOT treat its dots as
        // a traversal path that could escape its namespace.
        $_ENV['TEST_APP_NAME'] = 'injected';

        $config = (new ConfigBuilder())
            ->withOverride('app', ['name' => 'legitimate'])
            ->build();

        // Env-var conversion: TEST_APP_NAME -> test.app.name (lowercased, _ -> .)
        // This DOES create a 'test.app.name' key — by design. But it should NOT
        // overwrite the file/override 'app.name' key.
        self::assertSame('legitimate', $config->get('app.name'));
        self::assertSame('injected', $config->get('test.app.name'));
    }

    public function testOverrideKeyCannotTraverseUpViaEmptySegments(): void
    {
        $builder = new ConfigBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $builder->withOverride('app..name', 'injected');
    }

    public function testOverrideKeyCannotBeEmpty(): void
    {
        $builder = new ConfigBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $builder->withOverride('', 'injected');
    }

    public function testGetWithEmptyKeyThrows(): void
    {
        $config = (new ConfigBuilder())->build();

        $this->expectException(\InvalidArgumentException::class);
        $config->get('');
    }

    public function testConfigValuesAreNotExecutedAsStringEval(): void
    {
        // Even if a value LOOKS like PHP code, it's stored as a string.
        $_ENV['TEST_PAYLOAD'] = '<?php system("whoami"); ?>';

        try {
            $config = (new ConfigBuilder())->build();

            $value = $config->get('test.payload');
            self::assertIsString($value);
            self::assertSame('<?php system("whoami"); ?>', $value);
        } finally {
            unset($_ENV['TEST_PAYLOAD']);
        }
    }

    public function testNewlinesInEnvValuesArePreservedNotExecuted(): void
    {
        $_ENV['TEST_MULTILINE'] = "line1\nline2\nline3";

        try {
            $config = (new ConfigBuilder())->build();
            $value = $config->get('test.multiline');

            self::assertSame("line1\nline2\nline3", $value);
        } finally {
            unset($_ENV['TEST_MULTILINE']);
        }
    }

    public function testEmptyEnvValueIsSkippedNotTreatedAsNull(): void
    {
        $_ENV['TEST_EMPTY'] = '';

        try {
            $config = (new ConfigBuilder())->build();

            // Empty env values are skipped per spec — NOT stored as null or empty string.
            self::assertFalse($config->has('test.empty'));
            self::assertNull($config->get('test.empty'));
        } finally {
            unset($_ENV['TEST_EMPTY']);
        }
    }
}
