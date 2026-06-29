import type { InstanceStatus } from './api.js';

export interface ArrConfig {
  radarr: InstanceStatus[];
  sonarr: InstanceStatus[];
}

declare global {
  interface Window {
    __ARR_CONFIG__?: ArrConfig;
  }
}

let configPromise: Promise<ArrConfig> | null = null;

/** Read the bootstrap config injected by the server, or fetch it. */
export function getConfig(): Promise<ArrConfig> {
  if (configPromise !== null) return configPromise;

  // DEMO mode: use mock data, no network — dynamic import allows Vite to tree-shake
  // the entire demo-config module from production builds
  if (import.meta.env.VITE_DEMO) {
    configPromise = import('./demo-config.js').then((m) => m.getDemoConfig());
    return configPromise;
  }

  // PRODUCTION: existing logic
  if (window.__ARR_CONFIG__) {
    configPromise = Promise.resolve(window.__ARR_CONFIG__);
  } else {
    configPromise = fetch('/api/config')
      .then((r) => r.json() as Promise<ArrConfig>)
      .catch((): ArrConfig => ({
        radarr: [],
        sonarr: [],
      }));
  }

  return configPromise as Promise<ArrConfig>;
}
