<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { WxAction, WxButton, WxSelect, WxText, type SelectOption } from '@webx-ui/core'
import { textIn } from './document'
import type { Condition, StepDoc } from './engine/types'

/**
 * A condition of §5.6 — `{ step: [values] }` — built by choosing, never typed (§13.2): a row is a
 * step of options above the element and the options it may be chosen with. Every row has to
 * hold; inside a row, any of its options will do.
 *
 * The steps on offer are the ones a condition may name — choice steps standing above — so a
 * forward reference cannot be made here; one left over from a reorder is shown as it is, and the
 * check underlines it.
 */
defineOptions({ name: 'WxConfiguratorConditions' })

const props = withDefaults(
  defineProps<{
    label: string
    /** The steps a row may name. */
    steps: StepDoc[]
    locale: string
    disabled?: boolean
    /** Problems under this condition, by the step a row names. */
    errors?: Record<string, string[]>
  }>(),
  { disabled: false, errors: () => ({}) },
)

const value = defineModel<Condition | undefined>({ default: undefined })

const t = useTranslate('webx-configurator')

const rows = computed(() => Object.entries(value.value ?? {}))

function name(step: StepDoc): string {
  return textIn(step.label, props.locale, textIn(step.title, props.locale, step.key))
}

/** A row's step can become any step not named by another row. */
function stepOptions(current: string): SelectOption[] {
  const used = new Set(rows.value.map(([step]) => step))
  const options = props.steps
    .filter((step) => step.key === current || !used.has(step.key))
    .map((step) => ({ value: step.key, label: name(step) }))

  if (!options.some((option) => option.value === current)) {
    options.unshift({ value: current, label: current })
  }

  return options
}

function valueOptions(stepKey: string): SelectOption[] {
  const step = props.steps.find((one) => one.key === stepKey)

  return (step?.options ?? []).map((option) => ({
    value: option.value,
    label: textIn(option.label, props.locale, option.value),
  }))
}

/** Rows in their order, with one changed. An empty condition is no condition at all. */
function write(next: [string, string[]][]): void {
  value.value = next.length === 0 ? undefined : Object.fromEntries(next)
}

function changeStep(at: number, step: unknown): void {
  if (typeof step !== 'string') return

  write(rows.value.map((row, index) => (index === at ? [step, []] : row)))
}

function changeValues(at: number, values: unknown): void {
  const list = Array.isArray(values)
    ? values.filter((one): one is string => typeof one === 'string')
    : []

  write(rows.value.map((row, index) => (index === at ? [row[0], list] : row)))
}

function remove(at: number): void {
  write(rows.value.filter((_, index) => index !== at))
}

const free = computed(() => {
  const used = new Set(rows.value.map(([step]) => step))

  return props.steps.filter((step) => !used.has(step.key))
})

function add(): void {
  const step = free.value[0]

  if (step) write([...rows.value, [step.key, []]])
}
</script>

<template>
  <div class="wx-configurator-conditions">
    <div class="wx-configurator-conditions__head">
      <wx-text size="sm" weight="medium">{{ props.label }}</wx-text>
    </div>

    <div v-for="([step, values], at) in rows" :key="at" class="wx-configurator-conditions__row">
      <wx-select
        class="wx-configurator-conditions__step"
        :model-value="step"
        :options="stepOptions(step)"
        size="sm"
        :disabled="props.disabled"
        :aria-label="t('configurator.condition-step')"
        @update:model-value="(next: unknown) => changeStep(at, next)"
      />
      <wx-select
        class="wx-configurator-conditions__values"
        :model-value="values"
        :options="valueOptions(step)"
        multiple
        size="sm"
        :placeholder="t('configurator.condition-values')"
        :disabled="props.disabled"
        :aria-label="t('configurator.condition-values')"
        @update:model-value="(next: unknown) => changeValues(at, next)"
      />
      <wx-action
        v-if="!props.disabled"
        type="remove"
        size="sm"
        :title="t('configurator.condition-remove')"
        @click="remove(at)"
      />
      <p
        v-for="error in props.errors[step] ?? []"
        :key="error"
        class="wx-configurator-conditions__error"
      >
        {{ error }}
      </p>
    </div>

    <wx-text v-if="props.steps.length === 0" size="sm" tone="muted">
      {{ t('configurator.condition-none') }}
    </wx-text>
    <wx-button
      v-else-if="!props.disabled && free.length > 0"
      size="sm"
      variant="text"
      class="wx-configurator-conditions__add"
      @click="add"
    >
      + {{ t('configurator.condition-add') }}
    </wx-button>
  </div>
</template>

<style scoped>
.wx-configurator-conditions {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  min-width: 0;
}

.wx-configurator-conditions__row {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(0, 3fr) auto;
  align-items: center;
  gap: var(--wx-space-6);
}

.wx-configurator-conditions__error {
  grid-column: 1 / -1;
  margin: 0;
  font-size: var(--wx-font-size-sm);
  color: var(--wx-color-danger);
}

.wx-configurator-conditions__add {
  align-self: flex-start;
}
</style>
