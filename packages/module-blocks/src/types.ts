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

/**
 * Where a type is seen (§3.1 of the components spec). A `block` is offered in "Add a block" and
 * its schema is the editor's form; a `component` is only ever called with `<x-webx-block>` from
 * another template, and its schema describes what the caller hands it. Either can be called.
 */
export type BlockKind = 'block' | 'component'

/** A type whose published version calls this one: the "in 2 blocks" of a component. */
export interface BlockParent {
  id: number
  slug: string
  title: string
}

/**
 * A place a module declared for a component (`BlockComponents::declare`): the module draws its
 * own view there until the site customises it into a type of its own.
 */
export interface DeclaredComponent {
  slug: string
  /** The module's id, `recipes`; its name in the panel is the manifest's title for it. */
  module: string
  title: string
  description: string | null
  /** The view drawn while nothing is published under the slug. */
  fallback: string
  /** Whether the site has a type of that slug already. */
  customised: boolean
}

/** One key of a data shape (`BlockShapes`): what the help under the template names. */
export interface ShapeField {
  name: string
  type: string
  description: string | null
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
  /*
   * The five below come from a server that knows components. One older than that sends none of
   * them, and every type reads as a block nobody calls — which is what it is there.
   */
  kind?: BlockKind
  /** Slugs the template of the current version calls, sorted. */
  uses?: string[]
  /** Types whose published version calls this one. */
  used_by?: BlockParent[]
  /** The module's declaration of this slug, when there is one. Only in `GET /blocks/{id}`. */
  declared?: DeclaredComponent | null
  /** The data shapes the schema's `wx-data` nodes name, by name. Only in `GET /blocks/{id}`. */
  shape?: Record<string, { fields: ShapeField[] }>
  thumbnail: BlockThumbnail | null
  created_at: string | null
  updated_at: string | null
  /** The draft's content, or the published version's without a draft. Absent in the list. */
  content?: BlockContent
  warnings?: Lint[]
}

/** What a save sends: only what changed. */
export interface BlockInput {
  kind?: BlockKind
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

/** The section's list: the types, and the places modules declared for components. */
export interface BlockList {
  blocks: BlockType[]
  declared: DeclaredComponent[]
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
  /**
   * The page of the site the block is drawn on — its layout with an empty place in it — or
   * nothing from a server older than the stage, which draws the block on a bare document.
   */
  stage?: string | null
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
  /** The calling type the draft broke, when it was a parent that failed (§3.6). */
  parent?: BlockParent | null
  /** The module whose declared place failed on its own sample. */
  declared?: string | null
  /** The loop publishing would close, as slugs, the first repeated at the end. */
  cycle?: string[] | null
}

/** What the server-side module says about itself in the manifest. */
export interface BlocksMeta {
  groups: string[]
  editing: boolean
  provides: string[]
  /** The stage page, when the site has a layout — where thumbnails take the site's styles. */
  stage?: string | null
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
