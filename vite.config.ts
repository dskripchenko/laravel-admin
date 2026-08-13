import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { visualizer } from 'rollup-plugin-visualizer'
import { resolve } from 'node:path'

/**
 * The Vite library config for @dskripchenko/laravel-admin.
 *
 * Multi-entry: the main bundle plus two subpath bundles for the optional WYSIWYG
 * fields (quill / tinymce). A host project installs the peer deps only of the
 * editors it actually uses.
 *
 * - vue() — the SFC compiler
 * - visualizer() — the bundle stats report `dist/stats.html` (runs only under
 *   `ANALYZE=1 npm run build`)
 *
 * The .d.ts files are generated separately through
 * `vue-tsc --emitDeclarationOnly` in `npm run build` (which removes the
 * dependency on vite-plugin-dts → @microsoft/api-extractor → the ajv@8 conflict
 * with eslint).
 *
 * `cssFileName: 'style'` pins the CSS name as `style.css` (Vite 7 uses lib.name
 * by default → `laravel-admin.css`, but the hosts already import it as
 * `@dskripchenko/laravel-admin/style.css` through the exports).
 */
export default defineConfig({
  plugins: [
    vue(),
    process.env.ANALYZE === '1' &&
      visualizer({
        filename: 'dist/stats.html',
        title: '@dskripchenko/laravel-admin bundle',
        gzipSize: true,
        brotliSize: true,
      }),
  ],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'resources/ts'),
    },
  },
  build: {
    lib: {
      entry: {
        index: resolve(__dirname, 'resources/ts/index.ts'),
        quill: resolve(__dirname, 'resources/ts/components/fields/wysiwyg/quill/index.ts'),
        tinymce: resolve(__dirname, 'resources/ts/components/fields/wysiwyg/tinymce/index.ts'),
      },
      name: 'LaravelAdmin',
      formats: ['es', 'cjs'],
      fileName: (format, entryName) => `${entryName}.${format === 'es' ? 'mjs' : 'cjs'}`,
      cssFileName: 'style',
    },
    rollupOptions: {
      // Every peer dependency of the host is external. The regex paths cover
      // the sub-imports (`@tiptap/extension-image`, for instance).
      external: [
        'vue',
        'vue-router',
        'pinia',
        'axios',
        /^@dskripchenko\/ui($|\/)/,
        /^@dskripchenko\/wysiwyg($|\/)/,
        /^@tiptap\//,
        'marked',
        // The WYSIWYG peer deps: used by TinymceField/QuillField and supplied by
        // the host project as peer dependencies.
        '@tinymce/tinymce-vue',
        'tinymce',
        '@vueup/vue-quill',
        'quill',
      ],
      output: {
        globals: {
          vue: 'Vue',
          'vue-router': 'VueRouter',
          pinia: 'Pinia',
          axios: 'Axios',
        },
      },
    },
    sourcemap: true,
    target: 'es2022',
  },
})
