<nav class="grid grid-cols-3 items-center mb-6" aria-label="Calendar navigation">
  <div class="join join-horizontal">
    <button
      class="join-item btn btn-sm"
      onclick={() => onMonthChange(prevMonth)}
      disabled={isFetching}
      aria-label="Previous month"
    >
      ← Prev
    </button>
    <button
      class="join-item btn btn-sm"
      class:btn-active={isTodayMonth}
      onclick={goToToday}
      disabled={isFetching}
    >
      Today
    </button>
    <button
      class="join-item btn btn-sm"
      onclick={() => onMonthChange(nextMonth)}
      disabled={isFetching}
      aria-label="Next month"
    >
      Next →
    </button>
  </div>

  <span class="text-lg font-semibold text-center">{monthName}</span>

  <div class="justify-self-end">
    <SettingsDropdown {settings} {radarrInstances} {sonarrInstances} onchange={onSettingsChange} />
  </div>
</nav>

<script lang="ts">
  import type { InstanceStatus } from '../lib/api.js';
  import SettingsDropdown, { type Settings } from './SettingsDropdown.svelte';

  let {
    prevMonth,
    nextMonth,
    monthName,
    settings,
    radarrInstances = [],
    sonarrInstances = [],
    isTodayMonth = false,
    isFetching = false,
    onMonthChange,
    onSettingsChange,
  }: {
    prevMonth: string;
    nextMonth: string;
    monthName: string;
    settings: Settings;
    radarrInstances?: InstanceStatus[];
    sonarrInstances?: InstanceStatus[];
    isTodayMonth?: boolean;
    isFetching?: boolean;
    onMonthChange: (month: string) => void;
    onSettingsChange: (s: Settings) => void;
  } = $props();

  function goToToday() {
    const d = new Date();
    onMonthChange(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
  }
</script>
