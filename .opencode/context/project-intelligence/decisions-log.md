<!-- Context: project-intelligence/decisions | Priority: high | Version: 2.0 | Updated: 2026-06-29 -->

# Decisions Log — ArrCal

> Major architectural and technical decisions. Read this before suggesting we add HTMX back.

## Quick Reference

- **Purpose**: Document decisions so future maintainers understand context
- **Format**: Each decision as a separate entry
- **Status**: Decided | Pending | Under Review | Deprecated

---

## Decision: Svelte 5 SPA instead of server-rendered HTML

**Date**: 2026-06-29
**Status**: Decided
**Owner**: Developer

### Context
The project originally used Twig + HTMX (server-rendered HTML with partial swaps). For a Docker dashboard, this was fine — simple list views, occasional refresh. But the project pivoted to a calendar dashboard (Radarr/Sonarr), which needs client-side month navigation, tag filtering, and reactive state. HTMX + Twig made this painful: every month change required a full server round-trip, client-side filtering was impossible without Alpine.js or custom JS, and the "zero JS" promise was already broken by HTMX's own JS.

### Decision
Rewrite the frontend as a Svelte 5 SPA with TanStack Query for data fetching. The PHP backend becomes a pure JSON API.

### Rationale
Svelte 5 runes (`$state`, `$derived`, `$effect`) provide genuine reactivity without a virtual DOM. TanStack Query handles caching, loading states, error retry, and background refetch — all the boilerplate we were writing manually. The PHP backend is simpler without template rendering.

### Alternatives Considered
| Alternative | Pros | Cons | Why Rejected? |
|-------------|------|------|---------------|
| HTMX + Twig (keep) | Zero new framework | Terrible for calendar interactions, no client-side state | Calendar needs client reactivity |
| React + PHP | Huge ecosystem | Boilerplate, hooks complexity, slower dev | Svelte 5 is simpler and faster |
| Vue 3 + PHP | Good middle ground | Still more verbose than Svelte 5 | Svelte 5 runes win on simplicity |
| Livewire | Server-driven UI | Requires Laravel + FPM, incompatible with ReactPHP | Can't run on our async runtime |
| Inertia | Full-stack framework | Same problem: assumes Laravel/FPM | Dead end for ReactPHP |

### Impact
- **Positive**: Smooth month navigation, client-side filtering, proper reactive state, cleaner backend
- **Negative**: New frontend build step, TypeScript to maintain, larger bundle
- **Risk**: Two build pipelines (PHP + frontend) — mitigated by pnpm workspace

---

## Decision: TanStack Query for data fetching

**Date**: 2026-06-29
**Status**: Decided
**Owner**: Developer

### Context
The first Svelte prototype had manual `fetch` + `$state` for loading/error/data + `onMount` for initial load + manual refetch. Then a buggy `$effect` doubled every API call. Then we patched it with `onMount`. Then we realized we were writing what TanStack Query gives for free.

### Decision
Use `@tanstack/svelte-query` with `createQuery`. Pass the `queryClient` explicitly to avoid Svelte context timing issues (because `createQuery` runs before `QueryClientProvider` mounts when they're in the same component).

### Rationale
TanStack Query provides caching (with `staleTime`/`gcTime`), loading states, error handling, automatic refetch on query key change, and retry logic. The Svelte API (`createQuery`) wraps arguments in a function for reactivity — the `queryKey` includes `currentMonth`, so changing the month triggers a refetch automatically.

### Impact
- **Positive**: Zero manual fetch/loading/error management, built-in caching, refetch on month change works by changing the state
- **Negative**: Added dependency (~13KB gzipped)
- **Risk**: Must pass `queryClient` explicitly as `() => queryClient` since `createQuery` can't read context from a sibling `QueryClientProvider`

---

## Decision: pnpm workspace (monorepo)

**Date**: 2026-06-29
**Status**: Decided
**Owner**: Developer

### Context
The root `postinstall` script ran `composer install && cd frontend && pnpm install`. Running any pnpm command triggered the postinstall, which ran `cd frontend && pnpm install`, which triggered the root postinstall again — an infinite loop that killed builds.

### Decision
Create `pnpm-workspace.yaml` that includes `frontend/`. All scripts use `pnpm --filter arrcal-frontend` instead of `cd frontend && pnpm`. The postinstall only runs `composer install` now.

### Rationale
pnpm workspace is the standard monorepo approach. One `pnpm install` at root installs everything. No more recursion. Scripts are cleaner.

### Impact
- **Positive**: No infinite loop, clean dependency management
- **Negative**: Must remember `--filter arrcal-frontend` for frontend commands

---

## Decision: JSON API returns semantic data only (no CSS classes)

**Date**: 2026-06-29
**Status**: Decided
**Owner**: Developer

### Context
The original `CalendarEntry::toArray()` returned `badgeClass` (e.g., `"badge badge-warning"`) — a daisyUI-specific CSS class. The backend had a `MediaStatus::badgeClass()` method that returned Tailwind classes. This is backwards: the backend shouldn't know about the frontend's CSS framework.

### Decision
Remove `badgeClass` from the API response. The frontend maps `entry.status` (semantic: `"missing"`, `"downloaded"`, etc.) to daisyUI badge classes locally using a `Record<MediaStatus, string>`.

### Impact
- **Positive**: Backend is UI-agnostic. Changing CSS framework doesn't require backend changes.
- **Negative**: Slightly more code in the frontend (one mapping object)

---

## Onboarding Checklist

- [x] Understand the philosophy behind major architectural choices
- [x] Know why the project moved from Twig/HTMX to Svelte 5 SPA
- [x] Know that TanStack Query handles all data fetching
- [x] Know the API returns semantic status, not CSS classes
- [x] Know the pnpm workspace layout
