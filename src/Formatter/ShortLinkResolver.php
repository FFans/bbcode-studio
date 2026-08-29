<?php

namespace FFans\BbcodeStudio\Formatter;

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Throwable;

class ShortLinkResolver
{
    private const MAX_REDIRECTS = 3;
    private const SUCCESS_TTL = 2592000;
    private const FAILURE_TTL = 300;

    private ClientInterface $client;

    /** @var Closure(string): bool */
    private Closure $hostValidator;

    /** @var Closure(string): list<string> */
    private Closure $addressResolver;

    public function __construct(
        CacheRepository $cache,
        ?ClientInterface $client = null,
        ?Closure $hostValidator = null,
        ?Closure $addressResolver = null
    ) {
        $this->cache = $cache;
        $this->client = $client ?? new Client();
        $this->addressResolver = $addressResolver ?? $this->resolveAddresses(...);
        $this->hostValidator = $hostValidator ?? fn (string $host): bool => $this->hasOnlyPublicAddresses($host);
    }

    private CacheRepository $cache;

    /**
     * @param list<string> $sourceHosts
     * @param list<string> $targetHosts
     * @param list<string> $directPatterns
     * @param list<string> $requiredCaptures
     * @return array<string, string>|null
     */
    public function resolve(
        string $url,
        array $sourceHosts,
        array $targetHosts,
        array $directPatterns,
        array $requiredCaptures
    ): ?array {
        $url = $this->normalizeUrl($url);
        $cacheKey = 'ffans_bbcode_studio.short_link.v2.'.hash('sha256', json_encode([
            $url,
            $sourceHosts,
            $targetHosts,
            $directPatterns,
            $requiredCaptures,
        ], JSON_UNESCAPED_SLASHES));

        $cached = $this->cache->get($cacheKey);

        if (is_array($cached) && array_key_exists('captures', $cached)) {
            return is_array($cached['captures']) ? $cached['captures'] : null;
        }

        $captures = $this->follow($url, $sourceHosts, $targetHosts, $directPatterns, $requiredCaptures);
        $this->cache->put(
            $cacheKey,
            ['captures' => $captures],
            $captures === null ? self::FAILURE_TTL : self::SUCCESS_TTL
        );

        return $captures;
    }

    /** @param list<string> $sourceHosts @param list<string> $targetHosts @param list<string> $patterns @param list<string> $required */
    private function follow(string $url, array $sourceHosts, array $targetHosts, array $patterns, array $required): ?array
    {
        $allowedSources = array_map('strtolower', $sourceHosts);
        $allowedTargets = array_map('strtolower', $targetHosts);

        if (! $this->isAllowedUrl($url, $allowedSources)) {
            return null;
        }

        for ($hop = 0; $hop < self::MAX_REDIRECTS; $hop++) {
            try {
                $response = $this->client->request('GET', $url, [
                    'allow_redirects' => false,
                    'connect_timeout' => 2,
                    'http_errors' => false,
                    'timeout' => 3,
                    'verify' => true,
                    'headers' => ['User-Agent' => 'FFans-BBCode-Studio/1.0'],
                ]);
            } catch (Throwable) {
                return null;
            }

            $status = $response->getStatusCode();
            $location = trim($response->getHeaderLine('Location'));

            if ($status < 300 || $status >= 400 || $location === '') {
                return null;
            }

            try {
                $url = (string) UriResolver::resolve(Utils::uriFor($url), Utils::uriFor($location));
            } catch (Throwable) {
                return null;
            }

            $captures = $this->extract($url, $patterns, $required);

            if ($captures !== null && $this->isAllowedUrl($url, $allowedTargets)) {
                return $captures;
            }

            if (! $this->isAllowedUrl($url, array_values(array_unique(array_merge($allowedSources, $allowedTargets))))) {
                return null;
            }
        }

        return null;
    }

    /** @param list<string> $patterns @param list<string> $required @return array<string, string>|null */
    private function extract(string $url, array $patterns, array $required): ?array
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches) !== 1) {
                continue;
            }

            $captures = [];

            foreach ($required as $name) {
                if (! isset($matches[$name]) || $matches[$name] === '') {
                    continue 2;
                }

                $captures[$name] = (string) $matches[$name];
            }

            return $captures;
        }

        return null;
    }

    /** @param list<string> $allowedHosts */
    private function isAllowedUrl(string $url, array $allowedHosts): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return ($parts['scheme'] ?? '') === 'https'
            && $this->hostIsAllowed($host, $allowedHosts)
            && ($this->hostValidator)($host);
    }

    /** @param list<string> $allowedHosts */
    private function hostIsAllowed(string $host, array $allowedHosts): bool
    {
        foreach ($allowedHosts as $allowedHost) {
            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return str_starts_with($url, 'https://') ? $url : 'https://'.$url;
    }

    private function hasOnlyPublicAddresses(string $host): bool
    {
        $addresses = ($this->addressResolver)($host);

        if ($addresses === []) {
            return false;
        }

        foreach (array_unique($addresses) as $address) {
            if (! $this->isPublicOrProxyFakeIp($address)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function resolveAddresses(string $host): array
    {
        $addresses = gethostbynamel($host) ?: [];

        if (function_exists('dns_get_record')) {
            foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (isset($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($addresses));
    }

    private function isPublicOrProxyFakeIp(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            return true;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        [$first, $second] = array_map('intval', explode('.', $address, 3));

        return $first === 198 && ($second === 18 || $second === 19);
    }
}
