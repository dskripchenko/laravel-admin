import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'node:path'

/**
 * The prebuilt admin application (as opposed to vite.config.ts, the library).
 *
 * Everything is bundled — Vue, the router, Pinia, the UI kit, the default
 * WYSIWYG — so a host needs neither Node nor a build step: the output goes to
 * public/, is committed, and `admin:install` publishes it to the host's
 * public/vendor/admin. `base: './'` keeps every URL relative, so the bundle
 * works from whatever path the host serves it.
 */
export default defineConfig({
  plugins: [vue()],
  base: './',
  // public/ is the output here, not a folder of static files to copy.
  publicDir: false,
  resolve: {
    alias: {
      '@': resolve(__dirname, 'resources/ts'),
    },
  },
  build: {
    outDir: 'public',
    emptyOutDir: true,
    manifest: true,
    sourcemap: false,
    chunkSizeWarningLimit: 1500,
    rollupOptions: {
      input: resolve(__dirname, 'resources/ts/app.ts'),
    },
  },
})
