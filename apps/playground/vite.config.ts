import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// Alias to the package sources so edits in packages/* hot-reload without a rebuild.
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: [
      {
        find: /^@webx-ui\/core$/,
        replacement: fileURLToPath(new URL('../../packages/core/src/index.ts', import.meta.url)),
      },
      {
        find: /^@webx-ui\/tokens$/,
        replacement: fileURLToPath(new URL('../../packages/tokens/src/index.ts', import.meta.url)),
      },
    ],
  },
  server: {
    port: 5174,
  },
})
