import type { LocalizedValue } from '../../../../packages/core/src/composables/useLocalized'
import type { BlockNode } from '../../../../packages/module-blocks/src/types'
import type {
  ArticleCover,
  ArticleRow,
  ArticleStatus,
  ArticleVersion,
  BlogNamed,
  RubricRow,
  TagRow,
} from '../../../../packages/module-blog/src/types'
import type { ScreenModel } from '../../../../packages/schema/src/types'
import { fileByPath } from './media'

/**
 * The blog the playground edits: articles with their drafts, the rubrics they are filed under
 * and the tags they carry.
 *
 * What is stored here is the record and not the row: an article shows a different address in
 * every language and a different state depending on whether what is written matches what is on
 * the site, so the row a screen reads is built per request — the same thing `ArticleResource`
 * does on the server, for the same reason.
 *
 * Wide enough to page through, and with one of each state the section is actually made of: a
 * draft that has never been seen, one waiting for its day, one on the site, one on the site
 * with edits waiting, one taken off it, and one in the bin.
 */

/** The first segment of every blog address — the same in every language (§2.2). */
export const PREFIX = 'blog'

export const authors: BlogNamed[] = [
  { id: 1, title: 'Анна Ковальчук' },
  { id: 2, title: 'Дмитрий Левченко' },
  { id: 3, title: 'Ольга Сидоренко' },
]

export interface ArticleRecord {
  id: number
  /** What the editor opens: the draft laid over what was last published. */
  values: ScreenModel
  /** What the site is showing, or `null` for an article that has never been on it. */
  live: ScreenModel | null
  status: ArticleStatus
  published_at: string | null
  updated_at: string
  deleted_at: string | null
  versions: ArticleVersion[]
  /**
   * What each publication put on the site.
   *
   * The API never carries it — a version in the history is a number, a day and who pressed the
   * button — but restoring one has to put something back, and a fixture that answered `ok` and
   * changed nothing would be the one place this playground lies about what the panel does.
   */
  snapshots: Record<number, ScreenModel>
}

export const articles: ArticleRecord[] = []
export const rubrics: RubricRow[] = []
export const tags: TagRow[] = []

let nextArticleId = 1
let nextRubricId = 1
let nextTagId = 1

/* ---------------------------------------------------------------------------- rubrics ----- */

function rubric(title: [string, string], slug: string, lead: [string, string]): RubricRow {
  const id = nextRubricId++
  const row: RubricRow = {
    id,
    name: title[0],
    title: { ru: title[0], en: title[1] },
    slug: { ru: slug, en: slug },
    lead: { ru: lead[0], en: lead[1] },
    path: `${PREFIX}/${slug}`,
    url: `https://webx-demo.test/${PREFIX}/${slug}`,
    cover: null,
    is_visible: true,
    position: id,
    articles_count: 0,
    seo: {},
  }

  rubrics.push(row)

  return row
}

rubric(['Разработка', 'Development'], 'development', [
  'Как мы собираем сайты и панели — и почему именно так.',
  'How we build sites and panels.',
])
rubric(['Дизайн', 'Design'], 'design', [
  'Интерфейсы, токены и то, что видно только на настоящем устройстве.',
  'Interfaces, tokens and what only a real device shows.',
])
rubric(['Поддержка', 'Support'], 'support', [
  'Что происходит с сайтом после запуска.',
  'What happens to a site after launch.',
])
rubric(['Кейсы', 'Cases'], 'cases', [
  'Проекты целиком: задача, решение и что из этого вышло.',
  'Whole projects: the problem, the answer and what came of it.',
])

/* ------------------------------------------------------------------------------- tags ----- */

function tag(title: string, slug: string, noindex = true): TagRow {
  const id = nextTagId++
  const row: TagRow = {
    id,
    title,
    titles: { ru: title, en: title },
    slug,
    path: `${PREFIX}/tag/${slug}`,
    url: `https://webx-demo.test/${PREFIX}/tag/${slug}`,
    noindex,
    // A tag is out of the index until somebody decides otherwise; `rule` is the third answer —
    // somebody wrote a rule for its address in the SEO section (§12).
    indexing: noindex ? 'noindex' : 'open',
    articles_count: 0,
  }

  tags.push(row)

  return row
}

for (const [title, slug] of [
  ['Laravel', 'laravel'],
  ['Vue', 'vue'],
  ['Дизайн-система', 'design-system'],
  ['Токены', 'tokens'],
  ['Производительность', 'performance'],
  ['Мобильные', 'mobile'],
  ['Миграция', 'migration'],
  ['SEO', 'seo'],
  ['Доступность', 'accessibility'],
  ['Инструменты', 'tools'],
  ['Процесс', 'process'],
  ['Тестирование', 'testing'],
] as const) {
  tag(title, slug)
}

/* A word that is in the index because a rule was written for its address, not because of us. */
const seoTag = tags.find((one) => one.slug === 'seo')

if (seoTag !== undefined) {
  seoTag.noindex = false
  seoTag.indexing = 'rule'
}

/* --------------------------------------------------------------------------- articles ----- */

/** A block instance, the way a page of the site holds one. */
function block(type: string, values: Record<string, unknown>): BlockNode {
  return { key: `b${Math.random().toString(36).slice(2, 8)}`, type, values }
}

interface Seed {
  title: [string, string]
  slug: string
  lead?: [string, string]
  status?: ArticleStatus
  published_at?: string | null
  updated_at?: string
  pinned?: boolean
  author?: number | null
  rubrics?: string[]
  tags?: string[]
  related?: number[]
  cover?: string | null
  blocks?: BlockNode[]
  seo?: Record<string, unknown>
  deleted_at?: string | null
  versions?: number
}

function article(seed: Seed): ArticleRecord {
  const id = nextArticleId++
  const status = seed.status ?? 'published'

  const values: ScreenModel = {
    title: { ru: seed.title[0], en: seed.title[1] },
    slug: { ru: seed.slug, en: seed.slug },
    lead: { ru: seed.lead?.[0] ?? '', en: seed.lead?.[1] ?? '' },
    blocks: seed.blocks ?? [
      block('text', {
        title: { ru: seed.title[0], en: seed.title[1] },
        body: {
          ru: `<p>${seed.lead?.[0] ?? 'Текст статьи ещё не написан.'}</p>`,
          en: `<p>${seed.lead?.[1] ?? 'Nothing written yet.'}</p>`,
        },
      }),
    ],
    cover: seed.cover === undefined || seed.cover === null ? null : { path: seed.cover },
    author_id: seed.author === undefined ? 1 : seed.author,
    pinned: seed.pinned ?? false,
    published_at: seed.published_at === undefined ? '2026-09-01T09:00:00+00:00' : seed.published_at,
    rubrics: (seed.rubrics ?? []).map((slug) => rubricBySlug(slug).id),
    tags: (seed.tags ?? []).map((slug) => tagBySlug(slug).id),
    related: seed.related ?? [],
    seo: seed.seo ?? {},
  }

  const record: ArticleRecord = {
    id,
    values,
    // An article that has never been on the site has nothing to compare against — which is
    // exactly what tells "draft" apart from "on the site with edits waiting".
    live: status === 'draft' || status === 'scheduled' ? null : clone(values),
    status,
    published_at: values.published_at as string | null,
    updated_at: seed.updated_at ?? '2026-09-10T10:00:00+00:00',
    deleted_at: seed.deleted_at ?? null,
    versions: [],
    snapshots: {},
  }

  /* Newest first, and the newest is the day the article went out: the ones before it are a
     week apart each, walking backwards. */
  const published = seed.versions ?? (record.live === null ? 0 : 1)

  for (let number = published; number > 0; number -= 1) {
    record.versions.push({
      number,
      created_at: shift(record.published_at ?? record.updated_at, (number - published) * 7),
      author: authors[(number + id) % authors.length].title,
      source: 'panel',
      comment: number === 1 ? 'Первая публикация' : null,
      is_pinned: false,
    })

    record.snapshots[number] = clone(values)
  }

  articles.push(record)

  return record
}

/** What an article with edits waiting looks like: the draft moved on, the site did not. */
function edited(record: ArticleRecord, change: (values: ScreenModel) => void): ArticleRecord {
  record.live = clone(record.values)
  change(record.values)
  record.status = 'modified'
  record.updated_at = '2026-09-19T08:15:00+00:00'

  return record
}

const roadmap = article({
  title: ['Панель, которую не приходится объяснять', 'A panel nobody has to explain'],
  slug: 'panel-bez-obucheniya',
  lead: [
    'Год мы переписывали административную панель так, чтобы редактор открывал её и просто работал. Рассказываем, что из этого получилось.',
    'A year of rewriting the admin panel so that an editor just opens it and works.',
  ],
  status: 'published',
  pinned: true,
  published_at: '2026-09-18T07:00:00+00:00',
  updated_at: '2026-09-18T07:00:00+00:00',
  author: 1,
  rubrics: ['development', 'design'],
  tags: ['design-system', 'vue', 'tokens'],
  cover: 'blog/2026/09/panel-roadmap.svg',
  versions: 4,
  seo: {
    title: { ru: 'Панель, которую не приходится объяснять', en: 'A panel nobody has to explain' },
    description: {
      ru: 'Как мы переписали административную панель и что изменилось для редакторов.',
      en: 'How we rewrote the admin panel and what changed for editors.',
    },
    robots: 'index,follow',
  },
  blocks: [
    block('hero', {
      eyebrow: { ru: 'Дизайн-система', en: 'Design system' },
      title: { ru: 'Панель, которую не приходится объяснять', en: 'A panel nobody has to explain' },
      subtitle: {
        ru: 'Девяносто четыре компонента, две темы и один принцип: редактор не должен догадываться.',
        en: 'Ninety-four components, two themes, one principle.',
      },
      button_label: { ru: 'Посмотреть демо', en: 'See the demo' },
      button_url: '/contacts',
    }),
    block('text', {
      title: { ru: 'С чего всё началось', en: 'Where it started' },
      body: {
        ru: '<p>Первая версия панели была собрана за две недели и прожила четыре года. За это время в ней завелось три способа сделать одно и то же — и ни одного очевидного.</p><p>Переписывать целиком мы не собирались. Собирались починить таблицу.</p>',
        en: '<p>The first version took two weeks and lasted four years.</p>',
      },
    }),
    block('features', {
      title: { ru: 'Что изменилось', en: 'What changed' },
      items: [
        {
          title: { ru: 'Одна таблица на всё', en: 'One table' },
          text: {
            ru: 'Списки, деревья и корзина — один компонент, который на телефоне превращается в карточки.',
            en: 'Lists, trees and the bin are one component.',
          },
        },
        {
          title: { ru: 'Экраны как описание', en: 'Screens as data' },
          text: {
            ru: 'Форма — это JSON, поэтому модуль добавляет вкладку патчем, а не форком файла.',
            en: 'A form is JSON, so a module adds a tab with a patch.',
          },
        },
        {
          title: { ru: 'Тёмная тема без «почти»', en: 'A dark theme without "almost"' },
          text: {
            ru: 'Ни одного захардкоженного цвета: компоненты знают только переменные.',
            en: 'Not a single hard-coded colour.',
          },
        },
      ],
    }),
    block('cta', {
      title: { ru: 'Хотите такую же?', en: 'Want one like it?' },
      text: { ru: 'Расскажите про проект — ответим за день.', en: 'Tell us about the project.' },
      button_label: { ru: 'Написать', en: 'Write' },
      button_url: '/contacts',
      background: '#10224b',
    }),
  ],
})

const queues = article({
  title: ['Очереди в Laravel: что ломается на проде', 'Laravel queues: what breaks in production'],
  slug: 'laravel-queues',
  lead: [
    'Шесть случаев, когда воркер молча перестаёт работать, и что с каждым из них делать.',
    'Six ways a worker quietly stops, and what to do about each.',
  ],
  published_at: '2026-09-16T09:30:00+00:00',
  updated_at: '2026-09-16T09:30:00+00:00',
  author: 2,
  rubrics: ['development'],
  tags: ['laravel', 'performance', 'tools'],
  cover: 'blog/2026/09/laravel-queues.svg',
  versions: 2,
  blocks: [
    block('text', {
      title: { ru: 'Воркер жив, задачи стоят', en: 'The worker lives, the jobs wait' },
      body: {
        ru: '<p>Самый частый случай: воркер запущен до деплоя и держит в памяти прежний код. Он не падает — он делает вчерашнюю работу.</p>',
        en: '<p>The commonest case: a worker started before the deploy.</p>',
      },
    }),
    block('faq', {
      title: { ru: 'Короткие ответы', en: 'Short answers' },
      items: [
        {
          question: { ru: 'Сколько воркеров держать?', en: 'How many workers?' },
          answer: { ru: 'По числу ядер минус один, и не больше.', en: 'Cores minus one.' },
        },
        {
          question: { ru: 'Нужен ли Horizon?', en: 'Do we need Horizon?' },
          answer: { ru: 'Если очередей больше двух — да.', en: 'More than two queues: yes.' },
        },
      ],
    }),
  ],
})

edited(
  article({
    title: ['Токены вместо согласований', 'Tokens instead of meetings'],
    slug: 'design-tokens',
    lead: [
      'Как один JSON-файл заменил переписку о том, какой у нас серый.',
      'How one JSON file replaced the argument about which grey is ours.',
    ],
    published_at: '2026-09-12T08:00:00+00:00',
    author: 3,
    rubrics: ['design'],
    tags: ['tokens', 'design-system', 'accessibility'],
    cover: 'blog/2026/09/design-tokens.svg',
    versions: 3,
  }),
  (values) => {
    values.lead = {
      ru: 'Как один JSON-файл заменил переписку о том, какой у нас серый — и что мы поняли через полгода.',
      en: 'How one JSON file replaced the argument about grey — and what six months taught us.',
    }
  },
)

article({
  title: ['Жесты, которые эмулятор не показывает', 'Gestures an emulator never shows'],
  slug: 'mobile-gestures',
  lead: [
    'Device mode врёт про касания. Список того, что проверяется только на настоящем телефоне.',
    'Device mode lies about touch. What only a real phone answers.',
  ],
  published_at: '2026-08-29T10:00:00+00:00',
  author: 1,
  rubrics: ['design', 'development'],
  tags: ['mobile', 'testing'],
  cover: 'blog/2026/08/mobile-gestures.svg',
  versions: 2,
})

article({
  title: ['«Альфатех»: магазин за одиннадцать недель', 'Alfatech: a shop in eleven weeks'],
  slug: 'case-alfatech',
  lead: [
    'Каталог на девятнадцать тысяч позиций, обмен с 1С и панель, в которой работают четыре человека.',
    'Nineteen thousand items, an ERP exchange and a panel four people work in.',
  ],
  published_at: '2026-08-21T09:00:00+00:00',
  author: 2,
  rubrics: ['cases'],
  tags: ['migration', 'laravel'],
  cover: 'blog/2026/08/case-alfatech.svg',
  versions: 2,
})

article({
  title: ['Поддержка по договору: что входит', 'Support under contract'],
  slug: 'support-sla',
  lead: [
    'Четыре часа на первый ответ, обновления раз в месяц и журнал того, что мы трогали.',
    'Four hours to first response, monthly updates, a log of what we touched.',
  ],
  published_at: '2026-08-11T08:00:00+00:00',
  author: 3,
  rubrics: ['support'],
  tags: ['process'],
  cover: 'blog/2026/08/support-sla.svg',
})

article({
  title: ['Переезд с самописной CMS', 'Moving off a home-made CMS'],
  slug: 'migration',
  lead: [
    'Тысяча двести страниц, двенадцать лет адресов и ни одного потерянного.',
    'Twelve hundred pages, twelve years of addresses, none of them lost.',
  ],
  published_at: '2026-07-30T09:00:00+00:00',
  author: 1,
  rubrics: ['cases', 'development'],
  tags: ['migration', 'seo'],
  cover: 'blog/2026/07/migration.svg',
  versions: 2,
})

/* The one waiting for its day: written, dated, and not on the site until then (§7). */
article({
  title: ['Что мы выпустим осенью', 'What ships this autumn'],
  slug: 'autumn-release',
  lead: [
    'Конструктор блоков, блог и медиатека — и почему именно в этом порядке.',
    'The block constructor, the blog and the library.',
  ],
  status: 'scheduled',
  published_at: '2026-10-01T07:00:00+00:00',
  updated_at: '2026-09-19T12:00:00+00:00',
  author: 1,
  rubrics: ['development'],
  tags: ['process', 'tools'],
})

/* Never seen by anybody: no date, no history, nothing to compare against. */
article({
  title: ['Черновик про производительность', 'A draft about performance'],
  slug: 'performance-draft',
  lead: ['Пока только план и три замера.', 'A plan and three measurements.'],
  status: 'draft',
  published_at: null,
  updated_at: '2026-09-19T15:30:00+00:00',
  author: 2,
  rubrics: ['development'],
  tags: ['performance'],
})

/* Was on the site and was taken off it — which is not the same thing as a draft (§10). */
article({
  title: ['Акция «Лето»: условия', 'Summer offer: terms'],
  slug: 'summer-terms',
  lead: ['Акция закончилась, статья осталась.', 'The offer ended, the article stayed.'],
  status: 'unpublished',
  published_at: '2026-06-01T08:00:00+00:00',
  updated_at: '2026-09-02T09:00:00+00:00',
  author: 3,
  rubrics: ['support'],
  tags: [],
})

/* In the bin: the only way back to it is the bin's own list. */
article({
  title: ['Старый анонс вебинара', 'An old webinar announcement'],
  slug: 'webinar-2025',
  status: 'published',
  published_at: '2025-11-10T08:00:00+00:00',
  updated_at: '2026-09-15T12:00:00+00:00',
  deleted_at: '2026-09-15T12:00:00+00:00',
  author: 1,
  rubrics: ['development'],
  tags: ['tools'],
})

/* Enough rows below the interesting ones that the list pages, sorts and filters for real. */
const filler: Array<[string, string, string, string[]]> = [
  ['Почему мы не используем сборщики страниц', 'no-page-builders', 'development', ['tools']],
  ['Контейнерные запросы на практике', 'container-queries', 'design', ['tokens', 'mobile']],
  ['Сколько стоит поддержка', 'support-price', 'support', ['process']],
  ['Как мы считаем сроки', 'estimates', 'support', ['process']],
  ['Тесты, которые ничего не проверяют', 'useless-tests', 'development', ['testing']],
  ['Адреса, которые нельзя терять', 'urls-matter', 'development', ['seo', 'migration']],
  ['Тёмная тема: чего мы не ожидали', 'dark-theme', 'design', ['tokens']],
  ['Клавиатура и фокус', 'keyboard-focus', 'design', ['accessibility']],
  ['Кейс: сайт клиники', 'case-clinic', 'cases', ['laravel']],
  ['Кейс: каталог оборудования', 'case-equipment', 'cases', ['laravel', 'performance']],
  ['Что мы храним в очередях', 'queue-payloads', 'development', ['laravel']],
  ['Медиатека: ключ вместо адреса', 'media-keys', 'development', ['tools']],
  ['Русская типографика в панели', 'typography', 'design', ['tokens']],
  ['Как читать замеры Lighthouse', 'lighthouse', 'development', ['performance', 'seo']],
  ['Один экран — одна задача', 'one-screen', 'design', ['process']],
]

filler.forEach(([title, slug, section, words], index) => {
  article({
    title: [title, title],
    slug,
    lead: [`${title}: короткий пересказ для списка.`, `${title}: a short line for the list.`],
    published_at: shift('2026-07-20T09:00:00+00:00', -index * 6),
    updated_at: shift('2026-07-21T09:00:00+00:00', -index * 6),
    author: authors[index % authors.length].id,
    rubrics: [section],
    tags: words,
  })
})

/* Something to put in "related": the two articles the roadmap actually refers to. */
roadmap.values.related = [queues.id, articles[2].id]

recount()

/* ---------------------------------------------------------------------------- reading ----- */

/** Counts are derived: every save, delete and merge would otherwise have to keep them. */
export function recount(): void {
  for (const row of rubrics) row.articles_count = 0
  for (const row of tags) row.articles_count = 0

  for (const record of articles) {
    if (record.deleted_at !== null) continue

    for (const id of ids(record, 'rubrics')) {
      const row = rubrics.find((one) => one.id === id)

      if (row !== undefined) row.articles_count += 1
    }

    for (const id of ids(record, 'tags')) {
      const row = tags.find((one) => one.id === id)

      if (row !== undefined) row.articles_count += 1
    }
  }
}

export function ids(record: ArticleRecord, field: 'rubrics' | 'tags' | 'related'): number[] {
  const value = record.values[field]

  return Array.isArray(value) ? value.filter((one): one is number => typeof one === 'number') : []
}

export function find(id: number): ArticleRecord | null {
  return articles.find((record) => record.id === id) ?? null
}

export function rubricBySlug(slug: string): RubricRow {
  const row = rubrics.find((one) => one.slug.ru === slug)

  if (row === undefined) {
    throw new Error(`No rubric ${slug} in the fixture.`)
  }

  return row
}

export function tagBySlug(slug: string): TagRow {
  const row = tags.find((one) => one.slug === slug)

  if (row === undefined) {
    throw new Error(`No tag ${slug} in the fixture.`)
  }

  return row
}

/**
 * One article as a screen reads it.
 *
 * Built per request rather than kept, because two of its fields depend on who is asking: the
 * address is the slug of the language the panel is open in — and is `null` when that language
 * has none (§9) — and the titles of the rubrics and tags beside it are in that language too.
 */
export function row(record: ArticleRecord, locale: string): ArticleRow {
  const slug = text(record.values.slug, locale)

  /*
   * The title is the draft's and the address is the registry's, which is why a renamed article
   * shows a new title beside its old address: that is what the site is serving until somebody
   * publishes. The registry hears about an article when it goes out, so one that never has no
   * address at all — and the field that is being typed into says so rather than this.
   */
  const live = record.live === null ? '' : text(record.live.slug, locale)
  const path = live === '' ? null : `${PREFIX}/${live}`

  return {
    id: record.id,
    title: text(record.values.title, locale) || text(record.values.title, 'ru'),
    slug,
    lead: text(record.values.lead, locale),
    path,
    url: path === null ? null : `https://webx-demo.test/${path}`,
    status: record.status,
    pinned: record.values.pinned === true,
    published_at: record.published_at,
    updated_at: record.updated_at,
    deleted_at: record.deleted_at,
    author: author(record),
    cover: cover(record),
    rubrics: ids(record, 'rubrics').flatMap((id) => named(rubrics, id, locale)),
    tags: ids(record, 'tags').flatMap((id) => named(tags, id, locale)),
    revision: revision(record),
  }
}

/** What the editor read the article as — a save carrying an older one is refused with a 409. */
export function revision(record: ArticleRecord): string {
  return `${record.id}:${record.updated_at}`
}

/** The values the editor opens with, with the cover resolved to a key the field understands. */
export function values(record: ArticleRecord): ScreenModel {
  return clone(record.values)
}

export function clone<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T
}

function author(record: ArticleRecord): { id: number; name: string } | null {
  const id = record.values.author_id

  if (typeof id !== 'number') return null

  const found = authors.find((one) => one.id === id)

  return found === undefined ? null : { id: found.id, name: found.title }
}

function cover(record: ArticleRecord): ArticleCover | null {
  const value = record.values.cover as { path?: unknown } | null | undefined
  const path = typeof value?.path === 'string' ? value.path : null

  if (path === null) return null

  const file = fileByPath(path)

  return file === null ? null : { id: file.id, path: file.path, url: file.url, thumb: file.thumb }
}

function named(list: Array<RubricRow | TagRow>, id: number, locale: string): [BlogNamed] | [] {
  const row = list.find((one) => one.id === id)

  if (row === undefined) return []

  const title = 'titles' in row ? text(row.titles, locale) || row.title : text(row.title, locale)
  const slug = typeof row.slug === 'string' ? row.slug : text(row.slug, locale)

  return [{ id: row.id, title: title || `#${id}`, slug }]
}

/** One language out of a localized value — and nothing else: no falling back onto another. */
export function text(value: unknown, locale: string): string {
  if (typeof value === 'string') return value
  if (value === null || typeof value !== 'object') return ''

  const map = value as LocalizedValue

  return typeof map[locale] === 'string' ? map[locale] : ''
}

/** A date moved by whole days, for fixtures that want to read as a sequence. */
export function shift(from: string, days: number): string {
  const at = new Date(from)

  at.setUTCDate(at.getUTCDate() + days)

  return at.toISOString().replace(/\.\d+Z$/, '+00:00')
}
