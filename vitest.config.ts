import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'node:path'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'resources/ts'),
    },
  },
  test: {
    environment: 'jsdom',
    include: ['resources/ts/**/*.test.ts'],
    globals: true,
  },
})
