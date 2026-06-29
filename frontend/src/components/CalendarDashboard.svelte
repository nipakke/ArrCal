<div class="min-h-screen flex flex-col px-4 md:px-8 max-w-7xl mx-auto">
  <!-- Header -->
  <header class="flex items-center justify-between mb-6 pt-4 pb-2">
    <h1 class="text-2xl font-bold">ArrCal</h1>
    <div class="flex items-center gap-4">
      <!-- Service status (hidden when none configured) -->
      {#if !noServices}
        <div class="flex items-center gap-3 text-xs opacity-60">
          {#each sonarrInstances as inst}
            <span class="flex items-center gap-1">
              <span class="inline-block w-2 h-2 rounded-full {inst.enabled ? 'bg-success' : 'bg-base-300'}"></span>
              {inst.label ?? 'Sonarr'}
            </span>
          {/each}
          {#each radarrInstances as inst}
            <span class="flex items-center gap-1">
              <span class="inline-block w-2 h-2 rounded-full {inst.enabled ? 'bg-success' : 'bg-base-300'}"></span>
              {inst.label ?? 'Radarr'}
            </span>
          {/each}
        </div>
      {/if}
    </div>
  </header>

  <CalendarTopBar
    {prevMonth}
    {nextMonth}
    {monthName}
    settings={settings.value}
    {radarrInstances}
    {sonarrInstances}
    isTodayMonth={currentMonth === today.slice(0, 7)}
    isFetching={query.isFetching}
    onMonthChange={handleMonthChange}
    onSettingsChange={handleSettingsChange}
  />

  <!-- Service warnings -->
  {#each errors as err}
    <div class="alert alert-error mb-2">
      <span class="flex items-center gap-2">
        <span class="inline-block w-2 h-2 rounded-full bg-error shrink-0"></span>
        {err.label ?? err.id}: {err.error}
      </span>
    </div>
  {/each}

  <!-- Error banner (total failure) -->
  {#if query.isError}
    <div class="alert alert-error mb-4">
      <span>{query.error?.message ?? 'Failed to load calendar'}</span>
      <button onclick={() => query.refetch()} class="btn btn-sm btn-ghost">Retry</button>
    </div>
  {/if}

  <!-- Empty state: no services configured -->
  {#if noServices}
    <div class="flex flex-col items-center justify-center py-24 text-center">
      <svg class="w-16 h-16 mb-4 opacity-20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
        <rect x="3" y="4" width="18" height="16" rx="2" />
        <path d="M8 2v4M16 2v4M3 10h18" />
        <circle cx="12" cy="15" r="2" />
      </svg>
      <h2 class="text-xl font-semibold mb-2">No media services configured</h2>
      <p class="text-sm opacity-50 max-w-md leading-relaxed">
        Set at least one of <code class="font-mono font-semibold">RADARR_URL</code> +
        <code class="font-mono font-semibold">RADARR_API_KEY</code> or
        <code class="font-mono font-semibold">SONARR_URL</code> +
        <code class="font-mono font-semibold">SONARR_API_KEY</code> in your
        environment variables to get started.
      </p>
    </div>
  {:else}
    <!-- Calendar / Agenda -->
    {#if settings.value.view === 'month'}
      <CalendarGrid cells={filteredCells} {today} {currentMonth} />
    {:else}
      <AgendaView cells={filteredCells} />
    {/if}

    <!-- Legend -->
    <div class="mt-4 space-y-1.5 text-sm">
    <div class="flex items-center gap-8">
      <span class="font-semibold opacity-70 w-16 shrink-0">Source:</span>
      <div class="flex items-center gap-8 flex-wrap">
        {#each sonarrInstances as inst, i}
          <span class="flex items-center gap-1.5">
            <span class="inline-block w-0 h-4 border-l-[3px] {sonarrShade(i)}"></span>
            {inst.label ?? 'Sonarr'}
          </span>
        {/each}
        {#each radarrInstances as inst, i}
          <span class="flex items-center gap-1.5">
            <span class="inline-block w-0 h-4 border-l-[3px] {radarrShade(i)}"></span>
            {inst.label ?? 'Radarr'}
          </span>
        {/each}
      </div>
    </div>
    <div class="flex items-center gap-8">
      <span class="font-semibold opacity-70 w-16 shrink-0">Status:</span>
      <div class="flex items-center gap-6">
        <span class="flex items-center gap-1.5">
          <span class="inline-block w-0 h-4 border-l-[3px] border-l-success"></span>
          Downloaded
        </span>
        <span class="flex items-center gap-1.5">
          <span class="inline-block w-0 h-4 border-l-[3px] border-l-error"></span>
          Missing
        </span>
        <span class="flex items-center gap-1.5">
          <span class="inline-block w-0 h-4 border-l-[3px] border-l-secondary"></span>
          Queued
        </span>
        <span class="flex items-center gap-1.5">
          <span class="inline-block w-0 h-4 border-l-[3px] border-l-info"></span>
          Upcoming
        </span>
      </div>
    </div>
  </div>
{/if}
</div>

<script lang="ts">
  import { createQuery, keepPreviousData } from '@tanstack/svelte-query';
  import { fetchCalendar, type CalendarCell, type CalendarEntry, type InstanceStatus } from '../lib/api.js';
  import { getConfig, type ArrConfig } from '../lib/config.js';
  import { useLocalStorage } from '../lib/useLocalStorage.svelte.js';
  import AgendaView from './AgendaView.svelte';
  import CalendarGrid from './CalendarGrid.svelte';
  import CalendarTopBar from './CalendarTopBar.svelte';
  import { type ServiceFilter, type StatusFilter, type Settings } from './SettingsDropdown.svelte';

  function dateToday(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }

  function ymToday(): string {
    return dateToday().slice(0, 7);
  }

  let config: ArrConfig = $state({ radarr: [], sonarr: [] });
  let currentMonth = $state(ymToday());
  let settings = useLocalStorage<Settings>('arrcal:settings', { view: 'month', service: 'all', status: 'all' });

  $effect(() => {
    getConfig().then((c) => { config = c; });
  });

  function shiftMonth(base: string, delta: number): string {
    const d = new Date(base + '-01');
    d.setMonth(d.getMonth() + delta);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  }

  let prevMonth = $derived(shiftMonth(currentMonth, -1));
  let nextMonth = $derived(shiftMonth(currentMonth, +1));

  const query = createQuery(() => ({
    queryKey: ['calendar', currentMonth] as const,
    queryFn: ({ queryKey }) => fetchCalendar(queryKey[1]),
    placeholderData: keepPreviousData,
  }));

  let radarrInstances = $derived<InstanceStatus[]>(
    query.data?.services?.radarr ?? config.radarr
  );
  let sonarrInstances = $derived<InstanceStatus[]>(
    query.data?.services?.sonarr ?? config.sonarr
  );

  let errors = $derived<{ id: string; label: string | null; error: string }[]>(
    [...radarrInstances, ...sonarrInstances]
      .filter((inst): inst is InstanceStatus & { error: string } => inst.error !== null)
      .map((inst) => ({ id: inst.id, label: inst.label, error: inst.error! }))
  );

  let noServices = $derived(
    radarrInstances.length === 0 && sonarrInstances.length === 0 && query.isFetched
  );

  let today = $derived(dateToday());

  function blankCells(yearMonth: string): CalendarCell[] {
    const [y, m] = yearMonth.split('-').map(Number);
    const first = new Date(y, m - 1, 1);
    const daysInMonth = new Date(y, m, 0).getDate();
    const startPad = first.getDay();
    const prevDays = new Date(y, m - 1, 0).getDate();
    const cells: CalendarCell[] = [];

    for (let i = startPad - 1; i >= 0; i--) {
      const day = prevDays - i;
      cells.push({
        date: `${y}-${String(m - 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`,
        day,
        entries: [],
      });
    }
    for (let d = 1; d <= daysInMonth; d++) {
      const dateStr = `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
      cells.push({
        date: dateStr,
        day: d,
        entries: [],
      });
    }
    const remaining = 42 - cells.length;
    for (let d = 1; d <= remaining; d++) {
      const dateStr = `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
      cells.push({
        date: dateStr,
        day: d,
        entries: [],
      });
    }
    return cells;
  }

  let monthName = $derived(
    new Date(currentMonth + '-01').toLocaleDateString('en-US', { year: 'numeric', month: 'long' })
  );
  let calendarData = $derived<CalendarCell[]>(query.data?.calendarCells ?? blankCells(currentMonth));

  function entryMatchesFilter(e: CalendarEntry, f: { service: ServiceFilter; status: StatusFilter }): boolean {
    if (f.service !== 'all' && e.instanceId !== f.service && e.serviceSource !== f.service) return false;
    if (f.status !== 'all' && e.status !== f.status) return false;
    return true;
  }

  let filteredCells = $derived<CalendarCell[]>(
    settings.value.service === 'all' && settings.value.status === 'all'
      ? calendarData
      : calendarData.map((cell) => ({
          ...cell,
          entries: cell.entries.filter((e) => entryMatchesFilter(e, settings.value)),
        })),
  );

  const SONARR_SHADES = ['border-l-blue-800', 'border-l-blue-600', 'border-l-blue-400'];
  const RADARR_SHADES = ['border-l-amber-800', 'border-l-amber-600', 'border-l-amber-400'];

  function sonarrShade(i: number): string {
    return SONARR_SHADES[i % SONARR_SHADES.length];
  }

  function radarrShade(i: number): string {
    return RADARR_SHADES[i % RADARR_SHADES.length];
  }

  function handleMonthChange(month: string) {
    currentMonth = month;
  }

  function handleSettingsChange(s: Settings) {
    settings.value = s;
  }
</script>
