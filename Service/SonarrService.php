<?php

declare(strict_types=1);

namespace ArrCal\Service;

use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Http\Message\ResponseException;
use React\Promise\PromiseInterface;

/**
 * Async HTTP client for the Sonarr API (v3).
 *
 * Wraps ReactPHP's Browser to provide non-blocking access to Sonarr
 * endpoints. Configuration (URL, API key, instance ID, label) is
 * injected via the constructor rather than read from environment
 * variables, enabling multi-instance support.
 *
 * The static fromEnv() factory preserves backward compatibility for
 * single-instance setups.
 */
final class SonarrService
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
            $this->error = 'Sonarr API key is missing';
        } else {
            $this->baseUrl = \rtrim($url, '/');

            // Validate the URL is parseable (has a host)
            $parts = \parse_url($this->baseUrl);

            if ($parts === false || ! isset($parts['host']) || $parts['host'] === '') {
                $this->apiKey = null;
                $this->enabled = false;
                $this->error = 'Invalid Sonarr URL: '.$url;
            } else {
                $this->apiKey = $apiKey;
                $this->enabled = true;
                $this->error = null;
            }
        }
    }

    /**
     * Create a SonarrService from the legacy single-instance environment
     * variables (SONARR_URL, SONARR_API_KEY, SONARR_LABEL).
     *
     * Always produces instance "1". This factory exists for backward
     * compatibility until ServerKernel is updated to inject config
     * directly via the constructor.
     */
    public static function fromEnv(Browser $browser): self
    {
        $url = $_ENV['SONARR_URL'] ?? \getenv('SONARR_URL');
        $apiKey = $_ENV['SONARR_API_KEY'] ?? \getenv('SONARR_API_KEY');
        $label = $_ENV['SONARR_LABEL'] ?? \getenv('SONARR_LABEL');

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
     * Fetch calendar entries from Sonarr for the given date range.
     *
     * Returns an empty array if Sonarr is not configured.
     *
     * @return PromiseInterface<array> Resolves to the decoded JSON response array
     */
    public function fetchCalendar(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        bool $includeSubresources = true,
    ): PromiseInterface {
        if (! $this->enabled) {
            return \React\Promise\resolve([]);
        }

        $url = \sprintf(
            '%s/api/v3/calendar?start=%s&end=%s&includeUnmonitored=false&includeSpecials=true',
            $this->baseUrl,
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        );

        if ($includeSubresources) {
            $url .= '&includeSubresources=series';
        }

        $headers = [
            'X-Api-Key' => $this->apiKey,
            'Accept' => 'application/json',
        ];

        /** @var PromiseInterface<array> */
        return $this->browser->get($url, $headers)->then(
            $this->handleSuccess(...),
            $this->handleError(...),
        );
    }

    /**
     * Fetch all series from Sonarr.
     *
     * Returns the full series list which includes `titleSlug` for each series.
     * Returns an empty array if Sonarr is not configured.
     *
     * @return PromiseInterface<array> Resolves to the decoded JSON response array
     */
    public function fetchSeries(): PromiseInterface
    {
        if (! $this->enabled) {
            return \React\Promise\resolve([]);
        }

        $url = $this->baseUrl.'/api/v3/series';

        $headers = [
            'X-Api-Key' => $this->apiKey,
            'Accept' => 'application/json',
        ];

        /** @var PromiseInterface<array> */
        return $this->browser->get($url, $headers)->then(
            $this->handleSuccess(...),
            $this->handleError(...),
        );
    }

    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    /**
     * Process a successful HTTP response and decode the JSON body.
     */
    private function handleSuccess(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        /** @var array|null $data */
        $data = \json_decode($body, true);

        if (! \is_array($data)) {
            throw new \RuntimeException('Sonarr API error: Invalid JSON response');
        }

        return $data;
    }

    /**
     * Wrap connection or HTTP errors into a descriptive RuntimeException.
     */
    private function handleError(\Throwable $error): never
    {
        if ($error instanceof ResponseException) {
            $response = $error->getResponse();
            throw new \RuntimeException(
                \sprintf(
                    'Sonarr API error: %d %s',
                    $response->getStatusCode(),
                    $response->getReasonPhrase(),
                ),
                $response->getStatusCode(),
                $error,
            );
        }

        throw new \RuntimeException(
            \sprintf('Sonarr API error: %s', $error->getMessage()),
            (int) $error->getCode(),
            $error,
        );
    }
}
