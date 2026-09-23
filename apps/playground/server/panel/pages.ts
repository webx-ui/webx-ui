import type { BlockNode } from '../../../../packages/module-blocks/src/types'
import type { PageRow, PageVersion } from '../../../../packages/module-pages/src/types'
import type { ScreenModel } from '../../../../packages/schema/src/types'

/**
 * The site the playground edits: a tree of pages, their values, and what has been published.
 *
 * Deep enough on one branch to open three levels of the table tree, wide enough on another to
 * see what forty rows do to the list, and with a page in the bin — the three states the
 * section is actually made of.
 */

export interface PageRecord {
  row: PageRow
  values: ScreenModel
  versions: PageVersion[]
}

let nextId = 1

/** Pages by id, in the order they were written — the tree is `parent_id`, not this list. */
export const pages = new Map<number, PageRecord>()

function add(
  input: Partial<PageRow> & { title: string; slug: string; parent: number | null; en?: string },
  values: ScreenModel = {},
  versions: PageVersion[] = [],
): PageRecord {
  const id = nextId++
  const parent = input.parent === null ? null : (pages.get(input.parent) ?? null)
  const depth = parent === null ? 0 : parent.row.depth + 1
  const prefix = parent === null || parent.row.path === '' ? '' : `${parent.row.path}/`
  const path = input.is_home === true ? '' : `${prefix}${input.slug}`

  const row: PageRow = {
    id,
    parent_id: input.parent,
    depth,
    is_home: input.is_home ?? false,
    title: input.title,
    slug: input.slug,
    path,
    url: `https://webx-demo.test/${path}`,
    status: input.status ?? 'published',
    published_at: input.published_at ?? '2026-09-01T09:00:00+00:00',
    updated_at: input.updated_at ?? '2026-09-12T11:20:00+00:00',
    edited_by: input.edited_by ?? 'Анна Ковальчук',
    children_count: 0,
    descendants_count: 0,
    deleted_at: input.deleted_at ?? null,
    trashed_with: input.trashed_with ?? null,
    can: input.can ?? { move: true, delete: true, address: true },
  }

  const record: PageRecord = {
    row,
    values: {
      title: { ru: input.title, en: input.en ?? input.title },
      slug: { ru: input.slug, en: input.slug },
      is_home: row.is_home,
      blocks: [],
      seo: {},
      ...values,
    },
    versions,
  }

  pages.set(id, record)

  return record
}

/** A block instance, with the type's own sample as the starting point of its values. */
function block(type: string, values: Record<string, unknown>, hidden = false): BlockNode {
  const node: BlockNode = { key: `b${Math.random().toString(36).slice(2, 8)}`, type, values }

  if (hidden) {
    node.hidden = true
  }

  return node
}

const home = add(
  {
    title: 'Главная',
    slug: '',
    parent: null,
    is_home: true,
    can: { move: false, delete: false, address: true },
    status: 'modified',
    updated_at: '2026-09-19T07:40:00+00:00',
  },
  {
    blocks: [
      block('hero', {
        eyebrow: { ru: 'Студия разработки', en: 'Development studio' },
        title: { ru: 'Сайты, которые работают на бизнес', en: 'Sites that work' },
        subtitle: {
          ru: 'Проектируем, собираем и поддерживаем — от первого экрана до админки, которой пользуются каждый день.',
          en: 'We design, build and support — from the first screen to the panel used every day.',
        },
        button_label: { ru: 'Обсудить проект', en: 'Talk to us' },
        button: {
          target: 'entity',
          entity_type: 'page',
          entity_id: 13,
          url: null,
          hash: null,
          new_tab: false,
          rel: [],
        },
      }),
      block('features', {
        title: { ru: 'Почему с нами удобно', en: 'Why us' },
        items: [
          {
            title: { ru: 'Свои разработчики', en: 'Our own team' },
            text: {
              ru: 'Никаких подрядчиков на подряде: команда работает с вами от начала до сдачи.',
              en: 'No subcontractors: one team from start to launch.',
            },
          },
          {
            title: { ru: 'Админка без обучения', en: 'A panel you already know' },
            text: {
              ru: 'Редактор страниц собирает блоки сам, без вёрстки и без правок в коде.',
              en: 'Pages are assembled from blocks, with no markup and no code.',
            },
          },
          {
            title: { ru: 'Поддержка по договору', en: 'Support under contract' },
            text: {
              ru: 'Реагируем за четыре часа в рабочее время и держим сайт обновлённым.',
              en: 'Four working hours to first response.',
            },
          },
        ],
      }),
      block('text', {
        title: { ru: 'Как мы работаем', en: 'How we work' },
        body: {
          ru: '<p>Начинаем с разговора о задаче, а не с макета. Через неделю у вас на руках прототип, который можно показать команде и клиенту.</p><p>Дальше — сборка, тестирование на настоящих устройствах и передача в работу вместе с админкой.</p>',
          en: '<p>We start with the problem, not the mockup.</p>',
        },
      }),
      block('cta', {
        title: { ru: 'Расскажите о проекте', en: 'Tell us about it' },
        text: {
          ru: 'Ответим в течение дня и предложим план работ.',
          en: 'We answer within a day.',
        },
        button_label: { ru: 'Написать нам', en: 'Write to us' },
        button: {
          target: 'entity',
          entity_type: 'page',
          entity_id: 13,
          url: null,
          hash: null,
          new_tab: false,
          rel: [],
        },
        background: '#10224b',
      }),
    ],
    seo: {
      title: { ru: 'Разработка сайтов и админок', en: 'Web development studio' },
      description: {
        ru: 'Студия, которая проектирует, собирает и поддерживает сайты вместе с панелью управления.',
        en: 'A studio that designs, builds and supports sites together with their admin panel.',
      },
      robots: 'index,follow',
    },
  },
  [
    publication(4, '2026-09-18T16:30:00+00:00', 'Новый заголовок обложки'),
    publication(3, '2026-09-04T10:05:00+00:00', null),
    publication(2, '2026-08-21T12:00:00+00:00', 'Добавили преимущества'),
    publication(1, '2026-08-14T09:30:00+00:00', 'Первая публикация'),
  ],
)

const about = add(
  { title: 'О компании', slug: 'about', parent: home.row.id },
  {
    blocks: [
      block('text', {
        title: { ru: 'Кто мы', en: 'Who we are' },
        body: {
          ru: '<p>Двенадцать человек, одиннадцать лет и больше двухсот запущенных проектов.</p>',
          en: '<p>Twelve people, eleven years.</p>',
        },
      }),
    ],
  },
  [
    publication(2, '2026-09-02T09:00:00+00:00', null),
    publication(1, '2026-08-15T09:00:00+00:00', null),
  ],
)

add({ title: 'Команда', slug: 'team', parent: about.row.id, status: 'modified' }, {}, [
  publication(1, '2026-08-16T09:00:00+00:00', null),
])

const history = add({ title: 'История', slug: 'history', parent: about.row.id }, {}, [
  publication(1, '2026-08-16T10:00:00+00:00', null),
])

add({
  title: 'Награды',
  slug: 'awards',
  parent: history.row.id,
  status: 'draft',
  published_at: null,
})

const services = add(
  { title: 'Услуги', slug: 'services', parent: home.row.id },
  {
    blocks: [
      block('hero', {
        eyebrow: { ru: 'Что мы делаем', en: 'What we do' },
        title: { ru: 'Услуги студии', en: 'Our services' },
        subtitle: { ru: 'От прототипа до поддержки.', en: 'From prototype to support.' },
        button_label: { ru: '', en: '' },
        button_url: '',
      }),
      block('columns', {
        ratio: 'sidebar',
        children: [
          block('text', {
            title: { ru: 'Разработка', en: 'Development' },
            body: {
              ru: '<p>Сайты, магазины и панели управления.</p>',
              en: '<p>Sites and panels.</p>',
            },
          }),
          block('cta', {
            title: { ru: 'Нужна оценка?', en: 'Need an estimate?' },
            text: { ru: 'Пришлём в течение дня.', en: 'Within a day.' },
            button_label: { ru: 'Запросить', en: 'Ask' },
            button: {
              target: 'entity',
              entity_type: 'page',
              entity_id: 13,
              url: null,
              hash: null,
              new_tab: false,
              rel: [],
            },
            background: '#1f4f9c',
          }),
        ],
      }),
      block(
        'faq',
        {
          title: { ru: 'Частые вопросы', en: 'FAQ' },
          items: [
            {
              question: { ru: 'Сколько занимает разработка?', en: 'How long does it take?' },
              answer: { ru: 'Сайт-визитка — три недели.', en: 'Three weeks for a small site.' },
            },
          ],
        },
        true,
      ),
    ],
  },
  [
    publication(3, '2026-09-10T08:00:00+00:00', null),
    publication(2, '2026-08-25T08:00:00+00:00', null),
  ],
)

for (const [slug, title] of [
  ['development', 'Разработка'],
  ['support', 'Поддержка'],
  ['audit', 'Аудит и аналитика'],
  ['design', 'Дизайн интерфейсов'],
  ['integration', 'Интеграции и обмен данными'],
] as const) {
  add({ title, slug, parent: services.row.id }, {}, [
    publication(1, '2026-08-26T08:00:00+00:00', null),
  ])
}

add(
  { title: 'Проекты', slug: 'projects', parent: home.row.id, status: 'draft', published_at: null },
  {},
)

add(
  {
    title: 'Контакты',
    slug: 'contacts',
    parent: home.row.id,
    updated_at: '2026-09-19T15:10:00+00:00',
  },
  {
    blocks: [
      block('form', {
        title: { ru: 'Оставьте заявку', en: 'Leave a request' },
        text: { ru: 'Перезвоним в рабочее время.', en: 'We call back during work hours.' },
        form: 'feedback',
      }),
    ],
  },
  [publication(1, '2026-08-18T08:00:00+00:00', null)],
)

add(
  {
    title: 'Политика конфиденциальности',
    slug: 'privacy',
    parent: home.row.id,
    status: 'published',
    can: { move: true, delete: true, address: false },
  },
  {},
  [publication(1, '2026-08-14T09:35:00+00:00', null)],
)

const old = add(
  {
    title: 'Акция «Лето»',
    slug: 'summer',
    parent: home.row.id,
    status: 'published',
    deleted_at: '2026-09-15T12:00:00+00:00',
  },
  {},
)

add({
  title: 'Условия акции',
  slug: 'terms',
  parent: old.row.id,
  deleted_at: '2026-09-15T12:00:00+00:00',
  trashed_with: old.row.id,
})

function publication(number: number, createdAt: string, comment: string | null): PageVersion {
  return {
    number,
    created_at: createdAt,
    author: 'Анна Ковальчук',
    source: 'panel',
    comment,
    is_pinned: false,
  }
}

/** Counts are derived rather than written down: every move and delete would have to keep them. */
export function recount(): void {
  for (const record of pages.values()) {
    record.row.children_count = 0
    record.row.descendants_count = 0
  }

  for (const record of pages.values()) {
    if (record.row.deleted_at !== null) {
      // A page in the bin carries the branch that went down with it, which is what a restore
      // brings back; it is under nobody on the site, so it adds to no live page's count.
      const root = record.row.trashed_with === null ? null : pages.get(record.row.trashed_with)

      if (root !== undefined && root !== null) {
        root.row.descendants_count += 1
      }

      continue
    }

    const parent = record.row.parent_id === null ? null : pages.get(record.row.parent_id)

    if (parent !== undefined && parent !== null) {
      parent.row.children_count += 1
    }

    for (const ancestor of ancestorsOf(record.row)) {
      ancestor.descendants_count += 1
    }
  }
}

/** The trail above a page, home first. */
export function ancestorsOf(row: PageRow): PageRow[] {
  const trail: PageRow[] = []
  let current = row.parent_id === null ? undefined : pages.get(row.parent_id)

  while (current !== undefined) {
    trail.unshift(current.row)
    current = current.row.parent_id === null ? undefined : pages.get(current.row.parent_id)
  }

  return trail
}

/** Every page under this one, at any depth, in reading order. */
export function descendantsOf(id: number): PageRecord[] {
  const found: PageRecord[] = []

  for (const record of pages.values()) {
    if (ancestorsOf(record.row).some((ancestor) => ancestor.id === id)) {
      found.push(record)
    }
  }

  return found
}

/** The address of a page, worked out from the branch above it. */
export function pathOf(row: PageRow): string {
  if (row.is_home) {
    return ''
  }

  return [...ancestorsOf(row).map((node) => node.slug), row.slug]
    .filter((part) => part !== '')
    .join('/')
}

export function createPage(input: {
  title: string
  slug?: string
  parent_id?: number | null
}): PageRecord {
  const parent = input.parent_id ?? home.row.id
  const record = add({
    title: input.title,
    slug: input.slug ?? slugify(input.title),
    parent,
    status: 'draft',
    published_at: null,
    updated_at: new Date().toISOString(),
  })

  recount()

  return record
}

export function slugify(title: string): string {
  const map: Record<string, string> = {
    а: 'a',
    б: 'b',
    в: 'v',
    г: 'g',
    д: 'd',
    е: 'e',
    ё: 'e',
    ж: 'zh',
    з: 'z',
    и: 'i',
    й: 'i',
    к: 'k',
    л: 'l',
    м: 'm',
    н: 'n',
    о: 'o',
    п: 'p',
    р: 'r',
    с: 's',
    т: 't',
    у: 'u',
    ф: 'f',
    х: 'h',
    ц: 'c',
    ч: 'ch',
    ш: 'sh',
    щ: 'sch',
    ъ: '',
    ы: 'y',
    ь: '',
    э: 'e',
    ю: 'yu',
    я: 'ya',
  }

  return title
    .toLowerCase()
    .split('')
    .map((letter) => map[letter] ?? letter)
    .join('')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
}

recount()

export const homeId = home.row.id
