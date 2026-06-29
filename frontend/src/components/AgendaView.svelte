<script lang="ts">
  import type { CalendarCell, CalendarEntry, MediaStatus } from '../lib/api.js';

  let { cells = [] as CalendarCell[] }: { cells?: CalendarCell[] } = $props();

  /** Days that have at least one entry, sorted chronologically. */
  const activeDays = $derived(
    cells
      .filter((c) => c.entries.length > 0)
      .sort((a, b) => a.date.localeCompare(b.date)),
  );

  // -------- helpers ---------------------------------------------------

  function dateToday(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }

  function formatDateHeader(dateStr: string): string {
    const today = dateToday();
    if (dateStr === today) return 'Today';
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const t = `${tomorrow.getFullYear()}-${String(tomorrow.getMonth() + 1).padStart(2, '0')}-${String(tomorrow.getDate()).padStart(2, '0')}`;
    if (dateStr === t) return 'Tomorrow';
    const d = new Date(dateStr + 'T12:00:00');
    return d.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });
  }

  function formatAirTime(entry: CalendarEntry): string | null {
    if (entry.type === 'episode') {
      const airDate = entry.metadata?.airDateUtc;
      if (airDate && typeof airDate === 'string') {
        const d = new Date(airDate);
        if (isNaN(d.getTime())) return null;
        return d.toLocaleTimeString('en-US', {
          hour: 'numeric',
          minute: '2-digit',
          hour12: true,
        });
      }
    }
    return null;
  }

  function episodeTag(entry: CalendarEntry): string | null {
    if (
      entry.type === 'episode' &&
      entry.metadata?.seasonNumber != null &&
      entry.metadata?.episodeNumber != null
    ) {
      return `${entry.metadata.seasonNumber}x${entry.metadata.episodeNumber}`;
    }
    return null;
  }

  function seriesTitle(entry: CalendarEntry): string | null {
    if (entry.type === 'episode' && entry.metadata?.seriesTitle && typeof entry.metadata.seriesTitle === 'string') {
      return entry.metadata.seriesTitle;
    }
    return entry.title;
  }

  function releaseType(entry: CalendarEntry): string | null {
    if (entry.type === 'movie' && entry.metadata?.releaseType && typeof entry.metadata.releaseType === 'string') {
      return entry.metadata.releaseType;
    }
    return null;
  }

  function borderClass(status: MediaStatus): string {
    if (status === 'downloaded') return 'border-l-success';
    if (status === 'missing') return 'border-l-error';
    if (status === 'queued') return 'border-l-secondary';
    if (status === 'upcoming') return 'border-l-info';
    if (status === 'error') return 'border-l-error';
    return 'border-l-base-300';
  }

  const SONARR_BG_SHADES = ['bg-blue-800/70', 'bg-blue-700/70', 'bg-blue-600/70', 'bg-blue-500/70'];
  const RADARR_BG_SHADES = ['bg-amber-800/70', 'bg-amber-700/70', 'bg-amber-600/70', 'bg-amber-500/70'];

  function hashInstanceId(id: string | undefined): number {
    if (!id) return 0;
    let hash = 0;
    for (let i = 0; i < id.length; i++) {
      hash = ((hash << 5) - hash) + id.charCodeAt(i);
      hash |= 0;
    }
    return Math.abs(hash);
  }

  function bgClass(entry: CalendarEntry): string {
    const idx = hashInstanceId(entry.instanceId);
    return entry.serviceSource === 'sonarr'
      ? SONARR_BG_SHADES[idx % SONARR_BG_SHADES.length]
      : RADARR_BG_SHADES[idx % RADARR_BG_SHADES.length];
  }

  function entryKey(entry: CalendarEntry): string {
    return `${entry.instanceId ?? ''}|${entry.title}|${entry.type}|${entry.metadata?.seasonNumber ?? ''}|${entry.metadata?.episodeNumber ?? ''}`;
  }
</script>

<div class="divide-y divide-base-300">
  {#each activeDays as day}
    <section class="py-4 first:pt-0 last:pb-0">
      <!-- Date header -->
      <h3 class="text-sm font-semibold uppercase tracking-wider opacity-60 mb-3 px-1">
        {formatDateHeader(day.date)}
      </h3>

      <!-- Entries for this day -->
      <div class="space-y-1.5">
        {#each day.entries as entry (entryKey(entry))}
          <a
            href={entry.url ?? '#'}
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-start gap-3 px-3 py-2.5 border-l-[3px] no-underline rounded-r-lg transition-all duration-150 hover:brightness-125 {borderClass(entry.status)} {bgClass(entry)}"
          >
            <!-- Title column -->
            <div class="flex-1 min-w-0">
              <span class="text-sm font-medium truncate block">
                {seriesTitle(entry)}
              </span>
              {#if entry.type === 'episode' && entry.title}
                <span class="text-xs opacity-70 truncate block">
                  {entry.title}
                </span>
              {/if}
            </div>

            <!-- Detail column (right-aligned) -->
            <div class="flex items-center gap-3 shrink-0">
              {#if episodeTag(entry)}
                <span class="text-xs opacity-70">
                  {episodeTag(entry)}
                </span>
              {/if}
              {#if releaseType(entry)}
                <span class="text-xs opacity-70">
                  {releaseType(entry)}
                </span>
              {/if}
              {#if entry.instanceLabel}
                <span class="text-xs opacity-50">
                  {entry.instanceLabel}
                </span>
              {/if}
              {#if formatAirTime(entry)}
                <span class="text-xs opacity-70">
                  {formatAirTime(entry)}
                </span>
              {/if}
            </div>
          </a>
        {/each}
      </div>
    </section>
  {/each}

  {#if activeDays.length === 0}
    <div class="py-12 text-center opacity-50 text-sm">
      No entries this month
    </div>
  {/if}
</div>
