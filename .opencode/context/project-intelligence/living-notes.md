<!-- Context: project-intelligence/notes | Priority: high | Version: 2.0 | Updated: 2026-06-29 -->

# Living Notes — ArrCal

> Active issues, technical debt, open questions, and insights. Not about Docker containers anymore.

## Quick Reference

- **Purpose**: Capture current state, problems, and open questions
- **Update**: Weekly or when status changes
- **Archive**: Move resolved items to bottom with status

## Technical Debt

| Item | Impact | Priority | Mitigation |
|------|--------|----------|------------|
| No error handling for Radarr/Sonarr connection failures | Calendar shows empty if services are down | Medium | Add graceful fallback in CalendarAggregator |
| PHP Twig cache still exists in `cache/twig/` | Dead files, composer install complains | Low | Already cleaned manually |
| No frontend tests | No Svelte component tests | Low | Add when feature set stabilizes |
| Context files still referenced old project name "Dash" | AI agents get confused | Medium | ✅ **Just fixed** |

### Technical Debt Details

**No error handling for *arr connection failures**
- *Priority*: Medium
- *Impact*: If Radarr or Sonarr is unreachable, the calendar shows empty with no explanation
- *Root Cause*: Aggregator catches errors but just returns empty array silently
- *Proposed Solution*: Return partial data with a warning field when one service fails
- *Effort*: Small
- *Status*: Acknowledged

## Open Questions

| Question | Status | Next Action |
|----------|--------|-------------|
| Should we add tag/genre filtering? | Open | Build client-side filter UI in Svelte |
| Should we cache more aggressively? | Open | TanStack Query handles this, but server cache TTL could be longer |
| Dark mode toggle? | Open | daisyUI has it built-in, just need a button |

## Known Issues

| Issue | Severity | Workaround | Status |
|-------|----------|------------|--------|
| createQuery needs queryClient passed explicitly | Low | Pass `() => queryClient` as second arg | Won't fix — Svelte context timing |
| pnpm build runs postinstall (composer install) every time | Low | It's fast, not actually a problem | Acceptable |

## Insights & Lessons Learned

### What Works Well
- **Svelte 5 runes** — `$state`, `$derived`, `$effect` are genuinely nice. No hooks, no closure capture issues.
- **TanStack Query** — `createQuery` with a changing query key auto-refetches. Zero boilerplate.
- **pnpm workspace** — One `pnpm install` at root. Done. No recursion.
- **Semantic API** — Returning `status: "missing"` instead of `badgeClass: "badge-warning"` means the frontend can swap CSS frameworks without backend changes.

### What Could Be Better
- **Svelte context timing** — `createQuery` can't read context from a sibling `QueryClientProvider`. Must pass `queryClient` explicitly. Annoying but documented.
- **Error reporting** — When Radarr or Sonarr is down, the user gets an empty calendar with no explanation.
- **No loading skeletons** — The spinner is fine but a skeleton grid would feel faster.

### Patterns & Conventions

### Code Patterns Worth Preserving
- **Handler → Service → Domain** — Thin handlers, async services, immutable DTOs. Still valid.
- **Final readonly DTOs with named constructors** — `CalendarEntry::fromRadarrResponse()` is clean and testable.
- **Parallel fetch with Promise::all** — Both *arr services fetched simultaneously, results merged.
- **Frontend-mapped CSS** — `Record<MediaStatus, string>` in Svelte, not in PHP.

### Gotchas for Maintainers
- **`createQuery` needs `() => queryClient`** as second argument when used in the same component as `QueryClientProvider`. The context isn't set up yet when script runs.
- **TanStack Query v6 Svelte** uses `createQuery` (not `useQuery`). Arguments must be wrapped in a function.
- **pnpm workspace** means `pnpm --filter arrcal-frontend <cmd>` for frontend commands, not `cd frontend && pnpm`.

## Active Work

| Area | Goal | Status |
|------|------|--------|
| Calendar grid | Basic month view with entries | Done |
| Month navigation | Prev/Next/Today with caching | Done |
| TypeScript migration | All frontend code in TS | Done |
| TanStack Query | Replace manual fetch | Done |
| Tag filtering | Client-side genre/tag filter | Planned |
| Search | Search across entries | Planned |

## Archive (Resolved Items)

### Resolved: Double-fetch on navigation
- **Resolved**: 2026-06-29
- **Resolution**: Replaced `$effect` with TanStack Query. Query key changes auto-refetch without double-firing.
- **Learnings**: `$effect` watching a state variable that the effect itself changes = infinite loop. Use a proper data-fetching library.

### Resolved: Twig/HTMX removed
- **Resolved**: 2026-06-29
- **Resolution**: Stripped CalendarHandler, calendar.twig, layout.twig. Cleaned ServerKernel.
- **Learnings**: Server-rendered HTML works for simple views. Calendar apps need client-side state.

### Resolved: PHP returning CSS classes
- **Resolved**: 2026-06-29
- **Resolution**: Removed `badgeClass()` from `MediaStatus`, removed `badgeClass` from `toArray()`. Frontend maps status to classes.
- **Learnings**: Backend returns data. Frontend decides how it looks.

## Onboarding Checklist

- [x] Review known technical debt and understand impact
- [x] Know what open questions exist
- [x] Understand current issues and workarounds
- [x] Be aware of patterns and gotchas
- [x] Know active projects and timelines
