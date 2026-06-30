import { defineConfig } from 'vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';

const isDemo = !!process.env.VITE_DEMO;

export default defineConfig({
  plugins: [tailwindcss(), svelte()],
  base: isDemo ? '/ArrCal/' : '/',
  define: {
    'import.meta.env.VITE_DEMO': process.env.VITE_DEMO ? 'true' : 'false',
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
});
