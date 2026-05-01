import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { visualizer } from 'rollup-plugin-visualizer'
import { resolve } from 'node:path'

/**
 * Vite library config для @dskripchenko/laravel-admin.
 *
 * Multi-entry: основной bundle + два subpath-bundle'а для опциональных
 * WYSIWYG-полей (quill / tinymce). Host-проект устанавливает peer-deps
 * только тех редакторов, которыми пользуется.
 *
 * - vue() — SFC compiler
 * - visualizer() — bundle stats-отчёт `dist/stats.html` (запускается только
 *   при `ANALYZE=1 npm run build`)
 *
 * .d.ts-файлы генерируются отдельно через `vue-tsc --emitDeclarationOnly`
 * в `npm run build` (это убирает зависимость от vite-plugin-dts →
 * @microsoft/api-extractor → ajv@8 conflict с eslint).
 *
 * `cssFileName: 'style'` фиксирует имя CSS как `style.css` (Vite 7 по
 * умолчанию использует lib.name → `laravel-admin.css`, но host'ы уже
 * импортируют через `@dskripchenko/laravel-admin/style.css` через exports).
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
      // Все peer-зависимости host'а — external. Regex-paths покрывают
      // sub-imports (например `@tiptap/extension-image`).
      external: [
        'vue',
        'vue-router',
        'pinia',
        'axios',
        /^@dskripchenko\/ui($|\/)/,
        /^@tiptap\//,
        'marked',
        // WYSIWYG peer-deps: используются TinymceField/QuillField, поставляются
        // host-проектом через peer-dep.
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
