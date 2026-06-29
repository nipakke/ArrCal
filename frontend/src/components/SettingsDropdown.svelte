<script lang="ts">
  import type { InstanceStatus } from '../lib/api.js';

  export type ViewMode = 'month' | 'agenda';
  export type ServiceFilter = 'all' | string;
  export type StatusFilter = 'all' | 'downloaded' | 'missing' | 'queued' | 'upcoming';

  export interface Settings {
    view: ViewMode;
    service: ServiceFilter;
    status: StatusFilter;
  }

  let container: HTMLDetailsElement;

  let {
    settings = { view: 'month', service: 'all', status: 'all' } as Settings,
    radarrInstances = [] as InstanceStatus[],
    sonarrInstances = [] as InstanceStatus[],
    onchange,
  }: {
    settings?: Settings;
    radarrInstances?: InstanceStatus[];
    sonarrInstances?: InstanceStatus[];
    onchange?: (s: Settings) => void;
  } = $props();

  function label(): string {
    const parts: string[] = [settings.view === 'month' ? 'Month' : 'Agenda'];

    if (settings.service !== 'all') {
      const allInstances = [...sonarrInstances, ...radarrInstances];
      const inst = allInstances.find((i) => i.id === settings.service);
      parts.push(inst?.label ?? settings.service);
    }

    if (settings.status !== 'all') parts.push(settings.status.charAt(0).toUpperCase() + settings.status.slice(1));
    return parts.join(' · ');
  }

  function active(): boolean {
    return settings.service !== 'all' || settings.status !== 'all';
  }

  function select(partial: Partial<Settings>) {
    onchange?.({ ...settings, ...partial });
    container.open = false;
  }

  function handleClick(e: MouseEvent) {
    if (container.open && !container.contains(e.target as Node)) {
      container.open = false;
    }
  }

  const views: { value: ViewMode; label: string }[] = [
    { value: 'month', label: 'Month' },
    { value: 'agenda', label: 'Agenda' },
  ];

  const statuses: { value: StatusFilter; label: string }[] = [
    { value: 'all', label: 'All statuses' },
    { value: 'downloaded', label: 'Downloaded' },
    { value: 'missing', label: 'Missing' },
    { value: 'queued', label: 'Queued' },
    { value: 'upcoming', label: 'Upcoming' },
  ];

  let serviceOptions = $derived<{ value: ServiceFilter; label: string }[]>([
    { value: 'all', label: 'All sources' },
    ...radarrInstances.map((inst) => ({
      value: inst.id,
      label: inst.label ?? 'Radarr',
    })),
    ...sonarrInstances.map((inst) => ({
      value: inst.id,
      label: inst.label ?? 'Sonarr',
    })),
  ]);
</script>

<svelte:window onclick={handleClick} />

<details class="dropdown dropdown-end" bind:this={container}>
  <summary class="btn btn-sm {active() ? 'btn-active' : ''} list-none [&::-webkit-details-marker]:hidden">
    {label()}
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-1 opacity-50"><polyline points="6 9 12 15 18 9"></polyline></svg>
  </summary>

  <div class="dropdown-content menu bg-base-200 rounded-box z-30 w-48 p-2 shadow mt-1 flex flex-col gap-0.5" role="menu">
    <div class="text-xs font-semibold uppercase opacity-40 px-2 pt-1 pb-0.5">View</div>
    {#each views as v}
      <button
        class="w-full text-left text-sm px-3 py-1.5 rounded hover:bg-base-300 transition-colors {settings.view === v.value ? 'bg-base-300' : ''}"
        onclick={() => select({ view: v.value })}
      >
        {v.label}
        {#if settings.view === v.value}
          <span class="float-right opacity-50">✓</span>
        {/if}
      </button>
    {/each}

    <div class="text-xs font-semibold uppercase opacity-40 px-2 pt-2 pb-0.5 mt-1 border-t border-base-300">Source</div>
    {#each serviceOptions as s}
      <button
        class="w-full text-left text-sm px-3 py-1.5 rounded hover:bg-base-300 transition-colors {settings.service === s.value ? 'bg-base-300' : ''}"
        onclick={() => select({ service: s.value })}
      >
        {s.label}
        {#if settings.service === s.value}
          <span class="float-right opacity-50">✓</span>
        {/if}
      </button>
    {/each}

    <div class="text-xs font-semibold uppercase opacity-40 px-2 pt-2 pb-0.5 mt-1 border-t border-base-300">Status</div>
    {#each statuses as st}
      <button
        class="w-full text-left text-sm px-3 py-1.5 rounded hover:bg-base-300 transition-colors {settings.status === st.value ? 'bg-base-300' : ''}"
        onclick={() => select({ status: st.value })}
      >
        {st.label}
        {#if settings.status === st.value}
          <span class="float-right opacity-50">✓</span>
        {/if}
      </button>
    {/each}
  </div>
</details>
