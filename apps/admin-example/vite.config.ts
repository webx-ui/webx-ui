import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

/**
 * The panel, developed against the Laravel application `scripts/php-dev-app.sh` provisions.
 *
 * Everything goes through this one origin: the page, the API and the CSRF cookie. That is not
 * a convenience — a session cookie behaves differently across origins, and a panel that only
 * works because CORS was relaxed for development is a panel that does not work.
 */
const BACKEND = process.env.WEBX_BACKEND ?? 'http://127.0.0.1:8000'

const source = (path: string): string =>
  fileURLToPath(new URL(`../../packages/${path}`, import.meta.url))

export default defineConfig({
  plugins: [vue()],
  resolve: {
    // Exact matches only: a subpath like `@webx-ui/tokens/tokens.css` has to keep resolving
    // through the package's own exports, and a blanket alias would swallow it.
    alias: [
      { find: /^@webx-ui\/core$/, replacement: source('core/src/index.ts') },
      { find: /^@webx-ui\/tokens$/, replacement: source('tokens/src/index.ts') },
      { find: /^@webx-ui\/admin$/, replacement: source('admin/src/index.ts') },
      { find: /^@webx-ui\/module-auth$/, replacement: source('module-auth/src/index.ts') },
    ],
  },
  server: {
    proxy: {
      '/api': { target: BACKEND, changeOrigin: false },
      '/sanctum': { target: BACKEND, changeOrigin: false },
    },
  },
})
