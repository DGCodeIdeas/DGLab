<?php
declare(strict_types=1);

namespace SovereignStack\Core\Http;

use Psr\Http\Message\UriInterface;

/**
 * Immutable URI value object per PSR-7 §3.3.
 *
 * RFC 3986 component parsing + percent-encoding normalization. All with*()
 * methods return a new instance; the original is never mutated. Scheme and
 * host are lowercased on construction. Standard ports (80 for http, 443 for
 * https) are omitted from getAuthority() and getPort() returns null.
 *
 * Security: scheme, userInfo, and host are validated for CR/LF on every
 * construction and with*() setter (CWE-93/113). A Uri built from user
 * input is often serialized into the Location header of a redirect
 * Response; an injected \r\n in those components would split the header
 * line and allow response splitting. Path/query/fragment are
 * percent-encoded by the normalizers so cannot carry raw \r\n.
 */
final class Uri implements UriInterface
{
    private const SCHEME_STANDARD_PORTS = ['http' => 80, 'https' => 443];

    private string $scheme;
    private string $userInfo;
    private string $host;
    private ?int $port;
    private string $path;
    private string $query;
    private string $fragment;

    /**
     * @param string $uri URI string to parse. Empty string produces an empty URI.
     */
    public function __construct(string $uri = '')
    {
        if ($uri === '') {
            $this->scheme = '';
            $this->userInfo = '';
            $this->host = '';
            $this->port = null;
            $this->path = '';
            $this->query = '';
            $this->fragment = '';
            return;
        }

        $parts = parse_url($uri);
        if ($parts === false) {
            throw new \InvalidArgumentException("Unable to parse URI: {$uri}");
        }

        $this->scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        $this->userInfo = $this->buildUserInfo($parts['user'] ?? null, $parts['pass'] ?? null);
        $this->host = isset($parts['host']) ? strtolower($parts['host']) : '';
        $this->assertNoCrlf('scheme', $this->scheme);
        $this->assertNoCrlf('userInfo', $this->userInfo);
        $this->assertNoCrlf('host', $this->host);
        $this->port = isset($parts['port']) ? $this->filterPort((int) $parts['port'], $this->scheme) : null;
        $this->path = $this->normalizePath($parts['path'] ?? '');
        $this->query = $this->normalizeQuery($parts['query'] ?? '');
        $this->fragment = $this->normalizeFragment($parts['fragment'] ?? '');
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        if ($this->host === '') {
            return '';
        }
        $authority = $this->host;
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }
        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }
        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function __toString(): string
    {
        $uri = '';
        if ($this->scheme !== '') {
            $uri .= $this->scheme . ':';
        }
        $authority = $this->getAuthority();
        if ($authority !== '') {
            $uri .= '//' . $authority;
        }
        $uri .= $this->path;
        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }
        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }
        return $uri;
    }

    public function withScheme($scheme): UriInterface
    {
        $scheme = strtolower((string) $scheme);
        $this->assertNoCrlf('scheme', $scheme);
        $new = clone $this;
        $new->scheme = $scheme;
        $new->port = $this->port !== null ? $this->filterPort($this->port, $scheme) : null;
        return $new;
    }

    public function withUserInfo($user, $password = null): UriInterface
    {
        $userInfo = $this->buildUserInfo($user, $password);
        $this->assertNoCrlf('userInfo', $userInfo);
        $new = clone $this;
        $new->userInfo = $userInfo;
        return $new;
    }

    public function withHost($host): UriInterface
    {
        $host = strtolower((string) $host);
        $this->assertNoCrlf('host', $host);
        $new = clone $this;
        $new->host = $host;
        return $new;
    }

    public function withPort($port): UriInterface
    {
        $new = clone $this;
        $new->port = $port === null ? null : $this->filterPort((int) $port, $this->scheme);
        return $new;
    }

    public function withPath($path): UriInterface
    {
        $new = clone $this;
        $new->path = $this->normalizePath((string) $path);
        return $new;
    }

    public function withQuery($query): UriInterface
    {
        $new = clone $this;
        $new->query = $this->normalizeQuery((string) $query);
        return $new;
    }

    public function withFragment($fragment): UriInterface
    {
        $new = clone $this;
        $new->fragment = $this->normalizeFragment((string) $fragment);
        return $new;
    }

    /**
     * Build the user-info string from user and password components.
     */
    private function buildUserInfo(?string $user, ?string $password): string
    {
        if ($user === null || $user === '') {
            return '';
        }
        return $password !== null && $password !== ''
            ? $user . ':' . $password
            : $user;
    }

    /**
     * Filter port: return null if the port is the standard port for the scheme.
     */
    private function filterPort(int $port, string $scheme): ?int
    {
        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException("Port must be between 1 and 65535; got {$port}");
        }
        if (isset(self::SCHEME_STANDARD_PORTS[$scheme]) && self::SCHEME_STANDARD_PORTS[$scheme] === $port) {
            return null;
        }
        return $port;
    }

    /**
     * Normalize the path: percent-encode reserved chars per RFC 3986 §3.3.
     * Already-encoded sequences (%XX) are preserved.
     */
    private function normalizePath(string $path): string
    {
        if ($path === '') {
            return '';
        }
        // Don't double-encode — preserve existing %XX sequences.
        $path = preg_replace_callback(
            '/(?:[^a-zA-Z0-9_\-\.~\:@!$&\'()*+,;=\/%]|%(?![0-9A-Fa-f]{2}))/',
            static function (array $match): string {
                return rawurlencode($match[0]);
            },
            $path
        );
        return $path ?? '';
    }

    /**
     * Normalize the query string: strip leading '?', percent-encode.
     */
    private function normalizeQuery(string $query): string
    {
        if ($query === '' || $query === '?') {
            return '';
        }
        if ($query[0] === '?') {
            $query = substr($query, 1);
        }
        return preg_replace_callback(
            '/(?:[^a-zA-Z0-9_\-\.~\:@!$&\'()*+,;=\/\?%]|%(?![0-9A-Fa-f]{2}))/',
            static function (array $match): string {
                return rawurlencode($match[0]);
            },
            $query
        ) ?? '';
    }

    /**
     * Normalize the fragment: strip leading '#', percent-encode.
     */
    private function normalizeFragment(string $fragment): string
    {
        if ($fragment === '' || $fragment === '#') {
            return '';
        }
        if ($fragment[0] === '#') {
            $fragment = substr($fragment, 1);
        }
        return preg_replace_callback(
            '/(?:[^a-zA-Z0-9_\-\.~\:@!$&\'()*+,;=\/\?%]|%(?![0-9A-Fa-f]{2}))/',
            static function (array $match): string {
                return rawurlencode($match[0]);
            },
            $fragment
        ) ?? '';
    }

    /**
     * Reject CR/LF in the scheme, userInfo, and host components (CWE-93/113).
     *
     * These three URI components are serialized verbatim into __toString()
     * (no percent-encoding). A \r or \n in any of them would carry through
     * unchanged when the Uri is rendered into a redirect Location header,
     * enabling response splitting. Path/query/fragment are percent-encoded
     * by their normalizers, so raw \r\n cannot survive there.
     */
    private function assertNoCrlf(string $component, string $value): void
    {
        if (preg_match('/[\r\n]/', $value)) {
            throw new \InvalidArgumentException(
                "URI {$component} contains CR or LF; refusing to set (CWE-93/113)"
            );
        }
    }
}
