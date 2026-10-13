<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, provide, ref, shallowRef, watch } from 'vue'
import {
  HttpError,
  loadShortcodes,
  onSitePage,
  tellSitePage,
  useAdmin,
  useErrorText,
  useSiteBar,
  useTranslate,
  WxRowMenu,
  type EditingEditor,
  type RowAction,
  type ShortcodeToken,
  type SiteBarContext,
} from '@webx-ui/module-admin'
import { confirm, createModal, toast } from '@webx-ui/core'
import {
  coreTypes,
  WxScreenRenderer,
  type TypeRegistry,
  type ValidationErrors,
} from '@webx-ui/schema'
import { createBlocksApi, createRegionsApi, createSiteBarBlocksApi } from './api'
import BlockPicker from './BlockPicker.vue'
import { clone, countType, locate, nestedFields } from './content'
import { useBlocksMessages } from './i18n'
import { blocksRootKey } from './preview'
import RegionDraftNotice from './RegionDraftNotice.vue'
import { formSchema, markupFields, startValues, withPlaceholders, withShortcodes } from './schema'
import type {
  BlockNode,
  BlockOutlineLine,
  BlockType,
  SiteBarBlockEdited,
  SiteBarBlockOp,
  SiteBarBlocks,
  SiteBarRegion,
} from './types'

/**
 * The «Blocks» tab of the panel on the site (WEBX_UI_SITE_BAR.md, §8–§9, SB4).
 *
 * Two states, like the constructor's narrow column: maps of the blocks, or one block's fields.
 * The maps are the record's — the page behind the address — and the layout's regions', the
 * header and the footer, each under its own name; a row under the pointer frames its block on the
 * page. The pencil is the way from the page: with it on, a click on a block picks it there
 * instead of following its link, whichever tree it belongs to.
 *
 * The form is the constructor's own — the block's schema through the same steps, drawn by the
 * same renderer with the panel's field types — and its values are the draft's, never the
 * site's: an edit goes into the draft of whatever holds the block, and a form that showed what
 * is published would write over whatever a colleague prepared. «Save» writes the fields that
 * changed and puts the block, drawn from the draft, in place of the old one on the page.
 *
 * The record's draft is published from the head of the bar; a region's — with its own buttons
 * over its map and over the form of its block, since it is a draft of its own and goes on every
 * page at once.
 *
 * The structure too (SB5): a block added — below another, at the end of a tree, inside a
 * container — duplicated, moved up or down, hidden, shown or deleted, each one an operation of the
 * agent's dictionary into the same draft. The picker is the constructor's, held to the same rules
 * of the place; what cannot be done right now is greyed with the reason rather than missing.
 */
const props = defineProps<{ context: SiteBarContext }>()

const admin = useAdmin()
useBlocksMessages()
const t = useTranslate('webx-blocks')
const errorText = useErrorText()
const bar = useSiteBar()
const api = createSiteBarBlocksApi(admin)
const regionsApi = createRegionsApi(admin)

/* A list of blocks inside a block's form is the constructor's to change, in the panel: the
   nested field draws its note instead of a second constructor. */
provide(blocksRootKey, true)

/** One tree of blocks the tab edits: the record's, or a region's. */
interface Source {
  /** What the page knows the tree by: `page:12`, `region:header`. */
  scope: string
  /** The address of its blocks: the record's type and id, or `region` and the region's name. */
  type: string
  id: string | number
  title: string
  content: SiteBarBlocks
  region: SiteBarRegion | null
  /** What its top level takes: a region's declaration, or anything for a record. */
  top: { allow: string[] | null; max: number | null; root: string }
}

/** One list of blocks: the top level of a tree, or a container's field. */
interface Place {
  parentKey: string | null
  field: string | null
  list: BlockNode[]
  parent: BlockType | null
  allow: string[] | null
  max: number | null
  root: string
}

const sources = ref<Source[]>([])
const catalog = shallowRef<BlockType[]>([])
const loading = ref(true)
const failed = ref(false)
const selected = ref<{ scope: string; key: string } | null>(null)
const values = ref<Record<string, unknown>>({})
const held = ref('{}')
const errors = ref<ValidationErrors | undefined>(undefined)
/* What a save was refused for that is not one of this form's fields: where a block stands — this
   one or another of the same tree — which the constructor in the panel is the place to change. */
const refusals = ref<string[]>([])
const saving = ref(false)
const picking = ref(false)
const others = ref<string[]>([])
/* A region's publication refused — a block of its draft fails — said over that region's map. */
const regionRefusals = ref<Record<string, string[]>>({})
const busy = ref<string | null>(null)
/* A change of structure under way: one at a time, since each answers with the page's steps. */
const shaping = ref(false)
/* The sets of block styles and scripts the page has, as the page reported them. */
const pageBundles = ref<string[] | null>(null)

const pick = createModal<
  BlockType,
  {
    catalog: BlockType[]
    parent: BlockType | null
    allow: string[] | null
    tree: BlockNode[]
    groups: string[]
    blocksPath: string | null
    root: string
  }
>(BlockPicker)

const groupsOrder = computed<string[]>(
  () =>
    (admin.state.manifest?.modules.find((m) => m.id === 'blocks')?.meta?.groups as
      string[] | undefined) ?? [],
)

const entity = computed(() => props.context.entity)
const offersRecord = computed(() => props.context.sections.includes('blocks') && !!entity.value)
const offersRegions = computed(() => props.context.sections.includes('regions'))

function typeOf(slug: string): BlockType | null {
  return catalog.value.find((type) => type.slug === slug) ?? null
}

function titleOf(slug: string): string {
  return typeOf(slug)?.title ?? slug
}

function rowsOf(source: Source) {
  return (source.content.outline ?? []).map((line: BlockOutlineLine, index) => ({
    id: line.key ?? `~${index}`,
    key: line.key ?? null,
    depth: line.depth,
    title: titleOf(line.type),
    label: line.label && line.label !== titleOf(line.type) ? line.label : '',
    hidden: line.hidden === true,
  }))
}

/* A region that is not on the site shows the markup from code: there is nothing of it on the
   page to pick, and its draft is the panel's to set up. */
function onSite(source: Source): boolean {
  return source.region === null || source.region.published
}

const groups = computed(() =>
  sources.value.map((source) => ({ source, rows: onSite(source) ? rowsOf(source) : [] })),
)

const current = computed(() =>
  selected.value
    ? (sources.value.find((one) => one.scope === selected.value!.scope) ?? null)
    : null,
)
const tree = computed(() => current.value?.content.tree ?? [])
const found = computed(() => (selected.value ? locate(tree.value, selected.value.key) : null))
const selectedType = computed(() => (found.value ? typeOf(found.value.node.type) : null))

/* The blocks the open one is inside, outermost first — the way back up to its parent. */
const trail = computed(() => {
  const steps: { key: string; title: string }[] = []
  let at = found.value?.parent ?? null

  while (at) {
    steps.unshift({ key: at.key, title: titleOf(at.type) })
    at = locate(tree.value, at.key)?.parent ?? null
  }

  return steps
})

const types = computed<TypeRegistry>(() => ({ ...coreTypes, ...(admin.types ?? {}) }))
const translate = computed(() => admin.i18n.t)
const can = computed(() => admin.can)

const shortcodes = shallowRef<ShortcodeToken[]>([])
const marked = ref<string[]>([])

const formRoot = computed(() =>
  selectedType.value?.content
    ? withShortcodes(
        withPlaceholders(
          formSchema(selectedType.value.content.schema),
          selectedType.value.content.sample ?? {},
          marked.value,
          t('field.markup-in-text'),
        ),
        shortcodes.value,
        { tokensTitle: t('field.shortcodes'), tokensLabel: t('field.shortcodes-insert') },
      )
    : [],
)

/* What «Save» sends: the fields that differ from what the form was opened with. A list of
   blocks is left out — it is not this form's to change. */
const changed = computed<Record<string, unknown>>(() => {
  const before = JSON.parse(held.value) as Record<string, unknown>
  const nested = new Set(selectedType.value?.content?.schema ? nestedIds() : [])
  const out: Record<string, unknown> = {}

  for (const [name, value] of Object.entries(values.value)) {
    if (nested.has(name)) continue
    if (JSON.stringify(value ?? null) !== JSON.stringify(before[name] ?? null)) out[name] = value
  }

  return out
})

const dirty = computed(() => Object.keys(changed.value).length > 0)

function nestedIds(): string[] {
  const ids: string[] = []
  const visit = (nodes: { id?: string; type: string; children?: unknown }[]): void => {
    for (const node of nodes) {
      if (node.type === 'wx-blocks' && node.id) ids.push(node.id)
      if (Array.isArray(node.children)) visit(node.children as typeof nodes)
    }
  }

  visit(selectedType.value?.content?.schema ?? [])

  return ids
}

/* Where the panel edits what holds the block: the record's screen, or the region's editor. */
function panelHref(source: Source | null): string | null {
  const base = admin.basePath.replace(/\/$/, '')

  if (source?.region) {
    const path = (admin.modules ?? []).find((module) => module.id === 'regions')?.path ?? '/regions'

    return `${base}${path}/${encodeURIComponent(source.region.name)}`
  }

  return entity.value?.edit ? `${base}${entity.value.edit}` : null
}

/** The page is told which trees it may pick from, and what each block is called on hover. */
function announce(): void {
  tellSitePage({
    type: 'blocks',
    sources: sources.value.filter(onSite).map((source) => ({
      scope: source.scope,
      // The page's own blocks go by their type alone; a region's say whose they are.
      ...(source.region ? { title: source.title } : {}),
      items: (source.content.outline ?? []).flatMap((line) =>
        line.key ? [{ key: line.key, label: titleOf(line.type) }] : [],
      ),
    })),
  })
}

async function readRecord(): Promise<Source[]> {
  const record = entity.value

  if (!offersRecord.value || !record) return []

  return [
    {
      scope: `${record.type}:${record.id}`,
      type: record.type,
      id: record.id,
      title: record.kind,
      content: await api.read(record.type, record.id),
      region: null,
      top: { allow: null, max: null, root: 'root' },
    },
  ]
}

async function readRegions(): Promise<Source[]> {
  if (!offersRegions.value) return []

  const rows = await regionsApi.list()

  return Promise.all(
    rows.map(async (row): Promise<Source> => {
      const read = await api.read('region', row.name)

      return {
        scope: `region:${row.name}`,
        type: 'region',
        id: row.name,
        title: read.region?.title ?? row.title,
        content: read,
        region: read.region ?? null,
        top: { allow: row.allow, max: row.max, root: `region:${row.name}` },
      }
    }),
  )
}

async function load(): Promise<void> {
  loading.value = sources.value.length === 0
  failed.value = false

  try {
    const [record, regions, types] = await Promise.all([
      readRecord(),
      readRegions(),
      catalog.value.length ? Promise.resolve(catalog.value) : createBlocksApi(admin).catalog(),
    ])

    catalog.value = types
    sources.value = [...record, ...regions]
    announce()

    if (selected.value && !found.value) back()
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

function open(scope: string, key: string): void {
  const source = sources.value.find((one) => one.scope === scope)
  const hit = source ? locate(source.content.tree, key) : null

  if (!source || !hit) return

  if (selected.value && selected.value.scope !== scope) stopPresence()

  selected.value = { scope, key }
  values.value = clone(hit.node.values ?? {})
  held.value = JSON.stringify(values.value)
  errors.value = undefined
  refusals.value = []
  marked.value = typeOf(hit.node.type)?.content
    ? markupFields(typeOf(hit.node.type)!.content!.schema, hit.node.values ?? {})
    : []
  tellSitePage({ type: 'select', key, scope })
  startPresence()
}

function back(): void {
  const was = selected.value

  stopPresence()
  selected.value = null
  errors.value = undefined
  refusals.value = []
  tellSitePage({ type: 'select', key: null, ...(was ? { scope: was.scope } : {}) })
}

function highlight(scope: string, key: string | null): void {
  tellSitePage({ type: 'highlight', key, scope })
}

function togglePicking(): void {
  picking.value = !picking.value
  tellSitePage({ type: 'mode', on: picking.value })
}

/**
 * The refusal of a value under the field it names (`blocks.<key>.<field>`); everything else the
 * server said — a block that stands where its container does not take it, a region holding more
 * than it may — as lines above, under a sentence that says it is not this form's doing.
 */
function refuse(error: HttpError, key: string): void {
  const prefix = `blocks.${key}.`
  const fields: ValidationErrors = {}
  const lines: string[] = []

  for (const [path, messages] of Object.entries(error.errors)) {
    if (path.startsWith(prefix)) fields[path.slice(prefix.length)] = messages
    else lines.push(...messages)
  }

  errors.value = Object.keys(fields).length ? fields : undefined
  refusals.value = [...new Set(lines)]
}

async function save(): Promise<void> {
  const source = current.value
  const key = selected.value?.key

  if (!source || !key || !dirty.value || saving.value) return

  saving.value = true
  errors.value = undefined
  refusals.value = []

  try {
    const done = await api.edit(source.type, source.id, {
      revision: source.content.revision,
      locale: props.context.locale,
      url: props.context.url,
      bundles: pageBundles.value ?? undefined,
      ops: [{ op: 'set', key, values: changed.value }],
    })

    // The block from the draft in place of the old one, framed as a draft on the page.
    showOnPage(source, done, key)

    held.value = JSON.stringify(values.value)
    toast.success(t('site-bar.saved'))

    await load()
    // The head counts the record's changes; it reads them again. A region counts its own.
    if (!source.region) void bar?.refresh()
  } catch (caught) {
    if (caught instanceof HttpError && caught.status === 409) {
      // Somebody wrote the tree since the form read it: read again and say who.
      conflicted(caught)
      await load()
      open(source.scope, key)

      return
    }

    if (caught instanceof HttpError && caught.isValidation && Object.keys(caught.errors).length) {
      refuse(caught, key)

      return
    }

    toast.danger(errorText(caught))
  } finally {
    saving.value = false
  }
}

/*
 * What the page does to show the draft: the steps the server worked out, after the styles and
 * script of a type the page did not have — so that a new block arrives dressed.
 */
function showOnPage(source: Source, done: SiteBarBlockEdited, edited?: string): void {
  const scope = source.scope

  if (done.assets) tellSitePage({ type: 'assets', ...done.assets })

  // An answer of the release before: the one block drawn.
  if (!done.steps) {
    const key = edited ?? done.key

    if (key && done.html !== '') tellSitePage({ type: 'replace', key, scope, html: done.html })

    return
  }

  for (const step of done.steps) {
    if (step.do === 'reload') {
      tellSitePage({ type: 'reload' })
    } else if (step.do === 'remove') {
      tellSitePage({ type: 'remove', key: step.key, scope })
    } else {
      tellSitePage({
        type: step.do === 'move' ? 'move' : 'insert',
        key: step.key,
        scope,
        html: step.html ?? '',
        after: step.after ?? [],
        before: step.before ?? [],
        top: step.top === true,
      })
    }
  }
}

/* Somebody wrote the tree since the tab read it: say who. */
function conflicted(caught: HttpError): void {
  const body = caught.body as { changed?: { author?: string | null } | null } | null
  const name = body?.changed?.author

  toast.warning(name ? t('site-bar.conflict-by', { name }) : t('site-bar.conflict'))
}

/** One list of blocks of a tree: the top level, or the field of a container. */
function placeOf(source: Source, parentKey: string | null, field: string | null): Place {
  if (parentKey === null) {
    return {
      parentKey: null,
      field: null,
      list: source.content.tree,
      parent: null,
      allow: source.top.allow,
      max: source.top.max,
      root: source.top.root,
    }
  }

  const holder = locate(source.content.tree, parentKey)
  const parent = holder ? typeOf(holder.node.type) : null
  const slot = parent?.content
    ? nestedFields(parent.content.schema).find((one) => one.id === field)
    : undefined
  const value = field !== null ? holder?.node.values[field] : undefined

  return {
    parentKey,
    field,
    list: Array.isArray(value) ? (value as BlockNode[]) : [],
    parent,
    allow: (slot?.props?.allow as string[] | undefined) ?? null,
    max: (slot?.props?.max as number | undefined) ?? null,
    // A container's field is named by its own type in `allowed_in`, not by the tree's root.
    root: 'root',
  }
}

/** The list a block is in. */
function placeAround(source: Source, key: string): Place | null {
  const hit = locate(source.content.tree, key)

  return hit ? placeOf(source, hit.parent?.key ?? null, hit.field) : null
}

function isFull(place: Place): boolean {
  return place.max !== null && place.list.length >= place.max
}

/** Why nothing more goes into a list, or null when something does. */
function fullness(place: Place): string | null {
  return isFull(place) ? t('site-bar.full', { count: place.max! }) : null
}

/**
 * A change of structure into the draft, and the page brought in step. One at a time: each answer
 * names what the page has to do, and two of them crossing would leave it guessing.
 */
async function shape(source: Source, ops: SiteBarBlockOp[]): Promise<SiteBarBlockEdited | null> {
  if (shaping.value) return null

  shaping.value = true

  try {
    const done = await api.edit(source.type, source.id, {
      revision: source.content.revision,
      locale: props.context.locale,
      url: props.context.url,
      bundles: pageBundles.value ?? undefined,
      ops,
    })

    showOnPage(source, done)
    toast.success(t('site-bar.saved'))

    await load()
    if (!source.region) void bar?.refresh()

    return done
  } catch (caught) {
    if (caught instanceof HttpError && caught.status === 409) {
      conflicted(caught)
      await load()
    } else if (caught instanceof HttpError && caught.isValidation) {
      // Where a block may stand, how many a page takes — the server's words, as they are.
      const lines = [...new Set(Object.values(caught.errors).flat())]

      toast.danger(lines.length ? lines.join(' ') : errorText(caught))
    } else {
      toast.danger(errorText(caught))
    }

    return null
  } finally {
    shaping.value = false
  }
}

/**
 * A new block in a list — after `after`, or at its end — of a type chosen in the constructor's
 * own picker, held to the same rules of the place. Its form opens at once.
 */
async function addTo(source: Source, place: Place, after: string | null): Promise<void> {
  if (isFull(place) || shaping.value) return

  const type = await pick({
    catalog: catalog.value,
    parent: place.parent,
    allow: place.allow,
    tree: source.content.tree,
    groups: groupsOrder.value,
    blocksPath: null,
    root: place.root,
  })

  if (!type) return

  const done = await shape(source, [
    {
      op: 'add',
      type: type.slug,
      values: startValues(type.content?.schema ?? [], type.content?.sample ?? {}),
      ...(place.parentKey !== null ? { parent: place.parentKey, field: place.field ?? '' } : {}),
      ...(after !== null ? { after } : {}),
    },
  ])

  if (done?.key) open(source.scope, done.key)
}

async function duplicate(source: Source, key: string): Promise<void> {
  const done = await shape(source, [{ op: 'duplicate', key }])

  if (done?.key) open(source.scope, done.key)
}

/** One step up or down its own list, past the neighbour. */
async function moveBy(source: Source, key: string, by: -1 | 1): Promise<void> {
  const hit = locate(source.content.tree, key)
  const next = hit?.list[hit.index + by]

  if (!hit || !next?.key) return

  await shape(source, [
    {
      op: 'move',
      key,
      ...(hit.parent ? { parent: hit.parent.key, field: hit.field ?? '' } : {}),
      ...(by < 0 ? { before: next.key } : { after: next.key }),
    },
  ])
}

async function removeBlock(source: Source, key: string): Promise<void> {
  const hit = locate(source.content.tree, key)

  if (!hit) return

  const agreed = await confirm({
    title: t('site-bar.delete-title', { title: titleOf(hit.node.type) }),
    message: t('site-bar.delete-text'),
    confirmText: t('site-bar.delete'),
    cancelText: t('site-bar.cancel'),
    tone: 'danger',
  })

  if (agreed) await shape(source, [{ op: 'remove', key }])
}

/**
 * What a block offers, on its row of the map and over its form: the panel's own `···`. What
 * cannot be done right now — the first block up, a full list — is greyed with the reason.
 */
function actionsFor(source: Source, key: string): RowAction[] {
  const hit = locate(source.content.tree, key)

  if (!hit) return []

  const place = placeOf(source, hit.parent?.key ?? null, hit.field)
  const full = fullness(place)
  const type = typeOf(hit.node.type)
  const limit = type?.max_per_entity ?? null
  const limited =
    limit !== null && countType(source.content.tree, hit.node.type) >= limit
      ? t('site-bar.limit', { count: limit })
      : null
  const slots = type?.content ? nestedFields(type.content.schema) : []
  const hidden = hit.node.hidden === true
  // A neighbour of old content without a key cannot be named: nothing moves past it from here.
  const above = hit.list[hit.index - 1]
  const below = hit.list[hit.index + 1]
  const up = !above ? t('site-bar.at-top') : !above.key ? t('site-bar.temporary') : null
  const down = !below ? t('site-bar.at-bottom') : !below.key ? t('site-bar.temporary') : null

  return [
    {
      key: 'add-below',
      icon: 'plus',
      label: t('site-bar.add-below'),
      disabled: full !== null,
      hint: full ?? undefined,
      run: () => void addTo(source, place, key),
    },
    ...slots.map((slot): RowAction => {
      const inside = placeOf(source, key, slot.id)
      const why = fullness(inside)

      return {
        key: `add-inside-${slot.id}`,
        icon: 'plus',
        label:
          slots.length > 1
            ? `${t('site-bar.add-inside')} · ${String(slot.label ?? slot.id)}`
            : t('site-bar.add-inside'),
        disabled: why !== null,
        hint: why ?? undefined,
        run: () => void addTo(source, inside, null),
      }
    }),
    {
      key: 'duplicate',
      icon: 'copy',
      label: t('site-bar.duplicate'),
      disabled: full !== null || limited !== null,
      hint: full ?? limited ?? undefined,
      run: () => void duplicate(source, key),
    },
    {
      key: 'move-up',
      icon: 'arrow-up',
      label: t('site-bar.move-up'),
      disabled: up !== null,
      hint: up ?? undefined,
      run: () => void moveBy(source, key, -1),
    },
    {
      key: 'move-down',
      icon: 'arrow-down',
      label: t('site-bar.move-down'),
      disabled: down !== null,
      hint: down ?? undefined,
      run: () => void moveBy(source, key, 1),
    },
    {
      key: 'visibility',
      icon: hidden ? 'eye' : 'eye-off',
      label: hidden ? t('site-bar.show') : t('site-bar.hide'),
      run: () => void shape(source, [{ op: hidden ? 'show' : 'hide', key }]),
    },
    {
      key: 'delete',
      icon: 'trash',
      label: t('site-bar.delete'),
      danger: true,
      run: () => void removeBlock(source, key),
    },
  ]
}

/* The whole draft on a page, for after a reload: the region's preview drawn on this page. */
function regionPreview(source: Source): string | null {
  const url = source.region?.preview

  return url ? `${url}&at=${encodeURIComponent(props.context.url)}` : null
}

/*
 * A region's draft on the site, or thrown away — the same calls its editor in the panel makes.
 * Every page that prints it changes at once, so the page in front reloads to show it.
 */
async function publishRegion(source: Source): Promise<void> {
  if (!source.region || busy.value) return

  busy.value = source.scope
  regionRefusals.value = { ...regionRefusals.value, [source.scope]: [] }

  try {
    await regionsApi.publish(source.region.name, source.content.revision)
    tellSitePage({ type: 'reload' })
  } catch (caught) {
    // A block of the draft fails: the region would print its fallback, so it is not published,
    // and the lines say which block and why.
    const lines = caught instanceof HttpError ? Object.values(caught.errors).flat() : []

    regionRefusals.value = { ...regionRefusals.value, [source.scope]: lines }
    toast.danger(errorText(caught))
    // A 409 is somebody's edit the tab had not seen: the map shows it now.
    void load()
  } finally {
    busy.value = null
  }
}

async function discardRegion(source: Source): Promise<void> {
  if (!source.region || busy.value) return

  const agreed = await confirm({
    title: t('site-bar.discard-title', { name: source.title }),
    message: t('site-bar.discard-text'),
    confirmText: t('site-bar.discard'),
    cancelText: t('site-bar.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  busy.value = source.scope

  try {
    await regionsApi.discardDraft(source.region.name)
    tellSitePage({ type: 'reload' })
  } catch (caught) {
    toast.danger(errorText(caught))
  } finally {
    busy.value = null
  }
}

/*
 * Presence (§9): while a block's form is open the bar knocks where the editor in the panel does
 * — the record's, or the region's — so an editor open there at the same time sees that it is
 * being edited.
 */
let beat: ReturnType<typeof setInterval> | undefined
let knocking: string | null = null

function presenceUrl(): string | null {
  const source = current.value

  if (source?.region)
    return `${admin.apiPath}/editing/regions/${encodeURIComponent(source.region.name)}`

  const record = entity.value

  return source && record?.editing
    ? `${admin.apiPath}/editing/${record.editing}/${encodeURIComponent(String(record.id))}`
    : null
}

async function knock(): Promise<void> {
  if (!knocking) return

  try {
    const ping = (await admin.http.post<{ data: { editors?: EditingEditor[] } }>(knocking)).data

    others.value = (ping.editors ?? []).map((one) => one.name).filter((name) => name !== '')
  } catch {
    // A courtesy: its absence is not worth a word on screen.
  }
}

function startPresence(): void {
  const url = presenceUrl()

  if (beat !== undefined || !url) return

  knocking = url
  void knock()
  beat = setInterval(() => void knock(), 20_000)
}

function stopPresence(): void {
  if (beat === undefined) return

  clearInterval(beat)
  beat = undefined
  others.value = []

  if (knocking) void admin.http.delete(knocking).catch(() => {})
  knocking = null
}

/* The page's half: a block picked there, picking switched off there with Esc, «Add below» on the
   picked block, and the sets of styles the page has. A message without a tree is from a page of
   the release before, where the record's was the only one. */
const stopListening = onSitePage((message) => {
  if (message.type === 'mode') {
    picking.value = message.on
  } else if (message.type === 'select') {
    const scope = message.scope ?? sources.value[0]?.scope
    const source = sources.value.find((one) => one.scope === scope)

    if (source && locate(source.content.tree, message.key)) {
      bar?.show('blocks')
      open(source.scope, message.key)
    }
  } else if (message.type === 'add') {
    const scope = message.scope ?? sources.value[0]?.scope
    const source = sources.value.find((one) => one.scope === scope)
    const place = source ? placeAround(source, message.key) : null

    if (source && place) {
      bar?.show('blocks')
      void addTo(source, place, message.key)
    }
  } else if (message.type === 'bundles') {
    pageBundles.value = message.hashes
  }
})

/* Esc in the frame ends picking first; the screen closes the drawer on the next one. */
function onKeydown(event: KeyboardEvent): void {
  if (event.key !== 'Escape' || !picking.value) return

  event.preventDefault()
  togglePicking()
}

/* Another record — the bar was pointed at another address. The context is read again after every
   save, and the same record in a new object is not a reason to read the blocks twice. */
watch(
  () => (entity.value ? `${entity.value.type}:${entity.value.id}` : null),
  () => void load(),
)

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
  void load()
  void loadShortcodes(admin).then((list) => (shortcodes.value = list))
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  stopListening()
  stopPresence()

  if (picking.value) tellSitePage({ type: 'mode', on: false })
})
</script>

<template>
  <div class="wx-blocks-bar">
    <!-- One row: the way back to the list on the left, picking on the page on the right — two
         rows of one button each took the height the form needs. -->
    <div class="wx-blocks-bar__tools">
      <nav v-if="found && current" class="wx-blocks-bar__trail" :aria-label="t('site-bar.all')">
        <wx-button size="sm" variant="text" icon="arrow-left" @click="back">
          {{ t('site-bar.all') }}
        </wx-button>
        <template v-for="step in trail" :key="step.key">
          <span class="wx-blocks-bar__slash" aria-hidden="true">/</span>
          <wx-button size="sm" variant="text" @click="open(current.scope, step.key)">
            {{ step.title }}
          </wx-button>
        </template>
      </nav>
      <wx-button
        size="sm"
        icon="edit"
        :variant="picking ? 'solid' : 'outline'"
        :type="picking ? 'primary' : 'default'"
        :aria-pressed="picking"
        @click="togglePicking"
      >
        {{ picking ? t('site-bar.pick-off') : t('site-bar.pick') }}
      </wx-button>
    </div>

    <wx-text v-if="picking" size="sm" tone="muted">{{ t('site-bar.picking') }}</wx-text>

    <wx-loading v-if="loading" />

    <wx-result v-else-if="failed" status="info" :title="t('site-bar.error')">
      <wx-button @click="load">{{ t('site-bar.retry') }}</wx-button>
    </wx-result>

    <template v-else-if="found && current">
      <div class="wx-blocks-bar__name">
        <wx-text weight="semibold">
          {{ selectedType?.title ?? found.node.type }}
          <span v-if="current.region" class="wx-blocks-bar__where">· {{ current.title }}</span>
          <span v-if="found.node.hidden" class="wx-blocks-bar__where"
            >· {{ t('site-bar.hidden') }}</span
          >
        </wx-text>
        <wx-row-menu
          :actions="actionsFor(current, found.node.key)"
          :label="t('site-bar.actions')"
        />
      </div>

      <wx-text v-if="current.region" size="sm" tone="muted">
        {{ t('site-bar.region-everywhere') }}
      </wx-text>

      <!-- Here too, not only over the map: the form is where the editor is after saving. -->
      <region-draft-notice
        v-if="current.region?.draft.differs"
        :region="current.region"
        :busy="busy === current.scope"
        :blocked="busy !== null"
        :refusals="regionRefusals[current.scope]"
        :preview="regionPreview(current)"
        @publish="publishRegion(current)"
        @discard="discardRegion(current)"
      />

      <wx-text v-if="others.length" size="sm" tone="muted">
        {{ t('site-bar.others', { names: others.join(', ') }) }}
      </wx-text>

      <wx-alert
        v-if="refusals.length"
        type="danger"
        role="alert"
        class="wx-blocks-bar__refused"
        :title="t('site-bar.refused')"
      >
        <ul class="wx-blocks-bar__lines">
          <li v-for="(line, index) in refusals" :key="index">{{ line }}</li>
        </ul>
        <template v-if="panelHref(current)" #actions>
          <wx-button
            size="sm"
            variant="outline"
            :href="panelHref(current)!"
            target="_blank"
            rel="noopener"
          >
            {{ t('site-bar.open-in-panel') }}
          </wx-button>
        </template>
      </wx-alert>

      <div class="wx-blocks-bar__form">
        <wx-screen-renderer
          v-if="selectedType?.content"
          :key="`${current.scope}|${found.node.key}`"
          v-model="values"
          :root="formRoot"
          :types="types"
          :translate="translate"
          :can="can"
          :errors="errors"
        />
        <wx-text v-else size="sm" tone="danger">{{ t('site-bar.unknown-type') }}</wx-text>
      </div>

      <div class="wx-blocks-bar__actions">
        <wx-button type="primary" :disabled="!dirty" :loading="saving" @click="save">
          {{ t('site-bar.save') }}
        </wx-button>
      </div>
    </template>

    <template v-else>
      <wx-text size="sm" tone="muted">{{ t('site-bar.hint') }}</wx-text>

      <section
        v-for="{ source, rows } in groups"
        :key="source.scope"
        class="wx-blocks-bar__group"
        :aria-label="source.title"
      >
        <header class="wx-blocks-bar__head">
          <wx-text weight="semibold">{{ source.title }}</wx-text>
          <wx-text v-if="source.region" size="xs" tone="muted">
            {{ t('site-bar.region-everywhere') }}
          </wx-text>
        </header>

        <!-- A region's own draft: published from here, since the head of the bar is the record's. -->
        <region-draft-notice
          v-if="source.region?.draft.differs && onSite(source)"
          :region="source.region"
          :busy="busy === source.scope"
          :blocked="busy !== null"
          :refusals="regionRefusals[source.scope]"
          :preview="regionPreview(source)"
          @publish="publishRegion(source)"
          @discard="discardRegion(source)"
        />

        <template v-if="!onSite(source)">
          <wx-text size="sm" tone="muted">{{ t('site-bar.region-fallback') }}</wx-text>
          <div>
            <wx-button
              size="sm"
              variant="outline"
              :href="panelHref(source)!"
              target="_blank"
              rel="noopener"
            >
              {{ t('site-bar.open-in-panel') }}
            </wx-button>
          </div>
        </template>

        <wx-text v-else-if="rows.length === 0" size="sm" tone="muted">{{
          t('site-bar.empty')
        }}</wx-text>

        <ul v-else class="wx-blocks-bar__map">
          <li v-for="row in rows" :key="row.id" class="wx-blocks-bar__item">
            <button
              type="button"
              class="wx-blocks-bar__row"
              :data-key="row.key ?? undefined"
              :data-scope="source.scope"
              :class="{ 'is-hidden': row.hidden, 'is-temporary': row.key === null }"
              :style="{ '--wx-blocks-bar-depth': row.depth }"
              :disabled="row.key === null"
              :title="row.key === null ? t('site-bar.temporary') : undefined"
              @mouseenter="highlight(source.scope, row.key)"
              @mouseleave="highlight(source.scope, null)"
              @focus="highlight(source.scope, row.key)"
              @blur="highlight(source.scope, null)"
              @click="row.key && open(source.scope, row.key)"
            >
              <span class="wx-blocks-bar__title">{{ row.title }}</span>
              <span v-if="row.label" class="wx-blocks-bar__label">{{ row.label }}</span>
              <span v-if="row.hidden" class="wx-blocks-bar__flag">{{ t('site-bar.hidden') }}</span>
              <span v-if="row.key === null" class="wx-blocks-bar__flag">{{
                t('site-bar.temporary')
              }}</span>
            </button>
            <wx-row-menu v-if="row.key" :actions="actionsFor(source, row.key)" :label="row.title" />
          </li>
        </ul>

        <!-- At the end of the tree, whatever its blocks are: a page with none needs it most. -->
        <div v-if="onSite(source)" class="wx-blocks-bar__add">
          <wx-button
            size="sm"
            variant="outline"
            icon="plus"
            :disabled="fullness(placeOf(source, null, null)) !== null"
            :loading="shaping"
            @click="addTo(source, placeOf(source, null, null), null)"
          >
            {{ t('site-bar.add') }}
          </wx-button>
          <wx-text v-if="fullness(placeOf(source, null, null))" size="xs" tone="muted">
            {{ fullness(placeOf(source, null, null)) }}
          </wx-text>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.wx-blocks-bar {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-blocks-bar__actions {
  display: flex;
  gap: var(--wx-space-8);
}

/* The way back on the left, picking on the right; alone, picking stays where it always was. A
   long trail to a nested block wraps, and picking goes under it rather than off the edge. */
.wx-blocks-bar__tools {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
}

.wx-blocks-bar__trail {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-2);
  min-width: 0;
}

.wx-blocks-bar__slash,
.wx-blocks-bar__where {
  color: var(--wx-text-muted);
  font-weight: var(--wx-font-weight-regular);
}

/* One tree a group: the page's, then each region's, with what is said about it over its map. */
.wx-blocks-bar__group {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
  padding-top: var(--wx-space-12);
  border-top: 1px solid var(--wx-border-default);
}

.wx-blocks-bar__head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-blocks-bar__map {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

/* A row and its `···`: the menu keeps its square at the end whatever the row says. */
.wx-blocks-bar__item {
  display: flex;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-blocks-bar__name {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-blocks-bar__add {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}

/* One line a block, indented by how deep it is nested: the map is read by its shape. */
.wx-blocks-bar__row {
  display: flex;
  flex: 1 1 auto;
  align-items: baseline;
  gap: var(--wx-space-8);
  width: 100%;
  min-width: 0;
  padding: var(--wx-space-6) var(--wx-space-8);
  padding-left: calc(var(--wx-space-8) + var(--wx-blocks-bar-depth, 0) * var(--wx-space-16));
  border: 0;
  border-radius: var(--wx-radius-sm);
  background: transparent;
  color: var(--wx-text-default);
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.wx-blocks-bar__row:hover:not(:disabled),
.wx-blocks-bar__row:focus-visible {
  background: var(--wx-bg-fill);
}

.wx-blocks-bar__row:focus-visible {
  outline: 2px solid var(--wx-border-focus);
  outline-offset: -2px;
}

.wx-blocks-bar__row:disabled {
  cursor: default;
}

.wx-blocks-bar__title {
  flex: 0 0 auto;
  font-weight: var(--wx-font-weight-medium);
}

.wx-blocks-bar__label {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
  white-space: nowrap;
  text-overflow: ellipsis;
}

.wx-blocks-bar__flag {
  flex: 0 0 auto;
  margin-left: auto;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}

.wx-blocks-bar__row.is-hidden .wx-blocks-bar__title,
.wx-blocks-bar__row.is-temporary .wx-blocks-bar__title {
  color: var(--wx-text-muted);
}

.wx-blocks-bar__form {
  min-width: 0;
}

.wx-blocks-bar__lines {
  margin: 0;
  padding-left: var(--wx-space-16);
  overflow-wrap: anywhere;
}
</style>
