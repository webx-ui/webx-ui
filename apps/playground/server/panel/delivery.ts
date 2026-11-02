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
]

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
    { id: 1, country: 'XX', names: { ru: 'Регион A', en: 'Region A' }, code: 'A' },
    { id: 2, country: 'XX', names: { ru: 'Регион B', en: 'Region B' }, code: 'B' },
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
    settings: { sender: 'Demo shop', sender_phone: '+800 1234 5678' } as Record<string, unknown>,
    credentials: { api_key: 'demo-secret-key' } as Record<string, string>,
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

function brief(one: Locality, locale: string) {
  return {
    id: one.id,
    name: pick(one.names, locale),
    label: label(one, locale),
    kind: one.kind,
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
  const words = (locale: string, path: string) => line(locale, 'webx-delivery', `panel.${path}`)
  const refuse = (field: string, text: string) => fail(422, text, undefined, { [field]: [text] })

  const typeTitle = (type: string, locale: string) => {
    const lineKey = `types.${type.replace('.', '-')}`
    const text = words(locale, lineKey)

    return text.endsWith(lineKey) ? type : text
  }
  const carrierTitle = (carrier: string) => (carrier === 'store' ? 'Shop' : 'Carrier A')

  const kindOptions = (locale: string, kinds: string[]) =>
    kinds.map((value) => ({
      value,
      label: state.kinds[value]
        ? pick(state.kinds[value], locale)
        : line(locale, 'webx-delivery', `delivery.point_kinds.${value}`),
    }))

  const hidden = (type: string) => {
    const asks = TYPES.find((one) => one.key === type)?.asks

    return [
      ...(asks === 'point' ? [] : ['point_kind']),
      ...(type === 'store.courier' ? [] : ['localities']),
    ]
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
      carrier_title: type ? carrierTitle(type.carrier) : null,
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
        carrier: one.carrier,
        carrier_title: carrierTitle(one.carrier),
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

    return {
      ...paginate(
        rows.map((one) => pointRow(one, locale)),
        query,
      ),
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

  const carrierRow = () => ({
    key: 'carrier-a',
    title: 'Carrier A',
    icon: null,
    enabled: state.carrier.enabled,
    countries: ['XX'],
    syncs: true,
    directories: ['localities', 'points', 'streets'].map(directory),
    queue: state.bindings.filter((one) => one.status === 'pending').length,
  })

  const carrierDetail = () => ({
    ...carrierRow(),
    settings: {
      fields: [
        { name: 'sender', type: 'text', label: { ru: 'Отправитель', en: 'Sender' } },
        {
          name: 'sender_phone',
          type: 'text',
          label: { ru: 'Телефон отправителя', en: 'Sender phone' },
        },
      ],
      values: state.carrier.settings,
    },
    // The key itself never leaves the server: only whether it is set (§13.2).
    credentials: [
      {
        name: 'api_key',
        type: 'secret',
        label: { ru: 'Ключ API', en: 'API key' },
        set: Boolean(state.carrier.credentials.api_key),
      },
    ],
    runs: state.runs,
  })

  const known = (carrier: string) => {
    if (carrier !== 'carrier-a') throw fail(404, 'No such carrier.')
  }

  on('GET', '/delivery/carriers', () => ({ data: [carrierRow()] }))
  on(
    'GET',
    '/delivery/carriers/([a-z0-9-]+)',
    ({ params }) => (known(params[0]!), { data: carrierDetail() }),
  )

  on('PUT', '/delivery/carriers/([a-z0-9-]+)', ({ params, body }) => {
    known(params[0]!)

    if ('enabled' in body) state.carrier.enabled = Boolean(body.enabled)
    if (body.settings && typeof body.settings === 'object')
      Object.assign(state.carrier.settings, body.settings)

    for (const [name, value] of Object.entries(
      (body.credentials ?? {}) as Record<string, string>,
    )) {
      if (typeof value === 'string' && value.trim() !== '')
        state.carrier.credentials[name] = value.trim()
    }

    for (const name of (body.clear ?? []) as string[]) delete state.carrier.credentials[name]

    return { data: carrierDetail() }
  })

  on('POST', '/delivery/carriers/([a-z0-9-]+)/test', ({ params }) => {
    known(params[0]!)

    return new Reply(200, {
      data: state.carrier.credentials.api_key
        ? { ok: true, message: null }
        : { ok: false, message: 'The API key is not set.' },
    })
  })

  on('POST', '/delivery/carriers/([a-z0-9-]+)/sync/([a-z]+)', ({ params, locale }) => {
    known(params[0]!)

    if (!state.carrier.enabled) throw refuse('carrier', words(locale, 'errors.carrier_off'))

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
      carriers: ['carrier-a'],
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
        localities: state.localities.filter((each) => each.region_id === one.id).length,
      }))
      .sort((a, b) => a.name.localeCompare(b.name)),
  }))

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

    const one = { id: ++next, country, names, code: (body.code as string | null) ?? null }

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
      record: { ...one.record, lat: null, lng: null },
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
