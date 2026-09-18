<?php

declare(strict_types=1);

namespace SovereignStack\Core\Config\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SovereignStack\Core\Config\EnvLoader;
use SovereignStack\Core\Config\Exception\InvalidEnvFileException;

final class EnvLoaderTest extends TestCase
{
    private string $envFile;

    protected function setUp(): void
    {
        $this->envFile = __DIR__ . '/../Fixtures/config/.env.test';

        // Clean relevant $_ENV keys for deterministic test runs.
        foreach (array_keys($_ENV) as $key) {
            if (preg_match('/^(APP_|DB_|BASE_URL|SINGLE_QUOTED|DOUBLE_QUOTED|BARE_VALUE|EXPORTED_KEY|IGNORED_KEY)/', $key)) {
                unset($_ENV[$key]);
            }
        }

        // BASE_URL is referenced by APP_URL=${BASE_URL}/api in the fixture.
        $_ENV['BASE_URL'] = 'dglab.example.com';
    }

    protected function tearDown(): void
    {
        foreach (array_keys($_ENV) as $key) {
            if (preg_match('/^(APP_|DB_|BASE_URL|SINGLE_QUOTED|DOUBLE_QUOTED|BARE_VALUE|EXPORTED_KEY|IGNORED_KEY)/', $key)) {
                unset($_ENV[$key]);
            }
        }
    }

    public function testLoadParsesKeyValuePairs(): void
    {
        $loader = new EnvLoader();
        $loaded = $loader->load($this->envFile);

        self::assertSame('DGLab', $loaded['APP_NAME']);
        self::assertSame('testing', $loaded['APP_ENV']);
        self::assertSame('127.0.0.1', $loaded['DB_HOST']);
        self::assertSame('5432', $loaded['DB_PORT']);
    }

    public function testLoadWritesToEnvSuperglobal(): void
    {
        $loader = new EnvLoader();
        $loader->load($this->envFile);

        self::assertSame('DGLab', $_ENV['APP_NAME']);
        self::assertSame('testing', $_ENV['APP_ENV']);
    }

    public function testLoadInterpolatesDoubleQuotedValues(): void
    {
        $loader = new EnvLoader();
        $loaded = $loader->load($this->envFile);

        // DOUBLE_QUOTED="prefix-${APP_NAME}-suffix"
        // APP_NAME is loaded earlier in the same file, so interpolation resolves.
        self::assertSame('prefix-DGLab-suffix', $loaded['DOUBLE_QUOTED']);
    }

    public function testLoadInterpolatesReferencesToExistingEnvVars(): void
    {
        $loader = new EnvLoader();
        $loaded = $loader->load($this->envFile);

        // APP_URL=https://${BASE_URL}/api
        // BASE_URL was set in $_ENV before load() ran.
        self::assertSame('https://dglab.example.com/api', $loaded['APP_URL']);
    }

    public function testLoadDoesNotInterpolateSingleQuotedValues(): void
    {
        $loader = new EnvLoader();
        $loaded = $loader->load($this->envFile);

        // SINGLE_QUOTED='${NOT_EXPANDED}'
        self::assertSame('${NOT_EXPANDED}', $loaded['SINGLE_QUOTED']);
    }

    public function testLoadHandlesBareValues(): void
    {
        $loader = new EnvLoader();
        $loaded = $loader->load($this->envFile);

        self::assertSame('hello-world', $loaded['BARE_VALUE']);
    }

    public function testLoadHandlesExportPrefix(): void
    {
        $loader = new EnvLoader();
        $loaded = $loader->load($this->envFile);

        self::assertSame('exported-value', $loaded['EXPORTED_KEY']);
    }

    public function testLoadSkipsCommentLines(): void
    {
        $loader = new EnvLoader();
        $loaded = $loader->load($this->envFile);

        self::assertArrayNotHasKey('IGNORED_KEY', $loaded);
    }

    public function testLoadDoesNotOverwriteExistingEnvVars(): void
    {
        // Pre-set a key that the .env file also defines.
        $_ENV['APP_NAME'] = 'PreSet';

        $loader = new EnvLoader();
        $loaded = $loader->load($this->envFile);

        // Env wins — file value not loaded.
        self::assertArrayNotHasKey('APP_NAME', $loaded);
        self::assertSame('PreSet', $_ENV['APP_NAME']);
    }

    public function testLoadThrowsWhenFileMissing(): void
    {
        $loader = new EnvLoader();

        $this->expectException(InvalidEnvFileException::class);
        $this->expectExceptionMessage('.env file does not exist');

        $loader->load('/nonexistent/.env');
    }

    public function testLoadThrowsOnMalformedLineWithoutEquals(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'env_test_');
        file_put_contents($tmpFile, "VALID_KEY=value\nMALFORMED_LINE_WITHOUT_EQUALS\n");

        try {
            $loader = new EnvLoader();

            $this->expectException(\UnexpectedValueException::class);
            $this->expectExceptionMessage('missing');

            $loader->load($tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testLoadThrowsOnInvalidKeyName(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'env_test_');
        file_put_contents($tmpFile, "123_BAD=value\n");

        try {
            $loader = new EnvLoader();

            $this->expectException(\UnexpectedValueException::class);
            $this->expectExceptionMessage('invalid key');

            $loader->load($tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testLoadHandlesBlankLinesAndComments(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'env_test_');
        file_put_contents($tmpFile, "\n# A comment\n\nKEY=value\n   \n# Another comment\nKEY2=value2\n");

        try {
            $loader = new EnvLoader();
            $loaded = $loader->load($tmpFile);

            self::assertSame('value', $loaded['KEY']);
            self::assertSame('value2', $loaded['KEY2']);
            self::assertCount(2, $loaded);
        } finally {
            unlink($tmpFile);
            unset($_ENV['KEY'], $_ENV['KEY2']);
        }
    }

    public function testLoadHandlesCRLFLineEndings(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'env_test_');
        file_put_contents($tmpFile, "KEY1=value1\r\nKEY2=value2\r\n");

        try {
            $loader = new EnvLoader();
            $loaded = $loader->load($tmpFile);

            self::assertSame('value1', $loaded['KEY1']);
            self::assertSame('value2', $loaded['KEY2']);
        } finally {
            unlink($tmpFile);
            unset($_ENV['KEY1'], $_ENV['KEY2']);
        }
    }

    public function testUndefinedInterpolationLeftLiteral(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'env_test_');
        file_put_contents($tmpFile, 'KEY=prefix-${UNDEFINED_VAR}-suffix');

        try {
            $loader = new EnvLoader();
            $loaded = $loader->load($tmpFile);

            // Undefined ${VAR} is left literal rather than emptied.
            self::assertSame('prefix-${UNDEFINED_VAR}-suffix', $loaded['KEY']);
        } finally {
            unlink($tmpFile);
            unset($_ENV['KEY']);
        }
    }

    /**
     * Empty file: a zero-byte .env file yields no key/value pairs and
     * returns an empty array. load() must not throw.
     */
    public function testLoadReturnsEmptyArrayForEmptyFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'env_test_');
        file_put_contents($tmpFile, '');

        try {
            $loader = new EnvLoader();
            $loaded = $loader->load($tmpFile);

            self::assertSame([], $loaded);
        } finally {
            unlink($tmpFile);
        }
    }

    /**
     * Comments-only file: a .env file containing only blank lines and
     * `#`-prefixed comment lines yields no key/value pairs. The lines()
     * generator skips both shapes via the early-continue guard.
     */
    public function testLoadReturnsEmptyArrayForFileContainingOnlyComments(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'env_test_');
        file_put_contents(
            $tmpFile,
            "# This is a comment\n\n# Another comment\n   # Indented comment\n",
        );

        try {
            $loader = new EnvLoader();
            $loaded = $loader->load($tmpFile);

            self::assertSame([], $loaded);
        } finally {
            unlink($tmpFile);
        }
    }
}
