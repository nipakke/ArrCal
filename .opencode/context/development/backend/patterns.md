<!-- Context: development/backend/patterns | Priority: high | Version: 2.0 | Updated: 2026-06-29 -->

# Design Patterns — ArrCal Backend

> Layered async architecture patterns. Still no blocking I/O in the ReactPHP event loop. But no Twig either.

## Quick Reference

- **Layers**: Handler (HTTP) → Service (infrastructure) → Domain (DTOs)
- **Direction**: Top-down only. Lower layers never know about upper layers.
- **State**: Immutable DTOs carry data between layers. No mutation after construction.
- **Async**: All I/O returns `React\Promise\PromiseInterface`. Compose with `->then()`.
- **Output**: JSON only. No HTML rendering in PHP. The frontend handles that.

---

## Core Pattern: Handler → Service → Domain

```
HTTP Request
    │
    ▼
Handler         Parse request, delegate to Service, build JSON Response
    │
    ▼
Service         Talk to *arr APIs, manage cache, aggregate results
    │
    ▼
Domain          Immutable DTOs carrying data back up
```

### Layer Rules

| Layer | Knows About | Never Does |
|-------|-------------|------------|
| **Handler** | HTTP (Request, Response), Service interfaces | Business logic, *arr API calls, rendering |
| **Service** | External APIs (Radarr/Sonarr), cache, Domain objects | HTTP concerns, response formatting |
| **Domain** | Nothing — pure data | I/O, logic, transformation |

### Example: Calendar API Handler

```php
// Handler — thin HTTP adapter, returns JSON
final class CalendarApiHandler
{
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
                fn (array $result): Response => $this->jsonResponse($result, $start),
                fn (\Throwable $e): Response => $this->errorResponse($e),
            );
    }
}

// Service — async, parallel, cached
final class CalendarAggregator
{
    public function getCalendar(\DateTimeImmutable $start, \DateTimeImmutable $end): PromiseInterface
    {
        $radarrSafe = $this->fetchWithCache('radarr', fn() => $this->radarr->fetchCalendar(...), $start, $end);
        $sonarrSafe = $this->fetchWithCache('sonarr', fn() => $this->sonarr->fetchCalendar(...), $start, $end);

        return \React\Promise\all([$radarrSafe, $sonarrSafe])
            ->then(fn (array $results) => $this->buildResult($results, $start));
    }
}
```

**Key insight**: The Handler never touches Radarr, the Service never touches HTTP, and both are trivially testable.

---

## DTO Pattern (Data Transfer Objects)

DTOs carry typed data across boundaries. No behavior, no CSS classes:

```php
final readonly class CalendarEntry
{
    public function __construct(
        public \DateTimeImmutable $date,
        public string $title,
        public MediaType $type,
        public MediaStatus $status,
        public string $serviceSource,
        public array $metadata = [],
    ) {}

    /** Parse raw Radarr API response into typed domain object */
    public static function fromRadarrResponse(array $movie, string $date): self { /* ... */ }

    /** Parse raw Sonarr API response into typed domain object */
    public static function fromSonarrResponse(array $episode, string $date): self { /* ... */ }

    /** Convert to plain array for JSON serialization */
    public function toArray(): array
    {
        return [
            'date' => $this->date->format('Y-m-d'),
            'title' => $this->title,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'serviceSource' => $this->serviceSource,
            'metadata' => $this->metadata,
            // Note: NO badgeClass. The frontend maps status to CSS.
        ];
    }
}
```

**DTO rules**:
- `final readonly` — immutable after construction
- Named constructor for each data source (`fromRadarrResponse`, `fromSonarrResponse`)
- `toArray()` for JSON serialization — no HTML, no Template method calls
- Zero logic beyond formatting and validation
- No service dependencies, no I/O, no side effects

---

## Promise Composition (Async)

Same as before. ReactPHP promises for all I/O. Chain with `->then()`:

```php
// Parallel: fetch both services simultaneously
$promises = [
    $this->radarr->fetchCalendar($start, $end),
    $this->sonarr->fetchCalendar($start, $end),
];

return \React\Promise\all($promises)->then(function (array $results) {
    [$radarrEntries, $sonarrEntries] = $results;
    return array_merge($radarrEntries, $sonarrEntries);
});
```

### Error Handling

```php
// Catch errors at the Handler boundary
return $this->aggregator->getCalendar($start, $end)
    ->then(
        fn (array $result) => $this->jsonResponse($result),
        fn (\Throwable $e) => $this->errorResponse($e),
    );
```

**Rule**: Every method that performs I/O returns `PromiseInterface`. Never call a blocking function from within a promise chain.

---

## Cache Pattern

Two layers of caching — server-side and client-side — working together:

```php
// Server-side: in-memory TTL (default 5 min)
$cacheKey = $service . ':' . $start->format('Y-m-d') . ':' . $end->format('Y-m-d');
$cached = $this->cache->get($cacheKey);

if ($cached !== null) {
    return \React\Promise\resolve($cached);
}

return $fetcher()->then(function (array $result) use ($cacheKey): array {
    $this->cache->set($cacheKey, $result);
    return $result;
});

// Client-side: TanStack Query staleTime (5 min)
// This means revisiting a month within 5 minutes is instant — no network request.
```

---

## Pattern Selection Guide

| When You Need To... | Use This Pattern |
|---------------------|-----------------|
| Parse an HTTP request and return JSON | Handler (thin, delegates to Service) |
| Talk to Radarr or Sonarr API | Service (async HTTP via ReactPHP Browser) |
| Hold cached data | Service (ApiCache, in-memory TTL) |
| Merge data from multiple sources | Aggregator pattern (Promise::all + merge) |
| Carry typed data between layers | Domain DTO (`final readonly` class) |
| Represent a fixed set of values | Enum (backed, with helper methods) |
| Compose async operations | Promise chaining with `->then()` |

---

## Anti-Patterns

- ❌ **Handler doing business logic** — Handlers parse HTTP and delegate. Nothing more.
- ❌ **Service returning HTML** — Services return domain objects or Promises. The frontend renders.
- ❌ **DTOs with logic** — DTOs are data carriers. Use named constructors for creation.
- ❌ **Mixed sync/async** — If a method touches I/O, it returns a Promise. If it doesn't, it returns a value. Never both.
- ❌ **badgeClass in API response** — The backend returns semantic status. CSS is the frontend's problem.
- ❌ **Blocking I/O in event loop** — `file_get_contents`, `PDO`, `sleep` will stall the entire server.
- ❌ **Promise in constructor** — Constructors are synchronous. Defer async work to a method.

---

## Onboarding Checklist

- [ ] Understand the Handler → Service → Domain layering
- [ ] Know the rules for each layer
- [ ] Understand DTO immutability and named constructors
- [ ] Know how to compose and chain Promises
- [ ] Know that the API returns JSON, not HTML
- [ ] Know that CSS classes belong in the frontend, not in PHP
