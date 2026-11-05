<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  mustacheCompletions,
  mustacheProblems,
  snippetOf,
  WxAlert,
  WxButton,
  WxCard,
  WxCodeEditor,
  WxFormItem,
  WxInput,
  WxText,
  WxTooltip,
  type CodeEditorExposed,
  type MustacheVariable,
} from '@webx-ui/core'
import { useTranslate } from '@webx-ui/module-admin'
import { useFeedEditor } from './editor'
import FeedPreview from './FeedPreview.vue'
import { NAMESPACE, useCatalogFeedsMessages } from './i18n'

/**
 * `wx-catalog-feed-template`: the header, the product, the footer and what stands between products
 * (§11 of the feeds spec), with the preview beside them, or under them on a narrow panel. `{{`
 * offers what the part may say: a header and a footer have no product (§4.6). Under the part being
 * edited the same variables as buttons: the editor is the fast way, the buttons are the way to find
 * out what there is.
 */
defineOptions({ name: 'WxCatalogFeedTemplate' })

type Part = 'head' | 'item' | 'foot'

const PARTS: Part[] = ['head', 'item', 'foot']

const editor = useFeedEditor()
useCatalogFeedsMessages()

const t = useTranslate(NAMESPACE)

const active = ref<Part>('item')
const search = ref('')
const editors = ref<Partial<Record<Part, CodeEditorExposed | null>>>({})

const words = computed(() => ({
  variable: t('panel.hint-variable'),
  list: t('panel.hint-list'),
  field: t('panel.hint-field'),
  section: t('panel.hint-section'),
  inverted: t('panel.hint-inverted'),
  close: t('panel.hint-close'),
  comment: t('panel.hint-comment'),
  example: t('panel.hint-example'),
}))

function variablesOf(part: Part): MustacheVariable[] {
  const known = editor.variables.value

  return (part === 'item' ? known?.item : known?.frame) ?? []
}

const completions = computed(() => ({
  head: mustacheCompletions(variablesOf('head'), words.value),
  item: mustacheCompletions(variablesOf('item'), words.value),
  foot: mustacheCompletions(variablesOf('foot'), words.value),
}))

const language = computed(() =>
  editor.values.value.format === 'xml' ? 'mustache-html' : 'mustache',
)

function textOf(part: Part): string {
  return editor.values.value[part] ?? ''
}

function write(part: Part, text: unknown): void {
  editor.values.value = { ...editor.values.value, [part]: typeof text === 'string' ? text : '' }
}

function problemsOf(part: Part): string[] {
  const found = mustacheProblems(textOf(part), variablesOf(part))

  return [
    ...found.unclosed.map((name) => t('panel.problem-unclosed', { name })),
    ...found.stray.map((name) => t('panel.problem-stray', { name })),
    ...found.refused.map((tag) => t('panel.problem-refused', { tag })),
    ...(found.broken ? [t('panel.problem-broken')] : []),
    ...(found.unknown.length > 0
      ? [
          t('panel.problem-unknown', {
            names: found.unknown.map((name) => '{{' + name + '}}').join(', '),
          }),
        ]
      : []),
  ]
}

/** What the last save was refused with, under the part it is about. */
function errorOf(field: string): string | undefined {
  return editor.errors.value[field]?.[0]
}

/* A line break cannot be seen in a field, so it is shown as it is written in a template. */
const separator = computed({
  get: () => editor.values.value.separator.replace(/\n/g, '\\n').replace(/\t/g, '\\t'),
  set: (text: string) => {
    editor.values.value = {
      ...editor.values.value,
      separator: text.replace(/\\n/g, '\n').replace(/\\t/g, '\t'),
    }
  },
})

const shown = computed(() => {
  const term = search.value.trim().toLowerCase()
  const all = variablesOf(active.value)

  if (term === '') return all

  return all.filter(
    (variable) =>
      variable.name.includes(term) || (variable.label ?? '').toLowerCase().includes(term),
  )
})

function tooltipOf(variable: MustacheVariable): string {
  const fields = (variable.fields ?? []).map((field) => field.name).join(', ')

  return [variable.label, fields].filter(Boolean).join(' — ')
}

/** Into the part being edited, where its caret is. */
function insert(variable: MustacheVariable): void {
  const view = editors.value[active.value]?.view

  if (!view) return

  const snippet = snippetOf(variable)
  const range = view.state.selection.main

  view.dispatch({
    changes: { from: range.from, to: range.to, insert: snippet },
    selection: { anchor: range.from + snippet.length },
    scrollIntoView: true,
  })
  view.focus()
}

function keep(part: Part, exposed: unknown): void {
  editors.value[part] = exposed as CodeEditorExposed | null
}
</script>

<template>
  <div class="wx-catalog-feed-template">
    <div class="wx-catalog-feed-template__layout">
      <div class="wx-catalog-feed-template__parts">
        <wx-card>
          <div class="wx-catalog-feed-template__stack">
            <template v-for="part in PARTS" :key="part">
              <wx-form-item :label="t(`feed.${part}`)" :error="errorOf(part)">
                <wx-code-editor
                  :ref="(exposed) => keep(part, exposed)"
                  :model-value="textOf(part)"
                  :language="language"
                  :completions="completions[part]"
                  :disabled="editor.locked.value"
                  :line-numbers="true"
                  :line-wrapping="true"
                  :min-height="part === 'item' ? '260px' : '96px'"
                  @update:model-value="(text: unknown) => write(part, text)"
                  @focus="active = part"
                />
              </wx-form-item>

              <wx-alert
                v-for="line in problemsOf(part)"
                :key="line"
                type="warning"
                variant="soft"
                :description="line"
              />

              <div v-if="active === part" class="wx-catalog-feed-template__variables">
                <wx-text size="sm" weight="medium">{{ t('panel.variables') }}</wx-text>
                <wx-text size="sm" tone="muted">{{ t('panel.variables-help') }}</wx-text>

                <wx-input
                  v-model="search"
                  size="sm"
                  clearable
                  :placeholder="t('panel.variables-search')"
                  :aria-label="t('panel.variables-search')"
                />

                <div class="wx-catalog-feed-template__chips">
                  <wx-tooltip
                    v-for="variable in shown"
                    :key="variable.name"
                    :content="tooltipOf(variable)"
                  >
                    <wx-button
                      size="sm"
                      variant="outline"
                      :disabled="editor.locked.value"
                      @click="insert(variable)"
                    >
                      <span class="wx-catalog-feed-template__name">
                        {{
                          (variable.fields?.length ?? 0) > 0 ? `#${variable.name}` : variable.name
                        }}
                      </span>
                    </wx-button>
                  </wx-tooltip>
                </div>
              </div>
            </template>

            <wx-form-item
              :label="t('feed.separator')"
              :help="t('panel.separator-help')"
              :error="errorOf('separator')"
            >
              <wx-input v-model="separator" :disabled="editor.locked.value" />
            </wx-form-item>
          </div>
        </wx-card>
      </div>

      <feed-preview class="wx-catalog-feed-template__preview" />
    </div>
  </div>
</template>

<style scoped>
.wx-catalog-feed-template {
  container-type: inline-size;
}

.wx-catalog-feed-template__layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--wx-gap, var(--wx-space-16));
  align-items: start;
}

@container (min-width: 900px) {
  .wx-catalog-feed-template__layout {
    grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
  }
}

.wx-catalog-feed-template__stack {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-catalog-feed-template__variables {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
}

.wx-catalog-feed-template__chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-6);
  max-height: 200px;
  overflow-y: auto;
}

.wx-catalog-feed-template__name {
  font-family: var(--wx-font-family-mono);
}
</style>
