/**
 * What stands in for the controller behind the table lab.
 *
 * The rows are generated once and then only ever read, so every request answers the same
 * question the same way — a layout bug that only shows on row 37 stays on row 37 across a
 * reload. Everything the table can ask for (page, size, sort, search, the three filters) is
 * answered here rather than in the screen: a table that sorts its own page is a table that
 * never finds out what a real one does.
 *
 * The shape of the answer is Laravel's `->paginate()`, keys and all, because that is what
 * `WxTable` takes.
 */

export type RecordStatus = 'published' | 'modified' | 'scheduled' | 'draft' | 'unpublished'

export interface Named {
  id: number
  title: string
}

export interface RecordAuthor {
  id: number
  name: string
  initials: string
}

export interface LabRecord {
  id: number
  title: string
  slug: string
  cover: string | null
  rubrics: Named[]
  author: RecordAuthor | null
  channel: string
  views: number
  ctr: number
  revenue: number
  comments: number
  /** A UTM tail: one token, no spaces, far wider than any column it can be given. */
  source: string
  /** Prose of the length an editor actually types into a "note" field. */
  note: string
  published_at: string | null
  status: RecordStatus
  pinned: boolean
}

export interface LabPage {
  data: LabRecord[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
  filters: {
    rubrics: Named[]
    authors: Named[]
    channels: string[]
  }
}

/* ------------------------------------------------------------------ the data --- */

const RUBRICS: Named[] = [
  { id: 1, title: 'Ремонт' },
  { id: 2, title: 'Новини' },
  { id: 3, title: 'Обслуговування' },
  { id: 4, title: 'Техніка' },
  { id: 5, title: 'Гідравліка та пневматика' },
  { id: 6, title: 'Огляди' },
]

const AUTHORS: RecordAuthor[] = [
  { id: 1, name: 'Ганна Роут', initials: 'ГР' },
  { id: 2, name: 'Тарас Кемп', initials: 'ТК' },
  { id: 3, name: 'Олег Мороз', initials: 'ОМ' },
  { id: 4, name: 'Марія Цвях', initials: 'МЦ' },
  { id: 5, name: 'Костянтин Вишневецький-Ковальчук', initials: 'КВ' },
]

const CHANNELS = ['Органіка', 'Розсилка', 'Соцмережі', 'Партнери', 'Прямі заходи']

const STATUSES: RecordStatus[] = ['published', 'modified', 'scheduled', 'draft', 'unpublished']

const TITLES = [
  'Як вибрати привідний ремінь: 7 ознак зносу',
  'Огляд нових надходжень: вересень 2026',
  'Каталог Bobcat: чим відрізняються серії M і R',
  "П'ять помилок при заміні підшипників маточини",
  'Як продовжити термін служби мастила в гідросистемі',
  'Зимова підготовка техніки: п’ять пунктів',
  'Чим оригінальний фільтр відрізняється від аналога',
  'Гідравлічні фільтри: коли міняти і чому це важливо',
  'Що робити, якщо генератор перестав заряджати',
  'Підсумки виставки СТТ Експо: що показали виробники',
  'Ремонт гідророзподільника власноруч: покрокова інструкція з фотографіями та переліком інструменту',
  'Діагностика ходової частини міні-навантажувача за характерним звуком',
  'PN-3480-HYDRAULIC-PUMP-ASSEMBLY-REPLACEMENT-KIT-2026',
  'Мастило',
]

const NOTES = [
  'Donec ullamcorper nulla non metus auctor fringilla. Maecenas sed diam eget risus varius blandit sit amet non magna.',
  'Треба перезняти обкладинку — на нинішній видно край стенда.',
  '',
  'Узгоджено з відділом сервісу, друга частина виходить наступного тижня.',
  '',
  'Cras justo odio, dapibus ac facilisis in, egestas eget quam. Vestibulum id ligula porta felis euismod semper.',
]

/** A deterministic shuffle: the same row gets the same everything on every start. */
function pick<T>(list: T[], seed: number): T {
  return list[seed % list.length]
}

function makeRecord(index: number): LabRecord {
  const id = 4300 - index
  const title = pick(TITLES, index * 3 + (index % 5))
  const status = STATUSES[index % STATUSES.length]
  const hasCover = index % 7 !== 3
  const rubricCount = index % 4 === 0 ? 0 : 1 + (index % 3)
  const dated = status !== 'draft' && index % 11 !== 5

  return {
    id,
    title,
    slug: `/blog/${slugify(title)}-${id}`,
    cover: hasCover ? `/api/covers/${id}.svg` : null,
    rubrics: Array.from({ length: rubricCount }, (_, n) => pick(RUBRICS, index + n * 2)),
    author: index % 13 === 4 ? null : pick(AUTHORS, index + Math.floor(index / 5)),
    channel: pick(CHANNELS, index + 2),
    views: (index % 9 === 2 ? 0 : 118 + ((index * 3607) % 94000)) as number,
    ctr: Math.round(((index * 137) % 3800) / 100 + 0.4 * (index % 5)) / 10,
    revenue: Math.round((index % 6 === 1 ? 0 : 340 + ((index * 911) % 184000)) * 100) / 100,
    comments: index % 5 === 0 ? 0 : (index * 7) % 143,
    source:
      index % 4 === 1
        ? ''
        : `utm_source=newsletter&utm_medium=email&utm_campaign=autumn-2026-parts-${id}`,
    note: pick(NOTES, index + 1),
    published_at: dated ? dateFor(index) : null,
    status,
    pinned: index % 17 === 1,
  }
}

function dateFor(index: number): string {
  /* Fixed point in time so "today" in the panel and "today" here never argue. */
  const base = Date.UTC(2026, 8, 18, 10, 43)
  return new Date(base - index * 19 * 3600 * 1000).toISOString()
}

const MAP: Record<string, string> = {
  а: 'a',
  б: 'b',
  в: 'v',
  г: 'g',
  ґ: 'g',
  д: 'd',
  е: 'e',
  є: 'ie',
  ж: 'zh',
  з: 'z',
  и: 'y',
  і: 'i',
  ї: 'i',
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
  х: 'kh',
  ц: 'ts',
  ч: 'ch',
  ш: 'sh',
  щ: 'shch',
  ь: '',
  ю: 'iu',
  я: 'ia',
}

function slugify(value: string): string {
  return value
    .toLowerCase()
    .replace(/[а-яґєії]/g, (letter) => MAP[letter] ?? '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
    .slice(0, 48)
}

const ROWS: LabRecord[] = Array.from({ length: 246 }, (_, index) => makeRecord(index))

/* --------------------------------------------------------------- the answer --- */

export interface LabQuery {
  page?: number
  perPage?: number
  /** `views` or `-views`, the way the panel sends it. */
  sort?: string | null
  q?: string
  status?: string
  rubric?: number | null
  author?: number | null
  channel?: string
}

export function query(request: LabQuery): LabPage {
  const term = (request.q ?? '').trim().toLowerCase()

  const found = ROWS.filter((row) => {
    if (request.status && row.status !== request.status) return false
    if (request.channel && row.channel !== request.channel) return false
    if (request.rubric && !row.rubrics.some((rubric) => rubric.id === request.rubric)) return false
    if (request.author && row.author?.id !== request.author) return false
    if (!term) return true

    return `${row.title} ${row.slug} ${row.author?.name ?? ''}`.toLowerCase().includes(term)
  })

  const sort = request.sort ?? null

  if (sort) {
    const desc = sort.startsWith('-')
    const key = (desc ? sort.slice(1) : sort) as keyof LabRecord

    found.sort((a, b) => {
      const left = valueOf(a, key)
      const right = valueOf(b, key)
      const order = left > right ? 1 : left < right ? -1 : 0

      return desc ? -order : order
    })
  }

  const perPage = request.perPage && request.perPage > 0 ? request.perPage : 20
  const total = found.length
  const lastPage = Math.max(1, Math.ceil(total / perPage))
  const current = Math.min(Math.max(request.page ?? 1, 1), lastPage)
  const start = (current - 1) * perPage
  const data = found.slice(start, start + perPage)

  return {
    data,
    current_page: current,
    last_page: lastPage,
    per_page: perPage,
    total,
    from: total === 0 ? null : start + 1,
    to: total === 0 ? null : start + data.length,
    filters: {
      rubrics: RUBRICS,
      authors: AUTHORS.map((author) => ({ id: author.id, title: author.name })),
      channels: CHANNELS,
    },
  }
}

function valueOf(row: LabRecord, key: keyof LabRecord): string | number {
  const value = row[key]

  if (typeof value === 'number') return value
  if (typeof value === 'string') return value.toLowerCase()
  if (value === null) return ''

  return String(value)
}

/* ---------------------------------------------------------------- the cover --- */

const HUES = [8, 32, 48, 96, 168, 196, 214, 262, 292, 332]

/**
 * A picture without a network: a tile the colour of its id with the first words on it.
 *
 * The point is that the cell holds a real `<img>` of a real, awkward size — 1200×630, the way a
 * cover arrives from a media library — rather than a div pretending to be one.
 */
export function cover(id: number, label: string): string {
  const hue = HUES[id % HUES.length]
  const text = label.split(/\s+/).slice(0, 4).join(' ').replace(/[<>&]/g, '')

  return [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">',
    `<rect width="1200" height="630" fill="hsl(${hue} 46% 28%)"/>`,
    `<rect width="1200" height="630" fill="url(#g${id})"/>`,
    `<defs><linearGradient id="g${id}" x1="0" y1="0" x2="1" y2="1">`,
    `<stop offset="0" stop-color="hsl(${hue} 60% 42%)" stop-opacity="0.9"/>`,
    `<stop offset="1" stop-color="hsl(${(hue + 40) % 360} 55% 22%)" stop-opacity="0.9"/>`,
    '</linearGradient></defs>',
    '<text x="60" y="330" font-family="Arial, sans-serif" font-size="74" font-weight="700"',
    ' fill="#ffffff" opacity="0.92">',
    text,
    '</text>',
    '</svg>',
  ].join('')
}

export function titleOf(id: number): string {
  return ROWS.find((row) => row.id === id)?.title ?? 'WebX'
}
