import type { CalendarCell, CalendarResponse, MediaStatus } from './api.js';
import { getDemoConfig } from './demo-config.js';

// ---------------------------------------------------------------------------
// Deterministic data pools — stable arrays used with seeded RNG
// ---------------------------------------------------------------------------

const MOVIE_TITLES = [
  'The Silent Hour',
  'Crimson Tide Rising',
  'Echoes of Tomorrow',
  'The Last Frontier',
  'Midnight Protocol',
  'Glass Horizon',
  'Steel Dawn',
  'Phantom Thread',
  'Dark Waters',
  'Iron Valley',
  'The Vanishing Point',
  'Northern Lights',
] as const;

const SERIES_TITLES = [
  'The White Lotus',
  'Foundation',
  'Severance',
  'Stranger Things',
  'The Last of Us',
  'House of the Dragon',
  'The Bear',
  'Shogun',
  'Silo',
  'Andor',
] as const;

const RELEASE_TYPES = ['Theatrical', 'Digital', 'Blu-ray', 'Limited Theatrical'] as const;

const STATUS_POOL: MediaStatus[] = ['downloaded', 'upcoming', 'missing', 'queued'];

const STATUS_LABEL: Record<MediaStatus, string> = {
  downloaded: 'Downloaded',
  missing: 'Missing',
  upcoming: 'Upcoming',
  unmonitored: 'Unmonitored',
  error: 'Error',
  queued: 'Queued',
};

// ---------------------------------------------------------------------------
// Deterministic hash — same month string always produces the same seed
// ---------------------------------------------------------------------------

/**
 * Simple DJB2-style string hash producing a non-negative 32-bit integer.
 * Used as the seed for the entire month's data generation.
 */
function hashMonth(month: string): number {
  let hash = 5381;
  for (let i = 0; i < month.length; i++) {
    hash = ((hash << 5) + hash) ^ month.charCodeAt(i);
    hash |= 0; // clamp to 32-bit signed int
  }
  return Math.abs(hash);
}

// ---------------------------------------------------------------------------
// Seeded pseudo-random number generator (linear congruential)
// ---------------------------------------------------------------------------

/**
 * Returns a deterministic RNG function that produces values in [0, 1).
 * Same seed → same sequence of calls → identical output for a given month.
 */
function createRng(seed: number): () => number {
  let state = (seed * 1103515245 + 12345) & 0x7fffffff;
  return () => {
    state = (state * 1103515245 + 12345) & 0x7fffffff;
    return state / 0x80000000;
  };
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Pick a random element from a readonly array deterministically. */
function pick<T>(arr: readonly T[], rng: () => number): T {
  return arr[Math.floor(rng() * arr.length)];
}

/** Format a date as YYYY-MM-DD, handling month/year rollover correctly. */
function formatDate(y: number, m: number, d: number): string {
  // Date constructor uses 0-indexed months and handles overflow automatically
  const dt = new Date(y, m - 1, d);
  const year = dt.getFullYear();
  const month = String(dt.getMonth() + 1).padStart(2, '0');
  const day = String(dt.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

// ---------------------------------------------------------------------------
// Calendar cell grid builder — mirrors blankCells() in CalendarDashboard
// ---------------------------------------------------------------------------

/**
 * Build a 42-cell calendar grid (6 weeks × 7 days) for the given year-month.
 * Leading cells are padded from the previous month (Sunday-start),
 * trailing cells from the next month — all with no entries yet.
 */
function buildCells(yearMonth: string): CalendarCell[] {
  const [y, m] = yearMonth.split('-').map(Number);
  const firstDay = new Date(y, m - 1, 1);
  const daysInMonth = new Date(y, m, 0).getDate();
  const startPad = firstDay.getDay(); // Sunday = 0

  const cells: CalendarCell[] = [];

  // Leading days from previous month
  const prevMonthDays = new Date(y, m - 1, 0).getDate();
  for (let i = startPad - 1; i >= 0; i--) {
    const day = prevMonthDays - i;
    cells.push({
      date: formatDate(y, m - 1, day),
      day,
      entries: [],
    });
  }

  // Current month days
  for (let d = 1; d <= daysInMonth; d++) {
    cells.push({
      date: formatDate(y, m, d),
      day: d,
      entries: [],
    });
  }

  // Trailing days from next month to fill 42-cell grid
  const remaining = 42 - cells.length;
  for (let d = 1; d <= remaining; d++) {
    cells.push({
      date: formatDate(y, m + 1, d),
      day: d,
      entries: [],
    });
  }

  return cells;
}

// ---------------------------------------------------------------------------
// Main export — pure function producing identical output for the same month
// ---------------------------------------------------------------------------

/**
 * Generate deterministic mock calendar data for a given month.
 *
 * @param month - YYYY-MM format (e.g. "2026-06")
 * @returns CalendarResponse with 42-cell grid, 8-15 entries, and demo services
 *
 * Pure function: no side effects, no Math.random, no network calls.
 * Same `month` argument always returns identical data.
 */
export function generateDemoCalendar(month: string): CalendarResponse {
  const seed = hashMonth(month);
  const rng = createRng(seed);

  const [y, m] = month.split('-').map(Number);
  const daysInMonth = new Date(y, m, 0).getDate();

  // Build the 42-cell grid (empty entries)
  const cells = buildCells(month);

  // Determine entry count: 8-15 entries deterministically
  const entryCount = 8 + Math.floor(rng() * 8); // [8, 15]

  // Resolve instances from demo config
  const instances = getDemoConfig();
  const movieInstances = instances.radarr; // [radarr-hd, radarr-4k]
  const seriesInstances = instances.sonarr; // [sonarr-main, sonarr-anime]

  // Generate entries and place them on current-month cells.
  // Days are picked with replacement so some days may have multiple entries.
  for (let i = 0; i < entryCount; i++) {
    // Deterministically select a day within the current month
    const day = 1 + Math.floor(rng() * daysInMonth);

    // Find the cell for this day (cells are in order: leading, current, trailing)
    // The current month cells start at index `startPad` and span `daysInMonth`.
    const startPad = new Date(y, m - 1, 1).getDay();
    const cellIndex = startPad + (day - 1);
    const cell = cells[cellIndex];

    const isMovie = rng() < 0.5;

    if (isMovie) {
      const inst = pick(movieInstances, rng);
      const status = pick(STATUS_POOL, rng);
      const title = pick(MOVIE_TITLES, rng);
      const releaseType = pick(RELEASE_TYPES, rng);

      cell.entries.push({
        title,
        type: 'movie',
        status,
        statusLabel: STATUS_LABEL[status],
        serviceSource: 'radarr',
        monitored: true,
        url: null,
        metadata: { releaseType },
        instanceId: inst.id,
        instanceLabel: inst.label ?? undefined,
      });
    } else {
      const inst = pick(seriesInstances, rng);
      const status = pick(STATUS_POOL, rng);
      const seriesTitle = pick(SERIES_TITLES, rng);
      const seasonNumber = 1 + Math.floor(rng() * 6); // seasons 1-6
      const episodeNumber = 1 + Math.floor(rng() * 12); // episodes 1-12

      cell.entries.push({
        title: `${seriesTitle} S${String(seasonNumber).padStart(2, '0')}E${String(episodeNumber).padStart(2, '0')}`,
        type: 'episode',
        status,
        statusLabel: STATUS_LABEL[status],
        serviceSource: 'sonarr',
        monitored: true,
        url: null,
        metadata: {
          seriesTitle,
          seasonNumber,
          episodeNumber,
          airDateUtc: `${cell.date}T01:00:00Z`,
        },
        instanceId: inst.id,
        instanceLabel: inst.label ?? undefined,
      });
    }
  }

  return {
    calendarCells: cells,
    services: {
      radarr: instances.radarr,
      sonarr: instances.sonarr,
    },
  };
}
