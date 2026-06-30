<div
  class="min-h-16 md:min-h-24 bg-base-100 {isToday ? 'bg-primary/10' : ''} {!isCurrent ? 'opacity-40' : ''} relative"
>
  <!-- Day number behind entries -->
  <div
    class="absolute inset-0 flex items-center justify-center pointer-events-none select-none text-5xl md:text-7xl font-bold opacity-15 text-shadow-lg {isToday ? 'text-primary' : 'text-base-content'}"
  >
    {cell.day}
  </div>

  <!-- Entries on top - natural flow so cell expands to fit all entries -->
    <div class="relative z-10 opacity-77 hover:opacity-100 transition-all duration-150 p-0.5">
    <div class="space-y-0.5">
      {#each entries as entry}
        <EntryBadge {entry} />
      {/each}
    </div>
  </div>
</div>

<script lang="ts">
  import EntryBadge from './EntryBadge.svelte';
  import type { CalendarCell as CalendarCellType, CalendarEntry } from '../lib/api.js';

  let { cell, today = '', currentMonth = '' }: {
    cell: CalendarCellType;
    today?: string;
    currentMonth?: string;
  } = $props();

  const isToday = $derived(cell.date === today);
  const isCurrent = $derived(cell.date.slice(0, 7) === currentMonth);
  const entries = $derived<CalendarEntry[]>(cell.entries ?? []);
</script>
