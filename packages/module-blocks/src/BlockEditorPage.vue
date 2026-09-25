<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, provide, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxHelpButton,
  WxRenameButton,
  WxScreenHead,
  type ScreenAction,
} from '@webx-ui/module-admin'
import {
  confirm,
  toast,
  WxActionBar,
  WxAlert,
  WxBadge,
  WxButton,
  WxCard,
  WxCodeEditor,
  WxFormItem,
  WxIconPicker,
  WxInput,
  WxInputNumber,
  WxPopover,
  WxSelect,
  WxSkeleton,
  WxSwitch,
  WxTab,
  WxTabs,
  WxTagsInput,
  WxText,
  WxTextarea,
} from '@webx-ui/core'
import { coreTypes, WxScreenRenderer, type ScreenModel } from '@webx-ui/schema'
import { createBlocksApi } from './api'
import BlockChecks from './BlockChecks.vue'
import BlockHistory from './BlockHistory.vue'
import BlockStage from './BlockStage.vue'
import { clone } from './content'
import { useBlocksMessages } from './i18n'
import {
  completions,
  schemaCompletions,
  stylesCompletions,
  templateCompletions,
  type TagTarget,
} from './completions'
import { lintBlock } from './lint'
import { blocksOwnerKey } from './preview'
import {
  callerWords,
  callTag,
  formSchema,
  groupLabel,
  kindOf,
  schemaFields,
  usageWords,
} from './schema'
import type {
  BlockContent,
  BlockKind,
  BlocksMeta,
  BlockType,
  BlockUsage,
  PublishRefusal,
} from './types'

/**
 * The editor of one type: six tabs across the screen — the four files, the settings and the
 * history — and what each of them needs beside it. The template has the block drawn on its
 * sample; the schema has the form it builds. The rest have nothing, and take the width.
 *
 * Where the type stands is a list behind one word of the subtitle.
 *
 * The loop is closed all the same: edit the schema and the form rebuilds, edit the values and
 * the stage redraws, edit the template or the styles and so does it.
 *
 * A component is edited on the same screen (§3.9 of the components spec). What differs is what
 * its schema means — the input a caller hands it, not a form — so the settings lose what only a
 * block on a page needs, "Fields" become "Input data", and under the template stands the tag that
 * calls it. Where it is used is the blocks that call it rather than pages.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/blocks' })

const context = useAdmin()
const api = createBlocksApi(context)
const route = useRoute()
const router = useRouter()
useBlocksMessages()

const t = useTranslate('webx-blocks')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const id = computed(() => Number(route.params.id))

const block = ref<BlockType | null>(null)
const usage = ref<BlockUsage[]>([])
const loading = ref(true)
const saving = ref(false)
const publishing = ref(false)
const tab = ref('template')

const meta = computed<BlocksMeta>(() => {
  const found = context.state.manifest?.modules.find((module) => module.id === 'blocks')?.meta

  return {
    groups: (found?.groups as string[] | undefined) ?? [],
    editing: (found?.editing as boolean | undefined) ?? true,
    provides: (found?.provides as string[] | undefined) ?? [],
  }
})

const canManage = computed(() => context.can('blocks.manage') && meta.value.editing)

const settings = reactive({
  kind: 'block' as BlockKind,
  slug: '',
  title: '',
  description: '' as string | null,
  icon: '' as string | null,
  group: 'content',
  sort: 0,
  allow: [] as string[],
  allowed_in: [] as string[],
  max_per_entity: null as number | null,
  is_enabled: true,
})

/* The sample form's nested constructors add into this block, as it stands in the settings. */
provide(
  blocksOwnerKey,
  computed(() =>
    block.value
      ? {
          ...block.value,
          slug: settings.slug,
          title: settings.title,
          allow: settings.allow.length ? settings.allow : null,
        }
      : null,
  ),
)

const content = reactive<BlockContent>({
  schema: [],
  template: '',
  styles: '',
  script: null,
  sample: {},
})

/** The schema as text, kept as typed: reformatting JSON under the caret moves the caret. */
const schemaText = ref('[]')
const schemaError = ref<string | null>(null)

const snapshot = ref('')
const errors = ref<Record<string, string[]>>({})
const refusal = ref<PublishRefusal | null>(null)

const stage = reactive({
  html: '',
  styles: '',
  script: null as string | null,
  runtime: null as string | null,
  stage: null as string | null,
  loading: false,
})

const current = computed(() => JSON.stringify({ settings, content }))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const lints = computed(() => lintBlock(settings.slug, content, t))

/**
 * What a tag in this template can call: every type, and every place a module declared and the
 * site has not customised — a tag with a `fallback` calls those too. Read once per editor.
 */
const targets = ref<TagTarget[]>([])
const typeIds = new Map<string, number>()
const schemas = new Map<string, Promise<BlockContent['schema'] | null>>()

async function loadTargets(): Promise<void> {
  try {
    const list = await api.index()

    typeIds.clear()
    for (const type of list.blocks) typeIds.set(type.slug, type.id)

    targets.value = [
      ...list.blocks.map((type) => ({
        slug: type.slug,
        title: type.title,
        kind: kindOf(type),
        fallback: list.declared.find((place) => place.slug === type.slug)?.fallback ?? null,
      })),
      ...list.declared
        .filter((place) => !place.customised)
        .map((place) => ({
          slug: place.slug,
          title: place.title,
          kind: 'component' as const,
          fallback: place.fallback,
        })),
    ]
  } catch {
    // Only the suggestions go without it; the editor has nothing else to do with the list.
  }
}

/** A called type's schema, asked for once and kept: its own for the type being edited. */
function schemaOf(
  slug: string,
): BlockContent['schema'] | null | Promise<BlockContent['schema'] | null> {
  if (slug === settings.slug) return content.schema

  const id = typeIds.get(slug)
  if (id === undefined) return null

  let found = schemas.get(slug)

  if (found === undefined) {
    found = api.get(id).then(
      (type) => type.content?.schema ?? null,
      () => null,
    )
    schemas.set(slug, found)
  }

  return found
}

/* Built once: the sources read the other files of the type when asked, not when made. */
const assist = {
  template: [
    completions(
      templateCompletions({
        schema: () => content.schema,
        styles: () => content.styles,
        types: () => targets.value,
        schemaOf,
      }),
    ),
  ],
  styles: [
    completions(stylesCompletions({ slug: () => settings.slug, template: () => content.template })),
  ],
  // The renderer's own types under the panel's, the same merge the sample form is drawn with.
  schema: [completions(schemaCompletions({ types: () => ({ ...coreTypes, ...context.types }) }))],
}

const groupOptions = computed(() => {
  const ids = [...meta.value.groups]
  if (!ids.includes(settings.group)) ids.push(settings.group)

  return ids.map((group) => ({ value: group, label: groupLabel(group, t) }))
})

const sampleModel = computed<ScreenModel>({
  get: () => content.sample,
  set: (next) => {
    content.sample = next
  },
})

const fieldIds = computed(() => collectIds(content.schema))

function collectIds(nodes: BlockContent['schema']): string[] {
  const ids: string[] = []

  for (const node of nodes) {
    if (node.type !== 'wx-blocks' && !(node.children?.length && !node.name)) ids.push(node.id)
    if (node.children) ids.push(...collectIds(node.children))
  }

  return ids
}

function take(loaded: BlockType): void {
  block.value = loaded

  Object.assign(settings, {
    kind: kindOf(loaded),
    slug: loaded.slug,
    title: loaded.title,
    description: loaded.description,
    icon: loaded.icon,
    group: loaded.group,
    sort: loaded.sort,
    allow: loaded.allow ?? [],
    allowed_in: loaded.allowed_in ?? [],
    max_per_entity: loaded.max_per_entity,
    is_enabled: loaded.is_enabled,
  })

  const stored = loaded.content ?? {
    schema: [],
    template: '',
    styles: '',
    script: null,
    sample: {},
  }

  Object.assign(content, clone(stored))
  schemaText.value = JSON.stringify(stored.schema, null, 2)
  schemaError.value = null
  snapshot.value = current.value
}

async function load(): Promise<void> {
  loading.value = true

  try {
    take(await api.get(id.value))
    usage.value = await api.usage(id.value)
    void loadTargets()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

function onSchemaText(text: string): void {
  schemaText.value = text

  try {
    const parsed: unknown = JSON.parse(text)

    if (!Array.isArray(parsed)) throw new Error('not a list')

    content.schema = parsed as BlockContent['schema']
    schemaError.value = null
  } catch (error) {
    schemaError.value = t('page.schema-invalid', { error: (error as Error).message })
  }
}

/* The stage redraws a moment after the last keystroke, not on every one: a render is a
   request, and a request per character is a queue nobody asked for. */
let timer: ReturnType<typeof setTimeout> | undefined
let pending = 0

function redraw(): void {
  clearTimeout(timer)
  timer = setTimeout(() => void render(), 250)
}

async function render(): Promise<void> {
  if (!block.value) return

  const ticket = ++pending
  stage.loading = true

  try {
    const drawn = await api.render(block.value.id, {
      values: content.sample,
      content: canManage.value
        ? {
            template: content.template,
            styles: content.styles,
            script: content.script,
            schema: content.schema,
          }
        : undefined,
    })

    if (ticket !== pending) return

    stage.html = drawn.html
    stage.styles = drawn.styles
    stage.script = drawn.script
    stage.runtime = drawn.runtime
    stage.stage = drawn.stage ?? null
  } catch (error) {
    if (ticket === pending) {
      toast.danger(message(error))
    }
  } finally {
    if (ticket === pending) stage.loading = false
  }
}

watch(
  () => [content.template, content.styles, content.script, content.schema, content.sample],
  redraw,
  { deep: true },
)

const templateEditor = ref<{ view: { value: unknown } } | null>(null)

/* Written as a function because the mustaches themselves cannot stand in a template. */
function pill(field: string): string {
  return `{{ $${field} }}`
}

/** `{{ $field }}` at the caret of the template editor. */
function insertField(field: string): void {
  const view = (templateEditor.value as { view?: { value?: unknown } } | null)?.view as
    | {
        value?: {
          dispatch: (tr: unknown) => void
          state: { replaceSelection: (text: string) => unknown }
          focus: () => void
        }
      }
    | undefined
  const editor = view?.value

  if (!editor) {
    content.template += `{{ $${field} }}`

    return
  }

  editor.dispatch(editor.state.replaceSelection(`{{ $${field} }}`))
  editor.focus()
}

async function save(): Promise<void> {
  if (!block.value) return

  saving.value = true
  errors.value = {}
  refusal.value = null

  const before = block.value.draft?.number ?? null

  try {
    const saved = await api.update(block.value.id, {
      ...settings,
      allow: settings.allow.length ? settings.allow : null,
      allowed_in: settings.allowed_in.length ? settings.allowed_in : null,
      description: settings.description || null,
      icon: settings.icon || null,
      content: schemaError.value ? { ...content, schema: undefined } : content,
    })

    take(saved)

    toast.success(
      saved.draft && saved.draft.number !== before
        ? t('page.saved', { number: saved.draft.number })
        : t('page.unchanged'),
    )
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]>; message?: string } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(t('page.failed'))
    } else {
      toast.danger(message(error, t('page.failed')))
    }
  } finally {
    saving.value = false
  }
}

async function publish(): Promise<void> {
  if (!block.value?.draft) return

  if (dirty.value) await save()
  if (!block.value.draft) return

  const agreed = await confirm({
    title: t('page.publish-title', { number: block.value.draft.number }),
    // A component stands on no page itself; what a new version reaches is the blocks calling it.
    // A declared place is also printed by the module's views, which the count does not see.
    message: declaredModule.value
      ? t('components.publish-declared', {
          module: declaredModule.value,
          count: callers.value.length,
        })
      : isComponent.value
        ? t('components.publish-text', { count: callers.value.length })
        : t('page.publish-text', { count: block.value.usage_count }),
    confirmText: t('page.publish'),
    cancelText: t('page.cancel'),
  })

  if (!agreed) return

  publishing.value = true
  refusal.value = null

  try {
    take(await api.publish(block.value.id))
    toast.success(t('page.published', { number: block.value.published?.number ?? 0 }))
  } catch (error) {
    const body = (error as { status?: number; body?: PublishRefusal }).body

    if (body?.errors) {
      refusal.value = body
      tab.value = 'template'
      toast.danger(t('page.publish-failed'))
    } else {
      toast.danger(message(error, t('page.publish-failed')))
    }
  } finally {
    publishing.value = false
  }
}

async function remove(): Promise<void> {
  if (!block.value) return

  // Deleting a customised place is how it goes back to the module's view (§2, decision 5), and
  // the dialog says so: "cannot be undone" alone would read as losing the recipe card for good.
  const reset = block.value.declared != null

  const agreed = await confirm({
    title: reset
      ? t('components.delete-declared-title', { title: block.value.title })
      : t('page.delete-title', { title: block.value.title }),
    message: reset ? t('components.delete-declared-text') : t('page.delete-text'),
    confirmText: reset ? t('components.delete-declared') : t('page.delete'),
    cancelText: t('page.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(block.value.id)
    toast.success(t('page.deleted'))
    void router.push(props.base)
  } catch (error) {
    toast.danger(message(error))
  }
}

const refusalError = computed(() =>
  refusal.value
    ? {
        message: refusal.value.errors.template?.[0] ?? refusal.value.message,
        line: refusal.value.line,
        where: refusal.value.entity
          ? t('page.failed-on', {
              title: refusal.value.entity.title ?? `#${refusal.value.entity.id}`,
            })
          : null,
      }
    : null,
)

/** The two tabs the picture belongs to: the markup and the styles both change it. */
const withStage = computed(() => tab.value === 'template' || tab.value === 'styles')

/**
 * What the script field is, in three lines of it.
 *
 * The body of `async (el, values) => { … }` is a contract nobody guesses from an empty
 * editor: the sentence under it names `el` and `values`, and these say what they are for.
 * The code is code and stays in English; what each one does is a line of the dictionary.
 */
const examples = computed(() => [
  {
    title: t('page.example-handler'),
    code: `el.querySelector('.b-hero__button')?.addEventListener('click', (event) => {\n  event.preventDefault()\n  el.classList.toggle('is-open')\n})`,
  },
  {
    title: t('page.example-values'),
    /* The one thing the sentence above the examples does not say: values reach the script
       only through the attribute, and a template that never prints it hands over `{}`. */
    code: `// <section data-wx-values="{{ json_encode($block->values) }}">\nconst seconds = Number(values.delay ?? 5)\n\nsetInterval(() => el.classList.toggle('is-second'), seconds * 1000)`,
  },
  {
    title: t('page.example-provided'),
    code: `const Swiper = await webx.use('swiper')\n\nnew Swiper(el.querySelector('.b-slider__viewport'), { loop: true })`,
  },
])

const shownUsage = computed(() => usage.value.slice(0, 5))

const isComponent = computed(() => settings.kind === 'component')

/** The blocks whose published version calls this type. */
const callers = computed(() => block.value?.used_by ?? [])

/** The module whose views call this slug, by its name in the panel. */
const declaredModule = computed(() => {
  const id = block.value?.declared?.module

  if (!id) return null

  return (
    context.state.manifest?.modules.find((module) => module.id === id)?.title ??
    id.charAt(0).toUpperCase() + id.slice(1)
  )
})

/** The words of the subtitle: pages for a block, calling blocks for a component. */
const whereWords = computed(() =>
  isComponent.value
    ? callerWords(callers.value.length, t)
    : usageWords(block.value?.usage_count ?? 0, t),
)

/** Anything behind those words: pages, calling blocks, or a module's views. */
const hasWhere = computed(
  () =>
    (block.value?.usage_count ?? 0) > 0 ||
    callers.value.length > 0 ||
    declaredModule.value !== null,
)

/** Why the type cannot be deleted, when it cannot. Pages first: they are what editors see. */
const deleteBlocked = computed<string | null>(() => {
  const count = block.value?.usage_count ?? 0

  if (count > 0) return count === 1 ? t('page.delete-used-one') : t('page.delete-used', { count })

  if (callers.value.length > 0) {
    return t('components.delete-called', {
      titles: callers.value.map((parent) => `"${parent.title}"`).join(', '),
    })
  }

  return null
})

/** The tag that calls this type, with its inputs — the help under a component's template. */
const call = computed(() =>
  callTag(settings.slug, content.schema, content.sample, block.value?.declared?.fallback ?? null),
)

/** Each `wx-data` input and what its shape holds, as far as the server described it. */
const dataInputs = computed(() =>
  schemaFields(content.schema)
    .filter((node) => node.type === 'wx-data')
    .map((node) => {
      const shape = typeof node.props?.shape === 'string' ? node.props.shape : null

      return {
        id: node.id,
        shape,
        fields: shape ? (block.value?.shape?.[shape]?.fields ?? []) : [],
      }
    }),
)

async function copyCall(): Promise<void> {
  try {
    await navigator.clipboard.writeText(call.value)
    toast.success(t('components.copied'))
  } catch {
    // No clipboard (an insecure origin, a denied permission): the tag is on the screen to select.
  }
}

function errorOf(field: string): string | undefined {
  return errors.value[field]?.[0]
}

function leaveGuard(event: BeforeUnloadEvent): void {
  if (dirty.value) event.preventDefault()
}

onMounted(() => {
  void load()
  window.addEventListener('beforeunload', leaveGuard)
})

onBeforeUnmount(() => {
  clearTimeout(timer)
  window.removeEventListener('beforeunload', leaveGuard)
})

watch(id, () => void load())
/* The two things this editor is for. The bar along the bottom repeats them (§3.3). */
const kindOptions = computed(() => [
  { value: 'block', label: t('components.kind-block') },
  { value: 'component', label: t('components.kind-component') },
])

const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [
        { key: 'save', label: t('page.save'), loading: saving.value, run: () => void save() },
        {
          key: 'publish',
          label: t('page.publish'),
          primary: true,
          loading: publishing.value,
          disabled: !block.value?.draft && !dirty.value,
          run: () => void publish(),
        },
      ]
    : [],
)
</script>

<template>
  <div class="wx-block-editor">
    <!-- Shaped like the page it stands in for: the head on the ground, the rest on cards. -->
    <template v-if="loading || !block">
      <wx-skeleton class="wx-block-editor__ghost-head" title :rows="1" />
      <div class="wx-block-editor__split">
        <wx-card><wx-skeleton :rows="8" /></wx-card>
        <wx-card><wx-skeleton :rows="4" /></wx-card>
      </div>
    </template>

    <template v-else>
      <!--
        The panel's head, not this editor's: the way out, the name, what state the type is in
        and what can be done with it. Renaming is a control beside the name rather than typing
        into what looks like a heading.
      -->
      <wx-screen-head
        :title="settings.title"
        :back="base"
        :back-label="t('page.back')"
        :actions="actions"
      >
        <template #title-after>
          <wx-rename-button
            v-if="canManage"
            :name="settings.title"
            :placeholder="t('page.title')"
            @rename="(name: string) => (settings.title = name)"
          />
          <wx-badge v-if="dirty" type="primary" dot>{{ t('page.unsaved') }}</wx-badge>
          <wx-badge v-if="block.draft" type="warning" dot>{{
            t('page.draft', { number: block.draft.number })
          }}</wx-badge>
          <wx-badge v-if="block.published" type="success" dot>{{
            t('page.live', { number: block.published.number })
          }}</wx-badge>
          <wx-badge v-else type="default">{{ t('page.never-published') }}</wx-badge>
        </template>

        <!--
          Where the type stands is one line of the subtitle and a list behind it: a card of
          five page names took a quarter of the column beside the stage to say what the head
          already says in three words. The words are the door.
        -->
        <template #subtitle>
          <code>{{ settings.slug }}</code>
          ·
          {{ isComponent ? t('components.kind-component') : groupLabel(settings.group, t) }}
          ·
          <wx-popover v-if="hasWhere" :title="t('page.usage')" :width="260" align="start">
            <template #trigger>
              <button type="button" class="wx-block-editor__uses">
                {{ whereWords }}
              </button>
            </template>

            <div class="wx-block-editor__usage">
              <!-- The blocks calling it first on a component: those are what a change reaches. -->
              <router-link
                v-for="parent in callers"
                :key="`type:${parent.id}`"
                :to="`${base}/${parent.id}`"
                class="wx-block-editor__use"
              >
                {{ t('components.called-by', { title: parent.title }) }}
              </router-link>
              <div v-if="declaredModule" class="wx-block-editor__use">
                {{ t('components.module-views', { module: declaredModule }) }}
              </div>
              <div
                v-for="entity in shownUsage"
                :key="`${entity.model}:${entity.id}`"
                class="wx-block-editor__use"
              >
                <span>{{ entity.title ?? `#${entity.id}` }}</span>
                <wx-badge v-if="!entity.published" type="warning" size="sm">{{
                  t('page.unpublished-page')
                }}</wx-badge>
              </div>
              <wx-text v-if="usage.length > shownUsage.length" size="sm" tone="muted">
                {{ t('page.usage-more', { count: usage.length - shownUsage.length }) }}
              </wx-text>
            </div>
          </wx-popover>
          <template v-else>{{ whereWords }}</template>
        </template>
      </wx-screen-head>

      <wx-alert
        v-if="!meta.editing"
        type="info"
        variant="soft"
        :description="t('page.editing-off')"
      />

      <!--
        The block stands beside the two files that draw it and nowhere else: it used to be a
        column down the whole screen, half the width of the settings and of the history, which
        have nothing to do with how the block looks. One stage and not one per tab — `v-show`,
        because a frame taken out of the document and put back reloads.
      -->
      <div class="wx-block-editor__columns" :class="{ 'is-alone': !withStage }">
        <wx-tabs v-model="tab" keep-alive>
          <wx-tab value="template" :label="t('page.tab-template')">
            <div class="wx-block-editor__pane">
              <wx-code-editor
                ref="templateEditor"
                v-model="content.template"
                language="php"
                :extensions="assist.template"
                :readonly="!canManage"
                min-height="340px"
                max-height="70vh"
                line-wrapping
              />
              <div v-if="fieldIds.length" class="wx-block-editor__pills">
                <wx-text size="sm" tone="muted">{{ t('page.insert-field') }}</wx-text>
                <button
                  v-for="field in fieldIds"
                  :key="field"
                  type="button"
                  class="wx-block-editor__pill"
                  :disabled="!canManage"
                  @click="insertField(field)"
                  v-text="pill(field)"
                />
              </div>
              <block-checks
                file="template"
                :lints="lints"
                :slug="settings.slug"
                :error="refusalError"
              />
            </div>

            <!--
              How this component is called, ready to copy: the tag with every input it takes, and
              for a structure passed from code, what the structure holds. A component is only
              ever used this way, so the one thing a person needs next is written out here.
            -->
            <wx-card v-if="isComponent" class="wx-block-editor__call" :title="t('components.call')">
              <wx-text size="sm" tone="muted">{{ t('components.call-help') }}</wx-text>
              <div class="wx-block-editor__call-tag">
                <pre><code>{{ call }}</code></pre>
                <wx-button size="sm" variant="outline" @click="copyCall">
                  {{ t('components.copy') }}
                </wx-button>
              </div>

              <div v-for="input in dataInputs" :key="input.id" class="wx-block-editor__shape">
                <wx-text size="sm" tone="muted">
                  {{
                    input.fields.length
                      ? t('components.shape', { name: input.id })
                      : t('components.shape-free', { name: input.id })
                  }}
                  <code v-if="input.shape">{{ input.shape }}</code>
                </wx-text>
                <dl v-if="input.fields.length" class="wx-block-editor__shape-fields">
                  <template v-for="field in input.fields" :key="field.name">
                    <dt>
                      <code>{{ field.name }}</code>
                      <span class="wx-block-editor__shape-type">{{ field.type }}</span>
                    </dt>
                    <dd>{{ field.description }}</dd>
                  </template>
                </dl>
              </div>

              <wx-text size="sm" tone="muted">{{ t('components.site-views') }}</wx-text>
            </wx-card>
          </wx-tab>

          <wx-tab value="styles" :label="t('page.tab-styles')">
            <div class="wx-block-editor__pane">
              <wx-code-editor
                v-model="content.styles"
                language="css"
                :extensions="assist.styles"
                :readonly="!canManage"
                min-height="340px"
                max-height="70vh"
              />
              <block-checks file="styles" :lints="lints" :slug="settings.slug" />
            </div>
          </wx-tab>

          <wx-tab value="script" :label="t('page.tab-script')">
            <div class="wx-block-editor__pane">
              <wx-code-editor
                :model-value="content.script ?? ''"
                language="javascript"
                :readonly="!canManage"
                min-height="340px"
                max-height="70vh"
                @update:model-value="content.script = $event || null"
              />
              <div class="wx-block-editor__note">
                <wx-text size="sm" tone="muted">{{ t('page.script-help') }}</wx-text>
                <wx-text size="sm" tone="muted">
                  {{
                    meta.provides.length
                      ? t('page.provides', { names: meta.provides.join(', ') })
                      : t('page.provides-none')
                  }}
                </wx-text>
              </div>
            </div>

            <!--
            What the two names in scope are actually for. The examples are code and stay in
            English; what each one is for is a line of the dictionary. Reading, not inserting:
            dropping four lines into the middle of somebody's function is not a favour.
          -->
            <wx-card class="wx-block-editor__examples" :title="t('page.examples')">
              <div
                v-for="example in examples"
                :key="example.title"
                class="wx-block-editor__example"
              >
                <wx-text size="sm" tone="muted">{{ example.title }}</wx-text>
                <pre><code>{{ example.code }}</code></pre>
              </div>
            </wx-card>
          </wx-tab>

          <!--
          The schema and the form it builds, side by side: the sample used to stand in the
          column beside the stage, where it pushed the picture up and stood open on every tab
          — including the four where nobody is looking at values. Here it is the other half of
          the tab it belongs to, and editing a field shows what it becomes.
        -->
          <wx-tab
            value="fields"
            :label="isComponent ? t('components.tab-inputs') : t('page.tab-fields')"
          >
            <div class="wx-block-editor__split">
              <div class="wx-block-editor__pane">
                <wx-code-editor
                  :model-value="schemaText"
                  language="json"
                  :extensions="assist.schema"
                  lint
                  :readonly="!canManage"
                  min-height="340px"
                  max-height="70vh"
                  @update:model-value="onSchemaText"
                />
                <div class="wx-block-editor__note">
                  <wx-text v-if="schemaError" size="sm" tone="danger">{{ schemaError }}</wx-text>
                  <wx-text v-else-if="errorOf('content.schema')" size="sm" tone="danger">{{
                    errorOf('content.schema')
                  }}</wx-text>
                  <wx-text v-else size="sm" tone="muted">{{
                    isComponent ? t('components.inputs-help') : t('page.fields-help')
                  }}</wx-text>

                  <!-- The whole of what a schema is, in the one place somebody writing one is
                       looking. The same page an agent is handed over MCP, from the same
                       `help.schema` line, so that the two cannot drift apart. -->
                  <wx-help-button :title="t('help.schema-title')" :body="t('help.schema')" />
                </div>
              </div>

              <wx-card class="wx-block-editor__sample" :title="t('page.sample')">
                <wx-screen-renderer
                  v-if="content.schema.length"
                  v-model="sampleModel"
                  :root="formSchema(content.schema)"
                  :types="context.types"
                  :translate="context.i18n.t"
                  :can="context.can"
                  :disabled="!canManage"
                  size="sm"
                />
              </wx-card>
            </div>
          </wx-tab>

          <wx-tab value="settings" :label="t('page.tab-settings')">
            <wx-card class="wx-block-editor__sheet">
              <div class="wx-block-editor__settings">
                <wx-form-item
                  :label="t('components.kind')"
                  :help="t('components.kind-help')"
                  :error="errorOf('kind')"
                  :disabled="!canManage"
                >
                  <wx-select v-model="settings.kind" :options="kindOptions" />
                </wx-form-item>
                <wx-form-item
                  :label="t('page.identifier')"
                  :help="t('page.identifier-help')"
                  :error="errorOf('slug')"
                  :disabled="!canManage"
                >
                  <wx-input v-model="settings.slug" />
                </wx-form-item>
                <!-- What only a block on a page needs: where it is picked from, what it may
                     hold and stand in, how many a page takes. A component is never picked. -->
                <wx-form-item
                  v-if="!isComponent"
                  :label="t('page.group')"
                  :help="t('page.group-help')"
                  :error="errorOf('group')"
                  :disabled="!canManage"
                >
                  <wx-select v-model="settings.group" :options="groupOptions" />
                </wx-form-item>
                <wx-form-item
                  class="is-wide"
                  :label="t('page.description')"
                  :help="t('page.description-help')"
                  :error="errorOf('description')"
                  :disabled="!canManage"
                >
                  <wx-textarea
                    :model-value="settings.description ?? ''"
                    :rows="2"
                    @update:model-value="settings.description = String($event ?? '')"
                  />
                </wx-form-item>
                <wx-form-item
                  :label="t('page.icon')"
                  :error="errorOf('icon')"
                  :disabled="!canManage"
                >
                  <!-- Picked, not typed: a name the set does not have draws nothing at all,
                       and nothing on the screen would say which of the two it was. -->
                  <wx-icon-picker
                    :model-value="settings.icon"
                    clearable
                    :placeholder="t('page.icon-search')"
                    :empty-text="t('page.icon-none')"
                    :unknown-text="t('page.icon-unknown')"
                    :clear-label="t('page.icon-clear')"
                    @update:model-value="settings.icon = $event"
                  />
                </wx-form-item>
                <wx-form-item
                  :label="t('page.sort')"
                  :help="t('page.sort-help')"
                  :error="errorOf('sort')"
                  :disabled="!canManage"
                >
                  <wx-input-number v-model="settings.sort" />
                </wx-form-item>
                <wx-form-item
                  v-if="!isComponent"
                  :label="t('page.allow')"
                  :help="t('page.allow-help')"
                  :error="errorOf('allow')"
                  :disabled="!canManage"
                >
                  <wx-tags-input v-model="settings.allow" allow-create />
                </wx-form-item>
                <wx-form-item
                  v-if="!isComponent"
                  :label="t('page.allowed-in')"
                  :help="t('page.allowed-in-help')"
                  :error="errorOf('allowed_in')"
                  :disabled="!canManage"
                >
                  <wx-tags-input
                    v-model="settings.allowed_in"
                    allow-create
                    :suggestions="['root']"
                  />
                </wx-form-item>
                <wx-form-item
                  v-if="!isComponent"
                  :label="t('page.max-per-entity')"
                  :help="t('page.max-per-entity-help')"
                  :error="errorOf('max_per_entity')"
                  :disabled="!canManage"
                >
                  <wx-input-number
                    :model-value="settings.max_per_entity ?? undefined"
                    :min="1"
                    @update:model-value="settings.max_per_entity = $event ?? null"
                  />
                </wx-form-item>
                <wx-form-item
                  v-if="!isComponent"
                  :label="t('page.enabled')"
                  :help="t('page.enabled-help')"
                  :disabled="!canManage"
                >
                  <wx-switch v-model="settings.is_enabled" />
                </wx-form-item>
                <div v-if="canManage" class="is-wide wx-block-editor__danger">
                  <wx-button
                    type="danger"
                    variant="outline"
                    :disabled="deleteBlocked !== null"
                    @click="remove"
                  >
                    {{ block.declared ? t('components.delete-declared') : t('page.delete') }}
                  </wx-button>
                  <wx-text v-if="deleteBlocked" size="sm" tone="muted">{{ deleteBlocked }}</wx-text>
                </div>
              </div>
            </wx-card>
          </wx-tab>

          <wx-tab value="history" :label="t('page.tab-history')">
            <wx-card class="wx-block-editor__sheet" padding="none">
              <block-history :block="block" :can-manage="canManage" @restored="take" />
            </wx-card>
          </wx-tab>
        </wx-tabs>

        <block-stage
          v-show="withStage"
          class="wx-block-editor__stage"
          :html="stage.html"
          :styles="stage.styles"
          :script="stage.script"
          :runtime="stage.runtime"
          :stage="stage.stage"
          :loading="stage.loading"
        />
      </div>

      <!-- The editor is six tabs of code and the stage beside them, so the head is long gone
           by the time there is anything to save: the two buttons stand here as well.
           The three badges do not. What state the type is in is a fact about the type, not
           about the last keystroke, and it is already said once — beside the name, where this
           panel says the state of a record. Twice on one screen is not twice as clear. -->
      <wx-action-bar v-if="canManage">
        <wx-button variant="outline" :loading="saving" @click="save">{{
          t('page.save')
        }}</wx-button>
        <wx-button
          type="primary"
          :loading="publishing"
          :disabled="!block.draft && !dirty"
          @click="publish"
        >
          {{ t('page.publish') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-block-editor {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-block-editor__ghost-head {
  max-width: 420px;
}

/* The tabs and the block beside them, while a tab that draws the block is the open one.
   `is-alone` is the same grid with the second track taken away: a hidden item still leaves
   its column standing, and the tabs would keep half the screen with nothing in the rest. */
.wx-block-editor__columns {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: var(--wx-gap, var(--wx-space-16));
  align-items: start;
}

.wx-block-editor__columns.is-alone {
  grid-template-columns: minmax(0, 1fr);
}

/* What a tab puts side by side: the schema and the form it builds.
   One column below 1080, where two of anything is two half-width columns of nothing. */
.wx-block-editor__split {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: var(--wx-gap, var(--wx-space-16));
  align-items: start;
}

@container (max-width: 1080px) {
  .wx-block-editor__columns,
  .wx-block-editor__split {
    grid-template-columns: minmax(0, 1fr);
  }
}

/* The tag to copy: code on the subtle ground, the button beside it — below it on a phone. */
.wx-block-editor__call {
  min-width: 0;
}

.wx-block-editor__call :deep(.wx-card__body) {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-10);
}

.wx-block-editor__call-tag {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: var(--wx-space-8);
}

.wx-block-editor__call-tag pre {
  flex: 1 1 240px;
  min-width: 0;
  margin: 0;
  padding: var(--wx-space-10) var(--wx-space-12);
  border-radius: var(--wx-radius-sm);
  background: var(--wx-bg-subtle);
  overflow-x: auto;
}

.wx-block-editor__call-tag code {
  line-height: 1.6;
  color: var(--wx-text-default);
  white-space: pre;
}

.wx-block-editor__shape {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
}

/* Name and type on one line, what it is under them: a glossary, read top to bottom. */
.wx-block-editor__shape-fields {
  display: grid;
  grid-template-columns: minmax(0, max-content) minmax(0, 1fr);
  gap: var(--wx-space-4) var(--wx-space-12);
  margin: 0;
  font-size: var(--wx-font-size-sm);
}

.wx-block-editor__shape-fields dt {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: var(--wx-space-6);
}

.wx-block-editor__shape-fields dd {
  margin: 0;
  color: var(--wx-text-muted);
}

.wx-block-editor__shape-type {
  font-size: var(--wx-font-size-xs);
  color: var(--wx-text-muted);
}

@container (max-width: 520px) {
  .wx-block-editor__shape-fields {
    grid-template-columns: minmax(0, 1fr);
  }

  .wx-block-editor__shape-fields dd {
    margin-block-end: var(--wx-space-6);
  }
}

/* Read, not copied: monospace, wrapped, and no box that says "editor". */
.wx-block-editor__examples {
  min-width: 0;
}

.wx-block-editor__example + .wx-block-editor__example {
  margin-block-start: var(--wx-space-12);
}

.wx-block-editor__example pre {
  margin: var(--wx-space-4) 0 0;
  padding: var(--wx-space-10) var(--wx-space-12);
  border-radius: var(--wx-radius-sm);
  background: var(--wx-bg-subtle);
  overflow-x: auto;
}

.wx-block-editor__example code {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  line-height: 1.6;
  color: var(--wx-text-default);
  white-space: pre;
}

/* The picture stays put while the template scrolls under it. */
.wx-block-editor__stage {
  position: sticky;
  top: var(--wx-space-12);
}

.wx-block-editor__pane {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  overflow: hidden;
}

.wx-block-editor__pane :deep(.wx-code-editor) {
  border: 0;
  border-radius: 0;
}

.wx-block-editor__sample {
  min-width: 0;
}

.wx-block-editor__pills {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-6);
  padding: var(--wx-space-8) var(--wx-space-12);
  border-block-start: 1px solid var(--wx-border-muted);
}

.wx-block-editor__pill {
  padding: 2px var(--wx-space-8);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-full);
  background: var(--wx-bg-surface);
  color: var(--wx-color-primary);
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  cursor: pointer;
}

.wx-block-editor__pill:hover:not(:disabled) {
  border-color: var(--wx-color-primary);
}

/* A row: what the editor is being told, and the `?` that tells it at length, at the end. */
.wx-block-editor__note {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-8) var(--wx-space-12);
  border-block-start: 1px solid var(--wx-border-muted);
}

.wx-block-editor__note > :first-child {
  flex: 1 1 auto;
  min-width: 0;
}

/* The settings and the history sit on a card, the way the side column does: the code tabs
   bring their own white with the editor, these two would otherwise stand on the grey. */
.wx-block-editor__sheet {
  min-width: 0;
}

.wx-block-editor__settings {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: var(--wx-space-12) var(--wx-space-16);
}

.wx-block-editor__settings .is-wide {
  grid-column: 1 / -1;
}

.wx-block-editor__danger {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  flex-wrap: wrap;
}

/* A word in the subtitle that opens the list: a link, because that is what it does. */
.wx-block-editor__uses {
  padding: 0;
  border: 0;
  background: none;
  font: inherit;
  color: var(--wx-text-link);
  cursor: pointer;
}

.wx-block-editor__uses:hover {
  text-decoration: underline;
  text-underline-offset: 0.2em;
}

.wx-block-editor__uses:focus-visible {
  outline: none;
  border-radius: var(--wx-radius-xs);
  box-shadow: var(--wx-ring-focus);
}

.wx-block-editor__usage {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
}

.wx-block-editor__use {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
}

a.wx-block-editor__use {
  color: var(--wx-text-link);
  text-decoration: none;
}

a.wx-block-editor__use:hover {
  text-decoration: underline;
  text-underline-offset: 0.2em;
}

code {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
}
</style>
