import { fileByPath } from './media'

/**
 * Banners (`module-banners`): places, the banners in them, and their one order per place.
 *
 * Only the panel half: banners reach a site through a template helper and nothing else
 * (decision 7 of the spec), so there is no block, no source and no page to preview here. The
 * answers are the ones §5.6 fixes — a declared place exists before its row does, a refusal
 * comes back under the name of the field, a button's under `buttons.<n>.<field>` — and nothing
 * is written when something is refused, not even the lazy row of a place.
 */

type Localized = Record<string, string>

/** The value of `wx-media`: a key in the library, never an address; `alt` and `title` by language. */
type Media = { path: string; alt?: unknown; title?: unknown }

export interface Button {
  label: Localized
  link: Record<string, unknown>
  variant: string
}

export interface Banner {
  id: number
  place_id: number
  image: Media | null
  image_mobile: Media | null
  video: Media | null
  title: Localized
  text: Localized
  buttons: Button[]
  enabled: boolean
  position: number
  extra: Record<string, unknown>
  updated_at: string
  deleted_at: string | null
}

/** A row of `banner_places`: made by the first banner of a declared place, or by hand. */
interface PlaceRecord {
  id: number
  key: string
  title: Localized
}

/** Thrown for what the server refuses; `index.ts` turns it into its own failure. */
export class Refusal extends Error {
  constructor(
    readonly status: number,
    message: string,
    readonly errors?: Record<string, string[]>,
    readonly extra?: Record<string, unknown>,
  ) {
    super(message)
  }
}

const STAMP = '2026-09-28T09:00:00+00:00'
/** The language a place's name is required in, and the one a title falls back on. */
const DEFAULT = 'ru'

/**
 * `webx-banners.places` of this demo site: the package's two, as its config ships them. Their
 * names are translation keys the panel reads in its own language.
 */
export const DECLARED: Record<string, { title: string; layout: string }> = {
  hero: { title: 'places.hero', layout: 'slider' },
  promo: { title: 'places.promo', layout: 'single' },
}

/** `webx-banners.layout`: the layout of a place that names none. */
const LAYOUT = 'slider'

/** `webx-banners.variants`: the looks of a button. The first one is the fallback (decision 11). */
export const VARIANTS: Record<string, string> = {
  primary: 'Primary',
  secondary: 'Secondary',
  link: 'Link',
}

/*
 * `hero` has a row — its banners made it; `promo` has none yet: declared, empty, and listed all
 * the same (decision 1). `blog-side` is a place somebody made in the panel.
 */
const places: PlaceRecord[] = [
  { id: 1, key: 'hero', title: {} },
  { id: 2, key: 'blog-side', title: { ru: 'Колонка блога', en: 'Blog sidebar' } },
]

const page = (id: number) => ({
  target: 'entity',
  entity_type: 'page',
  entity_id: id,
  url: null,
  hash: null,
  new_tab: false,
  rel: [],
})

const address = (url: string, newTab = false) => ({
  target: 'url',
  entity_type: null,
  entity_id: null,
  url,
  hash: null,
  new_tab: newTab,
  rel: newTab ? ['nofollow'] : [],
})

export const banners: Banner[] = [
  banner(1, 1, {
    image: { path: 'banners/spring-sale.svg' },
    image_mobile: { path: 'banners/spring-sale-mobile.svg' },
    title: { ru: 'Весенняя распродажа', en: 'Spring sale' },
    text: {
      ru: 'Сайт под ключ со скидкой 20 % до конца апреля.\nПоддержка — первый месяц бесплатно.',
      en: 'A turnkey site 20% off until the end of April.\nThe first month of support is free.',
    },
    buttons: [
      { label: { ru: 'Оставить заявку', en: 'Get in touch' }, link: page(2), variant: 'primary' },
      {
        label: { ru: 'Условия', en: 'Terms' },
        link: address('https://example.com/terms', true),
        variant: 'link',
      },
    ],
    enabled: true,
  }),
  banner(2, 1, {
    image: { path: 'banners/studio.svg' },
    video: { path: 'banners/showreel.mp4' },
    title: { ru: 'Студия за работой', en: 'The studio at work' },
    text: { ru: 'Две минуты о том, как мы делаем сайты.', en: 'Two minutes on how we make sites.' },
    // A look this site dropped from its config since: it goes back as it came (decision 11).
    buttons: [{ label: { ru: 'Наши работы', en: 'Our work' }, link: page(3), variant: 'ghost' }],
    enabled: true,
  }),
  /* No Russian words: not on the Russian site at all (decision 12). */
  banner(3, 1, {
    image: { path: 'banners/support.svg' },
    title: { ru: '', en: 'Support around the clock' },
    text: { ru: '', en: 'Somebody answers within the hour, weekends too.' },
    enabled: true,
  }),
  /* Switched off: in the list, quieter, and nowhere on the site. */
  banner(4, 1, {
    image: { path: 'banners/workshop.svg' },
    title: { ru: 'Воркшоп по дизайну', en: 'A design workshop' },
    buttons: [
      { label: { ru: 'Записаться', en: 'Sign up' }, link: address('/events'), variant: 'primary' },
    ],
    enabled: false,
  }),
  banner(5, 2, {
    image: { path: 'banners/blog-side.svg' },
    title: { ru: 'Рассылка раз в месяц', en: 'A monthly letter' },
    buttons: [
      {
        label: { ru: 'Подписаться', en: 'Subscribe' },
        link: address('/subscribe'),
        variant: 'secondary',
      },
    ],
    enabled: true,
  }),
  /* In the bin of `hero`: the only way back to it is its place's bin. */
  banner(6, 1, {
    image: { path: 'banners/spring-sale.svg' },
    title: { ru: 'Зимняя распродажа', en: 'Winter sale' },
    enabled: false,
    deleted_at: '2026-09-27T12:00:00+00:00',
  }),
]

function banner(
  id: number,
  placeId: number,
  seed: Partial<Omit<Banner, 'id' | 'place_id'>>,
): Banner {
  return {
    image: null,
    image_mobile: null,
    video: null,
    title: {},
    text: {},
    buttons: [],
    enabled: false,
    position: id,
    extra: {},
    updated_at: STAMP,
    deleted_at: null,
    ...seed,
    id,
    place_id: placeId,
  }
}

/* ------------------------------------------------------------------------------ places ----- */

const live = (one: { deleted_at: string | null }): boolean => one.deleted_at === null

/** The options of the look select, patched onto `banners.form` from the config (§5.3). */
export function variantOptions(): { value: string; label: string }[] {
  return Object.entries(VARIANTS).map(([value, label]) => ({ value, label }))
}

function rowOf(key: string): PlaceRecord | null {
  return places.find((one) => one.key === key) ?? null
}

/** In the config or in the table: a place a banner can stand in. */
export function known(key: string): boolean {
  return key in DECLARED || rowOf(key) !== null
}

/**
 * One place as the list draws it (§5.6). `name` reads a declared place's title key in the
 * panel's language — the server's `__()`.
 */
function placeRow(
  key: string,
  locale: string,
  name: (key: string) => string,
): Record<string, unknown> {
  const row = rowOf(key)
  const declared = DECLARED[key]
  const count =
    row === null ? 0 : banners.filter((one) => one.place_id === row.id && live(one)).length

  return {
    id: row?.id ?? null,
    key,
    title: declared ? name(declared.title) : pick(row?.title ?? {}, locale) || key,
    // Every language of an own place's name, for the rename dialog (not in §5.6 — see B2's notes).
    ...(declared ? {} : { titles: { ...(row?.title ?? {}) } }),
    declared: declared !== undefined,
    layout: declared?.layout ?? LAYOUT,
    count,
  }
}

/** `GET /banners/places`: the declared ones in the config's order, then the own ones by name. */
export function listPlaces(
  locale: string,
  name: (key: string) => string,
): Record<string, unknown>[] {
  const own = places
    .filter((one) => !(one.key in DECLARED))
    .map((one) => placeRow(one.key, locale, name))
    .sort((a, b) => String(a.title).localeCompare(String(b.title)))

  return [...Object.keys(DECLARED).map((key) => placeRow(key, locale, name)), ...own]
}

export function createPlace(
  body: Record<string, unknown>,
  locale: string,
  name: (key: string) => string,
): Record<string, unknown> {
  const key = typeof body.key === 'string' ? body.key.trim() : ''
  const title = asMap(body.title)
  const errors: Record<string, string[]> = {}

  if (!/^[a-z][a-z0-9-]{0,63}$/.test(key)) {
    errors.key = ['Латинские строчные буквы, цифры и дефис, с буквы, до 64 символов.']
  } else if (known(key)) {
    errors.key = ['Место с таким ключом уже есть.']
  }

  if (said(title[DEFAULT]) === '')
    errors[`title.${DEFAULT}`] = ['Название нужно на языке по умолчанию.']

  if (Object.keys(errors).length > 0) throw new Refusal(422, 'Invalid', errors)

  places.push({ id: nextPlaceId(), key, title })

  return placeRow(key, locale, name)
}

/** Only an own place, and only its name; the languages sent replace the ones there. */
export function renamePlace(
  key: string,
  body: Record<string, unknown>,
  locale: string,
  name: (key: string) => string,
): Record<string, unknown> {
  const row = ownPlace(key)
  const title = { ...row.title, ...asMap(body.title) }

  if (said(title[DEFAULT]) === '') {
    throw new Refusal(422, 'Invalid', {
      [`title.${DEFAULT}`]: ['Название нужно на языке по умолчанию.'],
    })
  }

  row.title = title

  return placeRow(key, locale, name)
}

/** Only an own place with nothing in it — the bin counts: the cascade would take it too. */
export function deletePlace(key: string): void {
  const row = ownPlace(key)
  const count = banners.filter((one) => one.place_id === row.id).length

  if (count > 0) {
    throw new Refusal(
      422,
      'Invalid',
      { place: [`В месте есть баннеры, включая корзину: ${count}. Сначала уберите их.`] },
      { count },
    )
  }

  places.splice(places.indexOf(row), 1)
}

function ownPlace(key: string): PlaceRecord {
  if (key in DECLARED) throw new Refusal(403, 'A declared place is the config’s to change.')

  const row = rowOf(key)

  if (row === null) throw new Refusal(404, 'No such place.')

  return row
}

function nextPlaceId(): number {
  return Math.max(0, ...places.map((one) => one.id)) + 1
}

/** The row of a place, made on the spot for a declared one that has none yet (§5.1). */
function ensureRow(key: string): PlaceRecord {
  const row = rowOf(key)

  if (row !== null) return row

  const made = { id: nextPlaceId(), key, title: {} }

  places.push(made)

  return made
}

/* ----------------------------------------------------------------------------- banners ----- */

/** One banner as the list line draws it (§5.6). */
export function bannerRow(record: Banner, locale: string): Record<string, unknown> {
  const picture = record.image === null ? null : fileByPath(record.image.path)

  return {
    id: record.id,
    title: pick(record.title, locale) || `#${record.id}`,
    thumb: picture?.thumb ?? null,
    video: record.video !== null,
    enabled: record.enabled,
    position: record.position,
    updated_at: record.updated_at,
    deleted_at: record.deleted_at,
  }
}

/** `GET /banners/places/{key}/banners`: the place in its order, or its bin. */
export function listBanners(
  key: string,
  trashed: boolean,
  locale: string,
): Record<string, unknown>[] {
  if (!known(key)) throw new Refusal(404, 'No such place.')

  const row = rowOf(key)

  if (row === null) return []

  const found = banners.filter((one) => one.place_id === row.id && live(one) !== trashed)

  found.sort((a, b) =>
    trashed ? String(b.deleted_at).localeCompare(String(a.deleted_at)) : a.position - b.position,
  )

  return found.map((one) => bannerRow(one, locale))
}

/** One banner as its form opens it (`BannerForm::describe()`). */
export function bannerDetail(record: Banner, locale: string): Record<string, unknown> {
  return {
    banner: {
      id: record.id,
      place: places.find((one) => one.id === record.place_id)?.key ?? '',
      title: pick(record.title, locale) || `#${record.id}`,
      enabled: record.enabled,
      deleted_at: record.deleted_at,
    },
    values: {
      ...record.extra,
      image: record.image === null ? null : { ...record.image },
      image_mobile: record.image_mobile === null ? null : { ...record.image_mobile },
      video: record.video === null ? null : { ...record.video },
      title: { ...record.title },
      text: { ...record.text },
      buttons: record.buttons.map((one) => ({
        ...one,
        label: { ...one.label },
        link: { ...one.link },
      })),
      enabled: record.enabled,
    },
  }
}

export function findBanner(id: number): Banner | null {
  return banners.find((one) => one.id === id) ?? null
}

/**
 * The values of `banners.form`, checked and written — a new banner in `place` when `record` is
 * `null`, a move to the end of `place` when it names another one (decision 14). Nothing is
 * written when something is refused: not the banner, not the lazy row of its place.
 */
export function writeBanner(
  record: Banner | null,
  values: Record<string, unknown>,
  place: string | null,
): Banner {
  const errors: Record<string, string[]> = {}

  if (place !== null && !known(place)) errors.place = ['Такого места нет.']

  const merged = (name: string, fallback: unknown): unknown =>
    values[name] !== undefined ? values[name] : fallback

  const image = media(merged('image', record?.image ?? null))
  const imageMobile = media(merged('image_mobile', record?.image_mobile ?? null))
  const video = media(merged('video', record?.video ?? null))

  // The one required field (decision 8), and the reason a video alone is refused under it too.
  if (image === null) {
    errors.image = [
      video === null
        ? 'Нужна картинка.'
        : 'Нужна картинка: без неё видео не показать — она его постер и замена.',
    ]
  }

  for (const [name, value, kind] of [
    ['image', image, 'image'],
    ['image_mobile', imageMobile, 'image'],
    ['video', video, 'video'],
  ] as const) {
    if (value === null || errors[name]) continue

    const file = fileByPath(value.path)

    if (file === null) errors[name] = ['Этого файла нет в библиотеке.']
    else if (file.type !== kind) {
      errors[name] = [kind === 'image' ? 'Сюда нужна картинка.' : 'Сюда нужно видео.']
    }
  }

  let buttons: Button[] | undefined

  if (values.buttons !== undefined) {
    const rows = asRows(values.buttons)

    buttons = []

    if (rows.length > 3) errors.buttons = ['Не больше трёх кнопок.']

    rows.forEach((row, index) => {
      const label = asMap(row.label)
      const link =
        row.link !== null && typeof row.link === 'object'
          ? (row.link as Record<string, unknown>)
          : null
      const variant = typeof row.variant === 'string' ? row.variant : ''
      const at = `buttons.${index}`
      const hasLabel = Object.values(label).some((words) => said(words) !== '')
      const pointed = link !== null && linked(link)

      // Neither a word nor a link: a row somebody added and left — dropped, not refused (§5.6).
      if (!hasLabel && !pointed) return

      if (!pointed) errors[`${at}.link`] = ['Укажите, куда ведёт кнопка.']

      // A look the banner already has passes back even when the config dropped it (decision 11).
      const kept = record?.buttons.some((one) => one.variant === variant) ?? false

      if (variant === '') errors[`${at}.variant`] = ['Выберите вид кнопки.']
      else if (!(variant in VARIANTS) && !kept)
        errors[`${at}.variant`] = ['Такого вида нет в настройках сайта.']

      buttons!.push({ label, link: link ?? {}, variant })
    })
  }

  if (Object.keys(errors).length > 0) throw new Refusal(422, 'Invalid', errors)

  const target =
    record ?? banner(Math.max(0, ...banners.map((one) => one.id)) + 1, 0, { enabled: false })

  target.image = image
  target.image_mobile = imageMobile
  target.video = video

  for (const [name, value] of Object.entries(values)) {
    if (name === 'title') target.title = asMap(value)
    else if (name === 'text') target.text = asMap(value)
    else if (name === 'enabled') target.enabled = value === true
    else if (!['image', 'image_mobile', 'video', 'buttons'].includes(name))
      target.extra[name] = value
  }

  if (buttons !== undefined) target.buttons = buttons

  // A new banner, and one moved, stands last in its place (`max(position) + 1`, §5.1).
  const moving = place !== null && places.find((one) => one.id === target.place_id)?.key !== place

  if (record === null || moving) {
    const row = ensureRow(place as string)
    const last = banners.filter((one) => one.place_id === row.id && one !== target)

    target.place_id = row.id
    target.position = Math.max(0, ...last.map((one) => one.position)) + 1
  }

  if (record === null) banners.push(target)

  target.updated_at = new Date().toISOString()

  return target
}

/** The order on screen, written whole: every id a banner of this place outside the bin. */
export function reorderBanners(key: string, ids: unknown): void {
  if (!known(key)) throw new Refusal(404, 'No such place.')

  if (!Array.isArray(ids)) throw new Refusal(422, 'Invalid', { ids: ['Нужен список.'] })

  const row = rowOf(key)
  const moved = ids.map((id) => banners.find((one) => one.id === Number(id)) ?? null)

  if (row === null || moved.some((one) => one === null || one.place_id !== row.id || !live(one))) {
    throw new Refusal(422, 'Invalid', { ids: ['Не все эти баннеры стоят в этом месте.'] })
  }

  moved.forEach((one, index) => {
    one!.position = index + 1
  })
}

/* ---------------------------------------------------------------------------- helpers ----- */

/** The language asked for, or the default one where it is empty — the panel names what it can. */
function pick(value: Localized, locale: string): string {
  return said(value[locale]) || said(value[DEFAULT]) || said(value.en)
}

function said(value: string | undefined): string {
  return (value ?? '').trim()
}

/** Somewhere to go: a chosen record, or an address that is not empty. */
function linked(link: Record<string, unknown>): boolean {
  if (link.target === 'entity')
    return typeof link.entity_type === 'string' && Number(link.entity_id) > 0
  if (link.target === 'url') return typeof link.url === 'string' && link.url.trim() !== ''

  return false
}

function asMap(value: unknown): Localized {
  if (typeof value === 'string') return { [DEFAULT]: value }
  if (value === null || typeof value !== 'object') return {}

  const out: Localized = {}

  for (const [code, words] of Object.entries(value as Record<string, unknown>)) {
    out[code] = typeof words === 'string' ? words : ''
  }

  return out
}

function asRows(value: unknown): Record<string, unknown>[] {
  return Array.isArray(value)
    ? value.filter((row): row is Record<string, unknown> => typeof row === 'object' && row !== null)
    : []
}

function media(value: unknown): Media | null {
  if (value === null || typeof value !== 'object') return null

  const path = (value as { path?: unknown }).path

  return typeof path === 'string' && path !== '' ? { ...(value as Media), path } : null
}
