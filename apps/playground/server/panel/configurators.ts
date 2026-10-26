import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import {
  chosenOptions,
  float,
  price,
  resolve,
  select,
  set,
  today,
} from '../../../../packages/module-configurator/src/engine/engine'
import { validate } from '../../../../packages/module-configurator/src/engine/validator'
import type { ConfiguratorSchema } from '../../../../packages/module-configurator/src/engine/types'
import type { ConfiguratorChoice } from '../../../../packages/module-configurator/src/types'
import { forms } from './inbox'

/*
 * «Configurators» (WEBX_UI_MODULE_CONFIGURATOR.md) the way §13.3 of the spec fixes the panel's
 * API: a draft beside what the site prices by, a revision and its 409, publications as versions,
 * a check and a price. The demo is the package's own — `resources/demo/shower.json`, read off disk
 * like the dictionaries are, with its `demo:<name>` pictures pointing at the library's folder of
 * the same drawings — so the panel here edits exactly what `webx:demo --module=configurators`
 * installs.
 *
 * The check and the price are the npm package's engine: on a site the server's PHP gives them,
 * and the parity cases keep the two the same.
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
type Fail = (
  status: number,
  message: string,
  data?: unknown,
  errors?: Record<string, string[]>,
  extra?: Record<string, unknown>,
) => Error
type Line = (locale: string, namespace: string, path: string) => string

type Localized = Record<string, string>

interface Drafted {
  title: Localized
  schema: ConfiguratorSchema
  form: string | null
}

interface Version {
  number: number
  created_at: string
  author: string | null
  source: string
  payload: Drafted
}

interface Configurator {
  id: number
  key: string
  /** What the site prices by — null until the first publication, and while it is off the site. */
  live: Drafted | null
  /** What is being edited, when it differs from `live`. */
  draft: Drafted | null
  published_at: string | null
  updated_at: string
  deleted_at: string | null
  versions: Version[]
  /** Counts saves: the revision an editor holds. */
  saves: number
}

/** The language a name needs words in, and the one the check asks for: the demo site's. */
const DEFAULT = 'ru'
const STAMP = '2026-10-20T09:00:00+00:00'
const NAMESPACE = 'webx-configurator'

const demo = JSON.parse(
  readFileSync(
    fileURLToPath(
      new URL(
        '../../../../php/packages/module-configurator/resources/demo/shower.json',
        import.meta.url,
      ),
    ),
    'utf8',
  ),
) as { key: string; title: Localized; schema: ConfiguratorSchema }

/** `demo:corner-slide` → the key of the drawing in the library's folder «Configurators». */
function pictures<T>(value: T): T {
  if (typeof value === 'string' && value.startsWith('demo:')) {
    return `configurators/${value.slice('demo:'.length)}.svg` as T
  }

  if (Array.isArray(value)) return value.map(pictures) as T

  if (value && typeof value === 'object') {
    return Object.fromEntries(Object.entries(value).map(([key, one]) => [key, pictures(one)])) as T
  }

  return value
}

const copy = <T>(value: T): T => JSON.parse(JSON.stringify(value)) as T

const shower: Drafted = {
  title: demo.title,
  schema: pictures(demo.schema),
  form: forms.some((form) => form.slug === 'callback') ? 'callback' : (forms[0]?.slug ?? null),
}

export const configurators: Configurator[] = [
  {
    id: 1,
    key: demo.key,
    live: copy(shower),
    draft: null,
    published_at: STAMP,
    updated_at: STAMP,
    deleted_at: null,
    versions: [
      { number: 1, created_at: STAMP, author: 'Анна', source: 'panel', payload: copy(shower) },
    ],
    saves: 1,
  },
  /* Half built: a draft never published, with the problems a half-built one has. */
  {
    id: 2,
    key: 'glass-doors',
    live: null,
    draft: {
      title: { ru: 'Стеклянные двери', en: 'Glass doors' },
      schema: {
        version: 1,
        currency: 'UAH',
        pricing: { round: 10 },
        steps: [
          {
            key: 'door',
            type: 'buttons',
            label: { ru: 'Дверь', en: 'Door' },
            options: [
              {
                value: 'single',
                label: { ru: 'Одностворчатая', en: 'Single' },
                price: { fixed: 6500 },
              },
              {
                value: 'double',
                label: { ru: '', en: 'Double' },
                price: { fixed: 11800, perM2: 900 },
              },
            ],
          },
        ],
      },
      form: null,
    },
    published_at: null,
    updated_at: '2026-10-25T15:30:00+00:00',
    deleted_at: null,
    versions: [],
    saves: 1,
  },
]

let nextId = 3

function editing(record: Configurator): Drafted {
  return record.draft ?? record.live ?? { title: {}, schema: skeleton(), form: null }
}

function skeleton(): ConfiguratorSchema {
  return { version: 1, currency: 'UAH', pricing: { round: 1 }, steps: [] }
}

function revision(record: Configurator): string {
  return `r${record.id}-${record.saves}`
}

function status(record: Configurator): string {
  if (record.live === null) return record.versions.length > 0 ? 'unpublished' : 'draft'

  return record.draft !== null ? 'modified' : 'published'
}

function pick(text: Localized | undefined, locale: string): string {
  return text?.[locale] || text?.[DEFAULT] || Object.values(text ?? {}).find((one) => one) || ''
}

function row(record: Configurator, locale: string) {
  const shown = editing(record)

  return {
    id: record.id,
    key: record.key,
    title: pick(shown.title, locale),
    status: status(record),
    form: shown.form,
    steps: shown.schema.steps?.length ?? 0,
    currency: shown.schema.currency ?? null,
    key_locked: record.versions.length > 0,
    published_at: record.live === null ? null : record.published_at,
    updated_at: record.updated_at,
    ...(record.deleted_at === null ? {} : { deleted_at: record.deleted_at }),
  }
}

export function registerConfigurators(on: On, fail: Fail, line: Line): void {
  const words = (locale: string, path: string, params: Record<string, string | number> = {}) =>
    Object.entries(params).reduce(
      (text, [name, value]) => text.replaceAll(`:${name}`, String(value)),
      line(locale, NAMESPACE, path),
    )

  const problems = (schema: unknown, locale: string) =>
    validate(schema, DEFAULT).map((problem) => ({
      path: problem.path,
      code: problem.code,
      message: words(locale, `errors.${problem.code}`, problem.params),
    }))

  const describe = (record: Configurator, locale: string) => ({
    configurator: row(record, locale),
    values: { key: record.key, ...copy(editing(record)) },
    revision: revision(record),
    errors: problems(editing(record).schema, locale),
    has_inbox: true,
  })

  const find = (id: string, trashed = false): Configurator => {
    const record = configurators.find((one) => one.id === Number(id))

    if (!record || (!trashed && record.deleted_at !== null)) throw fail(404, 'Not found.')

    return record
  }

  const refuse = (locale: string, field: string, reason: string) => {
    const message = words(locale, `configurator.refused.${reason}`)

    return fail(422, message, undefined, { [field]: [message] })
  }

  const checkKey = (key: unknown, locale: string, except?: Configurator) => {
    if (typeof key !== 'string' || !/^[a-z0-9][a-z0-9-]{0,63}$/.test(key)) {
      throw refuse(locale, 'key', 'key.invalid')
    }

    if (configurators.some((one) => one !== except && one.key === key)) {
      throw refuse(locale, 'key', 'key.taken')
    }
  }

  const touch = (record: Configurator) => {
    record.saves++
    record.updated_at = new Date().toISOString()
  }

  on('GET', '/configurators', ({ query, locale }) => {
    const trashed = query.get('trashed') === '1'
    const term = (query.get('search') ?? '').trim().toLowerCase()

    return {
      data: configurators
        .filter((one) => (one.deleted_at !== null) === trashed)
        .map((one) => row(one, locale))
        .filter(
          (one) => term === '' || one.title.toLowerCase().includes(term) || one.key.includes(term),
        ),
    }
  })

  on('POST', '/configurators', ({ body, locale }) => {
    const title = typeof body.title === 'string' ? body.title.trim() : ''

    checkKey(body.key, locale)

    if (title === '') throw refuse(locale, 'title', 'title.missing')

    const record: Configurator = {
      id: nextId++,
      key: body.key as string,
      live: null,
      draft: { title: { [DEFAULT]: title }, schema: skeleton(), form: null },
      published_at: null,
      updated_at: new Date().toISOString(),
      deleted_at: null,
      versions: [],
      saves: 1,
    }

    configurators.push(record)

    return { data: describe(record, locale) }
  })

  on('POST', '/configurators/validate', ({ body, locale }) => {
    const found = problems(body.schema, locale)

    return { data: { valid: found.length === 0, errors: found } }
  })

  on('GET', '/configurators/(\\d+)', ({ params, locale }) => ({
    data: describe(find(params[0]!), locale),
  }))

  on('PUT', '/configurators/(\\d+)', ({ params, body, locale }) => {
    const record = find(params[0]!)

    if (typeof body.revision === 'string' && body.revision !== revision(record)) {
      throw fail(409, words(locale, 'configurator.conflict'), describe(record, locale), undefined, {
        changed: { author: 'Анна', author_id: 2, source: 'panel', at: record.updated_at },
      })
    }

    const values = (body.values ?? {}) as Partial<Drafted & { key: string }>
    const next = copy(editing(record))

    if (values.key !== undefined && values.key !== record.key) {
      if (record.versions.length > 0) throw refuse(locale, 'key', 'key.locked')

      checkKey(values.key, locale, record)
      record.key = values.key
    }

    if (values.title !== undefined) {
      const title = Object.fromEntries(
        Object.entries(values.title ?? {}).filter(
          ([, text]) => typeof text === 'string' && text.trim() !== '',
        ),
      )

      if (!title[DEFAULT]) throw refuse(locale, 'title', 'title.missing')

      next.title = title
    }

    if (values.schema !== undefined) {
      if (
        typeof values.schema !== 'object' ||
        values.schema === null ||
        Array.isArray(values.schema)
      ) {
        throw fail(422, words(locale, 'errors.schema.invalid'), undefined, {
          schema: [words(locale, 'errors.schema.invalid')],
        })
      }

      next.schema = values.schema
    }

    if (values.form !== undefined) {
      if (values.form !== null && !forms.some((form) => form.slug === values.form)) {
        throw refuse(locale, 'form', 'form.unknown')
      }

      next.form = values.form
    }

    record.draft = next
    touch(record)

    return { data: describe(record, locale) }
  })

  on('DELETE', '/configurators/(\\d+)', ({ params }) => {
    find(params[0]!).deleted_at = new Date().toISOString()

    return { data: null }
  })

  on('POST', '/configurators/(\\d+)/restore', ({ params, locale }) => {
    const record = find(params[0]!, true)

    record.deleted_at = null

    return { data: row(record, locale) }
  })

  on('POST', '/configurators/(\\d+)/publish', ({ params, body, locale }) => {
    const record = find(params[0]!)

    if (typeof body.revision === 'string' && body.revision !== revision(record)) {
      throw fail(409, words(locale, 'configurator.conflict'), undefined, undefined, {
        revision: revision(record),
        changed: { author: 'Анна', author_id: 2, source: 'panel', at: record.updated_at },
      })
    }

    const draft = editing(record)
    const found = problems(draft.schema, locale)

    if (found.length > 0) {
      const message = words(locale, 'configurator.publish-refused', { count: found.length })

      throw fail(422, message, undefined, { schema: [message] }, { problems: found })
    }

    const stamp = new Date().toISOString()

    record.live = copy(draft)
    record.draft = null
    record.published_at = stamp
    record.versions.unshift({
      number: (record.versions[0]?.number ?? 0) + 1,
      created_at: stamp,
      author: null,
      source: 'panel',
      payload: copy(draft),
    })
    touch(record)

    return { data: describe(record, locale) }
  })

  on('POST', '/configurators/(\\d+)/unpublish', ({ params, locale }) => {
    const record = find(params[0]!)

    if (record.live !== null) {
      record.draft = record.draft ?? copy(record.live)
      record.live = null
      touch(record)
    }

    return { data: describe(record, locale) }
  })

  on('POST', '/configurators/(\\d+)/discard', ({ params, locale }) => {
    const record = find(params[0]!)

    if (record.live !== null && record.draft !== null) {
      record.draft = null
      touch(record)
    }

    return { data: describe(record, locale) }
  })

  on('GET', '/configurators/(\\d+)/versions', ({ params }) => {
    const record = find(params[0]!)
    const live = record.live === null ? null : (record.versions[0]?.number ?? null)

    return {
      data: record.versions.map((version) => ({
        number: version.number,
        created_at: version.created_at,
        author: version.author,
        source: version.source,
        comment: null,
        on_site: version.number === live,
      })),
    }
  })

  on('POST', '/configurators/(\\d+)/versions/(\\d+)/restore', ({ params, locale }) => {
    const record = find(params[0]!)
    const version = record.versions.find((one) => one.number === Number(params[1]))

    if (!version) throw fail(404, 'Not found.')

    record.draft = copy(version.payload)
    touch(record)

    return { data: describe(record, locale) }
  })

  /*
   * The editor's heartbeat (`EditedRecords`): the revision, and nobody else here. The copies of
   * the draft between publications are not kept by this fixture.
   */
  on('POST', '/editing/configurators/(\\d+)', ({ params }) => {
    const record = find(params[0]!, true)

    return {
      data: {
        revision: revision(record),
        changed: null,
        editors: [],
        heartbeat: 30,
      },
    }
  })

  on('GET', '/editing/configurators/(\\d+)/drafts', () => ({ data: [] }))

  /* The server's price (§7.4): the same engine, each line in words, as `Breakdown` writes them. */
  on('POST', '/configurators/(\\d+)/quote', ({ params, body, locale }) => {
    const record = find(params[0]!)
    const schema = body.draft === true ? editing(record).schema : record.live?.schema

    if (!schema) throw fail(422, words(locale, 'configurator.not-live'))

    const found = problems(schema, locale)

    if (found.length > 0) return { data: { errors: found } }

    let state = resolve(schema, (body.values ?? {}) as Record<string, unknown>)

    if (state.errors.length > 0) return { data: { errors: state.errors } }

    for (const action of (body.actions ?? []) as Record<string, [string, unknown]>[]) {
      if (action.select) state = select(schema, state, action.select[0], String(action.select[1]))
      else if (action.set) state = set(schema, state, action.set[0], float(action.set[1]) ?? 0)
    }

    const quote = price(schema, state, typeof body.today === 'string' ? body.today : today())
    const label = (text: unknown) =>
      typeof text === 'string' ? text : pick(text as Localized | undefined, locale)

    return {
      data: {
        ...quote,
        lines: quote.lines.map((one) => ({ ...one, line: `${one.kind}: ${one.amount}` })),
        selection: chosenOptions(schema, state).map(([step, option]) => ({
          step,
          label: label(schema.steps.find((one) => one.key === step)?.label),
          value: String(option.value),
          option: label(option.label),
        })),
      },
    }
  })
}

/**
 * What a block may start a configurator from (`ConfiguratorTarget::choices()`): the steps of the
 * published schema with their values, the sizes with their limits.
 */
function choices(schema: ConfiguratorSchema | null, locale: string): ConfiguratorChoice[] {
  if (schema === null) return []
  const initial = (schema.initial ?? {}) as Record<string, unknown>
  // A text of the document is a string (the default language) or a map of languages.
  const say = (text: unknown): string =>
    typeof text === 'string' ? text : pick(text as Localized | undefined, locale)

  return (schema.steps ?? []).flatMap((step): ConfiguratorChoice[] => {
    const title = say(step.label) || say(step.title) || step.key

    if (step.type !== 'dimensions') {
      return [
        {
          key: step.key,
          kind: 'choice',
          label: title,
          initial: typeof initial[step.key] === 'string' ? (initial[step.key] as string) : null,
          options: (step.options ?? []).map((option) => ({
            value: option.value,
            label: say(option.label) || option.value,
          })),
        },
      ]
    }

    return (step.fields ?? []).map((field) => ({
      key: field.key,
      kind: 'size',
      label: say(field.label) || field.key,
      unit: say(field.unit),
      min: field.min ?? 0,
      max: field.max ?? 0,
      step: field.step ?? 1,
      initial: typeof initial[field.key] === 'number' ? (initial[field.key] as number) : null,
    }))
  })
}

/**
 * The relation target `configurator` (`ConfiguratorTarget`): the name, the key under it and as
 * `key` — what `wx-configurator` keeps; one off the site is marked, not dropped — and the choices
 * of `wx-configurator-preset`.
 */
export function configuratorCandidates(locale: string) {
  return configurators.map((record) => ({
    candidate: {
      id: record.id,
      title: pick(editing(record).title, locale) || record.key,
      subtitle: record.key,
      key: record.key,
      thumb: null,
      visible: record.live !== null,
      choices: choices(record.live?.schema ?? null, locale),
    },
    deleted: record.deleted_at !== null,
  }))
}
