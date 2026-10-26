<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  useElementWidth,
  WxAction,
  WxAlert,
  WxButton,
  WxCard,
  WxDropdown,
  WxDropdownItem,
  WxFormItem,
  WxSegmented,
  WxSortableList,
  WxText,
} from '@webx-ui/core'
import { WxMediaField } from '@webx-ui/module-media'
import ConditionsEditor from './ConditionsEditor.vue'
import ConfiguratorPreview from './ConfiguratorPreview.vue'
import StepEditor from './StepEditor.vue'
import { conditionErrors, errorsAt, useBuilder } from './builder'
import { forgetKey, isChoice, newStep, rowId } from './document'
import type { Condition, ConfiguratorValues, PreviewRule, StepDoc, StepType } from './engine/types'

/**
 * The constructor (§13.2): the steps on the left — dragged into order, unfolded one at a time,
 * conditions chosen rather than typed — and the configurator as a visitor sees it on the right,
 * priced by the same engine as the site.
 *
 * Problems are listed over the steps and said again at the field they are about; clicking one
 * unfolds its step and brings it into view. Narrow, the two columns become two views of one.
 */
defineOptions({ name: 'WxConfiguratorBuilder' })

const props = defineProps<{
  /** The language the preview shows the texts in. */
  locale: string
}>()

const builder = useBuilder()
const t = useTranslate('webx-configurator')

const root = ref<HTMLElement | null>(null)
const width = useElementWidth(root)
const wide = computed(() => width.value === 0 || width.value >= 960)
const view = ref<'builder' | 'preview'>('builder')

const schema = computed(() => builder.schema.value)
const disabled = builder.disabled

const types: StepType[] = ['cards', 'swatches', 'buttons', 'dimensions']

function setSteps(next: StepDoc[]): void {
  schema.value.steps = next
}

function addStep(type: StepType): void {
  const step = newStep(schema.value, type, builder.locale.value)

  schema.value.steps = [...schema.value.steps, step]

  // The step and its first option or field unfolded: what is added is what is filled in next.
  const added = schema.value.steps[schema.value.steps.length - 1]!
  const first = (added.options ?? added.fields ?? [])[0]

  builder.toggle(rowId(added))
  if (first) builder.toggle(rowId(first))
}

function removeStep(at: number): void {
  const step = schema.value.steps[at]

  if (!step) return

  for (const key of [step.key, ...(step.fields ?? []).map((field) => field.key)]) {
    forgetKey(schema.value, key)
  }

  schema.value.steps = schema.value.steps.filter((_, index) => index !== at)
}

/* ------------------------------------------------------------------------------ problems */

const problems = computed(() => builder.problems.value)

/** Unfold the step (and the option or field) a path is about, then bring it into view. */
async function reveal(path: string): Promise<void> {
  const [, stepAt, kind, itemAt] = /^steps\.(\d+)(?:\.(options|fields)\.(\d+))?/.exec(path) ?? []

  if (stepAt === undefined) return

  if (!wide.value) view.value = 'builder'

  const step = schema.value.steps[Number(stepAt)]

  if (!step) return

  if (!builder.isOpen(rowId(step))) builder.toggle(rowId(step))

  const item = kind ? (step[kind as 'options' | 'fields'] ?? [])[Number(itemAt)] : undefined

  if (item && !builder.isOpen(rowId(item))) builder.toggle(rowId(item))

  await nextTick()

  const target =
    root.value?.querySelector(
      `[data-path="${path
        .split('.')
        .slice(0, kind ? 4 : 2)
        .join('.')}"]`,
    ) ?? root.value?.querySelector(`[data-path="steps.${stepAt}"]`)

  target?.scrollIntoView({ block: 'center', behavior: 'smooth' })
}

/* A problem pointed at from the JSON tab: unfolded once the constructor is drawn. */
watch(
  () => builder.focus.value,
  (path) => {
    if (path === null) return

    builder.focus.value = null
    void reveal(path)
  },
  { immediate: true },
)

function place(path: string): string {
  return path === '' ? '—' : path
}

/* ------------------------------------------------------------------------- picture rules */

const choiceSteps = computed(() => schema.value.steps.filter((step) => isChoice(step.type)))

function rules(): PreviewRule[] {
  return schema.value.previews ?? []
}

function addRule(): void {
  schema.value.previews = [...rules(), { when: {}, image: '' }]
}

function removeRule(at: number): void {
  const next = rules().filter((_, index) => index !== at)

  if (next.length === 0) delete schema.value.previews
  else schema.value.previews = next
}

function setRuleImage(at: number, next: { path?: string } | null): void {
  const rule = rules()[at]

  if (rule) rule.image = next?.path ?? ''
}

function setRuleWhen(at: number, next: Condition | undefined): void {
  const rule = rules()[at]

  if (rule) rule.when = next ?? {}
}

function setRules(next: PreviewRule[]): void {
  schema.value.previews = next
}

/* ------------------------------------------------------------------------ starting choice */

function setInitial(values: ConfiguratorValues): void {
  schema.value.initial = values
  toast.success(t('configurator.preview-initial-done'))
}
</script>

<template>
  <div ref="root" class="wx-configurator-builder" :class="{ 'is-wide': wide }">
    <wx-segmented
      v-if="!wide"
      v-model="view"
      block
      :options="[
        { value: 'builder', label: t('configurator.view-builder') },
        { value: 'preview', label: t('configurator.view-preview') },
      ]"
    />

    <div v-show="wide || view === 'builder'" class="wx-configurator-builder__steps">
      <wx-alert
        v-if="problems.length > 0"
        type="warning"
        :title="t('configurator.problems', { count: problems.length })"
      >
        <p class="wx-configurator-builder__help">{{ t('configurator.problems-help') }}</p>
        <ul class="wx-configurator-builder__problems">
          <li v-for="(problem, at) in problems.slice(0, 12)" :key="at">
            <button
              type="button"
              class="wx-configurator-builder__problem"
              @click="reveal(problem.path)"
            >
              <code>{{ place(problem.path) }}</code>
              <span>{{ builder.message(problem) }}</span>
            </button>
          </li>
        </ul>
      </wx-alert>

      <wx-card>
        <template #header>
          <div class="wx-configurator-builder__head">
            <div>
              <h3 class="wx-configurator-builder__title">{{ t('configurator.steps') }}</h3>
              <wx-text size="sm" tone="muted">{{ t('configurator.steps-help') }}</wx-text>
            </div>
          </div>
        </template>

        <wx-sortable-list
          :model-value="schema.steps"
          plain
          :item-key="(item: StepDoc) => rowId(item)"
          :item-label="(item: StepDoc) => item.key"
          :drag-label="t('configurator.reorder')"
          :disabled="disabled"
          :aria-label="t('configurator.steps')"
          :empty-text="t('configurator.preview-empty')"
          @update:model-value="setSteps"
        >
          <template #default="{ index }">
            <step-editor :index="index" @remove="removeStep(index)" />
          </template>
        </wx-sortable-list>

        <div v-if="!disabled" class="wx-configurator-builder__add">
          <wx-dropdown>
            <template #trigger>
              <wx-button size="sm" variant="outline">+ {{ t('configurator.add-step') }}</wx-button>
            </template>
            <wx-dropdown-item v-for="type in types" :key="type" @click="addStep(type)">
              <span class="wx-configurator-builder__type">
                <span>{{ t(`configurator.type-${type}`) }}</span>
                <small>{{ t(`configurator.type-${type}-help`) }}</small>
              </span>
            </wx-dropdown-item>
          </wx-dropdown>
        </div>
      </wx-card>

      <wx-card v-if="choiceSteps.length > 0">
        <template #header>
          <div>
            <h3 class="wx-configurator-builder__title">{{ t('configurator.previews') }}</h3>
            <wx-text size="sm" tone="muted">{{ t('configurator.previews-help') }}</wx-text>
          </div>
        </template>

        <wx-sortable-list
          v-if="rules().length > 0"
          :model-value="rules()"
          plain
          size="sm"
          :item-key="(item: PreviewRule) => rowId(item)"
          :drag-label="t('configurator.reorder')"
          :disabled="disabled"
          :aria-label="t('configurator.previews')"
          @update:model-value="setRules"
        >
          <template #default="{ item, index }">
            <div class="wx-configurator-builder__rule" :data-path="`previews.${index}`">
              <wx-form-item
                :label="t('configurator.preview-image')"
                :error="errorsAt(builder, `previews.${index}.image`)"
              >
                <wx-media-field
                  :model-value="item.image ? { path: item.image } : null"
                  accept="image"
                  :aspect="null"
                  :height="96"
                  :width="160"
                  :captions="false"
                  :disabled="disabled"
                  @update:model-value="(next) => setRuleImage(index, next)"
                />
              </wx-form-item>
              <conditions-editor
                :model-value="item.when"
                :label="t('configurator.when')"
                :steps="choiceSteps"
                :locale="builder.locale.value"
                :disabled="disabled"
                :errors="conditionErrors(builder, `previews.${index}.when`)"
                @update:model-value="(next) => setRuleWhen(index, next)"
              />
              <wx-action
                v-if="!disabled"
                class="wx-configurator-builder__rule-remove"
                type="remove"
                size="sm"
                :title="t('configurator.preview-remove')"
                @click="removeRule(index)"
              />
            </div>
          </template>
        </wx-sortable-list>

        <wx-button
          v-if="!disabled"
          class="wx-configurator-builder__add-rule"
          size="sm"
          variant="outline"
          @click="addRule"
        >
          + {{ t('configurator.preview-add') }}
        </wx-button>
      </wx-card>
    </div>

    <div v-show="wide || view === 'preview'" class="wx-configurator-builder__preview">
      <wx-card>
        <template #header>
          <div>
            <h3 class="wx-configurator-builder__title">{{ t('configurator.preview-title') }}</h3>
            <wx-text size="sm" tone="muted">{{ t('configurator.preview-help') }}</wx-text>
          </div>
        </template>
        <configurator-preview :locale="props.locale" @initial="setInitial" />
      </wx-card>
    </div>
  </div>
</template>

<style scoped>
.wx-configurator-builder {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
}

.wx-configurator-builder.is-wide {
  display: grid;
  grid-template-columns: minmax(0, 3fr) minmax(320px, 2fr);
  align-items: start;
}

.wx-configurator-builder__steps {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
}

.wx-configurator-builder.is-wide .wx-configurator-builder__preview {
  position: sticky;
  top: var(--wx-space-16);
}

.wx-configurator-builder__title {
  margin: 0;
  font-size: var(--wx-font-size-md);
  font-weight: var(--wx-font-weight-semibold);
}

.wx-configurator-builder__help {
  margin: 0 0 var(--wx-space-8);
}

.wx-configurator-builder__problems {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-configurator-builder__problem {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
  padding: 0;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  font-size: var(--wx-font-size-sm);
  text-align: start;
  cursor: pointer;
}

.wx-configurator-builder__problem:hover span {
  text-decoration: underline;
}

.wx-configurator-builder__problem code {
  font-family: var(--wx-font-family-mono);
  color: var(--wx-text-muted);
  overflow-wrap: anywhere;
}

.wx-configurator-builder__add {
  margin-top: var(--wx-space-12);
}

.wx-configurator-builder__add-rule {
  align-self: flex-start;
}

.wx-configurator-builder__type {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
}

.wx-configurator-builder__type small {
  font-size: var(--wx-font-size-xs);
  color: var(--wx-text-muted);
}

.wx-configurator-builder__rule {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: start;
  gap: var(--wx-space-12);
  flex: 1 1 auto;
  min-width: 0;
  padding: var(--wx-space-8) 0;
}

@container (max-width: 560px) {
  .wx-configurator-builder__rule {
    grid-template-columns: minmax(0, 1fr) auto;
  }
}
</style>
