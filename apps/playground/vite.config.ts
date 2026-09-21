import type { ServerResponse } from 'node:http'
import { fileURLToPath } from 'node:url'
import { defineConfig, type Plugin } from 'vite'
import vue from '@vitejs/plugin-vue'
import { cover, query, titleOf } from './server/records'
import { panelServer } from './server/panel'

/**
 * The table lab talks to a server, and this is it.
 *
 * A table that pages, sorts and filters in the same component that draws it proves nothing:
 * every bug worth finding here lives in the round trip — the row that arrives a frame after the
 * header, the width that settles before the data does, the loading state nobody sees because
 * there is nothing to wait for. So the rows go over HTTP, with a delay, in Laravel's paginator
 * shape.
 */
function mockApi(): Plugin {
  return {
    name: 'webx-playground-api',
    configureServer(server) {
      server.middlewares.use((request, response, next) => {
        const url = new URL(request.url ?? '/', 'http://localhost')

        const asCover = url.pathname.match(/^\/api\/covers\/(\d+)\.svg$/)
        if (asCover) return sendCover(response, Number(asCover[1]))

        if (url.pathname !== '/api/records') return next()

        const number = (name: string) => {
          const value = url.searchParams.get(name)
          return value === null || value === '' ? null : Number(value) || null
        }

        const page = query({
          page: number('page') ?? 1,
          perPage: number('per_page') ?? 20,
          sort: url.searchParams.get('sort'),
          q: url.searchParams.get('q') ?? '',
          status: url.searchParams.get('status') ?? '',
          rubric: number('rubric'),
          author: number('author'),
          channel: url.searchParams.get('channel') ?? '',
        })

        /* Long enough to see the skeleton, short enough to keep clicking through pages. */
        setTimeout(() => {
          response.setHeader('Content-Type', 'application/json; charset=utf-8')
          response.end(JSON.stringify(page))
        }, 320)
      })
    },
  }
}

function sendCover(response: ServerResponse, id: number): void {
  response.setHeader('Content-Type', 'image/svg+xml; charset=utf-8')
  response.setHeader('Cache-Control', 'public, max-age=3600')
  response.end(cover(id, titleOf(id)))
}

// Alias to the package sources so edits in packages/* hot-reload without a rebuild — and so
// every package resolves the same copy of the core rather than one from its own dist.
const pkg = (name: string) => ({
  find: new RegExp(`^@webx-ui/${name}$`),
  replacement: fileURLToPath(new URL(`../../packages/${name}/src/index.ts`, import.meta.url)),
})

export default defineConfig({
  plugins: [vue(), mockApi(), panelServer()],
  resolve: {
    alias: [
      pkg('core'),
      pkg('tokens'),
      pkg('schema'),
      pkg('module-admin'),
      pkg('module-auth'),
      pkg('module-blocks'),
      pkg('module-blog'),
      pkg('module-inbox'),
      pkg('module-media'),
      pkg('module-pages'),
      pkg('module-seo'),
    ],
  },
  // Two pages: the component playground, and the panel with the module screens in it.
  build: {
    rollupOptions: {
      input: {
        main: fileURLToPath(new URL('index.html', import.meta.url)),
        panel: fileURLToPath(new URL('panel.html', import.meta.url)),
      },
    },
  },
  server: {
    port: 5174,
  },
})
