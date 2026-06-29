<!-- Context: project-intelligence/business | Priority: high | Version: 2.0 | Updated: 2026-06-29 -->

# Business Domain — ArrCal

> ArrCal is a unified calendar dashboard for Radarr and Sonarr. No more alt-tabbing between two UIs to see what's coming out this week.

## Quick Reference

- **Purpose**: Understand why this project exists (it's not a Docker dashboard anymore, wake up)
- **Update When**: Business direction changes, new features shipped, pivot
- **Audience**: Developers needing context, stakeholders, product team

## Project Identity

- **Project Name**: ArrCal
- **Tagline**: Radarr + Sonarr calendar, unified
- **Problem Statement**: Media server operators run both Radarr (movies) and Sonarr (TV shows). Each has its own calendar view. Switching between them to see what's releasing this week is tedious. There's no unified view.
- **Solution**: A single-page calendar dashboard that queries both Radarr and Sonarr APIs, aggregates movie and episode releases into one monthly view with status badges. Runs as a lightweight PHP + Svelte app alongside your *arr stack.

## Target Users

| User Segment | Who They Are | What They Need | Pain Points |
|--------------|--------------|----------------|-------------|
| Primary: Media server operators | Running Radarr + Sonarr on their server | One calendar showing all upcoming releases | Switching between two UIs, no unified view |
| Secondary: Home media enthusiasts | Curating a personal media library | Planning what to watch this month | Missing releases because they forgot to check |
| Tertiary: ArrStack power users | Automating their whole media pipeline | Quick visual overview of the week ahead | Terminal fatigue, want a dashboard |

## Value Proposition

**For Users**:
- One calendar showing Radarr movies AND Sonarr episodes together
- Color-coded status badges (Downloaded, Missing, Upcoming)
- Client-side month navigation with caching (revisiting a month is instant)
- Lightweight — runs alongside your *arr stack without extra infrastructure

**For the Project**:
- Demonstrates the JSON API + SPA architecture pattern
- Reference implementation for ReactPHP async HTTP + Svelte 5 runes
- Clean separation: PHP knows nothing about the frontend's CSS

## Success Metrics

| Metric | Definition | Target | Current |
|--------|------------|--------|---------|
| Radarr integration | Fetches movie calendar correctly | 100% | ✅ |
| Sonarr integration | Fetches episode calendar correctly | 100% | ✅ |
| Month navigation | Smooth prev/next with caching | Instant on revisit | ✅ (TanStack Query) |
| Single dev command | `pnpm dev` starts both servers | Yes | ✅ |
| No Twig/HTMX in codebase | Zero references in backend | Yes | ✅ |

## Key Stakeholders

| Role | Responsibility |
|------|----------------|
| Developer | Full-stack — PHP API + Svelte SPA + *arr integration |
| AI Agents (OAC) | Assisted development via .opencode/ context system (when the context isn't lying) |

## Roadmap Context

**Current Focus**: Working calendar with month navigation
**Next Milestone**: Client-side tag/genre filtering
**Long-term Vision**: Search, notifications, multi-user support

## Business Constraints

- Must run alongside existing Radarr/Sonarr instances (Docker or bare metal)
- Must be lightweight — no database, no Redis, no message queue
- Must respect *arr API rate limits (5-min server-side cache)
- Must work with Docker Compose (dev containers provided)

## Onboarding Checklist

- [x] Understand the problem: unified *arr calendar
- [x] Know this is NOT a Docker monitoring dashboard
- [x] Know the target users are media server operators
- [x] Understand the value proposition
