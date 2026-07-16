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
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $cols = explode("\t", $line);
                $projects[] = [
                    'name' => $cols[0] ?? '',
                    'url' => $cols[1] ?? '',
                    'ssl' => ($cols[2] ?? 'no') === 'yes',
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
