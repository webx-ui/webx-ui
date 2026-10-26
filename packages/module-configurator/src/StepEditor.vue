<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import {
  confirm,
  WxAction,
  WxBadge,
  WxButton,
  WxFormItem,
  WxIcon,
  WxInput,
  WxSelect,
  WxSortableList,
  type LocalizedValue,
} from '@webx-ui/core'
import ConditionsEditor from './ConditionsEditor.vue'
import FieldEditor from './FieldEditor.vue'
import OptionEditor from './OptionEditor.vue'
import { conditionErrors, errorsAt, useBuilder } from './builder'
import {
  forgetKey,
  forgetValue,
  isChoice,
  localized,
  newField,
  newOption,
  problemsUnder,
  renameKey,
  rowId,
  setText,
  stepsAbove,
  takenKeys,
  textIn,
} from './document'
import { mainStep } from './engine/engine'
import type { Condition, FieldDoc, OptionDoc, StepType } from './engine/types'

/**
 * One step of the configurator in the constructor (§13.2): folded, a line with its name, type and
 * problems; unfolded, its key, words and conditions, and under them its options or its size
 * fields — each dragged into order and unfolded one at a time.
 *
 * A key renamed takes every condition that names it along, a step removed takes them away: what
 * was built on a step does not break silently when the step changes.
 */
defineOptions({ name: 'WxConfiguratorStep' })

const props = defineProps<{ index: number }>()

const emit = defineEmits<{ remove: [] }>()

const builder = useBuilder()
const t = useTranslate('webx-configurator')

const step = computed(() => builder.schema.value.steps[props.index]!)
const path = computed(() => `steps.${props.index}`)
const locale = builder.locale
const disabled = builder.disabled

const errors = (suffix: string) => errorsAt(builder, `${path.value}.${suffix}`)
const count = computed(() => problemsUnder(builder.problems.value, path.value).length)

const id = computed(() => rowId(step.value))
const open = computed(() => builder.isOpen(id.value))

const name = computed(() =>
  textIn(step.value.label, locale.value, textIn(step.value.title, locale.value, '')),
)

const main = computed(() => mainStep(builder.schema.value) === step.value.key)
const choice = computed(() => isChoice(step.value.type))

/* --------------------------------------------------------------------------- the key */

const key = ref(step.value.key)
watch(
  () => step.value.key,
  (next) => (key.value = next),
)

/* Renamed when the field is left, not on every key: halfway it may spell another step's key. */
function commitKey(): void {
  const next = key.value.trim()
  const before = step.value.key

  if (next === before) return

  if (next !== '' && !takenKeys(builder.schema.value).includes(next)) {
    renameKey(builder.schema.value, before, next)
  }

  step.value.key = next
}

/* --------------------------------------------------------------------- type and words */

const types = computed(() =>
  (['cards', 'swatches', 'buttons'] as const).map((type) => ({
    value: type,
    label: t(`configurator.type-${type}`),
  })),
)

/**
 * Between the kinds of options the change is only how they are drawn: a picture a card needs, a
 * swatch the swatches; the options stay. A sizes step is a sizes step for good.
 */
function setType(next: unknown): void {
  if (typeof next !== 'string' || !isChoice(next)) return

  step.value.type = next as StepType

  for (const option of step.value.options ?? []) {
    if (next === 'cards' && option.image === undefined) option.image = null
    if (next === 'swatches' && option.swatch === undefined && !option.swatchImage)
      option.swatch = '#cccccc'
  }
}

function text(
  field: 'label' | 'title' | 'hint',
  next: LocalizedValue | string | number | undefined,
): void {
  setText(step.value, field, typeof next === 'number' ? String(next) : (next ?? null))
}

const above = computed(() => stepsAbove(builder.schema.value, props.index))

function setCondition(which: 'when' | 'unless', next: Condition | undefined): void {
  if (next === undefined) delete step.value[which]
  else step.value[which] = next
}

/* ------------------------------------------------------------------ options and fields */

function setOptions(next: OptionDoc[]): void {
  step.value.options = next
}

function setFields(next: FieldDoc[]): void {
  step.value.fields = next
}

function addOption(): void {
  const option = newOption(step.value, locale.value)

  step.value.options = [...(step.value.options ?? []), option]
  builder.toggle(rowId(step.value.options[step.value.options.length - 1]!))
}

function addField(): void {
  const field = newField(takenKeys(builder.schema.value), locale.value)

  step.value.fields = [...(step.value.fields ?? []), field]
  builder.toggle(rowId(step.value.fields[step.value.fields.length - 1]!))
}

function removeOption(at: number): void {
  const option = step.value.options?.[at]

  if (!option) return

  forgetValue(builder.schema.value, step.value.key, option.value)
  step.value.options = step.value.options!.filter((_, index) => index !== at)
}

function removeField(at: number): void {
  const field = step.value.fields?.[at]

  if (!field) return

  forgetKey(builder.schema.value, field.key)
  step.value.fields = step.value.fields!.filter((_, index) => index !== at)
}

async function remove(): Promise<void> {
  const agreed = await confirm({
    title: t('configurator.step-remove-title', { name: name.value || step.value.key }),
    message: t('configurator.step-remove-text'),
    confirmText: t('configurator.step-remove'),
    cancelText: t('configurator.cancel'),
    tone: 'danger',
  })

  if (agreed) emit('remove')
}

function optionName(option: OptionDoc): string {
  return textIn(option.label, locale.value, '')
}

function optionProblems(at: number): number {
  return problemsUnder(builder.problems.value, `${path.value}.options.${at}`).length
}

function fieldProblems(at: number): number {
  return problemsUnder(builder.problems.value, `${path.value}.fields.${at}`).length
}
</script>

<template>
  <div class="wx-configurator-step" :class="{ 'is-open': open }" :data-path="path">
    <div class="wx-configurator-step__head">
      <button
        type="button"
        class="wx-configurator-step__toggle"
        :aria-expanded="open"
        :title="open ? t('configurator.collapse') : t('configurator.expand')"
        @click="builder.toggle(id)"
      >
        <wx-icon :name="open ? 'chevron-down' : 'chevron-right'" size="sm" />
        <span class="wx-configurator-step__name">{{
          name || t('configurator.step-untitled')
        }}</span>
        <span class="wx-configurator-step__key">{{ step.key }}</span>
      </button>
      <span class="wx-configurator-step__badges">
        <wx-badge size="sm">{{ t(`configurator.type-${step.type}`) }}</wx-badge>
        <wx-badge v-if="main" size="sm" type="primary" :title="t('configurator.step-main-help')">
          {{ t('configurator.step-main') }}
        </wx-badge>
        <wx-badge v-if="count > 0" size="sm" type="danger" round>{{ count }}</wx-badge>
      </span>
      <wx-action
        v-if="!disabled"
        type="remove"
        size="sm"
        :title="t('configurator.step-remove')"
        @click="remove"
      />
    </div>

    <div v-if="open" class="wx-configurator-step__body">
      <div class="wx-configurator-step__grid">
        <wx-form-item
          :label="t('configurator.step-label')"
          :help="t('configurator.step-label-help')"
          :error="errors('label')"
        >
          <wx-input
            :model-value="localized(step.label, locale)"
            localized
            size="sm"
            :disabled="disabled"
            @update:model-value="(next) => text('label', next)"
          />
        </wx-form-item>
        <wx-form-item
          :label="t('configurator.step-title')"
          :help="t('configurator.step-title-help')"
          :error="errors('title')"
        >
          <wx-input
            :model-value="localized(step.title, locale)"
            localized
            size="sm"
            :disabled="disabled"
            @update:model-value="(next) => text('title', next)"
          />
        </wx-form-item>
        <wx-form-item :label="t('configurator.step-hint')" :error="errors('hint')">
          <wx-input
            :model-value="localized(step.hint, locale)"
            localized
            size="sm"
            :disabled="disabled"
            @update:model-value="(next) => text('hint', next)"
          />
        </wx-form-item>
        <wx-form-item
          :label="t('configurator.step-key')"
          :help="t('configurator.step-key-help')"
          :error="errors('key')"
          required
        >
          <wx-input
            v-model="key"
            size="sm"
            :disabled="disabled"
            @change="commitKey"
            @blur="commitKey"
          />
        </wx-form-item>
        <wx-form-item v-if="choice" :label="t('configurator.step-type')" :error="errors('type')">
          <wx-select
            :model-value="step.type"
            :options="types"
            size="sm"
            :disabled="disabled"
            @update:model-value="setType"
          />
        </wx-form-item>
      </div>

      <div class="wx-configurator-step__conditions">
        <conditions-editor
          :model-value="step.when"
          :label="t('configurator.when')"
          :steps="above"
          :locale="locale"
          :disabled="disabled"
          :errors="conditionErrors(builder, `${path}.when`)"
          @update:model-value="(next) => setCondition('when', next)"
        />
        <conditions-editor
          v-if="above.length > 0"
          :model-value="step.unless"
          :label="t('configurator.unless')"
          :steps="above"
          :locale="locale"
          :disabled="disabled"
          :errors="conditionErrors(builder, `${path}.unless`)"
          @update:model-value="(next) => setCondition('unless', next)"
        />
      </div>

      <template v-if="choice">
        <p v-for="error in errors('options')" :key="error" class="wx-configurator-step__error">
          {{ error }}
        </p>
        <wx-sortable-list
          :model-value="step.options ?? []"
          size="sm"
          :item-key="(item: OptionDoc) => rowId(item)"
          :item-label="(item: OptionDoc) => optionName(item) || item.value"
          :drag-label="t('configurator.reorder')"
          :disabled="disabled"
          :title="t('configurator.options')"
          @update:model-value="setOptions"
        >
          <template #default="{ item, index: at }">
            <div class="wx-configurator-step__row">
              <div class="wx-configurator-step__row-head">
                <button
                  type="button"
                  class="wx-configurator-step__toggle"
                  :aria-expanded="builder.isOpen(rowId(item))"
                  @click="builder.toggle(rowId(item))"
                >
                  <wx-icon
                    :name="builder.isOpen(rowId(item)) ? 'chevron-down' : 'chevron-right'"
                    size="sm"
                  />
                  <span
                    v-if="step.type === 'swatches'"
                    class="wx-configurator-step__swatch"
                    :style="
                      item.swatchImage && builder.picture(item.swatchImage)
                        ? {
                            backgroundImage: `url(&quot;${builder.picture(item.swatchImage)}&quot;)`,
                          }
                        : { background: item.swatch ?? 'transparent' }
                    "
                  />
                  <img
                    v-if="step.type === 'cards' && builder.picture(item.image)"
                    class="wx-configurator-step__thumb"
                    :src="builder.picture(item.image)!"
                    alt=""
                  />
                  <span class="wx-configurator-step__name">
                    {{ optionName(item) || t('configurator.option-untitled') }}
                  </span>
                  <span class="wx-configurator-step__key">{{ item.value }}</span>
                </button>
                <wx-badge v-if="optionProblems(at) > 0" size="sm" type="danger" round>
                  {{ optionProblems(at) }}
                </wx-badge>
                <wx-action
                  v-if="!disabled"
                  type="remove"
                  size="sm"
                  :title="t('configurator.option-remove')"
                  @click="removeOption(at)"
                />
              </div>
              <option-editor
                v-if="builder.isOpen(rowId(item))"
                :step="props.index"
                :index="at"
                :main="main"
              />
            </div>
          </template>
        </wx-sortable-list>
        <wx-button
          v-if="!disabled"
          size="sm"
          variant="outline"
          class="wx-configurator-step__add"
          @click="addOption"
        >
          + {{ t('configurator.add-option') }}
        </wx-button>
      </template>

      <template v-else>
        <p v-for="error in errors('fields')" :key="error" class="wx-configurator-step__error">
          {{ error }}
        </p>
        <wx-sortable-list
          :model-value="step.fields ?? []"
          size="sm"
          :item-key="(item: FieldDoc) => rowId(item)"
          :item-label="(item: FieldDoc) => textIn(item.label, locale, item.key)"
          :drag-label="t('configurator.reorder')"
          :disabled="disabled"
          :title="t('configurator.fields')"
          @update:model-value="setFields"
        >
          <template #default="{ item, index: at }">
            <div class="wx-configurator-step__row">
              <div class="wx-configurator-step__row-head">
                <button
                  type="button"
                  class="wx-configurator-step__toggle"
                  :aria-expanded="builder.isOpen(rowId(item))"
                  @click="builder.toggle(rowId(item))"
                >
                  <wx-icon
                    :name="builder.isOpen(rowId(item)) ? 'chevron-down' : 'chevron-right'"
                    size="sm"
                  />
                  <span class="wx-configurator-step__name">{{
                    textIn(item.label, locale, item.key)
                  }}</span>
                  <span class="wx-configurator-step__key"
                    >{{ item.key }} · {{ item.min }}–{{ item.max }}</span
                  >
                </button>
                <wx-badge v-if="fieldProblems(at) > 0" size="sm" type="danger" round>
                  {{ fieldProblems(at) }}
                </wx-badge>
                <wx-action
                  v-if="!disabled"
                  type="remove"
                  size="sm"
                  :title="t('configurator.field-remove')"
                  @click="removeField(at)"
                />
              </div>
              <field-editor v-if="builder.isOpen(rowId(item))" :step="props.index" :index="at" />
            </div>
          </template>
        </wx-sortable-list>
        <wx-button
          v-if="!disabled"
          size="sm"
          variant="outline"
          class="wx-configurator-step__add"
          @click="addField"
        >
          + {{ t('configurator.add-field') }}
        </wx-button>
      </template>
    </div>
  </div>
</template>

<style scoped>
.wx-configurator-step {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  flex: 1 1 auto;
  min-width: 0;
}

.wx-configurator-step__head,
.wx-configurator-step__row-head {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-configurator-step__toggle {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  flex: 1 1 auto;
  min-width: 0;
  padding: var(--wx-space-4) 0;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  text-align: start;
  cursor: pointer;
}

.wx-configurator-step__toggle:focus-visible {
  outline: 2px solid var(--wx-color-primary);
  outline-offset: 2px;
  border-radius: var(--wx-radius-xs);
}

.wx-configurator-step__name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: var(--wx-font-weight-medium);
}

.wx-configurator-step__key {
  flex: none;
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  color: var(--wx-text-muted);
}

.wx-configurator-step__badges {
  display: inline-flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: var(--wx-space-4);
}

.wx-configurator-step__body {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  padding-left: var(--wx-space-24);
  container-type: inline-size;
}

.wx-configurator-step__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr));
  gap: var(--wx-space-12);
}

.wx-configurator-step__conditions {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-configurator-step__row {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  flex: 1 1 auto;
  min-width: 0;
  padding: var(--wx-space-4) 0;
}

.wx-configurator-step__swatch {
  flex: none;
  width: 20px;
  height: 20px;
  border-radius: var(--wx-radius-full);
  border: 1px solid var(--wx-border-default);
  background-size: cover;
}

.wx-configurator-step__thumb {
  flex: none;
  width: 32px;
  height: 24px;
  object-fit: cover;
  border-radius: var(--wx-radius-xs);
}

.wx-configurator-step__add {
  align-self: flex-start;
}

.wx-configurator-step__error {
  margin: 0;
  font-size: var(--wx-font-size-sm);
  color: var(--wx-color-danger);
}

@container (max-width: 480px) {
  .wx-configurator-step__body {
    padding-left: 0;
  }
}
</style>
