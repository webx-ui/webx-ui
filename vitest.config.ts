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
    // The workspace's own packages are built JavaScript, but outside node_modules: vitest would
    // transform `@webx-ui/core` again for every file (1.4 s each). Node loads them once a worker.
    server: {
      deps: {
        external: [/\/packages\/[^/]+\/dist\//],
      },
    },
    // The JavaScript the Composer packages ship next to their Blade, and the CI scripts, too.
    include: [
      'packages/*/src/**/*.{test,spec}.ts',
      'php/packages/*/resources/js/**/*.test.js',
      'scripts/**/*.test.mjs',
    ],
  },
})
