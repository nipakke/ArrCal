<!-- Context: development/backend/php-standards | Priority: high | Version: 2.0 | Updated: 2026-06-29 -->

# PHP Language Standards — ArrCal

> How to write modern PHP in this codebase. Type-safe, async-aware, idiomatic. PHP 8.5 baseline.

## Quick Reference

- **Baseline**: PHP 8.5 (`declare(strict_types=1)` in every file)
- **Type coverage**: All properties, parameters, and return types explicitly declared
- **State**: `readonly` classes for DTOs, immutable by default
- **Flow**: `match` over `switch`, named arguments for clarity, enums for fixed sets
- **Never**: Blocking I/O in the ReactPHP event loop
- **Never**: CSS classes in API responses (that's the frontend's job)

---

## Type System

### Mandatory Strict Types

Every PHP file starts with:
```php
<?php
declare(strict_types=1);
```

No exceptions. No, not even for that one file you think is fine.

### Return Type Coverage

Every method and function declares a return type:
```php
public function fetchCalendar(\DateTimeImmutable $start, \DateTimeImmutable $end): PromiseInterface
public function getCacheKey(string $service, \DateTimeImmutable $start): string
public function toArray(): array
```

### Nullable Types

Prefer `null` over sentinel values:
```php
public function get(string $key): mixed   // returns null if not found
public function resolve(?string $host): string
```

---

## Language Features

### Constructor Property Promotion (PHP 8.0)

The canonical form in this project:
```php
// DTOs
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
}

// Service classes
final class RadarrService
{
    public function __construct(
        private readonly \React\Http\Browser $browser,
    ) {}
}
```

### Named Arguments (PHP 8.0)

Use when a constructor or function has 3+ parameters:
```php
$entry = new CalendarEntry(
    date: new \DateTimeImmutable($date),
    title: $title,
    type: MediaType::Episode,
    status: MediaStatus::Missing,
    serviceSource: 'sonarr',
    metadata: ['seasonNumber' => 3, 'episodeNumber' => 2],
);
```

### Match Expression (PHP 8.0)

Replace `switch` with `match`:
```php
public function label(): string
{
    return match ($this) {
        self::Downloaded => 'Downloaded',
        self::Missing => 'Missing',
        self::Upcoming => 'Upcoming',
        self::Unmonitored => 'Unmonitored',
        self::Error => 'Error',
    };
}
```

Note: no `badgeClass()` method. The frontend maps semantic status to CSS classes. The backend doesn't know what a badge is.

### Enums (PHP 8.1)

Use backed enums for any fixed set of values that cross a boundary:
```php
enum MediaStatus: string
{
    case Downloaded = 'downloaded';
    case Missing = 'missing';
    case Upcoming = 'upcoming';
    case Unmonitored = 'unmonitored';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Downloaded => 'Downloaded',
            self::Missing => 'Missing',
            self::Upcoming => 'Upcoming',
            self::Unmonitored => 'Unmonitored',
            self::Error => 'Error',
        };
    }

    // NO badgeClass() here. Go away. CSS is not PHP's problem.
}
```

### Readonly Classes (PHP 8.2)

Every class in `Domain/` should be `final readonly`:
```php
final readonly class CalendarEntry
{
    public function __construct(
        public \DateTimeImmutable $date,
        public string $title,
        // ...
    ) {}
}
```

### First-Class Callables (PHP 8.1)

```php
$promise = $client->get('/api/v3/calendar')
    ->then($this->parseResponse(...))
    ->then($this->transformEntries(...));
```

### Nullsafe Operator (PHP 8.0)

```php
$airDate = $episode['airDateUtc'] ?? null;
$status = $airDate !== null ? new \DateTimeImmutable($airDate) : null;
```

---

## Naming Conventions

| Construct | Convention | Example |
|-----------|------------|---------|
| Classes | PascalCase | `CalendarAggregator`, `RadarrService` |
| Methods/functions | camelCase | `fetchCalendar()`, `buildCalendarCells()` |
| Variables | camelCase | `$currentMonth`, `$airDateUtc` |
| Constants (class) | PascalCase | `MediaStatus::Downloaded` |
| Constants (global) | UPPER_SNAKE_CASE | `APP_ENV`, `CACHE_TTL` |
| Interfaces | `*Interface` suffix | (none currently — inject concrete types) |
| DTOs | Noun, no suffix | `CalendarEntry`, `MediaStatus` |
| Handlers | `*Handler` suffix | `CalendarApiHandler` |
| Services | `*Service` suffix | `RadarrService`, `SonarrService` |
| Test files | `*Test.php` suffix | `CalendarApiHandlerTest.php` |

---

## File Organization

```
├── Domain/      # final readonly DTOs, enums
├── Handler/     # Thin HTTP adapters, one per route
├── Service/     # *arr API clients, cache, rate limiting
└── Kernel/      # DI wiring, route registration, server bootstrap
```

**Rule**: Each directory maps to one architectural layer. Never mix concerns.

---

## Anti-Patterns

- ❌ **Missing strict types** — Every file needs `declare(strict_types=1)`
- ❌ **Blocking I/O** — `file_get_contents`, `PDO::query`, `sleep` stall the entire event loop
- ❌ **Mutable DTOs** — Domain objects must be `readonly`
- ❌ **Switch statements** — Use `match`
- ❌ **Stringly-typed statuses** — Use enums
- ❌ **CSS classes in API response** — The frontend maps semantic status. Stay in your lane.
- ❌ **Closure-wrapped callbacks** — Use first-class callable syntax (`$this->method(...)`)
- ❌ **Untyped closures** — Type closure parameters when the signature is known

---

## Onboarding Checklist

- [ ] Know the PHP baseline version and required strict types
- [ ] Understand when to use each PHP 8.x feature
- [ ] Know naming conventions per architectural layer
- [ ] Know the file organization rules
- [ ] Know that CSS classes do not belong in PHP code
