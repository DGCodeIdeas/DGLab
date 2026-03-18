<?php

namespace DGLab\Services\Auth;

class IpAccessService
{
    protected array $whitelist = [];
    protected array $blacklist = [];

    public function __construct()
    {
        $this->whitelist = config('auth.ip_whitelist', []);
        $this->blacklist = config('auth.ip_blacklist', []);
    }

    public function isAllowed(string $ip): bool
    {
        if ($this->isBlacklisted($ip)) return false;
        if (!empty($this->whitelist)) return $this->isWhitelisted($ip);
        return true;
    }

    public function isWhitelisted(string $ip): bool
    {
        return $this->ipInList($ip, $this->whitelist);
    }

    public function isBlacklisted(string $ip): bool
    {
        return $this->ipInList($ip, $this->blacklist);
    }

    protected function ipInList(string $ip, array $list): bool
    {
        foreach ($list as $pattern) {
            if ($this->ipMatches($ip, $pattern)) return true;
        }
        return false;
    }

    protected function ipMatches(string $ip, string $pattern): bool
    {
        if ($ip === $pattern) return true;
        if (str_contains($pattern, '*')) {
            $regex = str_replace(['.', '*'], ['\.', '.*'], $pattern);
            return preg_match('/^' . $regex . '$/', $ip) === 1;
        }
        if (str_contains($pattern, '/')) {
            return $this->ipInCidr($ip, $pattern);
        }
        return false;
    }

    protected function ipInCidr(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = -1 << (32 - (int)$mask);
            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }
        return false;
    }
}
