<?php

declare(strict_types=1);

namespace ArrCal\Domain;

/**
 * A single entry in the unified calendar — either a movie or an episode.
 *
 * Carries typed data from Radarr/Sonarr API responses up through the
 * application layers for JSON serialization, without exposing domain internals.
 */
final readonly class CalendarEntry
{
    /**
     * @param  array<string, mixed>  $metadata  Extra info: series name, season/episode, year, genres, etc.
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public string $title,
        public MediaType $type,
        public MediaStatus $status,
        public string $serviceSource,
        public bool $monitored,
        public ?string $url = null,
        public array $metadata = [],
        public ?string $instanceId = null,
        public ?string $instanceLabel = null,
    ) {}

    /**
     * Create a CalendarEntry from a Radarr MovieResource response.
     *
     * Maps the Radarr status fields to the unified MediaStatus enum.
     * The $publicUrl is used to construct clickable links to the media item
     * in the *arr web UI. This may differ from the internal API URL.
     *
     * @param  array<string, mixed>  $movie  Raw MovieResource from Radarr API
     * @param  string  $date  Calendar date to associate this entry with
     * @param  string  $publicUrl  Public URL for clickable links (may differ from internal API URL)
     */
    public static function fromRadarrResponse(array $movie, string $date, string $publicUrl = '', ?string $releaseType = null, ?string $instanceId = null, ?string $instanceLabel = null): self
    {
        $monitored = (bool) ($movie['monitored'] ?? false);
        $hasFile = (bool) ($movie['hasFile'] ?? false);
        $status = (string) ($movie['status'] ?? '');

        $mediaStatus = match (true) {
            $hasFile => MediaStatus::Downloaded,
            $status === 'deleted' => MediaStatus::Error,
            $status === 'queued' => MediaStatus::Queued,
            $status === 'announced',
            $status === 'tba' => MediaStatus::Upcoming,
            default => MediaStatus::Missing,
        };

        $slug = $movie['titleSlug'] ?? $movie['id'] ?? null;
        $url = $slug !== null ? "{$publicUrl}/movie/{$slug}" : null;

        return new self(
            date: new \DateTimeImmutable($date),
            title: (string) ($movie['title'] ?? ''),
            type: MediaType::Movie,
            status: $mediaStatus,
            serviceSource: 'radarr',
            monitored: $monitored,
            url: $url,
            metadata: [
                'movieId' => $movie['id'] ?? null,
                'movieSlug' => $movie['titleSlug'] ?? null,
                'year' => $movie['year'] ?? null,
                'genres' => $movie['genres'] ?? [],
                'releaseType' => $releaseType,
            ],
            instanceId: $instanceId,
            instanceLabel: $instanceLabel,
        );
    }

    /**
     * Create a CalendarEntry from a Sonarr EpisodeResource response.
     *
     * Computes the hasFile flag from episodeFileId, and compares airDateUtc
     * against the current time for upcoming status detection.
     * The $publicUrl is used to construct clickable links to the media item
     * in the *arr web UI. This may differ from the internal API URL.
     *
     * @param  array<string, mixed>  $episode  Raw EpisodeResource from Sonarr API
     * @param  string  $date  Calendar date to associate this entry with
     * @param  string  $publicUrl  Public URL for clickable links
     * @param  array<int, string>  $seriesSlugs  Lookup map: seriesId → titleSlug
     * @param  array<int, string>  $seriesTitles  Lookup map: seriesId → series title
     */
    public static function fromSonarrResponse(array $episode, string $date, string $publicUrl = '', array $seriesSlugs = [], array $seriesTitles = [], ?string $instanceId = null, ?string $instanceLabel = null): self
    {
        $monitored = (bool) ($episode['monitored'] ?? false);
        $hasFile = ($episode['episodeFileId'] ?? 0) > 0;
        $airDateUtc = $episode['airDateUtc'] ?? null;

        $mediaStatus = match (true) {
            $hasFile => MediaStatus::Downloaded,
            $airDateUtc !== null && new \DateTimeImmutable($airDateUtc) > new \DateTimeImmutable('now') => MediaStatus::Upcoming,
            default => MediaStatus::Missing,
        };

        $series = $episode['series'] ?? [];

        // Prefer titleSlug from the episode's series sub-resource; fall back to
        // the full series list lookup (fetched separately from /api/v3/series).
        $seriesId = $episode['seriesId'] ?? $series['id'] ?? null;
        $slug = $series['titleSlug'] ?? $series['slug'] ?? ($seriesId !== null ? ($seriesSlugs[(int) $seriesId] ?? null) : null);
        $url = $slug !== null ? "{$publicUrl}/series/{$slug}" : null;

        return new self(
            date: new \DateTimeImmutable($date),
            title: (string) ($episode['title'] ?? ''),
            type: MediaType::Episode,
            status: $mediaStatus,
            serviceSource: 'sonarr',
            monitored: $monitored,
            url: $url,
            metadata: [
                'seriesTitle' => $seriesTitles[(int) $seriesId] ?? $series['title'] ?? null,
                'seasonNumber' => $episode['seasonNumber'] ?? null,
                'episodeNumber' => $episode['episodeNumber'] ?? null,
                'airDateUtc' => $airDateUtc,
            ],
            instanceId: $instanceId,
            instanceLabel: $instanceLabel,
        );
    }

    /**
     * Convert to a plain array for JSON serialization.
     *
     * Only semantic data is exposed — the frontend maps status values to
     * its own UI classes.
     *
     * @return array{date: string, title: string, type: string, status: string, statusLabel: string, serviceSource: string, monitored: bool, url: string|null, metadata: array, instanceId: string|null, instanceLabel: string|null}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date->format('Y-m-d'),
            'title' => $this->title,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'serviceSource' => $this->serviceSource,
            'monitored' => $this->monitored,
            'url' => $this->url,
            'metadata' => $this->metadata,
            'instanceId' => $this->instanceId,
            'instanceLabel' => $this->instanceLabel,
        ];
    }
}
