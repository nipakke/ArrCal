import type { ArrConfig } from './config.js';
import type { InstanceStatus } from './api.js';

/**
 * Returns a static demo ArrConfig with 2 Radarr and 2 Sonarr instances
 * — all enabled with no errors. Pure function, no side effects.
 */
export function getDemoConfig(): ArrConfig {
  return {
    radarr: [
      { id: 'radarr-hd', label: 'HD Movies', enabled: true, url: null, error: null },
      { id: 'radarr-4k', label: '4K Movies', enabled: true, url: null, error: null },
    ] satisfies InstanceStatus[],
    sonarr: [
      { id: 'sonarr-main', label: 'Main TV', enabled: true, url: null, error: null },
      { id: 'sonarr-anime', label: 'Anime TV', enabled: true, url: null, error: null },
    ] satisfies InstanceStatus[],
  };
}
