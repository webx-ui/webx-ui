import type { LocalizedValue } from '../../../../packages/core/src/composables/useLocalized'
import type { CategoryRow } from '../../../../packages/module-admin/src/categories/types'
import type { BlockNode } from '../../../../packages/module-blocks/src/types'
import type {
  ServiceCategoryRef,
  ServiceCover,
  ServiceRow,
  ServiceStatus,
  ServiceVersion,
} from '../../../../packages/module-services/src/types'
import type { ScreenModel } from '../../../../packages/schema/src/types'
import { fileByPath } from './media'

/**
 * The catalogue the playground edits: services with their drafts, and the categories they are
 * filed under — the demo `webx:demo` seeds on a real site (`resources/demo/services.json`), with
 * a Russian side added, because the Russian panel is what the demo sites run.
 *
 * Two orders live here, and that is the point of the fixture: every service has a place in the
 * whole list (`position`), and every category keeps its own order of the services in it
 * (`items`). "Site maintenance" is in two categories — last in one, first in the other — so a
 * drag inside one category can be seen not to move it in the other.
 */

/** The first segment of every services address, the categories' included (§4.3). */
export const PREFIX = 'services'

export interface ServiceRecord {
  id: number
  /** What the editor opens: the draft laid over what was last published. */
  values: ScreenModel
  /** What the site is showing, or `null` for a service that has never been on it. */
  live: ScreenModel | null
  status: ServiceStatus
  position: number
  published_at: string | null
  updated_at: string
  deleted_at: string | null
  versions: ServiceVersion[]
  /** What each publication put on the site, so restoring one has something to put back. */
  snapshots: Record<number, ScreenModel>
}

/**
 * A category as the fake server keeps it: the row the shared list draws, what its page edits,
 * and the order of the services inside it — the pivot's `position` on a real site.
 */
export interface ServiceCategoryRecord extends CategoryRow {
  services_count: number
  lead: LocalizedValue
  cover: ServiceCover | null
  seo: Record<string, unknown>
  extra: Record<string, unknown>
  items: number[]
}

export const services: ServiceRecord[] = []
export const serviceCategories: ServiceCategoryRecord[] = []

/**
 * The fields that go into the draft. The categories and the SEO card take effect when saved —
 * a category is a row in a pivot — so they never make a service "modified".
 */
const UNDRAFTED = ['categories', 'seo']

/* ------------------------------------------------------------------------- categories ----- */

function category(title: [string, string], slug: string, lead: [string, string]) {
  const id = serviceCategories.length + 1

  serviceCategories.push({
    id,
    name: title[0],
    title: { ru: title[0], en: title[1] },
    slug: { ru: slug, en: slug },
    lead: { ru: lead[0], en: lead[1] },
    path: `${PREFIX}/${slug}`,
    url: `https://webx-demo.test/${PREFIX}/${slug}`,
    is_visible: true,
    position: id,
    deleted_at: null,
    services_count: 0,
    cover: null,
    seo: {},
    extra: {},
    items: [],
  })
}

category(['Сайты', 'Websites'], 'websites', [
  '<p>Сайты из блоков — тем, кто их пишет, не приходится ждать релиза.</p>',
  '<p>Sites built from blocks, so the people who write them never wait for a release.</p>',
])
category(['Дизайн', 'Design'], 'design', [
  '<p>Как выглядит сайт — и правила, которые держат его таким.</p>',
  '<p>How a site looks, and the rules that keep it looking that way.</p>',
])
category(['Поддержка', 'Support'], 'support', [
  '<p>Что происходит после запуска.</p>',
  '<p>What happens after launch.</p>',
])

/* --------------------------------------------------------------------------- services ----- */

const COVERS = ['pages/services.svg', 'pages/office.svg']

function block(type: string, values: Record<string, unknown>): BlockNode {
  return { key: `b${Math.random().toString(36).slice(2, 8)}`, type, values }
}

interface Seed {
  title: [string, string]
  slug: string
  lead: [string, string]
  cover: number
  status?: ServiceStatus
  versions?: number
}

function service(seed: Seed): ServiceRecord {
  const id = services.length + 1
  const status = seed.status ?? 'published'

  const values: ScreenModel = {
    title: { ru: seed.title[0], en: seed.title[1] },
    slug: { ru: seed.slug, en: seed.slug },
    lead: { ru: seed.lead[0], en: seed.lead[1] },
    blocks: [
      block('text', {
        title: { ru: 'Что входит', en: 'What is included' },
        body: {
          ru: `<p>${seed.lead[0]}</p><p>Это обычная услуга: её можно править, переставлять, публиковать и удалять.</p>`,
          en: `<p>${seed.lead[1]}</p><p>An ordinary service: edit it, reorder it, publish it, delete it.</p>`,
        },
      }),
      block('text', {
        title: { ru: 'Два порядка', en: 'Two orders' },
        body: {
          ru: '<p>У списка услуг свой порядок, и у каждой категории — ещё один. Перетащите услугу с выбранной категорией, и изменится только она.</p>',
          en: '<p>The list of services has an order of its own, and every category has another. Drag a service with a category chosen and only that category changes.</p>',
        },
      }),
    ],
    cover: { path: COVERS[seed.cover] },
    categories: [],
    seo: {},
  }

  const published = status === 'draft' ? null : '2026-09-10T09:00:00+00:00'
  const record: ServiceRecord = {
    id,
    values,
    live: status === 'draft' ? null : clone(values),
    status,
    position: id,
    published_at: published,
    updated_at: '2026-09-18T10:00:00+00:00',
    deleted_at: null,
    versions: [],
    snapshots: {},
  }

  const count = seed.versions ?? (record.live === null ? 0 : 1)

  for (let number = count; number > 0; number -= 1) {
    record.versions.push({
      number,
      created_at: shift('2026-09-10T09:00:00+00:00', (number - count) * 5),
      author: 'Анна Ковальчук',
      source: 'panel',
      comment: number === 1 ? 'Первая публикация' : null,
      is_pinned: false,
    })

    record.snapshots[number] = clone(values)
  }

  services.push(record)

  return record
}

service({
  title: ['Лендинг', 'Landing page'],
  slug: 'landing-page',
  lead: [
    'Одна страница, одно предложение, одна кнопка — готово за неделю.',
    'One page, one offer, one button — ready in a week.',
  ],
  cover: 0,
  versions: 2,
})
service({
  title: ['Корпоративный сайт', 'Company website'],
  slug: 'company-website',
  lead: [
    'Страницы, блог и каталог услуг — всё в одной панели.',
    'Pages, a blog and a catalogue of services, all edited in one panel.',
  ],
  cover: 1,
  versions: 3,
})

/* On the site, with edits waiting: the draft moved on and the site did not. */
const catalogue = service({
  title: ['Онлайн-каталог', 'Online catalogue'],
  slug: 'online-catalogue',
  lead: [
    'Каждый товар или услуга на своей странице, сгруппированные так, как их ищут покупатели.',
    'Every product or service on its own page, grouped the way customers look for them.',
  ],
  cover: 0,
})

catalogue.status = 'modified'
catalogue.values.lead = {
  ru: 'Каждый товар или услуга на своей странице — с фильтрами и сравнением.',
  en: 'Every product or service on its own page — with filters and comparison.',
}
catalogue.updated_at = '2026-09-22T08:15:00+00:00'

service({
  title: ['Поддержка сайта', 'Site maintenance'],
  slug: 'site-maintenance',
  lead: [
    'Обновления, резервные копии и мелкие правки каждый месяц. Лежит в двух категориях, и в каждой на своём месте.',
    'Updates, backups and small fixes, every month. Filed under two categories, and in a different place in each.',
  ],
  cover: 1,
})
service({
  title: ['Фирменный стиль', 'Brand identity'],
  slug: 'brand-identity',
  lead: [
    'Логотип, палитра и шрифт — и страница о том, как ими пользоваться.',
    'A logo, a palette and a typeface, with a page on how to use them.',
  ],
  cover: 0,
})

/* Never seen by anybody: no date, no history, nothing to compare against. */
service({
  title: ['Дизайн-система', 'Design system'],
  slug: 'design-system',
  lead: [
    'Компоненты и токены, из которых команда собирает каждый экран.',
    'Components and tokens a team builds every screen from.',
  ],
  cover: 1,
  status: 'draft',
})
service({
  title: ['SEO-аудит', 'SEO audit'],
  slug: 'seo-audit',
  lead: [
    'Что мешает сайту попасть на первую страницу выдачи — и в каком порядке это чинить.',
    'What stands between the site and the first page of results, and the order to fix it in.',
  ],
  cover: 0,
})
service({
  title: ['Редактура текстов', 'Content editing'],
  slug: 'content-editing',
  lead: [
    'Тексты для страниц, которые у сайта уже есть, — для тех, кто их читает.',
    'Texts for the pages the site already has, written for the people who read them.',
  ],
  cover: 1,
})

/* Filed the way `services.json` files them: maintenance last in Websites and first in Support. */
for (const [slug, members] of [
  ['websites', ['landing-page', 'company-website', 'online-catalogue', 'site-maintenance']],
  ['design', ['brand-identity', 'design-system']],
  ['support', ['site-maintenance', 'seo-audit', 'content-editing']],
] as const) {
  const found = categoryBySlug(slug)

  for (const member of members) {
    const record = serviceBySlug(member)

    found.items.push(record.id)
    record.values.categories = [...categoryIds(record), found.id]
    if (record.live !== null) record.live.categories = clone(record.values.categories)
    for (const snapshot of Object.values(record.snapshots)) {
      snapshot.categories = clone(record.values.categories)
    }
  }
}

recount()

/* ---------------------------------------------------------------------------- reading ----- */

/** Counts are derived: every save and delete would otherwise have to keep them. */
export function recount(): void {
  for (const row of serviceCategories) {
    row.services_count = row.items.filter((id) => find(id)?.deleted_at === null).length
  }
}

/**
 * Files a service under exactly these categories, in this order: a category it joins takes it
 * at the end of its own order, one it leaves forgets it, and the rest keep their place.
 */
export function file(record: ServiceRecord, ids: number[]): void {
  const known = ids.filter((id) => serviceCategories.some((one) => one.id === id))

  for (const row of serviceCategories) {
    const inside = row.items.includes(record.id)
    const wanted = known.includes(row.id)

    if (wanted && !inside) row.items.push(record.id)
    if (!wanted && inside) row.items = row.items.filter((id) => id !== record.id)
  }

  record.values.categories = [...new Set(known)]
  recount()
}

export function categoryIds(record: ServiceRecord): number[] {
  const value = record.values.categories

  return Array.isArray(value) ? value.filter((one): one is number => typeof one === 'number') : []
}

export function find(id: number): ServiceRecord | null {
  return services.find((record) => record.id === id) ?? null
}

export function findCategory(id: number): ServiceCategoryRecord | null {
  return serviceCategories.find((row) => row.id === id) ?? null
}

function serviceBySlug(slug: string): ServiceRecord {
  const found = services.find((record) => text(record.values.slug, 'en') === slug)

  if (found === undefined) throw new Error(`No service ${slug} in the fixture.`)

  return found
}

function categoryBySlug(slug: string): ServiceCategoryRecord {
  const found = serviceCategories.find((row) => text(row.slug, 'en') === slug)

  if (found === undefined) throw new Error(`No category ${slug} in the fixture.`)

  return found
}

/**
 * One service as a screen reads it — built per request, because the address and the titles of
 * its categories are in the language the panel is open in (`ServiceResource`).
 */
export function row(record: ServiceRecord, locale: string): ServiceRow {
  const live = record.live === null ? '' : text(record.live.slug, locale)
  const path = live === '' ? null : `${PREFIX}/${live}`

  return {
    id: record.id,
    title: text(record.values.title, locale) || text(record.values.slug, locale) || `#${record.id}`,
    slug: text(record.values.slug, locale),
    lead: text(record.values.lead, locale),
    path,
    url: path === null ? null : `https://webx-demo.test/${path}`,
    status: record.status,
    position: record.position,
    published_at: record.published_at,
    updated_at: record.updated_at,
    deleted_at: record.deleted_at,
    cover: cover(record),
    categories: categoryIds(record).flatMap((id) => named(id, locale)),
    revision: revision(record),
  }
}

/** A category as the shared list draws it: the row and its count, not what its page edits. */
export function categoryRow(row: ServiceCategoryRecord, locale: string) {
  return {
    id: row.id,
    name: text(row.title, locale) || row.name,
    title: row.title,
    slug: row.slug,
    path: row.path,
    url: row.url,
    is_visible: row.is_visible,
    position: row.position,
    deleted_at: row.deleted_at,
    services_count: row.services_count,
  }
}

/** What the editor read the service as — a save carrying an older one is refused with a 409. */
export function revision(record: ServiceRecord): string {
  return `${record.id}:${record.updated_at}`
}

/**
 * Whether what is written still matches what the site is showing — only for a service on the
 * site: "draft" has nothing to compare against, and "taken off" is a fact about the site.
 */
export function restate(record: ServiceRecord): void {
  if (record.live === null || (record.status !== 'published' && record.status !== 'modified')) {
    return
  }

  record.status = drafted(record.values) === drafted(record.live) ? 'published' : 'modified'
}

/** Everything that waits in the draft: the text and whatever a project patched on (`extra`). */
export function draftedFields(values: ScreenModel): string[] {
  return Object.keys(values).filter((name) => !UNDRAFTED.includes(name))
}

function drafted(values: ScreenModel): string {
  return draftedFields(values)
    .sort()
    .map((name) => JSON.stringify(values[name] ?? null))
    .join('|')
}

export function clone<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T
}

function cover(record: ServiceRecord): ServiceCover | null {
  const value = record.values.cover as { path?: unknown } | null | undefined
  const file = typeof value?.path === 'string' ? fileByPath(value.path) : null

  return file === null ? null : { id: file.id, path: file.path, url: file.url, thumb: file.thumb }
}

function named(id: number, locale: string): [ServiceCategoryRef] | [] {
  const found = findCategory(id)

  if (found === null) return []

  return [
    {
      id,
      title: text(found.title, locale) || found.name,
      slug: text(found.slug, locale),
    },
  ]
}

/** One language out of a localized value — and nothing else: no falling back onto another. */
export function text(value: unknown, locale: string): string {
  if (typeof value === 'string') return value
  if (value === null || typeof value !== 'object') return ''

  const map = value as LocalizedValue

  return typeof map[locale] === 'string' ? map[locale] : ''
}

function shift(from: string, days: number): string {
  const at = new Date(from)

  at.setUTCDate(at.getUTCDate() + days)

  return at.toISOString().replace(/\.\d+Z$/, '+00:00')
}
