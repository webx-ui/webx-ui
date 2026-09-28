import type {
  BlockNode,
  RegionDetail,
  RegionRow,
  RegionVersion,
} from '../../../../packages/module-blocks/src/types'

/**
 * The layout regions of the playground's site — `webx-blocks.regions` and `block_regions` of a
 * real one (§3 of the regions spec).
 *
 * Two, in the two states the section is made of: `header` published and built of blocks, and
 * `footer` declared and never saved — no row, `id: null`, the code's footer on every page.
 */

export interface RegionRecord {
  name: string
  id: number | null
  title: string
  description: string | null
  allow: string[] | null
  max: number | null
  /** The view the layout's tag names as `fallback`. */
  fallback: string | null
  /** What is on the site; `null` — never published. */
  blocks: BlockNode[] | null
  draft: BlockNode[] | null
  published_at: string | null
  updated_at: string | null
  versions: (RegionVersion & { blocks: BlockNode[] })[]
}

let nextId = 1

function block(type: string, values: Record<string, unknown>): BlockNode {
  return { key: `r${Math.random().toString(36).slice(2, 8)}`, type, values }
}

const headerBlocks: BlockNode[] = [
  block('site-header', {
    logo: 'Webx Demo',
    button_label: { ru: 'Обсудить проект', en: 'Talk to us' },
    button: {
      target: 'url',
      entity_type: null,
      entity_id: null,
      url: '/contacts',
      hash: null,
      new_tab: false,
      rel: [],
    },
  }),
  block('cta', {
    title: { ru: 'Бесплатный аудит сайта до конца месяца', en: 'A free site audit this month' },
    text: {
      ru: 'Покажем, что мешает заявкам, за один созвон.',
      en: 'One call, and we show what stands in the way.',
    },
    button_label: { ru: 'Записаться', en: 'Book' },
    button: {
      target: 'url',
      entity_type: null,
      entity_id: null,
      url: '/contacts',
      hash: null,
      new_tab: false,
      rel: [],
    },
    background: '#10224b',
  }),
]

export const regions = new Map<string, RegionRecord>([
  [
    'header',
    {
      name: 'header',
      id: nextId++,
      title: 'Шапка',
      description: 'Верх каждой страницы: логотип, меню, кнопка.',
      allow: null,
      max: null,
      fallback: 'components.header',
      blocks: headerBlocks,
      draft: null,
      published_at: '2026-09-28T09:05:00+00:00',
      updated_at: '2026-09-28T09:05:00+00:00',
      versions: [
        {
          number: 1,
          created_at: '2026-09-28T09:05:00+00:00',
          author: { id: 1, name: 'Анна Ковальчук' },
          source: 'panel',
          comment: null,
          is_pinned: false,
          blocks: headerBlocks,
        },
      ],
    },
  ],
  [
    'footer',
    {
      name: 'footer',
      id: null,
      title: 'Подвал',
      description: null,
      allow: null,
      max: null,
      fallback: 'components.footer',
      blocks: null,
      draft: null,
      published_at: null,
      updated_at: null,
      versions: [],
    },
  ],
])

/** The tree being edited: the draft when there is one, else what is on the site. */
export function treeOf(record: RegionRecord): BlockNode[] {
  return record.draft ?? record.blocks ?? []
}

export function revisionOf(record: RegionRecord): string {
  return `${record.name}:${record.updated_at ?? 'new'}`
}

export function regionRow(record: RegionRecord): RegionRow {
  return {
    name: record.name,
    id: record.id,
    title: record.title,
    description: record.description,
    allow: record.allow,
    max: record.max,
    published: record.published_at !== null,
    published_at: record.published_at,
    has_draft: record.draft !== null,
    count: treeOf(record).length,
    fallback: record.fallback,
    updated_at: record.updated_at,
  }
}

export function regionDetail(record: RegionRecord, canAdopt: boolean): RegionDetail {
  return {
    ...regionRow(record),
    blocks: treeOf(record),
    revision: revisionOf(record),
    // Signed on a real site; here the token is a stand-in, and the panel appends `&at=`.
    preview_url: `/preview/region/${record.name}?token=playground`,
    can_adopt: canAdopt && treeOf(record).length === 0,
  }
}

/** Written by a save: the row comes into being the first time, like a declared menu. */
export function saveDraft(record: RegionRecord, blocks: BlockNode[]): void {
  record.id ??= nextId++
  record.draft = blocks
  record.updated_at = new Date().toISOString()
}

export function publishRegion(record: RegionRecord): void {
  const now = new Date().toISOString()

  record.id ??= nextId++
  record.blocks = treeOf(record)
  record.draft = null
  record.published_at = now
  record.updated_at = now
  record.versions.unshift({
    number: (record.versions[0]?.number ?? 0) + 1,
    created_at: now,
    author: { id: 1, name: 'Анна Ковальчук' },
    source: 'panel',
    comment: null,
    is_pinned: false,
    blocks: record.blocks,
  })
}

/** Off the site; the blocks stay what the editor sees, so publishing again brings them back. */
export function unpublishRegion(record: RegionRecord): void {
  record.published_at = null
  record.updated_at = new Date().toISOString()
}

export function discardDraft(record: RegionRecord): void {
  record.draft = null
  record.updated_at = new Date().toISOString()
}

export function restoreVersion(record: RegionRecord, number: number): boolean {
  const version = record.versions.find((item) => item.number === number)

  if (version === undefined) return false

  record.draft = version.blocks
  record.updated_at = new Date().toISOString()

  return true
}

/** What the site prints in the region: the published tree, when it has a visible block. */
export function publishedTree(name: string): BlockNode[] | null {
  const record = regions.get(name)

  if (!record || record.published_at === null || record.blocks === null) return null

  return record.blocks.some((node) => node.hidden !== true) ? record.blocks : null
}
