<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * PSR-17 ServerRequestFactory — creates ServerRequest instances.
 *
 * Includes static fromGlobals() for SAPI integration: builds a ServerRequest
 * from $_SERVER, $_POST, $_FILES, $_COOKIE, $_GET.
 */
final class ServerRequestFactory implements ServerRequestFactoryInterface
{
    /**
     * @param array<string, mixed> $serverParams
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return new ServerRequest($method, $uri, serverParams: $serverParams);
    }

    /**
     * Build a ServerRequest from PHP superglobals.
     *
     * Normalizes HTTP_* server vars into headers (HTTP_X_FOO → X-Foo),
     * marshals the URI from scheme/host/path/query, wraps $_FILES into
     * UploadedFile[], and sets parsed body from $_POST when applicable.
     *
     * @param array<string, mixed> $server Override $_SERVER (for testing).
     * @param array<string, mixed> $get Override $_GET.
     * @param array<string, mixed> $post Override $_POST.
     * @param array<string, mixed> $cookie Override $_COOKIE.
     * @param array<string, mixed> $files Override $_FILES.
     */
    public static function fromGlobals(
        array $server = null,
        array $get = null,
        array $post = null,
        array $cookie = null,
        array $files = null,
    ): ServerRequestInterface {
        $server ??= $_SERVER;
        $get ??= $_GET;
        $post ??= $_POST;
        $cookie ??= $_COOKIE;
        $files ??= $_FILES;

        $method = (string)($server['REQUEST_METHOD'] ?? 'GET');
        $uri = self::marshalUriFromGlobals($server);
        $headers = self::marshalHeadersFromGlobals($server);
        $body = new Stream('php://input', 'r');

        $request = new ServerRequest($method, $uri, $headers, $body, '1.1', $server);

        // Query params from parsed URI query string (fall back to $_GET).
        $queryParams = $get;
        if ($uri->getQuery() !== '') {
            parse_str($uri->getQuery(), $parsedQuery);
            if (!empty($parsedQuery)) {
                $queryParams = array_merge($parsedQuery, $queryParams);
            }
        }
        $request = $request->withQueryParams($queryParams);

        // Cookie params.
        if (!empty($cookie)) {
            $request = $request->withCookieParams($cookie);
        }

        // Uploaded files.
        $uploadedFiles = self::normalizeUploadedFiles($files);
        if (!empty($uploadedFiles)) {
            $request = $request->withUploadedFiles($uploadedFiles);
        }

        // Parsed body: $_POST for form submissions (Content-Type: application/x-www-form-urlencoded or multipart/form-data).
        $contentType = (string)($server['CONTENT_TYPE'] ?? ($server['HTTP_CONTENT_TYPE'] ?? ''));
        if (!empty($post) && (
            str_starts_with($contentType, 'application/x-www-form-urlencoded')
            || str_starts_with($contentType, 'multipart/form-data')
        )) {
            $request = $request->withParsedBody($post);
        }

        return $request;
    }

    /**
     * Marshal the URI from server params.
     * @param array<string, mixed> $server
     */
    private static function marshalUriFromGlobals(array $server): Uri
    {
        $scheme = 'http';
        $https = $server['HTTPS'] ?? '';
        if (!is_string($https)) { $https = ''; }
        if ($https !== '' && strtolower($https) !== 'off') {
            $scheme = 'https';
        }

        $host = (string)($server['HTTP_HOST'] ?? ($server['SERVER_NAME'] ?? ''));
        $port = isset($server['SERVER_PORT']) ? (int) $server['SERVER_PORT'] : null;

        // Strip port from host header if present.
        if (str_contains($host, ':')) {
            [$host, $portFromHost] = explode(':', $host, 2);
            $port = (int) $portFromHost;
        }

        $path = (string)($server['REQUEST_URI'] ?? '/');
        // Strip query string from path.
        $qPos = strpos($path, '?');
        if ($qPos !== false) {
            $path = substr($path, 0, $qPos);
        }

        $query = (string)($server['QUERY_STRING'] ?? '');

        $uri = $scheme . '://';
        if ($host !== '') {
            $uri .= $host;
        }
        if ($port !== null && $port !== 80 && $port !== 443) {
            $uri .= ':' . $port;
        }
        $uri .= $path;
        if ($query !== '') {
            $uri .= '?' . $query;
        }

        return new Uri($uri);
    }

    /**
     * Normalize HTTP_* server vars into a headers array.
     *
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function marshalHeadersFromGlobals(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    /**
     * Normalize $_FILES structure into UploadedFileInterface[].
     *
     * PHP's $_FILES has a quirky nested structure when inputs are arrays
     * (e.g. <input name="files[]">). This flattens it to a clean array.
     *
     * @param array<string, mixed> $files
     * @return array<string, \Psr\Http\Message\UploadedFileInterface|array>
     */
    private static function normalizeUploadedFiles(array $files): array
    {
        /** @var array<string, \Psr\Http\Message\UploadedFileInterface|array> $normalized */
        $normalized = [];
        foreach ($files as $key => $value) {
            if ($value instanceof \Psr\Http\Message\UploadedFileInterface) {
                $normalized[$key] = $value;
                continue;
            }
            if (is_array($value) && isset($value['tmp_name']) && is_string($value['tmp_name'])) {
                $normalized[$key] = new UploadedFile(
                    $value['tmp_name'],
                    isset($value['size']) ? (int) $value['size'] : null,
                    isset($value['error']) ? (int) $value['error'] : \UPLOAD_ERR_OK,
                    isset($value['name']) ? (string) $value['name'] : null,
                    isset($value['type']) ? (string) $value['type'] : null,
                );
            } elseif (is_array($value)) {
                $normalized[$key] = self::normalizeUploadedFiles($value);
            }
        }
        return $normalized;
    }
}
