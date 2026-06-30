<?php

declare(strict_types=1);

namespace ArrCal\Service;

use ArrCal\Domain\CalendarEntry;
use React\Promise\PromiseInterface;

/**
 * Core orchestrator that unifies Radarr and Sonarr calendar data.
 *
 * Accepts arrays of RadarrService and SonarrService instances to support
 * multi-instance setups (e.g., HD + 4K Radarr, Main + Anime Sonarr).
 * Each instance is fetched independently with per-instance cache keys.
 * Entries are tagged with instanceId and instanceLabel for frontend filtering.
 *
 * Gracefully degrades: if one instance fails, entries from the others are
 * still returned. If all fail, an empty result is provided.
 */
final class CalendarAggregator
{
    /**
     * @param  RadarrService[]  $radarrServices
     * @param  SonarrService[]  $sonarrServices
     */
    public function __construct(
        private readonly array $radarrServices,
        private readonly array $sonarrServices,
        private readonly ApiCache $cache,
    ) {}

    /**
     * Fetch the unified calendar for a given date range.
     *
     * Iterates over all RadarrService and SonarrService instances, fetching
     * calendar data for each independently. Per-instance cache keys prevent
     * collisions. All fetches are collected into a single Promise\all() for
     * maximum concurrency.
     *
     * Each instance produces exactly one top-level promise. Radarr instances
     * fetch only the calendar endpoint. Sonarr instances fetch both the
     * calendar and series endpoints (combined via an inner Promise\all()).
     *
     * @return PromiseInterface<array{calendarCells: array<int, array{date: string, day: int, entries: array}>, services: array{radarr: array<int, array{id: string, label: string|null, enabled: bool, url: string|null, error: string|null}>, sonarr: array<int, array{id: string, label: string|null, enabled: bool, url: string|null, error: string|null}>}}>
     */
    public function getCalendar(\DateTimeImmutable $start, \DateTimeImmutable $end): PromiseInterface
    {
        $promises = [];

        // Build one promise per Radarr instance.
        foreach ($this->radarrServices as $instance) {
            $promises[] = $this->buildRadarrPromise($instance, $start, $end);
        }

        // Build one promise per Sonarr instance (calendar + series combined).
        foreach ($this->sonarrServices as $instance) {
            $promises[] = $this->buildSonarrPromise($instance, $start, $end);
        }

        // If no services are configured at all, resolve immediately.
        if ($promises === []) {
            return \React\Promise\resolve($this->buildEmptyResult($start));
        }

        /** @var PromiseInterface<array{calendarCells: array<int, array{date: string, day: int, entries: array}>, services: array{radarr: array<int, array{id: string, label: string|null, enabled: bool, url: string|null, error: string|null}>, sonarr: array<int, array{id: string, label: string|null, enabled: bool, url: string|null, error: string|null}>}}> */
        return \React\Promise\all($promises)
            ->then(function (array $results) use ($start): array {
                return $this->processResults($results, $start);
            });
    }

    /**
     * Build a safe promise for a single RadarrService instance.
     *
     * If disabled, resolves immediately with the config error. If enabled,
     * fetches through the cache layer and catches any runtime errors so a
     * single broken instance never rejects the overall Promise\all().
     *
     * @return PromiseInterface<array{type: string, instanceId: string, instanceLabel: string|null, isEnabled: bool, baseUrl: string|null, configError: string|null, fetchError: string|null, data: array}>
     */
    private function buildRadarrPromise(
        RadarrService $instance,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): PromiseInterface {
        $instanceId = $instance->getId();
        $instanceLabel = $instance->getLabel();
        $base = [
            'type' => 'radarr',
            'instanceId' => $instanceId,
            'instanceLabel' => $instanceLabel,
            'isEnabled' => $instance->isEnabled(),
            'baseUrl' => $instance->getBaseUrl(),
            'publicUrl' => $instance->getPublicUrl(),
            'configError' => $instance->getError(),
            'fetchError' => null,
            'data' => [],
        ];

        if (! $instance->isEnabled()) {
            return \React\Promise\resolve($base);
        }

        return $this->fetchWithCache(
            'radarr',
            $instanceId,
            fn (): PromiseInterface => $instance->fetchCalendar($start, $end),
            $start,
            $end,
        )->then(
            function (array $data) use ($base): array {
                return \array_merge($base, ['data' => $data, 'fetchError' => null]);
            },
            function (\Throwable $e) use ($base): array {
                $msg = $e->getMessage();
                \error_log('CalendarAggregator: Radarr instance '.$base['instanceId'].' error: '.$msg);

                return \array_merge($base, ['data' => [], 'fetchError' => $msg]);
            },
        );
    }

    /**
     * Build a safe promise for a single SonarrService instance.
     *
     * Internally fetches both the calendar and series endpoints via an inner
     * Promise\all(), then combines results into a single shape. This keeps the
     * top-level promise count per-instance (one per service) so result mapping
     * is straightforward.
     *
     * @return PromiseInterface<array{type: string, instanceId: string, instanceLabel: string|null, isEnabled: bool, baseUrl: string|null, configError: string|null, calendarError: string|null, seriesError: string|null, data: array, seriesData: array}>
     */
    private function buildSonarrPromise(
        SonarrService $instance,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): PromiseInterface {
        $instanceId = $instance->getId();
        $instanceLabel = $instance->getLabel();
        $base = [
            'type' => 'sonarr',
            'instanceId' => $instanceId,
            'instanceLabel' => $instanceLabel,
            'isEnabled' => $instance->isEnabled(),
            'baseUrl' => $instance->getBaseUrl(),
            'publicUrl' => $instance->getPublicUrl(),
            'configError' => $instance->getError(),
            'calendarError' => null,
            'seriesError' => null,
            'data' => [],
            'seriesData' => [],
        ];

        if (! $instance->isEnabled()) {
            return \React\Promise\resolve($base);
        }

        // Wrap each fetch so it always resolves (errors become payload, not rejections).
        $calSafe = $this->fetchWithCache(
            'sonarr',
            $instanceId,
            fn (): PromiseInterface => $instance->fetchCalendar($start, $end),
            $start,
            $end,
        )->then(
            fn (array $data): array => ['error' => null, 'data' => $data],
            fn (\Throwable $e): array => ['error' => $e->getMessage(), 'data' => []],
        );

        $seriesSafe = $this->fetchWithCache(
            'sonarr-series',
            $instanceId,
            fn (): PromiseInterface => $instance->fetchSeries(),
            $start,
            $end,
        )->then(
            fn (array $data): array => ['error' => null, 'data' => $data],
            fn (\Throwable $e): array => ['error' => $e->getMessage(), 'data' => []],
        );

        /** @var PromiseInterface<array{0: array{error: string|null, data: array}, 1: array{error: string|null, data: array}}> */
        $innerAll = \React\Promise\all([$calSafe, $seriesSafe]);

        return $innerAll->then(
            function (array $results) use ($base): array {
                [$calResult, $seriesResult] = $results;

                return \array_merge($base, [
                    'calendarError' => $calResult['error'],
                    'data' => $calResult['data'],
                    'seriesError' => $seriesResult['error'],
                    'seriesData' => $seriesResult['data'],
                ]);
            },
        );
    }

    /**
     * Process the resolved instance results: transform entries, build service
     * status arrays, sort, group, and assemble the final result.
     *
     * @param  array<int, array>  $results  Resolved per-instance promise results
     * @return array{calendarCells: array, services: array{radarr: array, sonarr: array}}
     */
    private function processResults(array $results, \DateTimeImmutable $start): array
    {
        $entries = [];
        $radarrServices = [];
        $sonarrServices = [];

        foreach ($results as $result) {
            $type = $result['type'];

            if ($type === 'radarr') {
                $radarrServices[] = $this->buildRadarrStatus($result);
                $entries = \array_merge(
                    $entries,
                    $this->transformRadarrEntries(
                        $result['data'],
                        (string) ($result['publicUrl'] ?? $result['baseUrl'] ?? ''),
                        $result['instanceId'],
                        $result['instanceLabel'],
                    ),
                );
            } else {
                // sonarr
                $sonarrServices[] = $this->buildSonarrStatus($result);

                // Build seriesId → titleSlug and seriesId → title lookups from
                // this instance's series data.
                $seriesSlugs = [];
                $seriesTitles = [];
                foreach ($result['seriesData'] as $series) {
                    if (isset($series['id'])) {
                        $id = (int) $series['id'];
                        if (isset($series['titleSlug'])) {
                            $seriesSlugs[$id] = (string) $series['titleSlug'];
                        }
                        if (isset($series['title'])) {
                            $seriesTitles[$id] = (string) $series['title'];
                        }
                    }
                }

                $entries = \array_merge(
                    $entries,
                    $this->transformSonarrEntries(
                        $result['data'],
                        (string) ($result['publicUrl'] ?? $result['baseUrl'] ?? ''),
                        $seriesSlugs,
                        $seriesTitles,
                        $result['instanceId'],
                        $result['instanceLabel'],
                    ),
                );
            }
        }

        return $this->buildResult($entries, $start, $radarrServices, $sonarrServices);
    }

    /**
     * Extract a Radarr service-status object from an instance result.
     *
     * The `url` field exposed to the frontend is the public URL (or falls back
     * to the internal URL if no public URL was configured).
     *
     * @param  array{instanceId: string, instanceLabel: string|null, isEnabled: bool, baseUrl: string|null, publicUrl: string|null, configError: string|null, fetchError: string|null}  $result
     * @return array{id: string, label: string|null, enabled: bool, url: string|null, error: string|null}
     */
    private function buildRadarrStatus(array $result): array
    {
        return [
            'id' => $result['instanceId'],
            'label' => $result['instanceLabel'],
            'enabled' => $result['isEnabled'],
            'url' => $result['publicUrl'] ?? $result['baseUrl'],
            'error' => $result['fetchError'] ?? $result['configError'],
        ];
    }

    /**
     * Extract a Sonarr service-status object from an instance result.
     *
     * The `url` field exposed to the frontend is the public URL (or falls back
     * to the internal URL if no public URL was configured).
     *
     * @param  array{instanceId: string, instanceLabel: string|null, isEnabled: bool, baseUrl: string|null, publicUrl: string|null, configError: string|null, calendarError: string|null, seriesError: string|null}  $result
     * @return array{id: string, label: string|null, enabled: bool, url: string|null, error: string|null}
     */
    private function buildSonarrStatus(array $result): array
    {
        // Prefer calendar error; fall back to series error or config error.
        $error = $result['calendarError']
            ?? $result['seriesError']
            ?? $result['configError'];

        return [
            'id' => $result['instanceId'],
            'label' => $result['instanceLabel'],
            'enabled' => $result['isEnabled'],
            'url' => $result['publicUrl'] ?? $result['baseUrl'],
            'error' => $error,
        ];
    }

    /**
     * Build an empty result when no services are configured at all.
     *
     * @return array{calendarCells: array, services: array{radarr: array, sonarr: array}}
     */
    private function buildEmptyResult(\DateTimeImmutable $start): array
    {
        $monthMeta = $this->buildMonthMeta($start);
        $calendarCells = $this->buildCalendarCells([], $monthMeta);

        return [
            'calendarCells' => $calendarCells,
            'services' => [
                'radarr' => [],
                'sonarr' => [],
            ],
        ];
    }

    /**
     * Build the calendar-day cell grid for template rendering.
     *
     * Generates a 6-week grid (42 cells max) starting from Sunday. Each cell
     * contains the date, day number, flags for current-month and today, and
     * any entries belonging to that day (converted to template-safe arrays).
     *
     * Pure function — no side effects.
     *
     * @param  array<string, CalendarEntry[]>  $groupedEntries  Entries keyed by 'Y-m-d'
     * @param  array<string, mixed>  $monthMeta  Calendar metadata from buildMonthMeta()
     * @return array<int, array{date: string, day: int, entries: array}>
     */
    public function buildCalendarCells(array $groupedEntries, array $monthMeta): array
    {
        $year = (int) $monthMeta['year'];
        $month = (int) $monthMeta['month'];
        $firstDayOfWeek = (int) $monthMeta['firstDayOfWeek'];
        $daysInMonth = (int) $monthMeta['daysInMonth'];
        $prevMonthDays = (int) $monthMeta['prevMonthDays'];

        $cells = [];

        // Previous month's trailing days (fills cells before the 1st)
        $prevMonthStart = $prevMonthDays - $firstDayOfWeek + 1;
        $prevYear = $month === 1 ? $year - 1 : $year;
        $prevMonth = $month === 1 ? 12 : $month - 1;

        for ($day = $prevMonthStart; $day <= $prevMonthDays; $day++) {
            $dateStr = \sprintf('%04d-%02d-%02d', $prevYear, $prevMonth, $day);
            $cells[] = $this->buildCell($dateStr, $day, $groupedEntries);
        }

        // Current month
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = \sprintf('%04d-%02d-%02d', $year, $month, $day);
            $cells[] = $this->buildCell($dateStr, $day, $groupedEntries);
        }

        // Next month (fill remaining cells to reach 42)
        $remaining = 42 - \count($cells);
        $nextYear = $month === 12 ? $year + 1 : $year;
        $nextMonth = $month === 12 ? 1 : $month + 1;

        for ($day = 1; $day <= $remaining; $day++) {
            $dateStr = \sprintf('%04d-%02d-%02d', $nextYear, $nextMonth, $day);
            $cells[] = $this->buildCell($dateStr, $day, $groupedEntries);
        }

        return $cells;
    }

    /**
     * Fetch data from a service, using cache if available.
     *
     * Cache keys now include the instance ID to avoid collisions between
     * multiple instances of the same service type.
     *
     * @param  string  $service  Service identifier ('radarr', 'sonarr', 'sonarr-series')
     * @param  string  $instanceId  Per-instance discriminator for cache key
     * @param  callable(): PromiseInterface  $fetcher  Async function returning a promise
     * @return PromiseInterface<array>
     */
    private function fetchWithCache(
        string $service,
        string $instanceId,
        callable $fetcher,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): PromiseInterface {
        $cacheKey = $service.':'.$instanceId.':'.$start->format('Y-m-d').':'.$end->format('Y-m-d');
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            /** @var array $cached */
            return \React\Promise\resolve($cached);
        }

        /** @var PromiseInterface<array> */
        $promise = $fetcher();

        return $promise->then(function (array $result) use ($cacheKey): array {
            $this->cache->set($cacheKey, $result);

            return $result;
        });
    }

    /**
     * Transform raw Radarr API responses into CalendarEntry DTOs.
     *
     * The $publicUrl parameter is used to construct clickable links to
     * media items in the Radarr web UI. This may differ from the internal
     * API URL used by RadarrService for API calls.
     *
     * @param  array<int, array<string, mixed>>  $rawMovies
     * @return CalendarEntry[]
     */
    private function transformRadarrEntries(
        array $rawMovies,
        string $publicUrl = '',
        ?string $instanceId = null,
        ?string $instanceLabel = null,
    ): array {
        $entries = [];

        foreach ($rawMovies as $movie) {
            $date = $movie['inCinemas'] ?? $movie['physicalRelease'] ?? $movie['digitalRelease'] ?? null;

            if ($date === null) {
                continue;
            }

            // Determine which release type this date corresponds to
            $releaseType = match (true) {
                $movie['inCinemas'] === $date => 'In Cinemas',
                $movie['digitalRelease'] === $date => 'Digital',
                $movie['physicalRelease'] === $date => 'Physical',
                default => null,
            };

            $entries[] = CalendarEntry::fromRadarrResponse(
                $movie,
                $date,
                $publicUrl,
                $releaseType,
                $instanceId,
                $instanceLabel,
            );
        }

        return $entries;
    }

    /**
     * Transform raw Sonarr API responses into CalendarEntry DTOs.
     *
     * The $publicUrl parameter is used to construct clickable links to
     * media items in the Sonarr web UI. This may differ from the internal
     * API URL used by SonarrService for API calls.
     *
     * @param  array<int, array<string, mixed>>  $rawEpisodes
     * @param  array<int, string>  $seriesSlugs  Lookup map: seriesId → titleSlug
     * @param  array<int, string>  $seriesTitles  Lookup map: seriesId → series title
     * @return CalendarEntry[]
     */
    private function transformSonarrEntries(
        array $rawEpisodes,
        string $publicUrl = '',
        array $seriesSlugs = [],
        array $seriesTitles = [],
        ?string $instanceId = null,
        ?string $instanceLabel = null,
    ): array {
        $entries = [];

        foreach ($rawEpisodes as $episode) {
            $airDateUtc = $episode['airDateUtc'] ?? null;

            if ($airDateUtc === null) {
                continue;
            }

            $dateStr = (new \DateTimeImmutable($airDateUtc))->format('Y-m-d');
            $entries[] = CalendarEntry::fromSonarrResponse(
                $episode,
                $dateStr,
                $publicUrl,
                $seriesSlugs,
                $seriesTitles,
                $instanceId,
                $instanceLabel,
            );
        }

        return $entries;
    }

    /**
     * Merge entries, sort by date, group, and build the complete result array.
     *
     * @param  CalendarEntry[]  $entries
     * @param  array<int, array{id: string, label: string|null, enabled: bool, url: string|null, error: string|null}>  $radarrServices
     * @param  array<int, array{id: string, label: string|null, enabled: bool, url: string|null, error: string|null}>  $sonarrServices
     * @return array{calendarCells: array, services: array{radarr: array, sonarr: array}}
     */
    private function buildResult(
        array $entries,
        \DateTimeImmutable $start,
        array $radarrServices,
        array $sonarrServices,
    ): array {
        // Sort by date ascending
        \usort($entries, static fn (CalendarEntry $a, CalendarEntry $b): int => $a->date <=> $b->date);

        // Group by date
        $groupedEntries = [];

        foreach ($entries as $entry) {
            $key = $entry->date->format('Y-m-d');
            $groupedEntries[$key][] = $entry;
        }

        $monthMeta = $this->buildMonthMeta($start);

        $calendarCells = $this->buildCalendarCells($groupedEntries, $monthMeta);

        return [
            'calendarCells' => $calendarCells,
            'services' => [
                'radarr' => $radarrServices,
                'sonarr' => $sonarrServices,
            ],
        ];
    }

    /**
     * Build the month metadata array for template rendering.
     *
     * @return array<string, mixed>
     */
    private function buildMonthMeta(\DateTimeImmutable $start): array
    {
        $firstDay = $start->modify('first day of this month');

        return [
            'year' => (int) $start->format('Y'),
            'month' => (int) $start->format('n'),
            'monthName' => $start->format('F'),
            'yearMonth' => $start->format('Y-m'),
            'firstDayOfWeek' => (int) $firstDay->format('w'),
            'daysInMonth' => (int) $firstDay->format('t'),
            'prevMonthDays' => (int) $start->modify('first day of last month')->format('t'),
            'prevYearMonth' => $start->modify('first day of last month')->format('Y-m'),
            'nextYearMonth' => $start->modify('first day of next month')->format('Y-m'),
        ];
    }

    /**
     * Build a single calendar cell.
     *
     * @param  array<string, CalendarEntry[]>  $groupedEntries
     * @return array{date: string, day: int, entries: array}
     */
    private function buildCell(
        string $dateStr,
        int $day,
        array $groupedEntries,
    ): array {
        return [
            'date' => $dateStr,
            'day' => $day,
            'entries' => \array_map(
                static fn (CalendarEntry $entry): array => $entry->toArray(),
                $groupedEntries[$dateStr] ?? [],
            ),
        ];
    }
}
