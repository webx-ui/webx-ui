/*
 * «System → Search index» (WEBX_UI_CATALOG_MANTICORE.md, decision 27) without a Manticore: a
 * server that answers, the English table up to date and the Russian one of an older schema — the
 * state a deploy leaves behind — so the page opens on what it exists for, «Rebuild». The rebuild
 * runs on the clock: a couple of seconds queued, then the products written over ten seconds, then
 * both tables up to date.
 *
 * `POST /search-index/_outage` switches the server off and on: the page then says it does not
 * answer, and the list of products says it comes from the database (decision 13). Not a route
 * of the real API — the playground's only way to show the notice.
 */

type On = (
  method: string,
  pattern: string,
  handler: (context: { body: Record<string, unknown> }) => unknown,
) => void
type Fail = (status: number, message: string) => Error

const PREFIX = 'webx_demo'
const QUEUED_FOR = 2000
const WRITING_FOR = 10000

let available = true
let rebuild: { queuedAt: number; total: number } | null = null
let rebuilt = false

const minutesAgo = (minutes: number) => new Date(Date.now() - minutes * 60_000).toISOString()

/** Whether the list of products answers from the database: the server is down. */
export function manticoreDown(): boolean {
  return !available
}

function progress(products: number) {
  if (rebuild === null) {
    return idle()
  }

  const elapsed = Date.now() - rebuild.queuedAt
  const queuedAt = new Date(rebuild.queuedAt).toISOString()
  const startedAt = new Date(rebuild.queuedAt + QUEUED_FOR).toISOString()

  if (elapsed < QUEUED_FOR) {
    return { ...idle(), state: 'queued', queued_at: queuedAt }
  }

  const done = Math.min(
    rebuild.total,
    Math.floor(((elapsed - QUEUED_FOR) / WRITING_FOR) * rebuild.total),
  )

  if (done < rebuild.total) {
    return {
      ...idle(),
      state: 'running',
      done,
      total: rebuild.total,
      queued_at: queuedAt,
      started_at: startedAt,
    }
  }

  rebuilt = true

  return {
    ...idle(),
    state: 'done',
    done: products,
    total: products,
    queued_at: queuedAt,
    started_at: startedAt,
    finished_at: new Date(rebuild.queuedAt + QUEUED_FOR + WRITING_FOR).toISOString(),
  }
}

function idle() {
  return {
    state: 'idle',
    done: 0,
    total: 0,
    queued_at: null as string | null,
    started_at: null as string | null,
    finished_at: null as string | null,
    error: null,
    stalled: false,
  }
}

export function registerManticore(on: On, fail: Fail, productCount: () => number): void {
  on('GET', '/search-index', () => {
    const products = productCount()
    const state = progress(products)
    const filling = state.state === 'queued' || state.state === 'running'
    const tables = available
      ? [
          {
            locale: 'ru',
            table: `${PREFIX}_catalog_products_ru`,
            state: rebuilt ? 'ready' : 'stale',
            reason: rebuilt ? null : 'the column [codes] is missing',
            documents: products,
            rebuilding: filling,
            filled: filling ? state.done : null,
          },
          {
            locale: 'en',
            table: `${PREFIX}_catalog_products_en`,
            state: 'ready',
            reason: null,
            documents: rebuilt ? products : products - 3,
            rebuilding: filling,
            filled: filling ? state.done : null,
          },
        ]
      : []

    return {
      data: {
        connection: {
          address: '127.0.0.1:9308',
          prefix: PREFIX,
          version: available ? '29.0.2 2a8be5c75@25072210' : null,
          available,
          error: available
            ? null
            : 'cURL error 7: Failed to connect to 127.0.0.1 port 9308: Connection refused',
        },
        products,
        tables,
        queue: rebuilt ? { waiting: 0, oldest: null } : { waiting: 3, oldest: minutesAgo(4) },
        rebuild: state,
        outdated: tables.some((table) => table.state !== 'ready'),
        // The panel's own rebuild holds the shared lock too; the page tells the console's apart.
        locked: filling,
      },
    }
  })

  on('POST', '/search-index/rebuild', () => {
    const state = progress(productCount())

    if (state.state === 'queued' || state.state === 'running') {
      throw fail(409, 'A rebuild is already under way.')
    }

    rebuild = { queuedAt: Date.now(), total: productCount() }

    return { data: progress(productCount()) }
  })

  on('POST', '/search-index/_outage', () => {
    available = !available

    return { data: { available } }
  })
}
