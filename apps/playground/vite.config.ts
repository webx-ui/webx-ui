import type { ServerResponse } from 'node:http'
import { fileURLToPath } from 'node:url'
import { defineConfig, type Plugin } from 'vite'
import vue from '@vitejs/plugin-vue'
import { cover, query, titleOf } from './server/records'

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

// Alias to the package sources so edits in packages/* hot-reload without a rebuild.
export default defineConfig({
  plugins: [vue(), mockApi()],
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
