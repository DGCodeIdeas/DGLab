<?php

declare(strict_types=1);

namespace Anvil\Web\Api;

/**
 * Endpoint handlers. Each method shells out to anvilctl via AnvilClient and
 * returns a response array shaped as {"ok":bool,"data":...,"error":...}.
 *
 * No orchestration logic lives here beyond mapping engine output into the
 * response envelope — the real work happens in the bash engine (anvilctl).
 */
final class Api
{
    public function __construct(private AnvilClient $client)
    {
    }

    public function status(): array
    {
        $result = $this->client->run('status');

        return $this->wrap($result, ['output' => $this->trim($result->stdout)]);
    }

    public function projects(): array
    {
        $result = $this->client->run('projects');

        $projects = [];
        if ($result->isOk()) {
            $lines = explode("\n", $result->stdout);
            $firstLine = true;
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;
                // Skip the header row emitted by anvil_registry_list:
                //   SLUG\tROOT\tCREATED
                if ($firstLine) {
                    $firstLine = false;
                    if (stripos($line, 'SLUG') === 0) continue;
                }
                $cols = explode("\t", $line);
                if (count($cols) < 1) continue;
                $slug    = $cols[0];
                $root    = $cols[1] ?? '';
                $created = $cols[2] ?? '';
                // Synthesize a URL from the slug + env-appropriate TLD.
                // Dev: *.test  (mkcert wildcard, dnsmasq)
                // Staging/prod: *.<parent of ANVIL_PRIMARY_FQDN>
                $env = getenv('ANVIL_ENV') ?: 'dev';
                $primary = getenv('ANVIL_PRIMARY_FQDN') ?: 'dglab.example.com';
                $tld = ($env === 'dev') ? 'test' : (strpos($primary, '.') !== false ? substr($primary, strpos($primary, '.') + 1) : 'test');
                $url = "https://{$slug}.{$tld}/";
                $projects[] = [
                    'name' => $slug,
                    'url' => $url,
                    'root' => $root,
                    'created' => $created,
                    'ssl' => true,  // v3 always has TLS (mkcert in dev, ACME on-demand in prod)
                ];
            }
        }

        return $this->wrap($result, ['projects' => $projects]);
    }

    public function start(): array
    {
        $result = $this->client->run('start');

        return $this->wrap($result, ['output' => $this->trim($result->stdout)]);
    }

    public function stop(): array
    {
        $result = $this->client->run('stop');

        return $this->wrap($result, ['output' => $this->trim($result->stdout)]);
    }

    public function createProject(string $name): array
    {
        $name = $this->sanitizeName($name);
        if ($name === '') {
            return ['ok' => false, 'data' => null, 'error' => 'Project name is required.'];
        }

        $result = $this->client->run('new', $name);

        return $this->wrap($result, ['name' => $name, 'output' => $this->trim($result->stdout)]);
    }

    public function createDatabase(string $name): array
    {
        $name = $this->sanitizeName($name);
        if ($name === '') {
            return ['ok' => false, 'data' => null, 'error' => 'Database name is required.'];
        }

        $result = $this->client->run('db', 'create', $name);

        return $this->wrap($result, ['name' => $name, 'output' => $this->trim($result->stdout)]);
    }

    public function logs(): array
    {
        $result = $this->client->run('logs');

        return $this->wrap($result, ['output' => $this->trim($result->stdout)]);
    }

    // ----- v3 endpoints ----------------------------------------------------

    /**
     * GET /api/doctor — run `anvilctl doctor` and surface the verdict.
     * Returns the full log output + a parsed ok/fail verdict.
     */
    public function doctor(): array
    {
        $result = $this->client->run('doctor');

        return $this->wrap($result, [
            'output' => $this->trim($result->stdout . "\n" . $result->stderr),
            'ok' => $result->isOk(),
        ]);
    }

    /**
     * GET /api/stack — return the current stack mode (slim|full) + env.
     * Reads the same env vars anvilctl reads so the UI reflects the truth.
     */
    public function stack(): array
    {
        $env   = getenv('ANVIL_ENV') ?: 'dev';
        $mode  = getenv('ANVIL_STACK_MODE') ?: 'slim';
        $data  = getenv('DATA_SOURCE') ?: 'docker';

        return [
            'ok' => true,
            'data' => [
                'env' => $env,
                'mode' => $mode,
                'data_source' => $data,
            ],
            'error' => null,
        ];
    }

    /**
     * POST /api/stack?mode=slim|full — switch the dev stack shape.
     */
    public function setStack(string $mode): array
    {
        if (!in_array($mode, ['slim', 'full'], true)) {
            return ['ok' => false, 'data' => null, 'error' => 'mode must be slim|full'];
        }
        $result = $this->client->run('stack', $mode);

        return $this->wrap($result, ['mode' => $mode, 'output' => $this->trim($result->stdout)]);
    }

    /**
     * POST /api/deploy — run `anvilctl deploy <env> [--release <digest>]`.
     * Body: {"env": "staging|production", "release": "<digest>"}
     * Releases can take 30+ seconds; the caller should not block the UI thread.
     */
    public function deploy(string $env, string $release): array
    {
        if (!in_array($env, ['staging', 'production'], true)) {
            return ['ok' => false, 'data' => null, 'error' => 'env must be staging|production'];
        }
        $args = ['deploy', $env, '--strategy', 'blue-green'];
        if ($release !== '') {
            $args[] = '--release';
            $args[] = $release;
        }
        $result = $this->client->run(...$args);

        return $this->wrap($result, [
            'env' => $env,
            'release' => $release,
            'output' => $this->trim($result->stdout . "\n" . $result->stderr),
        ]);
    }

    /**
     * POST /api/rollback — run `anvilctl rollback <env>`.
     */
    public function rollback(string $env): array
    {
        if (!in_array($env, ['staging', 'production'], true)) {
            return ['ok' => false, 'data' => null, 'error' => 'env must be staging|production'];
        }
        $result = $this->client->run('rollback', $env);

        return $this->wrap($result, [
            'env' => $env,
            'output' => $this->trim($result->stdout . "\n" . $result->stderr),
        ]);
    }

    /**
     * GET /api/verify?gate=boot|health|headers|ports|all — run a verify gate.
     */
    public function verify(string $gate): array
    {
        if (!in_array($gate, ['boot', 'health', 'headers', 'ports', 'all'], true)) {
            return ['ok' => false, 'data' => null, 'error' => 'gate must be boot|health|headers|ports|all'];
        }
        $result = $this->client->run('verify', $gate);

        return $this->wrap($result, [
            'gate' => $gate,
            'output' => $this->trim($result->stdout . "\n" . $result->stderr),
        ]);
    }

    /**
     * Wrap an engine result into the standard response envelope.
     */
    private function wrap(AnvilResult $result, array $data): array
    {
        $error = $result->isOk() ? null : $this->trim($result->stderr !== '' ? $result->stderr : $result->stdout);

        return [
            'ok' => $result->isOk(),
            'data' => $data,
            'error' => $error,
        ];
    }

    private function trim(string $value): string
    {
        return trim($value);
    }

    /**
     * Keep only safe characters for project / database names. The engine
     * performs its own validation; this is a first-pass sanitizer.
     */
    private function sanitizeName(string $name): string
    {
        $cleaned = preg_replace('/[^A-Za-z0-9_-]/', '', $name);

        return $cleaned === null ? '' : $cleaned;
    }
}
