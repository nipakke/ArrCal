/**
 * Reactive localStorage binding — Svelte 5 runes edition.
 *
 * Mirrors VueUse's useLocalStorage: a single `{ value }` object whose
 * `.value` property is backed by `$state` (so it's reactive in `.svelte`
 * components) and automatically persisted to localStorage on every change.
 *
 * ```svelte
 * <script lang="ts">
 *   let theme = useLocalStorage('arrcal:theme', 'dracula');
 *   // theme.value is reactive — use it directly in templates
 * </script>
 *
 * <div data-theme={theme.value}>…</div>
 * ```
 */
export function useLocalStorage<T>(key: string, defaultValue: T): { value: T } {
  let stored = $state<T>(
    typeof localStorage !== 'undefined'
      ? JSON.parse(localStorage.getItem(key) ?? JSON.stringify(defaultValue)) as T
      : defaultValue,
  );

  $effect(() => {
    localStorage.setItem(key, JSON.stringify(stored));
  });

  return {
    get value(): T {
      return stored;
    },
    set value(v: T) {
      stored = v;
    },
  };
}
