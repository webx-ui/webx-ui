import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@webx-ui/tokens': fileURLToPath(new URL('./packages/tokens/src/index.ts', import.meta.url)),
    },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./vitest.setup.ts'],
    globals: true,
    // The JavaScript the Composer packages ship next to their Blade, too.
    include: ['packages/*/src/**/*.{test,spec}.ts', 'php/packages/*/resources/js/**/*.test.js'],
  },
})
