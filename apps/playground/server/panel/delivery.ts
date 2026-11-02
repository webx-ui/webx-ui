import { readFileSync } from 'node:fs'
import { Reply } from './reply'

/**
 * Delivery (`module-delivery`, spec §14): the section of the panel on fixtures — methods in the
 * checkout's order, the shop's own points, one carrier with its three directories, the shared
 * geography kept by hand (decision 42), the popular towns (decision 40) and the binding queue.
 *
 * The answers have the shapes of the PHP controllers: a page of a table is Laravel's
 * `->paginate()`, a refusal is a 422 under the field's name, a locality like an existing one is a
 * 409 with the similar ones, and a key of the carrier is never in an answer — only whether it is
 * set. Everything is fake and anonymous: the private-use country `XX`, towns «Town A», the phone
 * `+800 1234 5678`.
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
/* A field of a carrier's settings or of its waybill, as `Field` of the base draws it. */
type SettingField = {
  name: string
  type: string
  label: Localized
  options?: string | { value: string; label: string }[]
  depends?: string[]
  search?: boolean
  [key: string]: unknown
}

interface Country {
  code: string
  names: Localized | null
  active: boolean
  position: number
  leader: string | null
}

interface Region {
  id: number
  country: string
  names: Localized
  code: string | null
  /** The country's own order, dragged in the panel; the name breaks a tie. */
  position: number
}

interface Locality {
  id: number
  country: string
  region_id: number | null
  kind: string
  names: Localized
  lat: number | null
  lng: number | null
  weight: number
  active: boolean
  created_by: string
  manual_fields: string[]
}

interface Method {
  id: number
  key: string
  type: string
  titles: Localized
  descriptions: Localized
  price: number | null
  point_kind: string | null
  countries: string[]
  honours_paid_by_store: boolean
  enabled: boolean
  position: number
  localities: number[]
}

interface Point {
  id: number
  carrier: string
  kind: string
  titles: Localized
  address: Localized
  locality_id: number | null
  lat: number | null
  lng: number | null
  phone: string | null
  active: boolean
  schedule: Record<string, unknown> | null
}

interface Binding {
  id: number
  carrier: string
  external_id: string
  status: 'matched' | 'pending' | 'ignored'
  locality_id: number | null
  reason: string | null
  candidates: { locality_id: number; distance_km: number | null }[]
  record: {
    name: string
    region: string | null
    district: string | null
    kind: string
    country: string
  }
}

interface Run {
  id: number
  directory: string
  status: 'running' | 'succeeded' | 'failed' | 'held'
  trigger: string
  pages: number
  seen: number
  created: number
  updated: number
  removed: number
  queued: number
  held: number | null
  error: string | null
  started_at: string | null
  finished_at: string | null
}

const MAIN = 'ru'
const STAMP = '2026-11-01T03:30:00+00:00'

/** The ISO list a country is switched on from — a few, named by `Intl` as `symfony/intl` would. */
const ISO = [
  'XX',
  'XY',
  'AT',
  'BE',
  'CZ',
  'DE',
  'DK',
  'EE',
  'ES',
  'FI',
  'FR',
  'IT',
  'LT',
  'LV',
  'NL',
  'PL',
  'PT',
  'SE',
  'SK',
]

const TYPES = [
  { key: 'store.pickup', carrier: 'store', asks: 'point', kinds: ['store', 'pickup'] },
  { key: 'store.courier', carrier: 'store', asks: 'address', kinds: [] },
  { key: 'carrier-a.branch', carrier: 'carrier-a', asks: 'point', kinds: ['branch', 'locker'] },
  { key: 'carrier-a.door', carrier: 'carrier-a', asks: 'address', kinds: [] },
  /* `webx-ui/delivery-novaposhta`: three types named by its own dictionary (`LabelledMethodType`). */
  { key: 'novaposhta.branch', carrier: 'novaposhta', asks: 'point', kinds: ['branch'] },
  { key: 'novaposhta.locker', carrier: 'novaposhta', asks: 'point', kinds: ['locker'] },
  { key: 'novaposhta.courier', carrier: 'novaposhta', asks: 'address', kinds: [] },
]

/** The carrier of a real satellite next to carrier A: its words are its package's dictionary. */
const NOVAPOSHTA = 'novaposhta'
const NOVAPOSHTA_KINDS = ['branch', 'locker', 'courier'] as const
/* Its mark from the package's assets, as `NovaPoshtaCarrier::icon()` gives it: a data URL. */
const NOVAPOSHTA_ICON = `data:image/svg+xml;base64,${readFileSync(
  new URL(
    '../../../../php/packages/delivery-novaposhta/resources/icons/novaposhta.svg',
    import.meta.url,
  ),
).toString('base64')}`

const KINDS = ['city', 'town', 'township', 'village', 'settlement', 'other']

const state = {
  countries: [
    {
      code: 'XX',
      names: { ru: 'Демо-страна', en: 'Demo country' },
      active: true,
      position: 0,
      leader: 'carrier-a',
    },
  ] as Country[],
  regions: [
    { id: 1, country: 'XX', names: { ru: 'Регион A', en: 'Region A' }, code: 'A', position: 1 },
    { id: 2, country: 'XX', names: { ru: 'Регион B', en: 'Region B' }, code: 'B', position: 2 },
  ] as Region[],
  localities: [
    {
      id: 1,
      country: 'XX',
      region_id: 1,
      kind: 'city',
      names: { ru: 'Город A', en: 'Town A' },
      lat: 10.1,
      lng: 20.1,
      weight: 112,
      active: true,
      created_by: 'manual',
      manual_fields: [],
    },
    {
      id: 2,
      country: 'XX',
      region_id: 1,
      kind: 'town',
      names: { ru: 'Город B', en: 'Town B' },
      lat: null,
      lng: null,
      weight: 50,
      active: true,
      created_by: 'manual',
      manual_fields: [],
    },
    {
      id: 3,
      country: 'XX',
      region_id: 2,
      kind: 'town',
      names: { ru: 'О’Таун', en: 'O’Town' },
      lat: 11.2,
      lng: 21.4,
      weight: 63,
      active: true,
      created_by: 'carrier:carrier-a',
      manual_fields: ['weight'],
    },
    {
      id: 4,
      country: 'XX',
      region_id: 2,
      kind: 'village',
      names: { ru: 'Село C', en: 'Village C' },
      lat: 11.5,
      lng: 21.9,
      weight: 10,
      active: true,
      created_by: 'carrier:carrier-a',
      manual_fields: [],
    },
    {
      id: 5,
      country: 'XX',
      region_id: 2,
      kind: 'village',
      names: { ru: 'Село D', en: 'Village D' },
      lat: null,
      lng: null,
      weight: 10,
      active: false,
      created_by: 'import',
      manual_fields: [],
    },
  ] as Locality[],
  methods: [
    {
      id: 1,
      key: 'store-pickup',
      type: 'store.pickup',
      titles: { ru: 'Самовывоз из магазина', en: 'Pick-up at the store' },
      descriptions: { ru: 'Сегодня, если заказ до 15:00', en: 'Today if ordered by 15:00' },
      price: null,
      point_kind: 'store',
      countries: [],
      honours_paid_by_store: true,
      enabled: true,
      position: 0,
      localities: [],
    },
    {
      id: 2,
      key: 'store-courier',
      type: 'store.courier',
      titles: { ru: 'Курьер магазина', en: "The shop's courier" },
      descriptions: {},
      price: 25000,
      point_kind: null,
      countries: [],
      honours_paid_by_store: true,
      enabled: true,
      position: 1,
      localities: [1, 2],
    },
    {
      id: 3,
      key: 'carrier-a-branch',
      type: 'carrier-a.branch',
      titles: { ru: 'Отделение службы A', en: 'Carrier A branch' },
      descriptions: {},
      price: null,
      point_kind: 'branch',
      countries: ['XX'],
      honours_paid_by_store: false,
      enabled: false,
      position: 2,
      localities: [],
    },
    // As the migration of `delivery-novaposhta` adds them: switched off, by the carrier's rates, `UA`.
    ...NOVAPOSHTA_KINDS.map((kind, index) => ({
      id: 4 + index,
      key: `novaposhta-${kind}`,
      type: `novaposhta.${kind}`,
      titles: {},
      descriptions: {},
      price: null,
      point_kind: kind === 'courier' ? null : kind,
      countries: ['UA'],
      honours_paid_by_store: true,
      enabled: false,
      position: 3 + index,
      localities: [],
    })),
  ] as Method[],
  points: [
    {
      id: 1,
      carrier: 'store',
      kind: 'store',
      titles: { ru: 'Магазин на Примерной', en: 'Store on Example street' },
      address: { ru: 'ул. Примерная, 1', en: '1 Example street' },
      locality_id: 1,
      lat: 10.11,
      lng: 20.12,
      phone: '+800 1234 5678',
      active: true,
      schedule: {
        week: {
          mon: [['09:00', '18:00']],
          tue: [['09:00', '18:00']],
          wed: [['09:00', '18:00']],
          thu: [['09:00', '18:00']],
          fri: [['09:00', '17:00']],
          sat: [],
          sun: [],
        },
      },
    },
    {
      id: 2,
      carrier: 'store',
      kind: 'pickup',
      titles: { ru: 'Пункт выдачи B', en: 'Pick-up point B' },
      address: { ru: 'пр. Тестовый, 7', en: '7 Test avenue' },
      locality_id: 2,
      lat: null,
      lng: null,
      phone: null,
      active: true,
      schedule: null,
    },
    {
      id: 3,
      carrier: 'store',
      kind: 'store',
      titles: { ru: 'Склад (закрыт)', en: 'Warehouse (closed)' },
      address: { ru: 'ул. Примерная, 9', en: '9 Example street' },
      locality_id: 1,
      lat: null,
      lng: null,
      phone: null,
      active: false,
      schedule: null,
    },
  ] as Point[],
  popular: [1, 3, 5],
  bindings: [
    {
      id: 1,
      carrier: 'carrier-a',
      external_id: 'L1',
      status: 'matched',
      locality_id: 3,
      reason: null,
      candidates: [],
      record: { name: 'O’Town', region: 'Region B', district: null, kind: 'town', country: 'XX' },
    },
    {
      id: 2,
      carrier: 'carrier-a',
      external_id: 'L7',
      status: 'pending',
      locality_id: null,
      reason: 'ambiguous',
      candidates: [
        { locality_id: 4, distance_km: 7.4 },
        { locality_id: 5, distance_km: 12.9 },
      ],
      record: {
        name: 'Village C',
        region: 'Region B',
        district: 'District E',
        kind: 'village',
        country: 'XX',
      },
    },
    {
      id: 3,
      carrier: 'carrier-a',
      external_id: 'L8',
      status: 'pending',
      locality_id: null,
      reason: 'not_found',
      candidates: [],
      record: {
        name: 'Hamlet F',
        region: 'Region B',
        district: null,
        kind: 'village',
        country: 'XX',
      },
    },
    {
      id: 4,
      carrier: 'carrier-a',
      external_id: 'L9',
      status: 'pending',
      locality_id: null,
      reason: 'kind_mismatch',
      candidates: [{ locality_id: 2, distance_km: 31.2 }],
      record: {
        name: 'Town B',
        region: 'Region A',
        district: null,
        kind: 'township',
        country: 'XX',
      },
    },
    {
      id: 5,
      carrier: 'carrier-a',
      external_id: 'L10',
      status: 'pending',
      locality_id: null,
      reason: 'region_unknown',
      candidates: [],
      record: {
        name: 'Settlement G',
        region: 'Region Z',
        district: null,
        kind: 'settlement',
        country: 'XX',
      },
    },
  ] as Binding[],
  carrier: {
    enabled: true,
    // The sender as `FakeCarrier` gives it from «its account»: `_labels` — the labels of the chosen values.
    settings: {
      sender: 'sender-a',
      contact: 'contact-a1',
      sender_city: 'L2',
      sender_point: 'P2',
      weight: 0.5,
      _labels: {
        sender: 'Sender A',
        contact: 'Contact A1',
        sender_city: 'Town 2',
        sender_point: 'Branch 2',
      },
    } as Record<string, unknown>,
    credentials: { api_key: 'demo-secret-key' } as Record<string, string>,
  },
  /* Nova Poshta as just installed: off, no key, the settings at their defaults. Its sender comes
     with the waybill (§4.3 of its spec), its directories with stages 4–6 — none of them here yet. */
  novaposhta: {
    enabled: false,
    settings: {
      payment_method: 'Cash',
      cargo_type: 'Parcel',
      weight: 0.5,
      description: 'Товари',
    } as Record<string, unknown>,
    credentials: {} as Record<string, string>,
  },
  runs: [
    {
      id: 3,
      directory: 'streets',
      status: 'held',
      trigger: 'schedule',
      pages: 4,
      seen: 0,
      created: 0,
      updated: 0,
      removed: 0,
      queued: 0,
      held: 160,
      error: null,
      started_at: STAMP,
      finished_at: STAMP,
    },
    {
      id: 2,
      directory: 'points',
      status: 'succeeded',
      trigger: 'schedule',
      pages: 2,
      seen: 60,
      created: 0,
      updated: 60,
      removed: 1,
      queued: 0,
      held: null,
      error: null,
      started_at: STAMP,
      finished_at: STAMP,
    },
    {
      id: 1,
      directory: 'localities',
      status: 'succeeded',
      trigger: 'schedule',
      pages: 1,
      seen: 20,
      created: 0,
      updated: 20,
      removed: 0,
      queued: 4,
      held: null,
      error: null,
      started_at: STAMP,
      finished_at: STAMP,
    },
  ] as Run[],
  kinds: {} as Record<string, Localized>,
}

let next = 100

const pick = (names: Localized | null | undefined, locale: string): string =>
  names?.[locale] ?? names?.[MAIN] ?? Object.values(names ?? {})[0] ?? ''

/** `Normalizer::key()` in miniature: case, apostrophes, dashes, spaces. */
const key = (text: string): string =>
  text
    .normalize('NFKC')
    .toLowerCase()
    .replace(/[’‘`ʼ]/g, "'")
    .replace(/[‐-―−]/g, '-')
    .replace(/["«».]/g, '')
    .replace(/\s+/g, ' ')
    .trim()

function countryName(country: Country | undefined, code: string, locale: string): string {
  const own = pick(country?.names, locale)

  if (own !== '') return own

  try {
    return new Intl.DisplayNames([locale], { type: 'region' }).of(code) ?? code
  } catch {
    return code
  }
}

const region = (id: number | null) => state.regions.find((one) => one.id === id) ?? null

function label(one: Locality, locale: string): string {
  return [pick(one.names, locale), pick(region(one.region_id)?.names, locale)]
    .filter((part) => part !== '')
    .join(', ')
}

/* The dictionary of the panel, once `registerDelivery` is given it: a locality's type in words. */
let say: Line | null = null

const kindLabel = (kind: string, locale: string) => {
  const text = say ? say(locale, 'webx-delivery', `delivery.locality_kinds.${kind}`) : kind

  return text.endsWith(`locality_kinds.${kind}`) ? kind : text
}

function brief(one: Locality, locale: string) {
  return {
    id: one.id,
    name: pick(one.names, locale),
    label: label(one, locale),
    kind: one.kind,
    kind_label: kindLabel(one.kind, locale),
    country: one.country,
    active: one.active,
  }
}

const source = (one: Locality) =>
  one.created_by === 'manual' ? 'manual' : one.created_by === 'import' ? 'import' : 'carrier'

/** What holds a locality (`LocalityReferences`): points and the carriers' bindings. */
function heldBy(id: number): string[] {
  return [
    ...(state.points.some((one) => one.locality_id === id) ? ['delivery points'] : []),
    ...(state.bindings.some((one) => one.locality_id === id) ? ['carrier bindings'] : []),
  ]
}

function paginate<T>(rows: T[], query: URLSearchParams) {
  const perPage = Math.min(100, Math.max(10, Number(query.get('per_page') ?? 25) || 25))
  const page = Math.max(1, Number(query.get('page') ?? 1) || 1)
  const total = rows.length
  const lastPage = Math.max(1, Math.ceil(total / perPage))
  const from = (page - 1) * perPage

  return {
    data: rows.slice(from, from + perPage),
    current_page: page,
    last_page: lastPage,
    per_page: perPage,
    total,
    from: total === 0 ? null : from + 1,
    to: total === 0 ? null : Math.min(total, from + perPage),
  }
}

const money = (minor: number | null) =>
  minor === null ? null : `${(minor / 100).toFixed(2).replace('.', ',')} ₴`

export function registerDelivery(on: On, fail: Fail, line: Line): void {
  say = line
  const words = (locale: string, path: string) => line(locale, 'webx-delivery', `panel.${path}`)
  /* A line of the panel's dictionary, or null — what `MethodForm::line()` is on the server. */
  const said = (locale: string, path: string, params: Record<string, string> = {}) => {
    let text = words(locale, path)

    if (text.endsWith(path)) return null
    for (const [name, value] of Object.entries(params)) text = text.replaceAll(`:${name}`, value)

    return text
  }
  const refuse = (field: string, text: string) => fail(422, text, undefined, { [field]: [text] })

  /* Nova Poshta's own words: `webx-delivery-novaposhta::novaposhta.*`, read from its package. */
  const novaposhta = (locale: string, path: string) =>
    line(locale, 'webx-delivery-novaposhta', `novaposhta.${path}`)

  // The migration fills the titles of its methods in the site's languages.
  for (const one of state.methods) {
    const kind = one.type.startsWith(`${NOVAPOSHTA}.`) ? one.type.split('.')[1] : null

    if (kind && Object.keys(one.titles).length === 0) {
      for (const locale of ['en', 'uk', 'ru']) {
        one.titles[locale] = novaposhta(locale, `types.${kind}.title`)
        one.descriptions[locale] = novaposhta(locale, `types.${kind}.description`)
      }
    }
  }

  /* The shop is the dictionary's word (`StoreCarrier::title()`); «Carrier A» names itself. */
  const carrierTitle = (carrier: string, locale = 'en') =>
    carrier === 'store'
      ? line(locale, 'webx-delivery', 'delivery.carriers.store')
      : carrier === NOVAPOSHTA
        ? novaposhta(locale, 'carrier.title')
        : 'Carrier A'
  /* `MethodForm::typeTitle()`: words, never the key — the kind after the dot, or what it asks. */
  const typeTitle = (type: string, locale: string) => {
    if (type.startsWith(`${NOVAPOSHTA}.`))
      return novaposhta(locale, `types.${type.split('.')[1]}.title`)

    const known = TYPES.find((one) => one.key === type)
    const own = said(locale, `types.${type.replace('.', '-')}`)

    if (own !== null || !known) return own ?? type

    const carrier = { carrier: carrierTitle(known.carrier, locale) }

    return (
      said(locale, `types.kinds.${type.split('.')[1] ?? ''}`, carrier) ??
      said(locale, `types.asks.${known.asks}`, carrier) ??
      carrier.carrier
    )
  }
  const typeDescription = (type: string, locale: string) => {
    if (type.startsWith(`${NOVAPOSHTA}.`))
      return novaposhta(locale, `types.${type.split('.')[1]}.description`)

    const known = TYPES.find((one) => one.key === type)

    return (
      said(locale, `types.help.${type.replace('.', '-')}`) ??
      (known ? (said(locale, `types.help.${known.asks}`) ?? '') : '')
    )
  }

  const kindOptions = (locale: string, kinds: string[]) =>
    kinds.map((value) => ({
      value,
      label: state.kinds[value]
        ? pick(state.kinds[value], locale)
        : line(locale, 'webx-delivery', `delivery.point_kinds.${value}`),
    }))

  /* The kind is asked of the shop's pick-up, and of a carrier's type only with a choice of kinds. */
  const hidden = (type: string) => {
    const known = TYPES.find((one) => one.key === type)
    const chooses = known?.asks === 'point' && (type === 'store.pickup' || known.kinds.length > 1)

    return [...(chooses ? [] : ['point_kind']), ...(type === 'store.courier' ? [] : ['localities'])]
  }

  const pointKinds = (type: string, locale: string) => {
    const kinds = TYPES.find((one) => one.key === type)?.kinds ?? []

    return kindOptions(
      locale,
      type === 'store.pickup' ? [...kinds, ...Object.keys(state.kinds)] : kinds,
    )
  }

  const methodRow = (one: Method, locale: string) => {
    const type = TYPES.find((each) => each.key === one.type)

    return {
      id: one.id,
      key: one.key,
      type: one.type,
      type_title: typeTitle(one.type, locale),
      carrier: type?.carrier ?? null,
      carrier_title: type ? carrierTitle(type.carrier, locale) : null,
      missing: !type,
      title: pick(one.titles, locale) || one.key,
      price: one.price,
      price_text: money(one.price),
      countries: one.countries,
      enabled: one.enabled,
      position: one.position,
    }
  }

  const methodDetail = (one: Method, locale: string) => ({
    method: methodRow(one, locale),
    values: {
      titles: one.titles,
      descriptions: one.descriptions,
      price: one.price === null ? null : one.price / 100,
      point_kind: one.point_kind,
      countries: one.countries,
      honours_paid_by_store: one.honours_paid_by_store,
      enabled: one.enabled,
      localities: one.localities,
    },
    hidden: hidden(one.type),
    point_kinds: pointKinds(one.type, locale),
  })

  const findMethod = (id: string) =>
    state.methods.find((one) => one.id === Number(id)) ??
    (() => {
      throw fail(404, 'No such method.')
    })()

  function writeMethod(one: Method, values: Record<string, unknown>, locale: string): void {
    const titles = (values.titles ?? one.titles) as Localized

    if (Object.values(titles ?? {}).every((text) => !text || String(text).trim() === ''))
      throw refuse('titles', words(locale, 'errors.title_required'))

    const skip = hidden(one.type)

    one.titles = titles
    if ('descriptions' in values) one.descriptions = (values.descriptions ?? {}) as Localized
    if ('price' in values)
      one.price =
        values.price === null || values.price === '' ? null : Math.round(Number(values.price) * 100)
    if ('point_kind' in values && !skip.includes('point_kind'))
      one.point_kind = (values.point_kind as string) ?? null
    if ('countries' in values) one.countries = (values.countries as string[]) ?? []
    if ('honours_paid_by_store' in values)
      one.honours_paid_by_store = Boolean(values.honours_paid_by_store)
    if ('enabled' in values) one.enabled = Boolean(values.enabled)
    if ('localities' in values && !skip.includes('localities'))
      one.localities = (values.localities as number[]) ?? []
  }

  /* ------------------------------------------------------------------------ methods ----- */

  on('GET', '/delivery/methods', ({ locale }) => ({
    data: {
      methods: [...state.methods]
        .sort((a, b) => a.position - b.position)
        .map((one) => methodRow(one, locale)),
      types: TYPES.map((one) => ({
        key: one.key,
        title: typeTitle(one.key, locale),
        description: typeDescription(one.key, locale),
        carrier: one.carrier,
        carrier_title: carrierTitle(one.carrier, locale),
        asks: one.asks,
      })),
    },
  }))

  on('GET', '/delivery/methods/blank', ({ query, locale }) => {
    const type = query.get('type') ?? ''

    if (!TYPES.some((one) => one.key === type))
      throw refuse('type', words(locale, 'errors.unknown_type'))

    return {
      data: {
        hidden: hidden(type),
        point_kinds: pointKinds(type, locale),
        type_title: typeTitle(type, locale),
      },
    }
  })

  on('GET', '/delivery/methods/(\\d+)', ({ params, locale }) => ({
    data: methodDetail(findMethod(params[0]!), locale),
  }))

  on('POST', '/delivery/methods', ({ body, locale }) => {
    const type = String(body.type ?? '')

    if (!TYPES.some((one) => one.key === type))
      throw refuse('type', words(locale, 'errors.unknown_type'))

    const base = type.replace('.', '-')
    let methodKey = base

    for (let n = 2; state.methods.some((one) => one.key === methodKey); n++)
      methodKey = `${base}-${n}`

    const one: Method = {
      id: ++next,
      key: methodKey,
      type,
      titles: {},
      descriptions: {},
      price: null,
      point_kind: null,
      countries: [],
      honours_paid_by_store: true,
      enabled: false,
      position: state.methods.length,
      localities: [],
    }

    writeMethod(one, (body.values ?? {}) as Record<string, unknown>, locale)
    if (one.point_kind === null && !hidden(type).includes('point_kind'))
      one.point_kind = pointKinds(type, locale)[0]?.value ?? null
    state.methods.push(one)

    return { data: methodDetail(one, locale) }
  })

  on('PUT', '/delivery/methods/order', ({ body, locale }) => {
    const ids = ((body.ids ?? []) as number[]).map(Number)

    if (
      [...ids].sort().join() !==
      state.methods
        .map((one) => one.id)
        .sort()
        .join()
    )
      throw refuse('ids', words(locale, 'errors.order'))

    ids.forEach((id, position) => {
      state.methods.find((one) => one.id === id)!.position = position
    })

    return {}
  })

  on('POST', '/delivery/methods/point-kinds', ({ body, locale }) => {
    const kind = String(body.kind ?? '')

    if (!/^[a-z0-9-]{1,32}$/.test(kind)) throw refuse('kind', words(locale, 'method.kind-key-help'))

    state.kinds[kind] = (body.names ?? {}) as Localized

    return { data: pointKinds('store.pickup', locale) }
  })

  on('PUT', '/delivery/methods/(\\d+)', ({ params, body, locale }) => {
    const one = findMethod(params[0]!)

    writeMethod(one, (body.values ?? {}) as Record<string, unknown>, locale)

    return { data: methodDetail(one, locale) }
  })

  on('DELETE', '/delivery/methods/(\\d+)', ({ params }) => {
    state.methods = state.methods.filter((one) => one.id !== Number(params[0]))

    return {}
  })

  /* ------------------------------------------------------------------------- points ----- */

  const locality = (id: number | null) => state.localities.find((one) => one.id === id) ?? null

  const pointRow = (one: Point, locale: string) => ({
    id: one.id,
    kind: one.kind,
    kind_label: kindOptions(locale, [one.kind])[0]!.label,
    title: pick(one.titles, locale),
    address: pick(one.address, locale),
    locality: locality(one.locality_id) ? brief(locality(one.locality_id)!, locale) : null,
    phone: one.phone,
    active: one.active,
    today: one.schedule ? 'до 18:00' : null,
  })

  const pointDetail = (one: Point, locale: string) => ({
    point: pointRow(one, locale),
    values: {
      kind: one.kind,
      titles: one.titles,
      address: one.address,
      locality_id: one.locality_id,
      lat: one.lat,
      lng: one.lng,
      phone: one.phone,
      active: one.active,
      schedule: one.schedule,
    },
  })

  const findPoint = (id: string) =>
    state.points.find((one) => one.id === Number(id)) ??
    (() => {
      throw fail(404, 'No such point.')
    })()

  function writePoint(one: Point, values: Record<string, unknown>, locale: string): void {
    const errors: Record<string, string[]> = {}
    const localityId =
      'locality_id' in values ? (values.locality_id as number | null) : one.locality_id

    if (localityId === null) errors.locality_id = [words(locale, 'errors.locality_required')]
    if (
      'address' in values &&
      Object.values((values.address ?? {}) as Localized).every((text) => !text?.trim())
    )
      errors.address = [words(locale, 'errors.address_required')]

    if (Object.keys(errors).length > 0)
      throw fail(422, Object.values(errors)[0]![0]!, undefined, errors)

    Object.assign(one, {
      kind: values.kind ?? one.kind,
      titles: values.titles ?? one.titles,
      address: values.address ?? one.address,
      locality_id: localityId,
      lat: values.lat ?? null,
      lng: values.lng ?? null,
      phone: values.phone ?? null,
      active: values.active ?? one.active,
      schedule: values.schedule ?? null,
    })
  }

  on('GET', '/delivery/points', ({ query, locale }) => {
    const q = key(query.get('q') ?? '')
    const rows = state.points
      .filter((one) => !query.get('kind') || one.kind === query.get('kind'))
      .filter((one) => query.get('state') !== 'active' || one.active)
      .filter((one) => query.get('state') !== 'inactive' || !one.active)
      .filter(
        (one) =>
          q === '' ||
          key([...Object.values(one.titles), ...Object.values(one.address)].join(' ')).includes(q),
      )

    /* `PointController::sort()`: by a column, a minus for the other way round. */
    const sort = query.get('sort') ?? ''
    const desc = sort.startsWith('-')
    const by = desc ? sort.slice(1) : sort
    const listed = rows.map((one) => pointRow(one, locale))
    const value = (row: (typeof listed)[number]) =>
      by === 'title'
        ? row.title
        : by === 'kind'
          ? row.kind
          : by === 'locality'
            ? (row.locality?.name ?? '')
            : by === 'active'
              ? String(Number(row.active))
              : ''

    if (by !== '') listed.sort((x, y) => (desc ? -1 : 1) * value(x).localeCompare(value(y)))

    return {
      ...paginate(listed, query),
      filters: { kinds: kindOptions(locale, ['store', 'pickup', ...Object.keys(state.kinds)]) },
    }
  })

  on('GET', '/delivery/points/(\\d+)', ({ params, locale }) => ({
    data: pointDetail(findPoint(params[0]!), locale),
  }))

  on('POST', '/delivery/points', ({ body, locale }) => {
    const one: Point = {
      id: ++next,
      carrier: 'store',
      kind: 'store',
      titles: {},
      address: {},
      locality_id: null,
      lat: null,
      lng: null,
      phone: null,
      active: true,
      schedule: null,
    }

    writePoint(one, (body.values ?? {}) as Record<string, unknown>, locale)
    state.points.push(one)

    return { data: pointDetail(one, locale) }
  })

  on('PUT', '/delivery/points/(\\d+)', ({ params, body, locale }) => {
    const one = findPoint(params[0]!)

    writePoint(one, (body.values ?? {}) as Record<string, unknown>, locale)

    return { data: pointDetail(one, locale) }
  })

  on('DELETE', '/delivery/points/(\\d+)', ({ params }) => {
    state.points = state.points.filter((one) => one.id !== Number(params[0]))

    return {}
  })

  /* ----------------------------------------------------------------------- carriers ----- */

  const directory = (name: string) => {
    const runs = state.runs.filter((one) => one.directory === name)
    const latest = runs[0] ?? null
    const success = runs.find((one) => one.status === 'succeeded') ?? null

    return {
      directory: name,
      cron: name === 'streets' ? '0 4 * * 1' : '30 3 * * *',
      last_success: success?.finished_at ?? null,
      latest,
      running: latest?.status === 'running',
      held: latest?.status === 'held' ? latest.held : null,
      stale: false,
      records: name === 'localities' ? state.bindings.length : name === 'points' ? 60 : 1200,
      remarks:
        name === 'streets'
          ? []
          : name === 'points'
            ? [{ key: 'skipped.unknown_locality', count: 2 }]
            : [],
    }
  }

  const carrierRow = (key = 'carrier-a', locale = 'en') =>
    key === NOVAPOSHTA
      ? {
          key: NOVAPOSHTA,
          title: carrierTitle(NOVAPOSHTA, locale),
          icon: NOVAPOSHTA_ICON,
          enabled: state.novaposhta.enabled,
          countries: ['UA'],
          // Its directories come with stages 4–6 of its spec: no `SyncsDirectories` yet.
          syncs: false,
          directories: [] as ReturnType<typeof directory>[],
          missing: [] as string[],
          queue: 0,
        }
      : {
          key: 'carrier-a',
          title: 'Carrier A',
          icon: null,
          enabled: state.carrier.enabled,
          countries: ['XX'],
          syncs: true,
          directories: ['localities', 'points', 'streets'].map(directory),
          missing: [] as string[],
          queue: state.bindings.filter((one) => one.status === 'pending').length,
        }

  /* `NovaPoshtaCarrier::settings()`: the sender's chain from «its account» and the directories,
     fixed selects, plain fields and the seat of a parcel locker, named in its dictionary. */
  const novaposhtaFields = (locale: string): SettingField[] => {
    const label = (path: string) =>
      Object.fromEntries(['en', 'uk', 'ru'].map((one) => [one, novaposhta(one, path)]))
    const options = (group: string, values: string[]) =>
      values.map((value) => ({ value, label: novaposhta(locale, `settings.${group}.${value}`) }))

    return [
      { name: 'sender', type: 'select', options: 'carrier', label: label('settings.sender') },
      {
        name: 'contact',
        type: 'select',
        options: 'carrier',
        depends: ['sender'],
        label: label('settings.contact'),
      },
      {
        name: 'sender_city',
        type: 'select',
        options: 'carrier',
        search: true,
        label: label('settings.sender_city'),
      },
      {
        name: 'sender_point',
        type: 'select',
        options: 'carrier',
        depends: ['sender_city'],
        search: true,
        label: label('settings.sender_point'),
      },
      {
        name: 'payment_method',
        type: 'select',
        default: 'Cash',
        label: label('settings.payment_method'),
        options: options('payment_methods', ['Cash', 'NonCash']),
      },
      {
        name: 'cargo_type',
        type: 'select',
        default: 'Parcel',
        label: label('settings.cargo_type'),
        options: options('cargo_types', ['Parcel', 'Cargo', 'Documents']),
      },
      {
        name: 'weight',
        type: 'number',
        default: 0.5,
        min: 0.1,
        step: 0.1,
        precision: 1,
        label: label('settings.weight'),
      },
      { name: 'description', type: 'text', label: label('settings.description') },
      ...LOCKER_SIZE.map(([name, max]) => ({
        name,
        type: 'number',
        min: 1,
        max,
        step: 1,
        precision: 0,
        label: label(`settings.${name}`),
      })),
    ]
  }
  /* `Waybills::LOCKER_SIZE`: what a parcel locker of the carrier takes, cm. */
  const LOCKER_SIZE: [string, number][] = [
    ['locker_width', 40],
    ['locker_height', 30],
    ['locker_length', 60],
  ]

  /* The settings of carrier A take the sender from «its account» (`ProvidesSettingOptions`): the
     options as `FakeCarrier::settingOptions()` gives them — two senders and their contacts, its
     towns searched by name, the branches of the town of sending. A key typed as `options-down`
     plays a carrier that does not answer: the list fails, the label of the chosen value stays. */
  const carrierFields: SettingField[] = [
    {
      name: 'sender',
      type: 'select',
      options: 'carrier',
      label: { en: 'Sender', ru: 'Отправитель' },
    },
    {
      name: 'contact',
      type: 'select',
      options: 'carrier',
      depends: ['sender'],
      label: { en: 'Contact person', ru: 'Контактное лицо' },
    },
    {
      name: 'sender_city',
      type: 'select',
      options: 'carrier',
      search: true,
      label: { en: 'Town of sending', ru: 'Город отправки' },
    },
    {
      name: 'sender_point',
      type: 'select',
      options: 'carrier',
      depends: ['sender_city'],
      search: true,
      label: { en: 'Branch of sending', ru: 'Отделение отправки' },
    },
    {
      name: 'weight',
      type: 'number',
      step: 0.1,
      min: 0.1,
      label: { en: 'Weight by default, kg', ru: 'Вес по умолчанию, кг' },
    },
  ]
  const SENDERS: Record<string, { label: string; hint: string; contacts: Record<string, string> }> =
    {
      'sender-a': {
        label: 'Sender A',
        hint: 'Tax id 00000001',
        contacts: { 'contact-a1': 'Contact A1', 'contact-a2': 'Contact A2' },
      },
      'sender-b': {
        label: 'Sender B',
        hint: 'Tax id 00000002',
        contacts: { 'contact-b1': 'Contact B1' },
      },
    }
  type Choice = { value: string; label: string; hint: string | null }
  /* The account and the directories of Nova Poshta as `SenderOptions` reads them — made up:
     one sender and its contact persons, three towns, the branches of each. */
  const NOVA_TOWNS = ['Місто А', 'Місто Б', 'Місто В']
  const novaOptions = (field: string, values: Record<string, unknown>, q: string | null) => {
    const found = (value: string, label: string) =>
      q === null || value === q || label.toLowerCase().includes(q.toLowerCase())
    const list: Choice[] = []

    if (field === 'sender')
      list.push({ value: 'np-sender-a', label: 'ФОП Відправник А', hint: '00000000' })
    else if (field === 'contact' && values.sender === 'np-sender-a') {
      list.push({ value: 'np-contact-a', label: 'Імʼя А Прізвище А', hint: '+800 1234 5678' })
      list.push({ value: 'np-contact-b', label: 'Імʼя Б Прізвище Б', hint: '+800 1234 5678' })
    } else if (field === 'sender_city') {
      NOVA_TOWNS.forEach((name, index) => {
        if (found(`np-town-${index}`, name))
          list.push({ value: `np-town-${index}`, label: `${name}, Область А`, hint: null })
      })
    } else if (field === 'sender_point') {
      const town = String(values.sender_city ?? '')

      for (let number = 1; number <= 3 && town.startsWith('np-town-'); number++) {
        const value = `${town}-branch-${number}`
        const label = `Відділення №${number}`
        if (found(value, label)) list.push({ value, label, hint: `вул. Прикладна, ${number}` })
      }
    }

    return list
  }
  const optionsOf = (
    field: string,
    values: Record<string, unknown>,
    q: string | null,
    carrier = 'carrier-a',
  ) => {
    if (carrier === NOVAPOSHTA) return novaOptions(field, values, q)

    const found = (value: string, label: string) =>
      q === null || value === q || label.toLowerCase().includes(q.toLowerCase())
    const list: Choice[] = []

    if (field === 'sender') {
      for (const [value, one] of Object.entries(SENDERS))
        list.push({ value, label: one.label, hint: one.hint })
    } else if (field === 'contact') {
      for (const [value, label] of Object.entries(
        SENDERS[String(values.sender ?? '')]?.contacts ?? {},
      ))
        list.push({ value, label, hint: '+800 1234 5678' })
    } else if (field === 'sender_city') {
      for (let index = 0; index < 20 && list.length < 50; index++) {
        const name = `Town ${index}`
        if (found(`L${index}`, name))
          list.push({ value: `L${index}`, label: name, hint: `Region ${'ABCDE'[index % 5]}` })
      }
    } else if (field === 'sender_point') {
      const city = Number(String(values.sender_city ?? '').slice(1))

      for (let index = 0; index < 60 && list.length < 50; index++) {
        // Sending only from a branch: every third point is a locker and takes no parcel in.
        if (index % 3 === 0 || index % 20 !== city) continue
        if (found(`P${index}`, `Branch ${index}`))
          list.push({
            value: `P${index}`,
            label: `Branch ${index}`,
            hint: `${index} Example street`,
          })
      }
    }

    return list.slice(0, 50)
  }
  const fieldsOf = (carrier: string, locale: string) =>
    carrier === NOVAPOSHTA ? novaposhtaFields(locale) : carrierFields
  const fieldTitle = (name: string, locale: string, carrier = 'carrier-a') => {
    const field = fieldsOf(carrier, locale).find((one) => one.name === name)

    return field?.label[locale] ?? field?.label.en ?? name
  }
  /* Why the options of a field cannot be asked now: `SettingOptions::options()` on the server. */
  const optionsRefusal = (
    field: string,
    values: Record<string, unknown>,
    locale: string,
    carrier = 'carrier-a',
  ) => {
    const definition = fieldsOf(carrier, locale).find(
      (one) => one.name === field && one.options === 'carrier',
    )
    const keys = known(carrier).credentials

    if (!definition) return fail(404, said(locale, 'options.no-field', { field }) ?? field)
    if (!keys.api_key) return fail(422, words(locale, 'options.no-key'))
    if (keys.api_key === 'options-down') {
      return fail(
        422,
        said(locale, 'options.unavailable', {
          reason: line(locale, 'webx-delivery', 'delivery.sync.errors.no_answer'),
        }) ?? 'The carrier did not answer.',
      )
    }

    for (const parent of definition.depends ?? []) {
      const value = values[parent]

      if (value === null || value === undefined || value === '')
        return fail(
          422,
          said(locale, 'options.choose-first', { field: fieldTitle(parent, locale, carrier) }) ??
            parent,
        )
    }

    return null
  }

  const carrierDetail = (key = 'carrier-a', locale = 'en') => {
    const one = known(key)
    const nova = key === NOVAPOSHTA

    return {
      ...carrierRow(key, locale),
      settings: {
        fields: nova ? novaposhtaFields(locale) : carrierFields,
        values: one.settings,
        missing: {},
      },
      // The key itself never leaves the server: only whether it is set (§13.2).
      credentials: [
        {
          name: 'api_key',
          type: 'secret',
          label: nova
            ? Object.fromEntries(
                ['en', 'uk', 'ru'].map((each) => [each, novaposhta(each, 'credentials.api_key')]),
              )
            : { ru: 'Ключ API', en: 'API key' },
          set: Boolean(one.credentials.api_key),
        },
      ],
      runs: nova ? [] : state.runs,
    }
  }

  const known = (carrier: string) => {
    if (carrier === NOVAPOSHTA) return state.novaposhta
    if (carrier !== 'carrier-a') throw fail(404, 'No such carrier.')

    return state.carrier
  }

  on('GET', '/delivery/carriers', ({ locale }) => ({
    data: [carrierRow('carrier-a', locale), carrierRow(NOVAPOSHTA, locale)],
  }))
  on('GET', '/delivery/carriers/([a-z0-9-]+)', ({ params, locale }) => ({
    data: carrierDetail(params[0]!, locale),
  }))

  on(
    'GET',
    '/delivery/carriers/([a-z0-9-]+)/options/([A-Za-z0-9_]+)',
    ({ params, query, locale }) => {
      known(params[0]!)

      const values: Record<string, unknown> = {}
      for (const [name, value] of query.entries()) {
        const match = /^values\[(.+)\]$/.exec(name)
        if (match) values[match[1]!] = value
      }

      const refusal = optionsRefusal(params[1]!, values, locale, params[0]!)
      if (refusal) throw refusal

      const definition = fieldsOf(params[0]!, locale).find((one) => one.name === params[1])

      return {
        data: optionsOf(
          params[1]!,
          values,
          definition?.search ? query.get('q') || null : null,
          params[0]!,
        ),
      }
    },
  )

  on('PUT', '/delivery/carriers/([a-z0-9-]+)', ({ params, body, locale }) => {
    const one = known(params[0]!)
    const nova = params[0] === NOVAPOSHTA

    if ('enabled' in body) one.enabled = Boolean(body.enabled)

    for (const [name, value] of Object.entries(
      (body.credentials ?? {}) as Record<string, string>,
    )) {
      if (typeof value === 'string' && value.trim() !== '') one.credentials[name] = value.trim()
    }

    for (const name of (body.clear ?? []) as string[]) delete one.credentials[name]

    if (body.settings && typeof body.settings === 'object') {
      // As `SettingOptions::check()`: a value from the account only if the account has it.
      const before = one.settings
      const after: Record<string, unknown> = { ...before }
      const labels = { ...((before._labels as Record<string, string> | undefined) ?? {}) }
      const errors: Record<string, string[]> = {}

      for (const field of nova ? novaposhtaFields(locale) : carrierFields) {
        if (field.name in (body.settings as Record<string, unknown>))
          after[field.name] = (body.settings as Record<string, unknown>)[field.name]
      }

      for (const field of fieldsOf(params[0]!, locale).filter(
        (each) => each.options === 'carrier',
      )) {
        const value = after[field.name]

        if (value === null || value === undefined || value === '') {
          delete labels[field.name]
          continue
        }

        const moved =
          String(value) !== String(before[field.name] ?? '') ||
          (field.depends ?? []).some(
            (parent) => String(after[parent] ?? '') !== String(before[parent] ?? ''),
          )

        if (!moved && labels[field.name]) continue

        const refusal = optionsRefusal(field.name, after, locale, params[0]!)
        if (refusal) {
          errors[`settings.${field.name}`] = [refusal.message]
          continue
        }

        const match = optionsOf(
          field.name,
          after,
          field.search ? String(value) : null,
          params[0]!,
        ).find((each) => each.value === String(value))

        if (!match) errors[`settings.${field.name}`] = [words(locale, 'options.choose-from-list')]
        else labels[field.name] = match.label
      }

      if (Object.keys(errors).length > 0)
        throw fail(422, Object.values(errors)[0]![0]!, undefined, errors)

      one.settings = { ...after, _labels: labels }
    }

    return { data: carrierDetail(params[0]!, locale) }
  })

  on('POST', '/delivery/carriers/([a-z0-9-]+)/test', ({ params, locale }) => {
    const one = known(params[0]!)

    // Nova Poshta answers in its words, as `NovaPoshtaCarrier::testConnection()` does: here the
    // account always has two senders — the fixtures never reach the carrier.
    if (params[0] === NOVAPOSHTA)
      return new Reply(200, {
        data: one.credentials.api_key
          ? { ok: true, message: novaposhta(locale, 'connection.ok').replace(':count', '2') }
          : { ok: false, message: novaposhta(locale, 'connection.no_key') },
      })

    return new Reply(200, {
      data: one.credentials.api_key
        ? { ok: true, message: null }
        : { ok: false, message: 'The API key is not set.' },
    })
  })

  on('POST', '/delivery/carriers/([a-z0-9-]+)/sync/([a-z]+)', ({ params, locale }) => {
    const one = known(params[0]!)

    if (!one.enabled) throw refuse('carrier', words(locale, 'errors.carrier_off'))

    const queued = params[1] === 'localities' ? ['localities', 'points', 'streets'] : [params[1]!]

    state.runs.unshift({
      id: ++next,
      directory: params[1]!,
      status: 'succeeded',
      trigger: 'panel',
      pages: 1,
      seen: 20,
      created: 0,
      updated: 20,
      removed: 0,
      queued: 0,
      held: null,
      error: null,
      started_at: new Date().toISOString(),
      finished_at: new Date().toISOString(),
    })

    return new Reply(202, { data: { queued } })
  })

  on('POST', '/delivery/carriers/([a-z0-9-]+)/confirm/([a-z]+)', ({ params, locale }) => {
    known(params[0]!)

    const run = state.runs.find((one) => one.directory === params[1])

    if (!run || run.status !== 'held') throw refuse('run', words(locale, 'errors.not_held'))

    run.status = 'succeeded'
    run.removed = run.held ?? 0
    run.held = null

    return new Reply(200, { data: run })
  })

  /* ---------------------------------------------------------------------- geography ----- */

  on('GET', '/delivery/geography/countries', ({ locale }) => ({
    data: {
      countries: [...state.countries]
        .sort((a, b) => Number(b.active) - Number(a.active) || a.position - b.position)
        .map((one) => ({
          code: one.code,
          name: countryName(one, one.code, locale),
          active: one.active,
          position: one.position,
          regions: state.regions.filter((each) => each.country === one.code).length,
          localities: state.localities.filter((each) => each.country === one.code).length,
          leader: one.leader,
          leader_fixed: false,
        })),
      choices: ISO.map((code) => ({
        code,
        name: countryName(
          state.countries.find((one) => one.code === code),
          code,
          locale,
        ),
      })),
      carriers: [{ value: 'carrier-a', label: carrierTitle('carrier-a') }],
    },
  }))

  on('POST', '/delivery/geography/countries', ({ body }) => {
    const code = String(body.code ?? '').toUpperCase()
    const one = state.countries.find((each) => each.code === code)

    if (one) one.active = true
    else
      state.countries.push({
        code,
        names: null,
        active: true,
        position: state.countries.length,
        leader: null,
      })

    return { data: { code } }
  })

  on('PUT', '/delivery/geography/countries/order', ({ body }) => {
    ;((body.codes ?? []) as string[]).forEach((code, position) => {
      const one = state.countries.find((each) => each.code === code)

      if (one) one.position = position
    })

    return {}
  })

  on('DELETE', '/delivery/geography/countries/([A-Za-z]+)', ({ params }) => {
    const one = state.countries.find((each) => each.code === params[0]!.toUpperCase())

    if (one) one.active = false

    return {}
  })

  /* Gone when nothing is in it, off otherwise (`Editor::removeCountry()`). */
  on('POST', '/delivery/geography/countries/([A-Za-z]+)/remove', ({ params }) => {
    const code = params[0]!.toUpperCase()
    const one = state.countries.find((each) => each.code === code)

    if (!one) throw fail(422, 'There is no such country on the site.')

    const held =
      state.regions.some((each) => each.country === code) ||
      state.localities.some((each) => each.country === code)

    if (held) one.active = false
    else state.countries = state.countries.filter((each) => each !== one)

    return { data: { removal: held ? 'deactivated' : 'deleted' } }
  })

  on('PUT', '/delivery/geography/countries/([A-Za-z]+)/leader', ({ params, body }) => {
    const one = state.countries.find((each) => each.code === params[0]!.toUpperCase())

    if (one)
      one.leader = typeof body.carrier === 'string' && body.carrier !== '' ? body.carrier : null

    return {}
  })

  on('GET', '/delivery/geography/regions', ({ query, locale }) => ({
    data: state.regions
      .filter((one) => one.country === (query.get('country') ?? '').toUpperCase())
      .map((one) => ({
        id: one.id,
        name: pick(one.names, locale),
        names: one.names,
        code: one.code,
        country: one.country,
        position: one.position,
        localities: state.localities.filter((each) => each.region_id === one.id).length,
      }))
      .sort((a, b) => a.position - b.position || a.name.localeCompare(b.name)),
  }))

  on('PUT', '/delivery/geography/regions/order', ({ body }) => {
    const country = String(body.country ?? '').toUpperCase()
    const ids = Array.isArray(body.ids) ? body.ids.map(Number) : []

    ids.forEach((id, position) => {
      const one = region(id)

      if (one && one.country === country) one.position = position
    })

    return {}
  })

  const regionFree = (country: string, names: Localized, except: number | null) =>
    !state.regions.some(
      (one) =>
        one.country === country &&
        one.id !== except &&
        Object.values(one.names).some((name) =>
          Object.values(names).some((other) => key(other) === key(name)),
        ),
    )

  on('POST', '/delivery/geography/regions', ({ body, locale }) => {
    const country = String(body.country ?? '').toUpperCase()
    const names = (body.names ?? {}) as Localized

    if (Object.values(names).every((text) => !text?.trim()))
      throw refuse('names', words(locale, 'errors.title_required'))
    if (!regionFree(country, names, null))
      throw refuse('names', 'The country already has this region.')

    // A new region goes after the ones already ordered, as on the server.
    const position =
      Math.max(
        0,
        ...state.regions.filter((each) => each.country === country).map((each) => each.position),
      ) + 1
    const one = { id: ++next, country, names, code: (body.code as string | null) ?? null, position }

    state.regions.push(one)

    return { data: { id: one.id } }
  })

  on('PUT', '/delivery/geography/regions/(\\d+)', ({ params, body }) => {
    const one = region(Number(params[0]))

    if (!one) throw fail(404, 'No such region.')
    if (body.names) {
      if (!regionFree(one.country, body.names as Localized, one.id))
        throw refuse('names', 'The country already has this region.')
      one.names = body.names as Localized
    }

    return {}
  })

  on('DELETE', '/delivery/geography/regions/(\\d+)', ({ params }) => {
    const id = Number(params[0])

    if (state.localities.some((one) => one.region_id === id))
      throw refuse(
        'region',
        'The region has localities. Move them to another region or delete them first.',
      )

    state.regions = state.regions.filter((one) => one.id !== id)

    return {}
  })

  on('GET', '/delivery/geography/localities', ({ query, locale }) => {
    const q = key(query.get('q') ?? '')
    const country = (query.get('country') ?? '').toUpperCase()
    const rows = state.localities
      .filter((one) => country === '' || one.country === country)
      .filter((one) => !query.get('region') || one.region_id === Number(query.get('region')))
      .filter((one) => !query.get('source') || source(one) === query.get('source'))
      .filter((one) => query.get('state') !== 'active' || one.active)
      .filter((one) => query.get('state') !== 'inactive' || !one.active)
      .filter(
        (one) =>
          q === '' ||
          Object.values(one.names).some((name) =>
            key(name)
              .split(' ')
              .some((_, at, words) => words.slice(at).join(' ').startsWith(q)),
          ),
      )
      .sort((a, b) => b.weight - a.weight || a.id - b.id)
      .map((one) => ({
        ...brief(one, locale),
        region: region(one.region_id)
          ? { id: one.region_id, name: pick(region(one.region_id)!.names, locale) }
          : null,
        district: null,
        weight: one.weight,
        created_by: one.created_by,
        source: source(one),
        bindings: state.bindings
          .filter((each) => each.locality_id === one.id)
          .map((each) => ({
            carrier: each.carrier,
            carrier_title: carrierTitle(each.carrier),
            external_id: each.external_id,
            status: each.status,
          })),
      }))

    return paginate(rows, query)
  })

  on('GET', '/delivery/geography/localities/suggest', ({ query, locale }) => {
    const q = key(query.get('q') ?? '')
    const country = (query.get('country') ?? '').toUpperCase()
    const on = new Set(state.countries.filter((one) => one.active).map((one) => one.code))

    return {
      data: state.localities
        .filter(
          (one) => one.active && (country === '' ? on.has(one.country) : one.country === country),
        )
        .filter(
          (one) =>
            q === '' ||
            Object.values(one.names).some((name) =>
              key(name)
                .split(' ')
                .some((_, at, all) => all.slice(at).join(' ').startsWith(q)),
            ),
        )
        .sort((a, b) => b.weight - a.weight || b.id - a.id)
        .slice(0, 10)
        .map((one) => brief(one, locale)),
    }
  })

  on('GET', '/delivery/geography/localities/brief', ({ query, locale }) => ({
    data: query
      .getAll('ids[]')
      .map(Number)
      .map((id) => locality(id))
      .filter((one): one is Locality => one !== null)
      .map((one) => brief(one, locale)),
  }))

  const localityDetail = (one: Locality, locale: string) => ({
    locality: {
      ...brief(one, locale),
      source: source(one),
      created_by: one.created_by,
      manual_fields: one.created_by === 'manual' ? [] : one.manual_fields,
      held_by: heldBy(one.id),
    },
    values: {
      names: one.names,
      kind: one.kind,
      country: one.country,
      region_id: one.region_id,
      lat: one.lat,
      lng: one.lng,
      weight: one.weight,
      active: one.active,
    },
  })

  const findLocality = (id: string) =>
    locality(Number(id)) ??
    (() => {
      throw fail(404, 'No such locality.')
    })()

  /** `Editor::similar()`: country, region, type and a name key in common. */
  const similar = (values: Record<string, unknown>, except: number | null) => {
    const keys = Object.values((values.names ?? {}) as Localized)
      .map(key)
      .filter((one) => one !== '')

    return state.localities.filter(
      (one) =>
        one.id !== except &&
        one.country === values.country &&
        one.kind === values.kind &&
        (one.region_id ?? null) === ((values.region_id as number | null) ?? null) &&
        Object.values(one.names).some((name) => keys.includes(key(name))),
    )
  }

  function checkLocality(values: Record<string, unknown>, locale: string): void {
    const errors: Record<string, string[]> = {}

    if (Object.values((values.names ?? {}) as Localized).every((text) => !text?.trim()))
      errors.names = ['A name is required, in at least one language of the site.']
    if (!KINDS.includes(String(values.kind))) errors.kind = [words(locale, 'locality.kind')]
    if (!state.countries.some((one) => one.code === values.country))
      errors.country = ['Choose the country.']

    const r = region((values.region_id as number | null) ?? null)

    if (values.region_id && r?.country !== values.country)
      errors.region_id = ['The region is not one of this country.']
    if (Object.keys(errors).length > 0)
      throw fail(422, Object.values(errors)[0]![0]!, undefined, errors)
  }

  function duplicate(found: Locality[], locale: string): Error {
    return fail(409, words(locale, 'locality.duplicate'), undefined, undefined, {
      similar: found.map((one) => ({ ...brief(one, locale), source: source(one) })),
    })
  }

  on('GET', '/delivery/geography/localities/(\\d+)', ({ params, locale }) => ({
    data: localityDetail(findLocality(params[0]!), locale),
  }))

  on('POST', '/delivery/geography/localities', ({ body, locale }) => {
    const values = (body.values ?? {}) as Record<string, unknown>

    checkLocality(values, locale)

    const found = similar(values, null)

    if (found.length > 0 && body.force !== true) throw duplicate(found, locale)

    const kindWeight: Record<string, number> = {
      city: 100,
      town: 50,
      township: 20,
      village: 10,
      settlement: 10,
      other: 0,
    }
    const one: Locality = {
      id: ++next,
      country: String(values.country),
      region_id: (values.region_id as number | null) ?? null,
      kind: String(values.kind),
      names: values.names as Localized,
      lat: (values.lat as number | null) ?? null,
      lng: (values.lng as number | null) ?? null,
      weight:
        values.weight === null || values.weight === undefined
          ? (kindWeight[String(values.kind)] ?? 0)
          : Number(values.weight),
      active: values.active !== false,
      created_by: 'manual',
      manual_fields: values.weight === null || values.weight === undefined ? [] : ['weight'],
    }

    state.localities.push(one)

    return { data: localityDetail(one, locale) }
  })

  on('PUT', '/delivery/geography/localities/(\\d+)', ({ params, body, locale }) => {
    const one = findLocality(params[0]!)
    const values: Record<string, unknown> = {
      ...((body.values ?? {}) as Record<string, unknown>),
      country: one.country,
    }

    checkLocality(values, locale)

    const found = similar(values, one.id)

    if (found.length > 0 && body.force !== true) throw duplicate(found, locale)

    const changed = (
      ['names', 'kind', 'region_id', 'lat', 'lng', 'weight', 'active'] as const
    ).filter(
      (field) => field in values && JSON.stringify(values[field]) !== JSON.stringify(one[field]),
    )

    Object.assign(one, {
      names: values.names ?? one.names,
      kind: values.kind ?? one.kind,
      region_id: values.region_id ?? null,
      lat: values.lat ?? null,
      lng: values.lng ?? null,
      weight: values.weight ?? one.weight,
      active: values.active ?? one.active,
    })

    if (one.created_by !== 'manual')
      one.manual_fields = [...new Set([...one.manual_fields, ...changed])]

    return { data: localityDetail(one, locale) }
  })

  on('DELETE', '/delivery/geography/localities/(\\d+)', ({ params }) => {
    const one = findLocality(params[0]!)

    if (heldBy(one.id).length > 0) {
      one.active = false

      return { data: { removal: 'deactivated' } }
    }

    state.localities = state.localities.filter((each) => each.id !== one.id)
    state.popular = state.popular.filter((id) => id !== one.id)
    for (const method of state.methods)
      method.localities = method.localities.filter((id) => id !== one.id)

    return { data: { removal: 'deleted' } }
  })

  on('POST', '/delivery/geography/localities/(\\d+)/activate', ({ params }) => {
    findLocality(params[0]!).active = true

    return new Reply(200, {})
  })

  on('POST', '/delivery/geography/localities/(\\d+)/deactivate', ({ params }) => {
    findLocality(params[0]!).active = false

    return new Reply(200, {})
  })

  /* ------------------------------------------------------------------------ popular ----- */

  const popular = (locale: string) => ({
    data: state.popular
      .map((id) => locality(id))
      .filter((one): one is Locality => one !== null)
      .map((one) => brief(one, locale)),
  })

  on('GET', '/delivery/popular', ({ locale }) => popular(locale))

  on('PUT', '/delivery/popular', ({ body, locale }) => {
    const ids = [...new Set(((body.ids ?? []) as number[]).map(Number))]

    if (ids.some((id) => !locality(id))) throw refuse('locality_id', 'Unknown locality.')

    state.popular = ids

    return popular(locale)
  })

  on('POST', '/delivery/popular', ({ body, locale }) => {
    const id = Number(body.locality_id)

    if (!locality(id)) throw refuse('locality_id', 'Unknown locality.')

    state.popular = [...state.popular.filter((one) => one !== id), id]

    return popular(locale)
  })

  on('DELETE', '/delivery/popular/(\\d+)', ({ params, locale }) => {
    state.popular = state.popular.filter((id) => id !== Number(params[0]))

    return popular(locale)
  })

  /* -------------------------------------------------------------- the order's tab ----- */

  /* The tab «Delivery» of an order's card (§11, §14.2): order 7 goes by carrier A's courier,
     order 9 is picked up at the shop's own store — no waybill —, orders 10 and 11 go to a branch
     and a parcel locker of Nova Poshta, and every other one goes to a branch of carrier A at a
     fixed price. The parcels live here; «Refresh tracking» moves a
     parcel one status on, as a carrier would tell it. */
  type Parcel = {
    id: number
    order: number
    number: string
    payer: 'sender' | 'recipient'
    status: string
    final: boolean
    events: { status: string; at: string; text: string | null }[]
  }
  const parcels: Parcel[] = []
  let parcelSeq = 0
  const PATH = ['created', 'in_transit', 'at_point', 'received']
  const statusWord = (locale: string, status: string) =>
    line(locale, 'webx-delivery', `delivery.shipments.statuses.${status}`)
  /* `delivery-novaposhta`: order 10 to a branch, its recipient in Ukrainian letters; order 11
     to a parcel locker, the buyer's name in Latin ones — the carrier's dialog marks it. */
  const NOVA_ORDERS: Record<number, 'branch' | 'locker'> = { 10: 'branch', 11: 'locker' }
  const novaView = (kind: 'branch' | 'locker') => ({
    method: kind === 'branch' ? 'Nova Poshta branch' : 'Nova Poshta parcel locker',
    method_key: `novaposhta-${kind}`,
    carrier: NOVAPOSHTA,
    carrier_title: 'Nova Poshta',
    locality: 'Місто А, Область А',
    point:
      kind === 'branch'
        ? {
            title: 'Відділення №1',
            number: '1',
            address: 'вул. Прикладна, 1',
            schedule: 'Mon–Fri 09:00–19:00',
          }
        : {
            title: 'Поштомат №42040',
            number: '42040',
            address: 'вул. Прикладна, 2',
            schedule: 'Every day 00:00–24:00',
          },
    address: null,
    note: null,
    price: 'By carrier rates',
    was: null,
    estimate: null,
    paid_by_store: false,
    payer: 'recipient' as const,
    waybill: null,
  })
  const orderView = (order: number) =>
    NOVA_ORDERS[order]
      ? novaView(NOVA_ORDERS[order])
      : order === 9
        ? {
            method: 'Pick up at a store',
            method_key: 'store-pickup',
            carrier: 'store',
            carrier_title: carrierTitle('store'),
            locality: 'Town A, Region A',
            point: { title: 'Store A', number: '1', address: '1 Example street', schedule: null },
            address: null,
            note: null,
            price: 'Free',
            was: null,
            estimate: null,
            paid_by_store: false,
            payer: 'sender' as const,
            waybill: null,
          }
        : order === 7
          ? {
              method: 'Carrier A courier',
              method_key: 'carrier-a-courier',
              carrier: 'carrier-a',
              carrier_title: 'Carrier A',
              locality: 'Town B, Region B',
              point: null,
              address: 'Elm 3, 5, 12',
              note: 'Ring twice',
              price: 'By carrier rates',
              was: null,
              estimate: '70,00 €',
              paid_by_store: false,
              payer: 'recipient' as const,
              waybill: null,
            }
          : order === 8
            ? {
                method: 'Carrier A locker',
                method_key: 'carrier-a-locker',
                carrier: 'carrier-a',
                carrier_title: 'Carrier A',
                locality: 'Town A, Region A',
                point: {
                  title: 'Locker 9',
                  number: '9',
                  address: '9 Example street',
                  schedule: 'Every day 00:00–24:00',
                },
                address: null,
                note: null,
                price: 'By carrier rates',
                was: null,
                estimate: null,
                paid_by_store: false,
                payer: 'recipient' as const,
                waybill: null,
              }
            : {
                method: 'Carrier A branch',
                method_key: 'carrier-a-branch',
                carrier: 'carrier-a',
                carrier_title: 'Carrier A',
                locality: 'Town A, Region A',
                point: {
                  title: 'Branch 12',
                  number: '12',
                  address: '12 Example street',
                  schedule: 'Mon–Fri 09:00–18:00',
                },
                address: null,
                note: null,
                price: 'Free',
                was: '9,00 €',
                estimate: null,
                paid_by_store: true,
                payer: 'sender' as const,
                waybill: null,
              }
  const waybillFields = [
    {
      name: 'weight',
      type: 'number',
      required: true,
      default: state.carrier.settings.weight ?? 0.5,
      min: 0.1,
      step: 0.1,
      label: { en: 'Weight, kg', ru: 'Вес, кг' },
    },
    {
      name: 'description',
      type: 'text',
      default: 'Goods',
      label: { en: 'What is inside', ru: 'Что внутри' },
    },
  ]
  /* `NovaPoshtaType::waybillFields()`: no cash on delivery; a locker takes one seat of its sizes. */
  const novaWaybillFields = (kind: 'branch' | 'locker', locale: string): SettingField[] => {
    const label = (path: string) =>
      Object.fromEntries(['en', 'uk', 'ru'].map((one) => [one, novaposhta(one, path)]))
    const locker = kind === 'locker'
    const cargo = locker ? ['Parcel', 'Documents'] : ['Parcel', 'Cargo', 'Documents']

    return [
      {
        name: 'weight',
        type: 'number',
        required: true,
        default: 0.5,
        min: 0.1,
        step: 0.1,
        precision: 1,
        label: label('waybill.weight'),
      },
      ...(locker
        ? []
        : [
            {
              name: 'seats',
              type: 'number',
              required: true,
              default: 1,
              min: 1,
              step: 1,
              precision: 0,
              label: label('waybill.seats'),
            },
          ]),
      {
        name: 'cargo_type',
        type: 'select',
        required: true,
        default: 'Parcel',
        label: label('waybill.cargo_type'),
        options: cargo.map((value) => ({
          value,
          label: novaposhta(locale, `settings.cargo_types.${value}`),
        })),
      },
      ...(locker
        ? LOCKER_SIZE.map(([name, max]) => ({
            name,
            type: 'number',
            required: true,
            // The carrier's settings give the seat by default.
            default: { locker_width: 30, locker_height: 20, locker_length: 40 }[name] ?? null,
            min: 1,
            max,
            step: 1,
            precision: 0,
            label: label(`waybill.${name.slice(7)}`),
          }))
        : []),
      {
        name: 'description',
        type: 'text',
        required: true,
        default: novaposhta('uk', 'settings.description_default'),
        label: label('waybill.description'),
      },
      { name: 'date', type: 'text', label: label('waybill.date') },
    ]
  }
  /* `RecipientName::valid()`: Ukrainian letters, an apostrophe, a hyphen, a space inside. */
  const UKRAINIAN =
    /^[А-ЩЬЮЯҐЄІЇа-щьюяґєії]+(?:['’ʼ\- ][А-ЩЬЮЯҐЄІЇа-щьюяґєії]+|[А-ЩЬЮЯҐЄІЇа-щьюяґєії])*$/u
  const draftOf = (order: number, locale = 'en') => {
    const view = orderView(order)
    const nova = NOVA_ORDERS[order]

    if (nova)
      return {
        order_id: order,
        order_number: `WX-${1000 + order}`,
        carrier: NOVAPOSHTA,
        type: `novaposhta.${nova}`,
        method: view.method_key,
        recipient:
          nova === 'branch'
            ? {
                first_name: 'Олена',
                last_name: 'Приклад',
                name: 'Олена Приклад',
                phone: '+800 1234 5678',
                email: null,
              }
            : {
                first_name: 'Buyer',
                last_name: 'K',
                name: 'Buyer K',
                phone: '+800 1234 5678',
                email: null,
              },
        sender: state.novaposhta.settings,
        lines: [{ name: 'Item A', sku: 'A-1', qty: 1, price: 4999 }],
        declared_value: 4999,
        currency: 'EUR',
        payer: view.payer,
        cod: null,
        carrier_locality: 'np-town-0',
        extra: {},
        form: { fields: novaWaybillFields(nova, locale), cod: false },
      }

    return {
      order_id: order,
      order_number: `WX-${1000 + order}`,
      carrier: view.carrier,
      type: `${view.carrier}.${order === 7 ? 'courier' : order === 8 ? 'locker' : 'branch'}`,
      method: view.method_key,
      recipient: {
        first_name: 'Buyer',
        last_name: 'ABCDEFGHIJKL'[order - 1] ?? '',
        name: `Buyer ${'ABCDEFGHIJKL'[order - 1] ?? ''}`.trim(),
        phone: '+800 1234 5678',
        email: null,
      },
      sender: { name: 'Example shop', phone: '+800 1234 5678' },
      lines: [{ name: 'Item A', sku: 'A-1', qty: 1, price: 4999 }],
      declared_value: 4999,
      currency: 'EUR',
      payer: view.payer,
      cod: null,
      carrier_locality: order === 7 ? 'L1' : 'L0',
      extra: {},
      // `DescribesWaybill` of carrier A's types: a branch asks weight and contents, a locker
      // (order 8) the same and takes no cash on delivery; its courier has none — the base's dialog.
      form: order === 7 ? undefined : { fields: waybillFields, cod: order !== 8 },
    }
  }
  const orderDelivery = (order: number, locale: string) => {
    const view = orderView(order)
    const own = view.carrier === 'store'
    const mine = parcels.filter((one) => one.order === order).sort((a, b) => b.id - a.id)
    const active = mine.find((one) => one.status !== 'cancelled') ?? null

    return {
      data: {
        order_id: order,
        currency: 'EUR',
        exponent: 2,
        delivery: { ...view, waybill: active?.number ?? null },
        waybills: !own,
        can_ship: true,
        draft: own || active ? null : draftOf(order, locale),
        active: active?.id ?? null,
        shipments: mine.map((one) => ({
          id: one.id,
          number: one.number,
          carrier: view.carrier,
          method: view.method_key,
          payer: one.payer,
          cost: 4500,
          cost_formatted: '45,00 €',
          status: one.status,
          status_label: statusWord(locale, one.status),
          status_at: one.events[0]?.at ?? null,
          final: one.final,
          next_check_at: null,
          cancellable: !one.final,
          label: one.status !== 'cancelled',
          events: one.events.map((event) => ({
            ...event,
            label: statusWord(locale, event.status),
          })),
          created_at: one.events[one.events.length - 1]?.at ?? null,
        })),
      },
    }
  }

  on('GET', '/delivery/orders/(\\d+)', ({ params, locale }) =>
    orderDelivery(Number(params[0]), locale),
  )

  on('POST', '/delivery/orders/(\\d+)/waybill', ({ params, body, locale }) => {
    const order = Number(params[0])
    const input = (body ?? {}) as Record<string, unknown>

    if (orderView(order).carrier === 'store') {
      throw refuse('waybill', line(locale, 'webx-delivery', 'delivery.shipments.no_waybills'))
    }

    // A locker takes no cash on delivery (`supportsCod()` false): refused, as `Shipments::request()`.
    if (order === 8 && input.cod !== undefined && input.cod !== null) {
      throw refuse('waybill', line(locale, 'webx-delivery', 'delivery.shipments.no_cod'))
    }

    // `Waybills::draft()` of Nova Poshta: no cash on delivery, the names in Ukrainian letters,
    // a locker's seat within its sizes — refused before a request, the field marked.
    const nova = NOVA_ORDERS[order]

    if (nova) {
      const np = (path: string) => novaposhta(locale, path)
      const extra = (input.extra ?? {}) as Record<string, unknown>

      if (input.cod !== undefined && input.cod !== null)
        throw refuse('waybill', np('errors.no_cod'))

      for (const field of ['recipient_first_name', 'recipient_last_name']) {
        const name = String(input[field] ?? '').trim()

        if (name === '') throw refuse(field, np('errors.name_empty'))
        if (!UKRAINIAN.test(name)) throw refuse(field, np('errors.name_letters'))
      }

      for (const [name, max] of nova === 'locker' ? LOCKER_SIZE : []) {
        const value = Number(extra[name])

        if (!(value > 0)) throw refuse(`extra.${name}`, np('errors.size'))
        if (value > max) throw refuse(`extra.${name}`, np('errors.locker_size'))
      }
    }

    if (input.dry_run) return { data: { request: { ...draftOf(order, locale), ...input } } }

    if (parcels.some((one) => one.order === order && one.status !== 'cancelled')) {
      throw refuse('waybill', line(locale, 'webx-delivery', 'delivery.shipments.made_already'))
    }

    parcelSeq++
    parcels.push({
      id: parcelSeq,
      order,
      number: NOVA_ORDERS[order]
        ? `2045000000${String(parcelSeq).padStart(4, '0')}`
        : `A-${String(parcelSeq).padStart(4, '0')}`,
      payer: orderView(order).payer,
      status: 'created',
      final: false,
      events: [{ status: 'created', at: new Date().toISOString(), text: null }],
    })

    return orderDelivery(order, locale)
  })

  on('POST', '/delivery/orders/(\\d+)/track', ({ params, locale }) => {
    const order = Number(params[0])

    for (const one of parcels.filter((parcel) => parcel.order === order && !parcel.final)) {
      const next = PATH[PATH.indexOf(one.status) + 1]

      if (!next) continue
      one.status = next
      one.final = next === 'received'
      one.events.unshift({
        status: next,
        at: new Date().toISOString(),
        text: next === 'at_point' ? 'Arrived at branch 12' : null,
      })
    }

    return orderDelivery(order, locale)
  })

  on('POST', '/delivery/shipments/(\\d+)/cancel', ({ params, locale }) => {
    const one = parcels.find((parcel) => parcel.id === Number(params[0]))

    if (!one) throw fail(404, 'Not found')
    one.status = 'cancelled'
    one.final = true
    one.events.unshift({ status: 'cancelled', at: new Date().toISOString(), text: null })

    return orderDelivery(one.order, locale)
  })

  on('GET', '/delivery/shipments/(\\d+)/label', ({ params }) => {
    const one = parcels.find((parcel) => parcel.id === Number(params[0]))

    if (!one) throw fail(404, 'Not found')

    return { data: { url: `https://carrier-a.example.test/labels/${one.number}.pdf` } }
  })

  /* ----------------------------------------------------------------------- bindings ----- */

  const pending = (query: { get: (name: string) => string | null }) =>
    state.bindings.filter(
      (one) =>
        one.status === 'pending' &&
        (!query.get('carrier') || one.carrier === query.get('carrier')) &&
        (!query.get('reason') || one.reason === query.get('reason')),
    )

  on('GET', '/delivery/bindings', ({ query, locale }) => {
    const rows = pending(query).map((one) => ({
      id: one.id,
      carrier: one.carrier,
      carrier_title: carrierTitle(one.carrier),
      external_id: one.external_id,
      reason: one.reason,
      record: {
        ...one.record,
        kind: one.record.kind ? kindLabel(one.record.kind, locale) : null,
        lat: null,
        lng: null,
      },
      candidates: one.candidates
        .map((candidate) => {
          const found = locality(candidate.locality_id)

          return found ? { ...brief(found, locale), distance_km: candidate.distance_km } : null
        })
        .filter(Boolean),
    }))
    const all = pending({ get: () => null })
    const count = (list: Binding[], field: 'carrier' | 'reason') =>
      [...new Set(list.map((one) => one[field] as string))].map((value) => ({
        value,
        count: list.filter((one) => one[field] === value).length,
      }))

    return {
      ...paginate(rows, query),
      filters: {
        carriers: count(all, 'carrier').map((one) => ({ ...one, label: carrierTitle(one.value) })),
        reasons: count(
          pending({ get: (name) => (name === 'carrier' ? query.get('carrier') : null) }),
          'reason',
        ),
      },
    }
  })

  on('POST', '/delivery/bindings/batch', ({ body, locale }) => {
    const action = String(body.action)
    const filter = {
      get: (name: string) => (typeof body[name] === 'string' ? (body[name] as string) : null),
    }
    const ids = ((body.ids ?? []) as number[]).map(Number)
    const chosen =
      body.all === true ? pending(filter) : pending(filter).filter((one) => ids.includes(one.id))

    if (chosen.length === 0) throw refuse('ids', words(locale, 'errors.nothing_chosen'))

    const target = action === 'bind' ? locality(Number(body.locality_id)) : null

    if (action === 'bind' && !target)
      throw refuse('locality_id', words(locale, 'errors.locality_required'))

    for (const one of chosen) {
      if (action === 'ignore') {
        one.status = 'ignored'
      } else if (action === 'bind') {
        one.status = 'matched'
        one.locality_id = target!.id
      } else {
        const r = state.regions.find(
          (each) =>
            each.country === one.record.country &&
            Object.values(each.names).some((name) => key(name) === key(one.record.region ?? '')),
        )
        const made: Locality = {
          id: ++next,
          country: one.record.country,
          region_id: r?.id ?? null,
          kind: one.record.kind,
          names: { [MAIN]: one.record.name },
          lat: null,
          lng: null,
          weight: 10,
          active: true,
          created_by: `carrier:${one.carrier}`,
          manual_fields: [],
        }

        state.localities.push(made)
        one.status = 'matched'
        one.locality_id = made.id
      }

      one.reason = null
    }

    return new Reply(200, {
      data: {
        done: chosen.length,
        failed: [],
        left: body.all === true ? pending(filter).length : 0,
      },
    })
  })
}
