{#if import.meta.env.VITE_DEMO}
  <div role="alert" class="alert alert-warning rounded-none py-2 px-4 text-center text-xs font-medium">
    DEMO MODE — Mock data for demonstration only. No real services connected.
  </div>
{/if}

<QueryClientProvider client={queryClient}>
  <CalendarDashboard />
</QueryClientProvider>

<script lang="ts">
  import { QueryClient, QueryClientProvider } from '@tanstack/svelte-query';
  import CalendarDashboard from './components/CalendarDashboard.svelte';

  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 5 * 60 * 1000,
        gcTime: 10 * 60 * 1000,
        retry: 1,
        refetchOnWindowFocus: false,
      },
    },
  });
</script>
