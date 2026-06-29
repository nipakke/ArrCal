<?php

declare(strict_types=1);

namespace ArrCal\Service;

use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;

/**
 * Async HTTP client for the Radarr API (v3).
 *
 * Wraps ReactPHP's Browser to provide non-blocking access to Radarr
 * endpoints. The injected Browser can be pre-configured with a base URL
 * and connection options, making it fully testable with a mock or
 * a loop-bound HTTP client.
 *
 * Configuration (URL, API key, instance ID, label) is injected via the
 * constructor rather than read from environment variables, enabling
 * multi-instance support. The static fromEnv() factory preserves
 * backward compatibility for single-instance setups.
 */
final class RadarrService
{
    private readonly ?string $baseUrl;

    private readonly ?string $apiKey;

    private readonly bool $enabled;

    private readonly ?string $error;

    public function __construct(
        private readonly Browser $browser,
        private readonly string $id,
        string $url,
        string $apiKey,
        private readonly ?string $label = null,
    ) {
        if ($url === '') {
            $this->baseUrl = null;
            $this->apiKey = null;
            $this->enabled = false;
            $this->error = null;
        } elseif ($apiKey === '') {
            $this->baseUrl = \rtrim($url, '/');
            $this->apiKey = null;
            $this->enabled = false;
            $this->error = 'Radarr API key is missing';
        } else {
            $this->baseUrl = \rtrim($url, '/');

            // Validate the URL is parseable (has a host)
            $parts = \parse_url($this->baseUrl);

            if ($parts === false || ! isset($parts['host']) || $parts['host'] === '') {
                $this->apiKey = null;
                $this->enabled = false;
                $this->error = 'Invalid Radarr URL: '.$url;
            } else {
                $this->apiKey = $apiKey;
                $this->enabled = true;
                $this->error = null;
            }
        }
    }

    /**
     * Create a RadarrService from the legacy single-instance environment
     * variables (RADARR_URL, RADARR_API_KEY, RADARR_LABEL).
     *
     * Always produces instance "1". This factory exists for backward
     * compatibility until ServerKernel is updated to inject config
     * directly via the constructor.
     */
    public static function fromEnv(Browser $browser): self
    {
        $url = \getenv('RADARR_URL');
        if ($url === false || $url === '') {
            $url = $_ENV['RADARR_URL'] ?? null;
        }

        $apiKey = \getenv('RADARR_API_KEY');
        if ($apiKey === false || $apiKey === '') {
            $apiKey = $_ENV['RADARR_API_KEY'] ?? null;
        }

        $label = \getenv('RADARR_LABEL');
        if ($label === false || $label === '') {
            $label = $_ENV['RADARR_LABEL'] ?? null;
        }

        return new self(
            $browser,
            '1',
            \is_string($url) ? $url : '',
            \is_string($apiKey) ? $apiKey : '',
            \is_string($label) ? $label : null,
        );
    }

    /**
     * Return the unique instance identifier.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Return the human-readable label for this instance, if set.
     */
    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Fetch the Radarr calendar for a given date range.
     *
     * Calls GET /api/v3/calendar with unmonitored movies excluded.
     * Returns an empty array if Radarr is not configured.
     *
     * @return PromiseInterface<array<int, array<string, mixed>>>
     */
    public function fetchCalendar(\DateTimeImmutable $start, \DateTimeImmutable $end): PromiseInterface
    {
        if (! $this->enabled) {
            return \React\Promise\resolve([]);
        }

        $url = \sprintf(
            '%s/api/v3/calendar?start=%s&end=%s&unmonitored=false',
            $this->baseUrl,
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        );

        return $this->browser->get(
            $url,
            ['X-Api-Key' => $this->apiKey],
        )->then(
            static function (ResponseInterface $response): array {
                $body = (string) $response->getBody();
                $data = \json_decode($body, true);

                if (! \is_array($data)) {
                    throw new \RuntimeException(
                        'Radarr API returned unexpected response format',
                    );
                }

                return $data;
            },
            static function (\Throwable $error): never {
                $message = $error->getMessage();

                if (\str_contains($message, '401')) {
                    throw new \RuntimeException('Radarr API error: 401 Unauthorized');
                }

                if (\str_contains($message, '403')) {
                    throw new \RuntimeException('Radarr API error: 403 Forbidden');
                }

                if (\str_contains($message, '404')) {
                    throw new \RuntimeException('Radarr API error: 404 Not Found');
                }

                throw new \RuntimeException(
                    \sprintf('Radarr API error: %s', $message),
                );
            },
        );
    }

    /**
     * Return the configured Radarr base URL, or null if not configured.
     */
    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }
}
