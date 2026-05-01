import { defineConfig } from 'vitest/config'
import { resolve } from 'node:path'

export default defineConfig({
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
