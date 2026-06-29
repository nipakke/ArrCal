<!-- Context: project-intelligence/technical | Priority: high | Version: 2.0 | Updated: 2026-06-29 -->

# Technical Domain — ArrCal

> PHP + Svelte 5 monorepo for unified Radarr/Sonarr calendar viewing. JSON API with a reactive SPA frontend. Twig who? HTMX never heard of her.

## Quick Reference

- **Purpose**: Understand how the project works technically (finally)
- **Update When**: New features, refactoring, tech stack changes
- **Audience**: Developers, AI agents who need accurate context

## Primary Stack

| Layer | Technology | Version | Rationale |
|-------|-----------|---------|-----------|
| Backend language | PHP | ^8.5 | Strong typing, ReactPHP async runtime, already had it |
| Async runtime | ReactPHP | ^1.9 (http) | Non-blocking I/O, no nginx/FPM needed |
| Frontend framework | Svelte | ^5.56 | Runes, snippets, no virtual DOM — lightweight and fast |
| Frontend language | TypeScript | ^6.0 | Because strings aren't types |
| Data fetching | TanStack Query | ^6.1 | Caching, loading states, refetch — without writing it ourselves |
| CSS | Tailwind CSS | ^4.3 | Utility-first |
| Component library | daisyUI | ^5.5 | Pre-styled components that don't suck |
| Build tool | Vite | ^8.1 | Fast HMR, native TS support, Svelte plugin |
| Package manager | pnpm | ^11.9 | Workspaces, fast, disk-efficient |
| Routing | FastRoute | ^1.3 | Simple PHP router (still good!) |
| Caching | In-memory ApiCache | 5-min TTL | Keeps Radarr/Sonarr API calls polite |
| Radarr API | HTTP | v3 | Movie calendar endpoint |
| Sonarr API | HTTP | v3 | Episode calendar endpoint |

## Architecture Pattern

```
Type: JSON API + SPA Monorepo (workspace)
Pattern: Handler → Service → Domain (read path, API only)
         └── JSON response (no HTML, no Twig, no HTMX, no SSE)
```

```
Browser (Svelte 5 SPA with TanStack Query)
       │
       ▼
Vite Dev Server (:5173) — proxies /api/* to PHP
       │
       ▼
ReactPHP HTTP Server (:8080)
       │
       ├── Static file serving (GET /assets/* → public/)
       ├── SPA root (GET / → public/index.html)
       ├── IpResolver (client IP, proxy-aware)
       ├── RateLimiter (token-bucket, per-IP)
       ├── FastRoute dispatcher
       │     └── CalendarApiHandler → CalendarAggregator → JSON
       │
       └── Services
             ├── RadarrService (async HTTP, /api/v3/calendar)
             ├── SonarrService (async HTTP, /api/v3/calendar)
             ├── CalendarAggregator (parallel fetch + cache + grid builder)
             └── ApiCache (in-memory TTL)
```

### Why This Architecture?

Because the old hypermedia monolith with Twig + HTMX was fine for a Docker dashboard but hilariously wrong for a calendar app that needs client-side filtering, month navigation without full page reloads, and proper reactive state. Svelte 5 runes handle this elegantly, TanStack Query manages caching/loading/error states, and the PHP backend is just a thin JSON API. No dual-template languages, no server-rendered HTML for dynamic views.

### Alternatives Considered (and rightfully rejected):

- **HTMX + Twig** — What we had. Server-rendered calendar HTML with partial swaps. Horrible for client-side tag filtering, month transitions felt like 2005.
- **Livewire** — Requires Laravel + FPM. Can't run on ReactPHP's event loop without nginx.
- **Inertia** — Same problem: assumes Laravel/FPM. Not happening.
- **Alpine.js** — Would need to manage state manually. In 2026? No thanks.

## Project Structure

```
├── bin/
│   └── server                        # Entry point — PHP CLI, boots kernel
├── Kernel/
│   └── ServerKernel.php              # DI, routes, middleware, static files
├── Domain/                           # Immutable DTOs and enums
│   ├── CalendarEntry.php             # final readonly — fromRadarrResponse/fromSonarrResponse
│   ├── MediaStatus.php               # Backed enum: downloaded, missing, upcoming, etc.
│   └── MediaType.php                 # Backed enum: movie, episode
├── Handler/
│   └── CalendarApiHandler.php        # GET /api/calendar?month=YYYY-MM → JSON
├── Service/
│   ├── CalendarAggregator.php        # Parallel fetch + cache + 42-cell grid builder
│   ├── RadarrService.php             # Async HTTP to Radarr API
│   ├── SonarrService.php             # Async HTTP to Sonarr API
│   ├── ApiCache.php                  # In-memory TTL cache
│   ├── RateLimiter.php               # Token-bucket per-IP
│   └── IpResolver.php                # Reverse-proxy-aware IP resolution
├── public/
│   └── index.html                    # Built SPA served in production
├── frontend/                         # Svelte 5 SPA (workspace package)
│   ├── src/
│   │   ├── App.svelte                # Root — QueryClientProvider wrapper
│   │   ├── main.ts                   # Mount point
│   │   ├── ambient.d.ts              # TS declarations for .svelte/.css
│   │   ├── app.css                   # Tailwind + daisyUI
│   │   ├── components/
│   │   │   ├── CalendarDashboard.svelte  # Main dashboard with createQuery
│   │   │   ├── CalendarTopBar.svelte     # Navigation (prev/next/today) + view toggles
│   │   │   ├── CalendarGrid.svelte       # 7-column grid
│   │   │   ├── CalendarCell.svelte       # Single day cell
│   │   │   └── EntryBadge.svelte         # Status badge (frontend-mapped, not API-provided)
│   │   └── lib/
│   │       └── api.ts                    # Type-safe API client
│   ├── package.json
│   ├── vite.config.ts
│   ├── svelte.config.js
│   └── tsconfig.json
├── pnpm-workspace.yaml               # Yes, it's a workspace now
├── package.json                       # Root scripts: dev, build:frontend
├── composer.json                      # PHP deps
└── docker-compose.dev.yml             # Radarr + Sonarr for dev
```

## Route Table

| Method | Path | Handler | Description |
|--------|------|---------|-------------|
| GET | `/` | — (SPA) | Serves `public/index.html` (built SPA) |
| GET | `/api/calendar` | CalendarApiHandler | JSON calendar data for a given month |
| GET | `/*` | Static file fallback | JS, CSS, fonts from `public/` |

That's it. Two routes. One does something useful. The other serves files.

## JSON API Shape

```json
GET /api/calendar?month=2026-06

{
  "calendar": [
    {
      "date": "2026-06-07",
      "day": 7,
      "isCurrentMonth": true,
      "isToday": false,
      "entries": [
        {
          "title": "Best Laid Plans",
          "type": "episode",
          "status": "missing",
          "statusLabel": "Missing",
          "serviceSource": "sonarr",
          "metadata": {
            "seriesTitle": "The White Lotus",
            "seasonNumber": 3,
            "episodeNumber": 2
          }
        }
      ]
    }
  ],
  "currentMonth": "2026-06",
  "prevMonth": "2026-05",
  "nextMonth": "2026-07",
  "monthName": "June 2026"
}
```

Note: NO `badgeClass` in the response. The backend doesn't know what a Tailwind class is. That's the frontend's job.

## Key Technical Decisions

| Decision | Rationale | Impact |
|----------|-----------|--------|
| Svelte 5 over React/Vue | Runes > hooks, smaller bundles, simpler reactivity | Faster dev, less boilerplate |
| TanStack Query for data | Caching, loading states, error retry, refetch — free | No manual fetch/loading/error state management |
| pnpm workspace | `pnpm install` at root installs everything | No more infinite postinstall loop |
| JSON-only API | Backend returns data, frontend renders it | No server-side HTML classes leaking into API |
| Client-side badge mapping | `status: "missing"` mapped to `badge-warning` in Svelte | Backend stays UI-agnostic |
| Pre-built calendar grid | API returns 42-cell grid, not raw entries | Frontend doesn't need calendar math |
| Svelte template-first | Template (HTML) before `<script>` — keeps UI visible at a glance | Consistent component structure, easier skimming |

## Svelte Conventions

### Template-First Structure
Every `.svelte` file **must** place the HTML template **before** the `<script lang="ts">` block:

```svelte
<!-- ✅ Correct: template first -->
<div class="container">
  <h1>{title}</h1>
</div>

<script lang="ts">
  let { title }: { title: string } = $props();
</script>
```

```svelte
<!-- ❌ Wrong: script first -->
<script lang="ts">
  let { title }: { title: string } = $props();
</script>

<div class="container">
  <h1>{title}</h1>
</div>
```

**Rationale**: Template-first makes the component's visual structure immediately visible — the markup you'll spend the most time editing sits at the top of the file. The script supports the template, not the other way around.

### Component Organization
- Reusable UI components go in `frontend/src/components/`
- Non-UI modules (API clients, utilities, types) go in `frontend/src/lib/`
- Component files use `PascalCase.svelte` naming

## Integration Points

| System | Purpose | Protocol | Direction |
|--------|---------|----------|-----------|
| Radarr API | Movie calendar data | HTTP REST (JSON) | Outbound |
| Sonarr API | Episode calendar data | HTTP REST (JSON) | Outbound |

## Development Environment

```
Setup: pnpm install (installs root + frontend deps, plus composer install)
Requirements: PHP 8.5+, Composer, pnpm, Docker (for Radarr/Sonarr)
Run: pnpm dev (concurrent: nodemon for PHP + Vite HMR for Svelte)
     Open http://localhost:5173 (Vite proxies /api/* to :8080)
Build: pnpm build:frontend (Vite build + copy to public/)
API only: php bin/server (serves on :8080)
Testing: vendor/bin/pest
```

## Deployment

```
Build: pnpm build:frontend → public/index.html + assets
Serve: php bin/server (port 8080, configurable via PORT env)
Docker: Build container with PHP 8.5 + Composer + pnpm
```

## Onboarding Checklist

- [x] Know the stack is PHP + Svelte 5, NOT PHP + Twig/HTMX
- [x] Understand the architecture changed from hypermedia monolith to JSON API + SPA
- [x] Know the old context files are wrong — trust this one
- [x] Know `pnpm dev` starts both servers
- [x] Know Svelte components use template-first structure (HTML before `<script>`)
- [x] Know there's no Twig, no HTMX, no SSE, no badgeClass in the API
