<?php

declare(strict_types=1);

namespace Anvil\Web\Api;

use function dirname;

// No Composer autoloader in this skin — load the sibling engine classes
// explicitly. Paths are relative to this file (web/src/Api).
require_once __DIR__ . '/AnvilResult.php';
require_once __DIR__ . '/AnvilClient.php';
require_once __DIR__ . '/Api.php';

/**
 * Front-controller router for the Anvil Web UI.
 *
 * Routes are resolved from the `?route=` query parameter (preferred, e.g.
 * index.php?route=api/status) or, failing that, from the request path
 * (e.g. /api/status). The root path ("/" or empty) serves the SPA shell;
 * anything under "api/" is dispatched to the Api handlers and answered with
 * JSON. Static assets (app.js, style.css) are served directly by the PHP
 * built-in server and never reach this router.
 */
final class Router
{
    private AnvilClient $client;
    private Api $api;

    public function __construct()
    {
        // web/src/Api -> web/src -> web -> anvil
        $anvilRoot = dirname(dirname(dirname(__DIR__)));
        $anvilctl = $anvilRoot . '/bin/anvilctl';

        $this->client = new AnvilClient($anvilctl);
        $this->api = new Api($this->client);
    }

    public function handle(): void
    {
        $route = $this->resolveRoute();

        if ($route === '' || $route === '/') {
            $this->serveShell();

            return;
        }

        if (str_starts_with($route, 'api/')) {
            $this->serveApi(substr($route, 4));

            return;
        }

        $this->serveJson(['ok' => false, 'data' => null, 'error' => 'Not found.'], 404);
    }

    private function resolveRoute(): string
    {
        $route = $_GET['route'] ?? null;
        if (is_string($route) && $route !== '') {
            return $this->normalize($route);
        }

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = is_string($uri) ? $uri : '/';

        return $this->normalize($uri);
    }

    private function normalize(string $route): string
    {
        return ltrim($route, '/');
    }

    private function serveApi(string $endpoint): void
    {
        $body = $this->readBody();

        switch ($endpoint) {
            case 'status':
                $data = $this->api->status();
                break;
            case 'projects':
                $data = $this->api->projects();
                break;
            case 'start':
                $data = $this->api->start();
                break;
            case 'stop':
                $data = $this->api->stop();
                break;
            case 'new':
                $name = $this->param($body, 'name');
                $data = $this->api->createProject(is_string($name) ? $name : '');
                break;
            case 'db-create':
                $name = $this->param($body, 'name');
                $data = $this->api->createDatabase(is_string($name) ? $name : '');
                break;
            case 'logs':
                $data = $this->api->logs();
                break;
            default:
                $this->serveJson(['ok' => false, 'data' => null, 'error' => 'Unknown endpoint.'], 404);

                return;
        }

        $this->serveJson($data, 200);
    }

    /**
     * Read and decode the request body as an associative array (JSON or
     * form-encoded). Returns an empty array when there is no body.
     *
     * @return array<string, mixed>
     */
    private function readBody(): array
    {
        $raw = (string) (file_get_contents('php://input') ?? '');
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        parse_str($raw, $parsed);

        return $parsed;
    }

    /**
     * Resolve a parameter from (in order) the JSON/form body, $_POST, $_GET.
     */
    private function param(array $body, string $key): ?string
    {
        if (array_key_exists($key, $body) && is_string($body[$key])) {
            return $body[$key];
        }
        if (isset($_POST[$key]) && is_string($_POST[$key])) {
            return $_POST[$key];
        }
        if (isset($_GET[$key]) && is_string($_GET[$key])) {
            return $_GET[$key];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function serveJson(array $data, int $code): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function serveShell(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo $this->shellHtml();
    }

    private function shellHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Anvil Control</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <header class="topbar">
    <h1>Anvil Control</h1>
    <div class="toggles">
      <button id="btn-start" class="toggle" data-action="start">Start</button>
      <button id="btn-stop" class="toggle" data-action="stop">Stop</button>
      <span id="stack-state" class="state">unknown</span>
    </div>
  </header>
  <main>
    <section class="projects">
      <h2>Projects</h2>
      <div id="project-list" class="cards"></div>
    </section>
    <section class="logs">
      <h2>Logs</h2>
      <pre id="log-pane" class="log-pane"></pre>
    </section>
  </main>
  <script src="app.js"></script>
</body>
</html>
HTML;
    }
}
