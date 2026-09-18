<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxBackButton,
  WxRenameButton,
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
  WxInput,
  WxInputNumber,
  WxSelect,
  WxSkeleton,
  WxSwitch,
  WxTab,
  WxTabs,
  WxTagsInput,
  WxText,
  WxTextarea,
} from '@webx-ui/core'
import { WxScreenRenderer, type ScreenModel } from '@webx-ui/schema'
import { createBlocksApi } from './api'
import BlockChecks from './BlockChecks.vue'
import BlockHistory from './BlockHistory.vue'
import BlockStage from './BlockStage.vue'
import { clone } from './content'
import { useBlocksMessages } from './i18n'
import { lintBlock } from './lint'
import { formSchema, groupLabel } from './schema'
import type { BlockContent, BlocksMeta, BlockType, BlockUsage, PublishRefusal } from './types'

/**
 * The editor of one type: two columns. On the left the four files and the settings, on the
 * right the block drawn on its sample, the sample's form built from the schema being edited,
 * and where the type stands. The loop is closed — edit the schema and the form rebuilds,
 * edit the values and the stage redraws, edit the template or the styles and so does it.
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
  loading: false,
})

const current = computed(() => JSON.stringify({ settings, content }))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const lints = computed(() => lintBlock(settings.slug, content, t))

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
    message: t('page.publish-text', { count: block.value.usage_count }),
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

  const agreed = await confirm({
    title: t('page.delete-title', { title: block.value.title }),
    message: t('page.delete-text'),
    confirmText: t('page.delete'),
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

const shownUsage = computed(() => usage.value.slice(0, 5))

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
</script>

<template>
  <div class="wx-block-editor">
    <!-- Shaped like the page it stands in for: the head on the ground, the rest on cards. -->
    <template v-if="loading || !block">
      <wx-skeleton class="wx-block-editor__ghost-head" title :rows="1" />
      <div class="wx-block-editor__columns">
        <wx-card><wx-skeleton :rows="8" /></wx-card>
        <wx-card><wx-skeleton :rows="4" /></wx-card>
      </div>
    </template>

    <template v-else>
      <div class="wx-block-editor__head">
        <!--
          The way out, said with a control rather than with a line of small grey type — and
          renaming with a control rather than by typing into what looks like a heading. Both
          are the panel's, not this editor's: every screen that opens one record needs them.
        -->
        <wx-back-button class="wx-block-editor__back" :to="base" :label="t('page.back')" />

        <div class="wx-block-editor__id">
          <div class="wx-block-editor__name">
            <h1 class="wx-block-editor__title">{{ settings.title }}</h1>
            <wx-rename-button
              v-if="canManage"
              :name="settings.title"
              :placeholder="t('page.title')"
              @rename="(name: string) => (settings.title = name)"
            />
          </div>
          <wx-text size="sm" tone="muted">
            <code>{{ settings.slug }}</code>
            · {{ groupLabel(settings.group, t) }} ·
            {{
              block.usage_count > 0
                ? t('page.on-pages', { count: block.usage_count })
                : t('page.not-used')
            }}
          </wx-text>
        </div>
        <div class="wx-block-editor__actions">
          <wx-badge v-if="dirty" type="primary" dot>{{ t('page.unsaved') }}</wx-badge>
          <wx-badge v-if="block.draft" type="warning" dot>{{
            t('page.draft', { number: block.draft.number })
          }}</wx-badge>
          <wx-badge v-if="block.published" type="success" dot>{{
            t('page.live', { number: block.published.number })
          }}</wx-badge>
          <wx-badge v-else type="default">{{ t('page.never-published') }}</wx-badge>
          <template v-if="canManage">
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
          </template>
        </div>
      </div>

      <wx-alert
        v-if="!meta.editing"
        type="info"
        variant="soft"
        :description="t('page.editing-off')"
      />

      <div class="wx-block-editor__columns">
        <div class="wx-block-editor__files">
          <wx-tabs v-model="tab" keep-alive>
            <wx-tab value="template" :label="t('page.tab-template')">
              <div class="wx-block-editor__pane">
                <wx-code-editor
                  ref="templateEditor"
                  v-model="content.template"
                  language="php"
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
            </wx-tab>

            <wx-tab value="styles" :label="t('page.tab-styles')">
              <div class="wx-block-editor__pane">
                <wx-code-editor
                  v-model="content.styles"
                  language="css"
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
            </wx-tab>

            <wx-tab value="fields" :label="t('page.tab-fields')">
              <div class="wx-block-editor__pane">
                <wx-code-editor
                  :model-value="schemaText"
                  language="json"
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
                  <wx-text v-else size="sm" tone="muted">{{ t('page.fields-help') }}</wx-text>
                </div>
              </div>
            </wx-tab>

            <wx-tab value="settings" :label="t('page.tab-settings')">
              <wx-card class="wx-block-editor__sheet">
                <div class="wx-block-editor__settings">
                  <wx-form-item
                    :label="t('page.identifier')"
                    :help="t('page.identifier-help')"
                    :error="errorOf('slug')"
                    :disabled="!canManage"
                  >
                    <wx-input v-model="settings.slug" />
                  </wx-form-item>
                  <wx-form-item
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
                    <wx-input
                      :model-value="settings.icon ?? ''"
                      placeholder="grid"
                      @update:model-value="settings.icon = String($event ?? '')"
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
                    :label="t('page.allow')"
                    :help="t('page.allow-help')"
                    :error="errorOf('allow')"
                    :disabled="!canManage"
                  >
                    <wx-tags-input v-model="settings.allow" allow-create />
                  </wx-form-item>
                  <wx-form-item
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
                      :disabled="block.usage_count > 0"
                      @click="remove"
                    >
                      {{ t('page.delete') }}
                    </wx-button>
                    <wx-text v-if="block.usage_count > 0" size="sm" tone="muted">
                      {{ t('page.delete-used', { count: block.usage_count }) }}
                    </wx-text>
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
        </div>

        <div class="wx-block-editor__side">
          <block-stage
            :html="stage.html"
            :styles="stage.styles"
            :script="stage.script"
            :runtime="stage.runtime"
            :loading="stage.loading"
          />

          <wx-card :title="t('page.sample')">
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
            <wx-text size="sm" tone="muted">{{ t('page.sample-help') }}</wx-text>
          </wx-card>

          <wx-card :title="t('page.usage')">
            <wx-text v-if="usage.length === 0" size="sm" tone="muted">{{
              t('page.usage-empty')
            }}</wx-text>
            <div v-else class="wx-block-editor__usage">
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
          </wx-card>
        </div>
      </div>

      <!-- The editor is four tabs of code and a column of cards beside them, so the head is
           long gone by the time there is anything to save. Same two buttons, same state. -->
      <wx-action-bar v-if="canManage">
        <template #state>
          <wx-badge v-if="dirty" type="primary" dot>{{ t('page.unsaved') }}</wx-badge>
          <wx-badge v-if="block.draft" type="warning" dot>{{
            t('page.draft', { number: block.draft.number })
          }}</wx-badge>
          <wx-badge v-if="block.published" type="success" dot>{{
            t('page.live', { number: block.published.number })
          }}</wx-badge>
        </template>

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

.wx-block-editor__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--wx-space-16);
  flex-wrap: wrap;
}

.wx-block-editor__ghost-head {
  max-width: 420px;
}

.wx-block-editor__id {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  flex: 1 1 280px;
  min-width: 0;
}

/* Level with the name rather than with the middle of the whole block of text under it. */
.wx-block-editor__back {
  flex: none;
  margin-block-start: var(--wx-space-2);
}

.wx-block-editor__name {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-block-editor__title {
  margin: 0;
  min-width: 0;
  overflow: hidden;
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-xl);
  font-weight: var(--wx-font-weight-bold);
  line-height: var(--wx-font-line-height-tight);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-block-editor__actions {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  flex-wrap: wrap;
}

.wx-block-editor__columns {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: var(--wx-gap, var(--wx-space-16));
  align-items: start;
}

@container (max-width: 1080px) {
  .wx-block-editor__columns {
    grid-template-columns: minmax(0, 1fr);
  }
}

.wx-block-editor__files {
  min-width: 0;
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

.wx-block-editor__pills {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-6);
  padding: var(--wx-space-8) var(--wx-space-12);
  border-block-start: 1px solid var(--wx-color-border-muted, var(--wx-border-default));
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

.wx-block-editor__note {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  padding: var(--wx-space-8) var(--wx-space-12);
  border-block-start: 1px solid var(--wx-color-border-muted, var(--wx-border-default));
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

.wx-block-editor__side {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
  position: sticky;
  top: var(--wx-space-12);
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

code {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
}
</style>
