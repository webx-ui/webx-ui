/*
 * «System → Cache» (WEBX_UI_CACHE.md §5) without a cache behind it: the groups a site built of
 * the demo's modules has, each with a version that moves when «Reset» is pressed, and the
 * journal of the presses — what the real page draws from `GET /cache` and `history/cache/0`.
 *
 * The words come out of the PHP packages' dictionaries, the way the real API translates them.
 */

type On = (
  method: string,
  pattern: string,
  handler: (context: {
    params: string[]
    query: URLSearchParams
    body: Record<string, unknown>
    locale: string
  }) => unknown,
) => void
type Fail = (status: number, message: string, errors?: Record<string, string[]>) => Error
type Line = (locale: string, namespace: string, path: string) => string

interface Group {
  id: string
  /** `[namespace, key]` in the dictionaries, or the words themselves. */
  title: [string, string] | string
  version: number
  models: string[]
}

interface Reset {
  id: number
  groups: string
  at: string
}

const groups: Group[] = [
  {
    id: 'locales',
    title: ['webx-admin', 'cache.group-locales'],
    version: 4120033001,
    models: ['Locale'],
  },
  {
    id: 'routes',
    title: ['webx-admin', 'cache.group-routes'],
    version: 6633100120,
    models: ['Route'],
  },
  { id: 'screens', title: ['webx-admin', 'cache.group-screens'], version: 2800411002, models: [] },
  { id: 'theme', title: ['webx-admin', 'cache.group-theme'], version: 5100002000, models: [] },
  {
    id: 'settings',
    title: ['webx-settings', 'module.title'],
    version: 3344120011,
    models: ['Setting'],
  },
  {
    id: 'menu',
    title: ['webx-menu', 'module.title'],
    version: 7731000210,
    models: ['Menu', 'MenuItem'],
  },
  {
    id: 'regions',
    title: ['webx-blocks', 'module.regions'],
    version: 1908802201,
    models: ['Region'],
  },
  {
    id: 'blocks',
    title: ['webx-blocks', 'module.title'],
    version: 8120034400,
    models: ['Block', 'BlockVersion'],
  },
  {
    id: 'seo',
    title: 'SEO',
    version: 4455600123,
    models: ['SeoUrl', 'SeoRedirect', 'SeoLinkBlock', 'SeoLinkItem'],
  },
  { id: 'sitemap', title: 'Sitemap', version: 2211009090, models: [] },
  {
    id: 'snippets',
    title: ['webx-snippets', 'module.title'],
    version: 6090012003,
    models: ['Snippet'],
  },
  {
    id: 'banners',
    title: ['webx-banners', 'module.banners'],
    version: 3030300030,
    models: ['Banner', 'Place'],
  },
  { id: 'catalog', title: ['webx-catalog', 'module.title'], version: 9001200340, models: [] },
]

const resets: Reset[] = [
  { id: 1, groups: 'menu', at: new Date(Date.now() - 3 * 86400_000).toISOString() },
]

export function registerCache(on: On, fail: Fail, line: Line): void {
  const report = (locale: string) => ({
    driver: 'file',
    store: 'file',
    enabled: true,
    ttl: 86400,
    groups: groups.map((group) => ({
      id: group.id,
      title: Array.isArray(group.title)
        ? line(locale, group.title[0], group.title[1])
        : group.title,
      version: group.version,
      models: group.models,
    })),
  })

  on('GET', '/cache', ({ locale }) => ({ data: report(locale) }))

  on('POST', '/cache/reset', ({ body, locale }) => {
    const asked =
      typeof body.group === 'string' && body.group.trim() !== '' ? body.group.trim() : null
    const moved = asked === null ? groups : groups.filter((group) => group.id === asked)

    if (moved.length === 0) {
      throw fail(422, `There is no cache group [${asked}].`, {
        group: [
          `There is no cache group [${asked}]; the groups are: ${groups.map((g) => g.id).join(', ')}.`,
        ],
      })
    }

    for (const group of moved) group.version += 1

    resets.unshift({
      id: resets.length + 1,
      groups: asked ?? '*',
      at: new Date().toISOString(),
    })

    return { data: { reset: moved.map((group) => group.id), ...report(locale) } }
  })

  /* The journal of the presses, in the shape `history/{type}/{id}` answers. */
  on('GET', '/history/cache/0', () => ({
    data: resets.map((reset) => ({
      id: reset.id,
      event: 'updated',
      source: 'panel',
      subject: { type: 'cache', id: 0 },
      admin: { id: 1, name: 'Анна Ковальчук' },
      grant_id: null,
      changes: [{ field: 'groups', label: 'Groups', from: null, to: reset.groups }],
      run: null,
      created_at: reset.at,
    })),
    current_page: 1,
    last_page: 1,
    total: resets.length,
  }))
}
