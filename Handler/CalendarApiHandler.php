<?php

declare(strict_types=1);

namespace ArrCal\Handler;

use ArrCal\Service\CalendarAggregator;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;

/**
 * JSON API handler for the calendar endpoint.
 *
 * Returns aggregated Radarr + Sonarr calendar data as JSON,
 * grouped by date with pre-computed calendar grid cells.
 * Used by the Svelte frontend.
 *
 * Query parameters:
 *   - month=2026-06  (combined YYYY-MM)
 *   - year=2026&month=6  (separate numeric)
 */
final class CalendarApiHandler
{
    private const int YEAR_MIN = 2020;

    private const int YEAR_MAX = 2099;

    public function __construct(
        private readonly CalendarAggregator $aggregator,
    ) {}

    public function __invoke(ServerRequestInterface $request): PromiseInterface
    {
        $params = $request->getQueryParams();
        [$year, $month] = $this->parseYearMonth($params);

        $start = new \DateTimeImmutable(\sprintf('%04d-%02d-01', $year, $month));
        $end = $start->modify('last day of this month');

        return $this->aggregator->getCalendar($start, $end)
            ->then(
                fn (array $result): Response => $this->jsonResponse($result),
                fn (\Throwable $e): Response => $this->errorResponse($e),
            );
    }

    /**
     * @param  array<string, string>  $params
     * @return array{int, int}
     */
    private function parseYearMonth(array $params): array
    {
        $year = (int) \date('Y');
        $month = (int) \date('n');

        $monthParam = isset($params['month']) && $params['month'] !== ''
            ? $params['month']
            : null;

        $yearParam = isset($params['year']) && $params['year'] !== ''
            ? $params['year']
            : null;

        if ($monthParam !== null && \preg_match('/^\d{4}-\d{2}$/', $monthParam) === 1) {
            $year = (int) \substr($monthParam, 0, 4);
            $month = (int) \substr($monthParam, 5, 2);
        } elseif ($monthParam !== null && $yearParam !== null) {
            $year = (int) $yearParam;
            $month = (int) $monthParam;
        } elseif ($monthParam !== null) {
            $month = (int) $monthParam;
        } elseif ($yearParam !== null) {
            $year = (int) $yearParam;
        }

        return [
            \max(self::YEAR_MIN, \min(self::YEAR_MAX, $year)),
            \max(1, \min(12, $month)),
        ];
    }

    private function jsonResponse(array $result): Response
    {
        $body = \json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return new Response(
            200,
            ['Content-Type' => 'application/json', 'Access-Control-Allow-Origin' => '*'],
            $body,
        );
    }

    private function errorResponse(\Throwable $e): Response
    {
        $body = \json_encode([
            'error' => 'Unable to load calendar data.',
            'detail' => $e->getMessage(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return new Response(
            500,
            ['Content-Type' => 'application/json', 'Access-Control-Allow-Origin' => '*'],
            $body,
        );
    }
}
