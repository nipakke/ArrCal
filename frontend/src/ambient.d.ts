/// <reference types="svelte" />
/// <reference types="vite/client" />

declare module '*.svelte' {
  import type { ComponentType } from 'svelte';
  const component: ComponentType;
  export default component;
}

declare module '*.css' {}

interface ImportMetaEnv {
  readonly VITE_DEMO?: string;
}

