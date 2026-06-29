<!-- Context: project-intelligence/bridge | Priority: high | Version: 2.0 | Updated: 2026-06-29 -->

# Business ↔ Tech Bridge — ArrCal

> How business needs translate to technical solutions. Spoiler: neither Twig nor HTMX is involved.

## Quick Reference

- **Purpose**: Show how technical choices serve the goal of a unified calendar
- **Update When**: New features, refactoring, business pivot

## Core Mapping

| Business Need | Technical Solution | Why This Mapping | Business Value |
|---------------|-------------------|------------------|----------------|
| See Radarr + Sonarr releases together | CalendarAggregator parallel fetch + merge | Both APIs called concurrently via Promise::all | One view for both services |
| Month navigation without page reload | Svelte 5 $state + TanStack Query | Query key changes → auto refetch, cached responses | Smooth, instant navigation |
| Status at a glance (Downloaded/Missing/etc.) | Color-coded daisyUI badges (frontend-mapped) | Semantic status from API → CSS on frontend | Quick visual scan |
| Fast revisits | TanStack Query staleTime 5min + server-side ApiCache 5min | Two layers of caching | Second visit is instant |
| Single command to start developing | pnpm workspace + concurrently | Root `pnpm dev` starts PHP + Vite concurrently | Zero friction onboarding |

## Feature Mapping

### Feature: Unified Calendar View

**Business Context**:
- User need: See movie and episode releases in one calendar
- Business goal: Eliminate switching between Radarr and Sonarr UIs
- Priority: Core feature

**Technical Implementation**:
- Solution: `CalendarApiHandler` → `CalendarAggregator` (parallel fetch) → `CalendarGrid` (Svelte)
- Architecture: PHP JSON API → Svelte SPA with TanStack Query
- Trade-offs: API returns pre-built 42-cell grid (not raw entries) so frontend doesn't do calendar math. Slightly less flexible but simpler.

**Connection**:
Without this feature, the project has no reason to exist. Every other feature is a nice-to-have on top of this.

### Feature: Client-side Month Navigation

**Business Context**:
- User need: Browse different months without full page reloads
- Business goal: Smooth, app-like experience
- Priority: High

**Technical Implementation**:
- Solution: `currentMonth` is `$state`. Changing it updates the TanStack Query key → auto-refetch. Prev/Next/Today buttons just set state.
- Architecture: Svelte `$state` + TanStack Query reactivity
- Trade-offs: No SSR, but for a dashboard that doesn't need SEO, who cares?

**Connection**:
The old Twig approach required a server round-trip for every month change. This made the calendar feel sluggish. Client-side navigation is instant on cache hits and smooth on misses.

## Trade-off Decisions

| Situation | Business Priority | Technical Priority | Decision Made | Rationale |
|-----------|-------------------|-------------------|---------------|-----------|
| UI framework | Reactivity, smooth transitions | Maintainable, type-safe | Svelte 5 + TS | Runes > hooks, smaller bundles |
| Data fetching | Fast navigation, caching | Predictable state | TanStack Query | Free caching, loading, error states |
| API response shape | Semantic data | UI-agnostic | No CSS classes in API | Backend doesn't know about daisyUI |
| Dev setup | One command to start | Reproducible | pnpm workspace | No more infinite loop |
| Calendar grid math | Correct date alignment | Don't duplicate logic | API returns 42 cells | Frontend doesn't need calendar lib |

## Common Misalignments

| Misalignment | Warning Signs | Resolution Approach |
|--------------|---------------|---------------------|
| Adding HTMX/Twig back | Someone says "let's server-render the calendar" | Ask: why? Client-side navigation is already instant. Don't regress. |
| Putting CSS classes in API | `badgeClass` appears in a PR | Reject it. The frontend maps semantic status to CSS. |
| Breaking the workspace | Someone runs `cd frontend && pnpm install` | It works but won't trigger root postinstall loop. Fine, but prefer `pnpm --filter arrcal-frontend`. |
| Not passing queryClient to createQuery | "No QueryClient was found" error | Pass `() => queryClient` as second argument. Context from sibling QueryClientProvider isn't available yet. |

## Stakeholder Communication

**For the Developer**:
- The Svelte SPA + JSON API split is intentional. PHP does data, Svelte does UI.
- TanStack Query handles ALL data fetching. Don't write manual fetch/loading/error code.
- The API returns semantic status, not CSS classes. Map on the frontend.

**For Potential Contributors**:
- Read `decisions-log.md` to understand why we're not using Twig/HTMX anymore
- The backend is PHP 8.5+ ReactPHP. The frontend is Svelte 5 + TS.
- `pnpm dev` starts both. `php bin/server` starts just the API.

## Onboarding Checklist

- [x] Understand the core business need: unified *arr calendar
- [x] See how each major feature maps to business value
- [x] Know the key trade-offs and why decisions were made
- [x] Know that the API returns semantic data, not UI classes
- [x] Know that TanStack Query handles all frontend data fetching
