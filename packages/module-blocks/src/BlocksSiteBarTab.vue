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
  type EditingEditor,
  type ShortcodeToken,
  type SiteBarContext,
} from '@webx-ui/module-admin'
import { toast } from '@webx-ui/core'
import {
  coreTypes,
  WxScreenRenderer,
  type TypeRegistry,
  type ValidationErrors,
} from '@webx-ui/schema'
import { createBlocksApi, createSiteBarBlocksApi } from './api'
import { clone, locate } from './content'
import { useBlocksMessages } from './i18n'
import { blocksRootKey } from './preview'
import { formSchema, markupFields, withPlaceholders, withShortcodes } from './schema'
import type { BlockOutlineLine, BlockType, SiteBarBlocks } from './types'

/**
 * The «Blocks» tab of the panel on the site (WEBX_UI_SITE_BAR.md, §8–§9).
 *
 * Two states, like the constructor's narrow column: the record's blocks as a map, or one
 * block's fields. The map is the way to a block from the bar — a row under the pointer frames
 * its block on the page — and the pencil is the way from the page: with it on, a click on a
 * block picks it there instead of following its link.
 *
 * The form is the constructor's own — the block's schema through the same steps, drawn by the
 * same renderer with the panel's field types — and its values are the draft's, never the
 * site's: an edit goes into the draft, and a form that showed what is published would write over
 * whatever a colleague prepared. «Save» writes the fields that changed and puts the block, drawn
 * from the draft, in place of the old one on the page.
 */
const props = defineProps<{ context: SiteBarContext }>()

const admin = useAdmin()
useBlocksMessages()
const t = useTranslate('webx-blocks')
const errorText = useErrorText()
const bar = useSiteBar()
const api = createSiteBarBlocksApi(admin)

/* A list of blocks inside a block's form is the constructor's to change, in the panel: the
   nested field draws its note instead of a second constructor. */
provide(blocksRootKey, true)

const content = ref<SiteBarBlocks | null>(null)
const catalog = shallowRef<BlockType[]>([])
const loading = ref(true)
const failed = ref(false)
const selectedKey = ref<string | null>(null)
const values = ref<Record<string, unknown>>({})
const held = ref('{}')
const errors = ref<ValidationErrors | undefined>(undefined)
/* What a save was refused for that is not one of this form's fields: where a block stands — this
   one or another on the page — which the constructor in the panel is the place to change. */
const refusals = ref<string[]>([])
const saving = ref(false)
const picking = ref(false)
const others = ref<string[]>([])

const entity = computed(() => props.context.entity)
const tree = computed(() => content.value?.tree ?? [])

function typeOf(slug: string): BlockType | null {
  return catalog.value.find((type) => type.slug === slug) ?? null
}

function titleOf(slug: string): string {
  return typeOf(slug)?.title ?? slug
}

/* The map, with what each row is called: the type, then the block's own words. */
const rows = computed(() =>
  (content.value?.outline ?? []).map((line: BlockOutlineLine, index) => ({
    id: line.key ?? `~${index}`,
    key: line.key ?? null,
    depth: line.depth,
    title: titleOf(line.type),
    label: line.label && line.label !== titleOf(line.type) ? line.label : '',
    hidden: line.hidden === true,
  })),
)

const selected = computed(() => (selectedKey.value ? locate(tree.value, selectedKey.value) : null))
const selectedType = computed(() => (selected.value ? typeOf(selected.value.node.type) : null))

/* The blocks the open one is inside, outermost first — the way back up to its parent. */
const trail = computed(() => {
  const steps: { key: string; title: string }[] = []
  let at = selected.value?.parent ?? null

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

/** The page is told which record's blocks it may pick, and what each is called on hover. */
function announce(): void {
  const found = entity.value

  if (!found || !content.value) return

  tellSitePage({
    type: 'blocks',
    entity: { type: found.type, id: found.id },
    items: (content.value.outline ?? []).flatMap((line) =>
      line.key ? [{ key: line.key, label: titleOf(line.type) }] : [],
    ),
  })
}

async function load(): Promise<void> {
  const found = entity.value

  if (!found) return

  loading.value = content.value === null
  failed.value = false

  try {
    const [read, types] = await Promise.all([
      api.read(found.type, found.id),
      catalog.value.length ? Promise.resolve(catalog.value) : createBlocksApi(admin).catalog(),
    ])

    catalog.value = types
    content.value = read
    announce()

    if (selectedKey.value && !locate(read.tree, selectedKey.value)) back()
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

function open(key: string): void {
  const found = locate(tree.value, key)

  if (!found) return

  selectedKey.value = key
  values.value = clone(found.node.values ?? {})
  held.value = JSON.stringify(values.value)
  errors.value = undefined
  refusals.value = []
  marked.value = typeOf(found.node.type)?.content
    ? markupFields(typeOf(found.node.type)!.content!.schema, found.node.values ?? {})
    : []
  tellSitePage({ type: 'select', key })
  startPresence()
}

function back(): void {
  selectedKey.value = null
  errors.value = undefined
  tellSitePage({ type: 'select', key: null })
  stopPresence()
}

function highlight(key: string | null): void {
  tellSitePage({ type: 'highlight', key })
}

function togglePicking(): void {
  picking.value = !picking.value
  tellSitePage({ type: 'mode', on: picking.value })
}

/**
 * The refusal of a value under the field it names (`blocks.<key>.<field>`); everything else the
 * server said — a block that stands where its container does not take it — as lines above.
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
  const found = entity.value
  const key = selectedKey.value

  if (!found || !key || !content.value || !dirty.value || saving.value) return

  saving.value = true
  errors.value = undefined
  refusals.value = []

  try {
    const done = await api.edit(found.type, found.id, {
      revision: content.value.revision,
      locale: props.context.locale,
      ops: [{ op: 'set', key, values: changed.value }],
    })

    // The block from the draft in place of the old one, framed as a draft on the page.
    if (done.html !== '') tellSitePage({ type: 'replace', key, html: done.html })

    held.value = JSON.stringify(values.value)
    toast.success(t('site-bar.saved'))

    await load()
    // The head counts the draft's changes; it reads them again.
    void bar?.refresh()
  } catch (caught) {
    if (caught instanceof HttpError && caught.status === 409) {
      // Somebody wrote the page since the form read it: read again and say who.
      const body = caught.body as { changed?: { author?: string | null } | null } | null
      const name = body?.changed?.author

      toast.warning(name ? t('site-bar.conflict-by', { name }) : t('site-bar.conflict'))
      await load()
      open(key)

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
 * Presence (§9): while a block's form is open the bar knocks where the record's editor in the
 * panel does, so an editor open there at the same time sees that the record is being edited.
 */
let beat: ReturnType<typeof setInterval> | undefined

function presenceUrl(): string | null {
  const found = entity.value

  return found?.editing
    ? `${admin.apiPath}/editing/${found.editing}/${encodeURIComponent(String(found.id))}`
    : null
}

async function knock(): Promise<void> {
  const url = presenceUrl()

  if (!url) return

  try {
    const ping = (await admin.http.post<{ data: { editors?: EditingEditor[] } }>(url)).data

    others.value = (ping.editors ?? []).map((one) => one.name).filter((name) => name !== '')
  } catch {
    // A courtesy: its absence is not worth a word on screen.
  }
}

function startPresence(): void {
  if (beat !== undefined || !presenceUrl()) return

  void knock()
  beat = setInterval(() => void knock(), 20_000)
}

function stopPresence(): void {
  if (beat === undefined) return

  clearInterval(beat)
  beat = undefined
  others.value = []

  const url = presenceUrl()

  if (url) void admin.http.delete(url).catch(() => {})
}

/* The page's half: a block picked there, and picking switched off there with Esc. */
const stopListening = onSitePage((message) => {
  if (message.type === 'mode') {
    picking.value = message.on
  } else if (message.type === 'select' && locate(tree.value, message.key)) {
    bar?.show('blocks')
    open(message.key)
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
    <div class="wx-blocks-bar__tools">
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

    <template v-else-if="selected">
      <nav class="wx-blocks-bar__trail" :aria-label="t('site-bar.all')">
        <wx-button size="sm" variant="text" icon="arrow-left" @click="back">
          {{ t('site-bar.all') }}
        </wx-button>
        <template v-for="step in trail" :key="step.key">
          <span class="wx-blocks-bar__slash" aria-hidden="true">/</span>
          <wx-button size="sm" variant="text" @click="open(step.key)">{{ step.title }}</wx-button>
        </template>
      </nav>

      <wx-text weight="semibold">{{ selectedType?.title ?? selected.node.type }}</wx-text>

      <wx-text v-if="others.length" size="sm" tone="muted">
        {{ t('site-bar.others', { names: others.join(', ') }) }}
      </wx-text>

      <div v-if="refusals.length" class="wx-blocks-bar__refusals" role="alert">
        <wx-text v-for="(line, index) in refusals" :key="index" size="sm" tone="danger">{{
          line
        }}</wx-text>
      </div>

      <div class="wx-blocks-bar__form">
        <wx-screen-renderer
          v-if="selectedType?.content"
          :key="selected.node.key"
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
      <wx-text v-if="rows.length === 0" size="sm" tone="muted">{{ t('site-bar.empty') }}</wx-text>

      <ul v-else class="wx-blocks-bar__map">
        <li v-for="row in rows" :key="row.id">
          <button
            type="button"
            class="wx-blocks-bar__row"
            :data-key="row.key ?? undefined"
            :class="{ 'is-hidden': row.hidden, 'is-temporary': row.key === null }"
            :style="{ '--wx-blocks-bar-depth': row.depth }"
            :disabled="row.key === null"
            :title="row.key === null ? t('site-bar.temporary') : undefined"
            @mouseenter="highlight(row.key)"
            @mouseleave="highlight(null)"
            @focus="highlight(row.key)"
            @blur="highlight(null)"
            @click="row.key && open(row.key)"
          >
            <span class="wx-blocks-bar__title">{{ row.title }}</span>
            <span v-if="row.label" class="wx-blocks-bar__label">{{ row.label }}</span>
            <span v-if="row.hidden" class="wx-blocks-bar__flag">{{ t('site-bar.hidden') }}</span>
            <span v-if="row.key === null" class="wx-blocks-bar__flag">{{
              t('site-bar.temporary')
            }}</span>
          </button>
        </li>
      </ul>
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

.wx-blocks-bar__tools,
.wx-blocks-bar__actions {
  display: flex;
  gap: var(--wx-space-8);
}

.wx-blocks-bar__trail {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-2);
  min-width: 0;
}

.wx-blocks-bar__slash {
  color: var(--wx-text-muted);
}

.wx-blocks-bar__map {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

/* One line a block, indented by how deep it is nested: the map is read by its shape. */
.wx-blocks-bar__row {
  display: flex;
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

.wx-blocks-bar__refusals {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}
</style>
