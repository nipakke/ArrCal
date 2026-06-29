<?php

declare(strict_types=1);

namespace ArrCal\Service;

/**
 * Parses multi-instance environment variables into validated instance config arrays.
 *
 * Supports both legacy single-instance vars (RADARR_URL, SONARR_URL) and
 * numbered multi-instance vars (RADARR_2_URL, RADARR_3_URL, etc.).
 *
 * Instance 1 always comes from the unnumbered legacy vars (RADARR_URL, etc.).
 * Additional instances are discovered by scanning numbered vars starting from 2,
 * stopping at the first gap (e.g. if RADARR_2_URL exists but RADARR_3_URL does
 * not, only instances 1 and 2 are produced).
 *
 * Each instance is validated: URLs must be parseable with a host, and API keys
 * must be present. Invalid or incomplete configs are returned with enabled=false
 * and a descriptive error — no exceptions are thrown for missing configuration.
 *
 * Pure service — receives an env reader callable via constructor injection.
 * Never accesses getenv() or $_ENV directly (the default reader does, but it can
 * be replaced for testing). No side effects, no mutation.
 */
final readonly class InstanceConfig
{
    /** @var list<array{id: string, url: string, apiKey: ?string, label: ?string, enabled: bool, error: ?string}> */
    private array $radarrInstances;

    /** @var list<array{id: string, url: string, apiKey: ?string, label: ?string, enabled: bool, error: ?string}> */
    private array $sonarrInstances;

    /**
     * @param  ?callable(string): ?string  $envReader  Optional env var reader.
     *                                                 Receives an env var name and returns its value, or null if unset.
     *                                                 Default reads getenv() then $_ENV (matches existing ServerKernel pattern).
     */
    public function __construct(?callable $envReader = null)
    {
        $reader = $envReader ?? self::defaultReader(...);
        $this->radarrInstances = $this->parseInstances('RADARR', $reader);
        $this->sonarrInstances = $this->parseInstances('SONARR', $reader);
    }

    /**
     * Default environment reader: getenv() first, $_ENV fallback.
     *
     * Matches the pattern used in RadarrService constructor — checks getenv()
     * for a non-false, non-empty value first, then falls back to $_ENV.
     */
    private static function defaultReader(string $key): ?string
    {
        $value = \getenv($key);

        if ($value === false || $value === '') {
            $value = $_ENV[$key] ?? null;
        }

        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Parse instances for a service prefix (RADARR or SONARR).
     *
     * Instance 1 comes from legacy unnumbered vars ({PREFIX}_URL).
     * Additional instances come from numbered vars ({PREFIX}_2_URL, etc.),
     * stopping at the first gap.
     *
     * @param  callable(string): ?string  $reader
     * @return list<array{id: string, url: string, apiKey: ?string, label: ?string, enabled: bool, error: ?string}>
     */
    private function parseInstances(string $prefix, callable $reader): array
    {
        $instances = [];
        $serviceId = \strtolower($prefix);

        // Instance 1: legacy vars (no number suffix)
        $url = $reader("{$prefix}_URL");

        if ($url !== null) {
            $instances[] = $this->buildInstance(
                id: "{$serviceId}-1",
                url: $url,
                apiKey: $reader("{$prefix}_API_KEY"),
                label: $reader("{$prefix}_LABEL"),
                serviceName: $prefix,
            );
        }

        // Additional instances: numbered from 2, stop at first gap
        $n = 2;
        while (true) {
            $url = $reader("{$prefix}_{$n}_URL");

            if ($url === null) {
                break;
            }

            $instances[] = $this->buildInstance(
                id: "{$serviceId}-{$n}",
                url: $url,
                apiKey: $reader("{$prefix}_{$n}_API_KEY"),
                label: $reader("{$prefix}_{$n}_LABEL"),
                serviceName: $prefix,
            );
            $n++;
        }

        return $instances;
    }

    /**
     * Build a validated instance config array.
     *
     * Validates URL parseability (must resolve to a host) and API key presence.
     * Invalid or incomplete configs are returned with enabled=false and a
     * descriptive error message — callers handle graceful degradation.
     *
     * @return array{id: string, url: string, apiKey: ?string, label: ?string, enabled: bool, error: ?string}
     */
    private function buildInstance(
        string $id,
        string $url,
        ?string $apiKey,
        ?string $label,
        string $serviceName,
    ): array {
        $trimmedUrl = \rtrim($url, '/');

        // Validate the URL is parseable and has a host
        $parts = \parse_url($trimmedUrl);

        if ($parts === false || ! isset($parts['host']) || $parts['host'] === '') {
            return [
                'id' => $id,
                'url' => $trimmedUrl,
                'apiKey' => null,
                'label' => $label,
                'enabled' => false,
                'error' => "Invalid {$serviceName} URL: {$url}",
            ];
        }

        // URL valid but API key missing
        if ($apiKey === null) {
            return [
                'id' => $id,
                'url' => $trimmedUrl,
                'apiKey' => null,
                'label' => $label,
                'enabled' => false,
                'error' => "{$serviceName} API key is missing",
            ];
        }

        // Fully configured and valid
        return [
            'id' => $id,
            'url' => $trimmedUrl,
            'apiKey' => $apiKey,
            'label' => $label,
            'enabled' => true,
            'error' => null,
        ];
    }

    /**
     * Get all configured Radarr instances.
     *
     * Always returns an array (may be empty if no Radarr instances are configured).
     *
     * @return list<array{id: string, url: string, apiKey: ?string, label: ?string, enabled: bool, error: ?string}>
     */
    public function getRadarrInstances(): array
    {
        return $this->radarrInstances;
    }

    /**
     * Get all configured Sonarr instances.
     *
     * Always returns an array (may be empty if no Sonarr instances are configured).
     *
     * @return list<array{id: string, url: string, apiKey: ?string, label: ?string, enabled: bool, error: ?string}>
     */
    public function getSonarrInstances(): array
    {
        return $this->sonarrInstances;
    }
}
