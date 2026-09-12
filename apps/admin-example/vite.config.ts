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

/** Where the built panel is served from inside the application, as a bare name. */
const mountPoint = (process.env.WEBX_MOUNT ?? '').replace(/^\/+|\/+$/g, '')

const source = (path: string): string =>
  fileURLToPath(new URL(`../../packages/${path}`, import.meta.url))

export default defineConfig({
  plugins: [vue()],
  // Built straight into the Laravel application's public directory when asked, so the real
  // host serves the panel the way production will — same origin, real cookies, no proxy.
  //
  // A name rather than a path: Git Bash rewrites anything that looks like an absolute POSIX
  // path into a Windows one on its way into the environment, and `/webx/` would arrive as
  // `C:/Program Files/Git/webx/` — which builds, and then 404s every font.
  base: mountPoint === '' ? '/' : `/${mountPoint}/`,
  build: {
    outDir: process.env.WEBX_OUT ?? 'dist',
    emptyOutDir: true,
    rollupOptions: {
      // Stable names: the Laravel side names these files in its config, and a hash in them
      // would mean editing that config on every build.
      output: {
        entryFileNames: 'webx.js',
        chunkFileNames: 'webx-[name].js',
        assetFileNames: 'webx.[ext]',
      },
    },
  },
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
