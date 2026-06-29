<?php

declare(strict_types=1);

namespace ArrCal\Service;

/**
 * Simple in-memory TTL cache.
 *
 * Stores values in an array with timestamps.
 * Expired entries are lazily removed on get().
 *
 * Configured via env vars:
 *  - CACHE_TTL : default TTL in seconds (default: 300)
 */
final class ApiCache
{
    private readonly int $defaultTtl;

    /** @var array<string, array{value: mixed, expires: int}> */
    private array $cache = [];

    /**
     * Create a new in-memory TTL cache.
     *
     * Defaults to 300 seconds (5 minutes).
     */
    public function __construct(?int $ttl = null)
    {
        $this->defaultTtl = $ttl ?? (int) (getenv('CACHE_TTL') ?: 300);
    }

    /**
     * Retrieve a cached value by key.
     *
     * Returns null if the key does not exist or the entry has expired.
     * Expired entries are lazily removed from storage.
     */
    public function get(string $key): mixed
    {
        if (! isset($this->cache[$key])) {
            return null;
        }

        $entry = $this->cache[$key];

        if ($entry['expires'] < time()) {
            unset($this->cache[$key]);

            return null;
        }

        return $entry['value'];
    }

    /**
     * Store a value in the cache.
     *
     * Uses the provided TTL, or falls back to the default TTL configured
     * in the constructor.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $ttl = $ttl ?? $this->defaultTtl;

        $this->cache[$key] = [
            'value' => $value,
            'expires' => time() + $ttl,
        ];
    }

    /**
     * Remove all entries from the cache.
     */
    public function clear(): void
    {
        $this->cache = [];
    }

    /**
     * Return the configured default TTL in seconds.
     */
    public function getTtl(): int
    {
        return $this->defaultTtl;
    }
}
