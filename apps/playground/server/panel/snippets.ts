import type { HistoryEntry, HistoryPage } from '../../../../packages/module-admin/src/history'
import type { Patch } from '../../../../packages/schema/src/types'

/**
 * The codes of outside services (`module-snippets`): every place with its codes, the bin, the
 * order of a place, and the editor's values — answered the way `SnippetController` answers.
 *
 * The places are a site's: the three built-in ones, one of its own from `config/webx-snippets.php`
 * that no page has printed yet, and one taken out of the config with a code still in it (§4.3).
 * The site runs as `staging`, so the list says that only the codes marked "on dev too" are
 * printed — the line a copy of a site shows.
 *
 * The codes are made-up stand-ins: no real ids of any service, no real addresses.
 */

export type Consent = 'necessary' | 'statistics' | 'marketing' | 'media'

export interface Snippet {
  id: number
  name: string
  place: string
  code: string
  consent: Consent
  only_paths: string[]
  except_paths: string[]
  locales: string[]
  on_stage: boolean
  enabled: boolean
  position: number
  updated_at: string
  deleted_at: string | null
}

interface Place {
  key: string
  label: string
  built_in: boolean
}

/** Built-in first, then the site's own — the order of `Places::all()`. */
const PLACES: Place[] = [
  { key: 'head', label: 'Head, as high as possible', built_in: true },
  { key: 'body_start', label: 'Start of the body', built_in: true },
  { key: 'body_end', label: 'End of the body', built_in: true },
  { key: 'before-footer', label: 'Before the footer', built_in: false },
]

/* The day a page last printed the place (`Seen`); the site's own place has not been seen yet. */
const SEEN: Record<string, string> = {
  head: '2026-10-16',
  body_start: '2026-10-16',
  body_end: '2026-10-15',
}

const ENVIRONMENT = 'staging'
const CONSENTS: Consent[] = ['necessary', 'statistics', 'marketing', 'media']
const LIMIT = 65536
const STAMP = '2026-10-14T10:30:00+00:00'

export const snippets: Snippet[] = [
  snippet(1, 'Tag manager', 'head', 'statistics', [
    '<!-- Tag manager: the container id is a placeholder. -->',
    '<script>',
    '  window.dataLayer = window.dataLayer || [];',
    "  window.dataLayer.push({ event: 'demo.start', container: 'DEMO-CONTAINER' });",
    '</script>',
  ]),
  snippet(2, 'Site ownership check', 'head', 'necessary', [
    '<meta name="site-verification" content="demo-verification-token">',
  ]),
  snippet(3, 'Tag manager without JavaScript', 'body_start', 'statistics', [
    '<noscript>',
    '  <img src="https://tags.example.test/ns.gif?id=DEMO-CONTAINER" alt="" width="1" height="1">',
    '</noscript>',
  ]),
  snippet(
    4,
    'Online chat',
    'body_end',
    'marketing',
    [
      '<script src="https://chat.example.test/widget.js" async></script>',
      '<script>',
      "  window.demoChat = { account: 'demo-account', position: 'right' };",
      '</script>',
    ],
    { only_paths: ['/contacts', '/services/*'], locales: ['ru'] },
  ),
  snippet(
    5,
    'Ads conversion pixel',
    'body_end',
    'marketing',
    ['<img src="https://ads.example.test/pixel.gif?account=DEMO" alt="" width="1" height="1">'],
    { except_paths: ['/blog/*'], enabled: false },
  ),
  snippet(
    6,
    'Console line for the stand',
    'body_end',
    'necessary',
    [
      '<script>',
      "  console.info('This page is printed with the codes of the site.');",
      '</script>',
    ],
    { on_stage: true },
  ),
  /* Pasted whole, with the document's own tags: saved, and the editor says what to look at. */
  snippet(
    7,
    'Newsletter form',
    'before-footer',
    'necessary',
    [
      '<html><body>',
      '<form action="https://mail.example.test/subscribe" method="post">',
      '  <input type="email" name="email" placeholder="you@example.test">',
      '  <button>Subscribe</button>',
      '</form>',
      '</body></html>',
    ],
    { enabled: false },
  ),
  /* A place taken out of the config: kept, printed nowhere, switched off. */
  snippet(
    8,
    'Autumn promo script',
    'promo',
    'marketing',
    ['<script src="/js/promo.js"></script>'],
    {
      enabled: false,
    },
  ),
  snippet(9, 'Old counter', 'body_end', 'statistics', ['<!-- an old counter -->'], {
    enabled: false,
    deleted_at: '2026-10-10T08:15:00+00:00',
  }),
]

function snippet(
  id: number,
  name: string,
  place: string,
  consent: Consent,
  lines: string[],
  extra: Partial<Snippet> = {},
): Snippet {
  return {
    id,
    name,
    place,
    code: lines.join('\n'),
    consent,
    only_paths: [],
    except_paths: [],
    locales: [],
    on_stage: false,
    enabled: true,
    position: id,
    updated_at: STAMP,
    deleted_at: null,
    ...extra,
  }
}

const declared = (key: string): boolean => PLACES.some((place) => place.key === key)

export function findSnippet(id: number): Snippet | null {
  return snippets.find((one) => one.id === id) ?? null
}

/** `SnippetForm::row()`. */
export function snippetRow(record: Snippet): Record<string, unknown> {
  return {
    id: record.id,
    name: record.name,
    place: record.place,
    place_declared: declared(record.place),
    consent: record.consent,
    only_paths: record.only_paths,
    except_paths: record.except_paths,
    locales: record.locales,
    on_stage: record.on_stage,
    enabled: record.enabled,
    position: record.position,
    bytes: new TextEncoder().encode(record.code).length,
    updated_at: record.updated_at,
    deleted_at: record.deleted_at,
  }
}

/** `SnippetValues::warnings()`: the tags of a whole document — a warning, not a refusal (§7.4). */
function warningsOf(code: string): string[] {
  const tags = [...new Set([...code.matchAll(/<\/?(html|head|body)\b/gi)].map((m) => m[1]!))]

  return tags.length === 0
    ? []
    : [
        `The code has ${tags.map((tag) => `<${tag.toLowerCase()}>`).join(', ')}: paste only what the service asks to put inside the page.`,
      ]
}

/** `SnippetForm::describe()`. */
export function snippetDetail(record: Snippet): Record<string, unknown> {
  return {
    snippet: snippetRow(record),
    values: {
      name: record.name,
      place: record.place,
      code: record.code,
      consent: record.consent,
      only_paths: record.only_paths,
      except_paths: record.except_paths,
      locales: record.locales,
      on_stage: record.on_stage,
      enabled: record.enabled,
    },
    warnings: warningsOf(record.code),
  }
}

/** `GET /snippets` — every declared place, then the ones nobody declares that still hold codes. */
export function listSnippets(trashed: boolean): Record<string, unknown> {
  if (trashed) {
    return {
      data: snippets
        .filter((one) => one.deleted_at !== null)
        .sort((a, b) => (b.deleted_at ?? '').localeCompare(a.deleted_at ?? ''))
        .map(snippetRow),
    }
  }

  const live = snippets
    .filter((one) => one.deleted_at === null)
    .sort((a, b) => a.position - b.position || a.id - b.id)
  const group = (key: string, label: string, builtIn: boolean, isDeclared: boolean) => ({
    key,
    label,
    built_in: builtIn,
    declared: isDeclared,
    last_seen: isDeclared ? (SEEN[key] ?? null) : null,
    snippets: live.filter((one) => one.place === key).map(snippetRow),
  })
  const orphans = [...new Set(live.map((one) => one.place))].filter((key) => !declared(key))

  return {
    data: [
      ...PLACES.map((place) => group(place.key, place.label, place.built_in, true)),
      ...orphans.map((key) => group(key, key, false, false)),
    ],
    environment: ENVIRONMENT,
    everything_printed: false,
  }
}

const FIELDS = [
  'name',
  'place',
  'code',
  'consent',
  'only_paths',
  'except_paths',
  'locales',
  'on_stage',
  'enabled',
] as const

const strings = (value: unknown): string[] =>
  Array.isArray(value) ? value.map((one) => String(one).trim()).filter((one) => one !== '') : []

/**
 * `SnippetValues::check()` and a save: a field that did not come is left as it is, and a refusal
 * is under the name of the field. A new code is off until switched on.
 */
export function writeSnippet(
  record: Snippet | null,
  input: Record<string, unknown>,
): { record: Snippet } | { errors: Record<string, string[]> } {
  const creating = record === null
  const errors: Record<string, string[]> = {}
  const values: Partial<Snippet> = {}

  for (const field of ['name', 'place', 'code'] as const) {
    if (creating && (typeof input[field] !== 'string' || (input[field] as string).trim() === '')) {
      errors[field] = [`The field "${field}" cannot be empty.`]
    }
  }

  for (const field of FIELDS) {
    if (!(field in input) || errors[field]) continue

    const value = input[field]

    if (field === 'name' || field === 'place' || field === 'code') {
      values[field] = String(value ?? '').trim() === '' ? '' : String(value)
      if (values[field] === '') errors[field] = [`The field "${field}" cannot be empty.`]
    } else if (field === 'consent') {
      if (CONSENTS.includes(value as Consent)) values.consent = value as Consent
      else errors.consent = [`The consent category is one of: ${CONSENTS.join(', ')}.`]
    } else if (field === 'on_stage' || field === 'enabled') {
      values[field] = value === true
    } else {
      values[field] = strings(value)
    }
  }

  if (values.code !== undefined && new TextEncoder().encode(values.code).length > LIMIT) {
    errors.code = [`The code is longer than ${LIMIT} bytes.`]
  }

  for (const mask of [...(values.only_paths ?? []), ...(values.except_paths ?? [])]) {
    if (!mask.startsWith('/') || mask.includes('?')) {
      errors[values.only_paths?.includes(mask) ? 'only_paths' : 'except_paths'] = [
        `A path starts from the root, like "/blog/*", without the address of the site or "?": [${mask}] is not one.`,
      ]
    }
  }

  const place = values.place ?? record?.place ?? ''
  const enabled = values.enabled ?? record?.enabled ?? false

  if (Object.keys(errors).length === 0 && !declared(place)) {
    const moved = values.place !== undefined && values.place !== record?.place

    if (creating || moved) {
      errors.place = [`The place "${place}" is not declared in config/webx-snippets.php.`]
    } else if (enabled) {
      errors.place = [
        `The place "${place}" is no longer declared, so the code would be printed nowhere: move it to another place or switch it off.`,
      ]
    }
  }

  if (Object.keys(errors).length > 0) return { errors }

  const target =
    record ??
    snippet(Math.max(0, ...snippets.map((one) => one.id)) + 1, '', place, 'necessary', [], {
      enabled: false,
    })

  // Moved into another place: the end of it, the way a drag that drops it last would.
  if (values.place !== undefined && values.place !== target.place) {
    target.position = Math.max(-1, ...siblings(values.place).map((one) => one.position)) + 1
  }

  if (record === null) {
    target.position = Math.max(-1, ...siblings(place).map((one) => one.position)) + 1
    snippets.push(target)
  }

  Object.assign(target, values, { updated_at: new Date().toISOString() })

  return { record: target }
}

/** Gone for good — only from the bin. */
export function purgeSnippet(record: Snippet): void {
  snippets.splice(snippets.indexOf(record), 1)
}

function siblings(place: string): Snippet[] {
  return snippets.filter((one) => one.place === place && one.deleted_at === null)
}

/** `SnippetOrder::reorder()`: the whole place, from zero; a code dragged in moves with it. */
export function reorderSnippets(place: unknown, ids: unknown): Record<string, string[]> | null {
  if (typeof place !== 'string' || !Array.isArray(ids)) {
    return { ids: ['The order is a place and the list of its codes.'] }
  }

  if (!declared(place)) {
    return { place: [`The place "${place}" is not declared in config/webx-snippets.php.`] }
  }

  ids.map(Number).forEach((id, position) => {
    const record = findSnippet(id)

    if (record !== null) {
      record.place = place
      record.position = position
    }
  })

  return null
}

/**
 * The places and the languages laid over `snippets.form`, as the provider lays them on the
 * server: both come from the config and the site, never from the JSON.
 */
export const snippetsFormPatches: Patch[] = [
  [
    {
      op: 'set',
      target: 'place',
      props: { options: PLACES.map((place) => ({ value: place.key, label: place.label })) },
    },
    {
      op: 'set',
      target: 'locales',
      props: { options: ['ru', 'en'].map((code) => ({ value: code, label: code })) },
    },
  ],
]

/**
 * `GET /history/snippets/{id}` — the journal keeps the whole code before and after (decision 4 of
 * the spec), so the tag manager has a save of its code by an agent and a switch in the panel.
 * Every other code has no past yet.
 */
export function snippetHistory(type: string, id: number): HistoryPage | null {
  if (type !== 'snippets') return null

  const entries: HistoryEntry[] =
    id !== 1
      ? []
      : [
          {
            id: 7302,
            event: 'updated',
            source: 'mcp',
            subject: { type, id },
            admin: { id: 1, name: 'Анна Ковальчук' },
            grant_id: 3,
            changes: [
              {
                field: 'code',
                label: 'Code',
                from: '<script>\n  window.dataLayer = window.dataLayer || [];\n</script>',
                to: snippets[0]!.code,
              },
            ],
            run: null,
            created_at: '2026-10-14T10:30:00+00:00',
          },
          {
            id: 7301,
            event: 'updated',
            source: 'panel',
            subject: { type, id },
            admin: { id: 1, name: 'Анна Ковальчук' },
            grant_id: null,
            changes: [{ field: 'enabled', label: 'Enabled', from: false, to: true }],
            run: null,
            created_at: '2026-10-13T16:05:00+00:00',
          },
        ]

  return { data: entries, current_page: 1, last_page: 1, total: entries.length }
}
