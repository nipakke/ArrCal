<div class="overflow-x-auto">
  <div class="min-w-[800px]">
    <!-- Day headers -->
    <div class="grid grid-cols-7 mb-1">
      {#each dayHeaders as header}
        <div class="text-center text-xs font-semibold uppercase tracking-wide opacity-60 py-2">
          {header}
        </div>
      {/each}
    </div>

    <!-- Calendar grid -->
    <div class="grid grid-cols-7 gap-px bg-base-300/40 rounded-box overflow-hidden border border-base-300">
      {#each cells as cell}
        <CalendarCell {cell} {today} {currentMonth} />
      {/each}
    </div>
  </div>
</div>

<script lang="ts">
  import CalendarCell from './CalendarCell.svelte';
  import type { CalendarCell as CalendarCellType } from '../lib/api.js';

  let { cells = [] as CalendarCellType[], today = '', currentMonth = '' }: {
    cells?: CalendarCellType[];
    today?: string;
    currentMonth?: string;
  } = $props();

  const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
</script>
