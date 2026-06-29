<a
  href={entryUrl}
  target="_blank"
  rel="noopener noreferrer"
  class="flex flex-col px-1.5 py-1 border-l-[3px] no-underline {bgClass} {borderClass} hover:brightness-125 transition-all duration-150"
>
  <span class="text-[13px] truncate leading-tight" title={displayTitleTooltip}>
    {displayTitle}
  </span>
  <span class="text-[11px] opacity-70 truncate leading-tight">{detailLine}</span>
</a>

<script lang="ts">
  import type { CalendarEntry, MediaStatus } from '../lib/api.js';

  let { entry }: { entry: CalendarEntry } = $props();

  const borderClass = $derived(computeBorderClass(entry.status));

  const SONARR_BG_SHADES = ['bg-blue-800', 'bg-blue-700', 'bg-blue-600', 'bg-blue-500'];
  const RADARR_BG_SHADES = ['bg-amber-800', 'bg-amber-700', 'bg-amber-600', 'bg-amber-500'];

  /**
   * Deterministic hash of instanceId to pick a consistent shade index.
   * When instanceId is undefined (legacy single-instance), returns 0
   * for the default (first) shade.
   */
  function hashInstanceId(id: string | undefined): number {
    if (!id) return 0;
    let hash = 0;
    for (let i = 0; i < id.length; i++) {
      hash = ((hash << 5) - hash) + id.charCodeAt(i);
      hash |= 0;
    }
    return Math.abs(hash);
  }

  const shadeIndex = $derived(hashInstanceId(entry.instanceId));

  const bgClass = $derived(
    entry.serviceSource === 'sonarr'
      ? SONARR_BG_SHADES[shadeIndex % SONARR_BG_SHADES.length]
      : RADARR_BG_SHADES[shadeIndex % RADARR_BG_SHADES.length],
  );

  const displayTitle = $derived(
    entry.type === 'episode' && entry.metadata?.seriesTitle
      ? entry.metadata.seriesTitle as string
      : entry.title,
  );

  /** Tooltip combines title with instance label for context */
  const displayTitleTooltip = $derived(
    entry.instanceLabel
      ? `${displayTitle} [${entry.instanceLabel}]`
      : displayTitle,
  );

  const episodeTag = $derived(
    entry.type === 'episode' &&
    entry.metadata?.seasonNumber != null &&
    entry.metadata?.episodeNumber != null
      ? `${entry.metadata.seasonNumber}x${entry.metadata.episodeNumber}`
      : null,
  );

  const displayTime = $derived(formatTime(entry));

  const releaseLabel = $derived(
    entry.type === 'movie' && entry.metadata?.releaseType && typeof entry.metadata.releaseType === 'string'
      ? entry.metadata.releaseType as string
      : null,
  );

  /** Unobtrusive instance label indicator (e.g. [HD], [4K]) */
  const instanceLabelTag = $derived(
    entry.instanceLabel ? `[${entry.instanceLabel}]` : null,
  );

  const detailLine = $derived(
    [episodeTag, releaseLabel, instanceLabelTag, displayTime].filter(Boolean).join(' · '),
  );

  const entryUrl = $derived(entry.url ?? '#');

  function formatTime(entry: CalendarEntry): string | null {
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

  function computeBorderClass(status: MediaStatus): string {
    if (status === 'downloaded') return 'border-l-success';
    if (status === 'missing') return 'border-l-error';
    if (status === 'queued') return 'border-l-secondary';
    if (status === 'upcoming') return 'border-l-info';
    if (status === 'error') return 'border-l-error';
    return 'border-l-base-300';
  }
</script>
