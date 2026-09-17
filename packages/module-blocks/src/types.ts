import type { ScreenNode } from '@webx-ui/schema'

export type BlockSource = 'panel' | 'mcp' | 'import'

/** One version, as the history lists it. */
export interface BlockVersionMeta {
  number: number
  source: BlockSource
  comment: string | null
  author_id: number | null
  author: string | null
  created_at: string | null
}

/** The content of a version: what the editor works on. */
export interface BlockContent {
  /** Screen nodes of `@webx-ui/schema`; a node's `id` is the field's key in the values. */
  schema: ScreenNode[]
  template: string
  styles: string
  script: string | null
  sample: Record<string, unknown>
}

export interface BlockVersion extends BlockVersionMeta {
  content?: BlockContent
}

/** The block drawn on its sample, ready for an iframe. */
export interface BlockThumbnail {
  html: string
  styles: string
}

/** What was noticed on saving; never a refusal. */
export interface Lint {
  file: 'template' | 'styles' | 'schema'
  code: string
  line: number | null
  message: string
}

/** A block type as the section shows it. */
export interface BlockType {
  id: number
  slug: string
  title: string
  description: string | null
  icon: string | null
  group: string
  sort: number
  /** Types allowed inside; `null` means the block is not a container. */
  allow: string[] | null
  /** Types that may hold this one, `root` standing for the page itself; `null` means anywhere. */
  allowed_in: string[] | null
  max_per_entity: number | null
  is_enabled: boolean
  draft: BlockVersionMeta | null
  published: BlockVersionMeta | null
  usage_count: number
  thumbnail: BlockThumbnail | null
  created_at: string | null
  updated_at: string | null
  /** The draft's content, or the published version's without a draft. Absent in the list. */
  content?: BlockContent
  warnings?: Lint[]
}

/** What a save sends: only what changed. */
export interface BlockInput {
  slug?: string
  title?: string
  description?: string | null
  icon?: string | null
  group?: string
  sort?: number
  allow?: string[] | null
  allowed_in?: string[] | null
  max_per_entity?: number | null
  is_enabled?: boolean
  content?: Partial<BlockContent>
  comment?: string | null
}

/** An entity a type stands on. */
export interface BlockUsage {
  model: string
  id: number | string
  title: string | null
  published: boolean
}

/** One block drawn by the server. */
export interface RenderResult {
  /** The HTML between the marker pair `<!--wx:key-->…<!--/wx:key-->`. */
  html: string
  styles: string
  /** The script wrapped for the runtime, or null when the block has none. */
  script: string | null
  /** Where the runtime is served. */
  runtime: string
  version: number
}

export interface RenderInput {
  values?: Record<string, unknown>
  key?: string
  /** Unsaved content to draw instead of the stored version. Needs the right to save. */
  content?: Partial<BlockContent>
}

/** A 422 from publishing: the reason, the line, and the page when a page broke. */
export interface PublishRefusal {
  message: string
  errors: Record<string, string[]>
  line: number | null
  entity: { model: string; id: number | string; title: string | null } | null
}

/** What the server-side module says about itself in the manifest. */
export interface BlocksMeta {
  groups: string[]
  editing: boolean
  provides: string[]
}

/**
 * One block on a page: the instance's key, the type's slug, and the values keyed by the
 * type's field ids. A container holds its children in one of the values, as a list of the
 * same shape.
 */
export interface BlockNode {
  key: string
  type: string
  values: Record<string, unknown>
  /**
   * Switched off: in the content and editable here, not drawn on the site — and neither is
   * anything nested inside it. The key is there only when it is true, so every tree written
   * before the switch existed reads as visible.
   */
  hidden?: boolean
}
