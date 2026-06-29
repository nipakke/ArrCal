export type MediaStatus = 'downloaded' | 'missing' | 'upcoming' | 'unmonitored' | 'error' | 'queued';

export interface CalendarEntry {
  title: string;
  type: 'movie' | 'episode';
  status: MediaStatus;
  statusLabel: string;
  serviceSource: 'radarr' | 'sonarr';
  monitored: boolean;
  url: string | null;
  metadata: Record<string, unknown>;
  instanceId?: string;
  instanceLabel?: string;
}

export interface CalendarCell {
  date: string;
  day: number;
  entries: CalendarEntry[];
}

export interface InstanceStatus {
  id: string;
  label: string | null;
  enabled: boolean;
  url: string | null;
  error: string | null;
}

export interface CalendarResponse {
  calendarCells: CalendarCell[];
  services: {
    radarr: InstanceStatus[];
    sonarr: InstanceStatus[];
  };
}

const BASE = '/api';

/**
 * Fetch calendar data for a given month.
 * @param month - YYYY-MM format
 */
export async function fetchCalendar(month: string): Promise<CalendarResponse> {
  // DEMO mode: deterministic mock data, no network — dynamic import allows Vite
  // to tree-shake the entire demo-calendar module from production builds
  if (import.meta.env.VITE_DEMO) {
    const { generateDemoCalendar } = await import('./demo-calendar.js');
    return generateDemoCalendar(month);
  }

  // PRODUCTION: fetch from server
  const res = await fetch(`${BASE}/calendar?month=${month}`);

  if (!res.ok) {
    const body = await res.json().catch(() => ({ error: 'Unknown error' }));
    throw new Error(body.error ?? `HTTP ${res.status}`);
  }

  return res.json() as Promise<CalendarResponse>;
}
