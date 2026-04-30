import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import dts from 'vite-plugin-dts'
import { resolve } from 'node:path'

export default defineConfig({
  plugins: [
    vue(),
    dts({
      insertTypesEntry: true,
      cleanVueFileName: true,
    }),
  ],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'resources/ts'),
    },
  },
  build: {
    lib: {
      entry: resolve(__dirname, 'resources/ts/index.ts'),
      name: 'LaravelAdmin',
      formats: ['es', 'cjs'],
      fileName: (format) => `index.${format === 'es' ? 'mjs' : 'cjs'}`,
    },
    rollupOptions: {
      external: [
        'vue',
        'vue-router',
        'pinia',
        'axios',
        '@dskripchenko/ui',
        '@tiptap/vue-3',
        '@tiptap/starter-kit',
        'marked',
      ],
      output: {
        globals: {
          vue: 'Vue',
          'vue-router': 'VueRouter',
          pinia: 'Pinia',
          axios: 'Axios',
        },
        assetFileNames: (assetInfo) =>
          assetInfo.name === 'style.css' ? 'style.css' : assetInfo.name!,
      },
    },
    sourcemap: true,
    target: 'es2022',
  },
})
