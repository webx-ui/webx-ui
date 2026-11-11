<script setup lang="ts">
import { computed, ref, toRaw, watch } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import {
  localizedValue,
  useLocales,
  WxAction,
  WxAlert,
  WxBadge,
  WxButton,
  WxIcon,
  WxSegmented,
  WxSelect,
  WxText,
} from '@webx-ui/core'
import {
  cleanCondition,
  conditionOf,
  describeCondition,
  isSource,
  labelOf,
  valuesOf,
  type Condition,
  type ConditionKey,
} from './conditions'
import { useInboxMessages } from './i18n'
import type { FieldOptions, InboxField } from './types'

/**
 * «Show» of the field dialog (FORM_STEPS §9.2): always, if, or unless some answers above were
 * given. Only choice fields that stand above this one are offered — the site works visibility
 * out in one pass from the top, so a field cannot count on an answer below it.
 *
 * A simple editor on purpose (§4.5): rows joined by "and", answers inside a row by "or". The
 * condition builder of webx-ui#55 takes this same place when it comes.
 */
const props = defineProps<{
  /** The options of the field being edited; `when` / `unless` are read from them. */
  options: FieldOptions
  /** The fields above this one, in their order: what a condition may name. */
  above: InboxField[]
  /** Every field of the form, for the words of a name that is no longer above. */
  fields: InboxField[]
  errors?: string[]
}>()

const emit = defineEmits<{ 'update:options': [options: FieldOptions] }>()

useInboxMessages()

const t = useTranslate('webx-inbox')
const locales = useLocales()

type Mode = 'always' | ConditionKey

interface Row {
  name: string
  values: string[]
}

const mode = ref<Mode>('always')
const rows = ref<Row[]>([])
/*
 * A field saved through MCP may have both keys. The editor writes one; the other is kept as it
 * is and said in words, so opening and saving the dialog changes nothing nobody touched.
 */
const kept = ref<{ key: ConditionKey; condition: Condition } | null>(null)

function load(options: FieldOptions): void {
  const when = conditionOf(options, 'when')
  const unless = conditionOf(options, 'unless')
  const shown = when ?? unless

  mode.value = when !== null ? 'when' : unless !== null ? 'unless' : 'always'
  rows.value = Object.entries(shown ?? {}).map(([name, values]) => ({ name, values: [...values] }))
  kept.value = when !== null && unless !== null ? { key: 'unless', condition: unless } : null
}

load(props.options)

/* A dialog opened for another field gets the options anew; our own write coming back is not re-read. */
let written: FieldOptions | null = null

watch(
  () => props.options,
  (options) => {
    if (toRaw(options) !== written) load(options)
  },
)

const sources = computed(() => props.above.filter((field) => isSource(field.type)))

const ticked = computed(() => t('fields.condition-ticked'))

function title(field: InboxField): string {
  return localizedValue(field.title, locales.active.value, field.key)
}

/** A field a row names that is not one it may name any more — gone, moved below, not a choice. */
function stranger(name: string): boolean {
  return name !== '' && !sources.value.some((field) => field.key === name)
}

function fieldOptions(row: Row) {
  const taken = rows.value.filter((one) => one !== row).map((one) => one.name)
  const list = sources.value
    .filter((field) => !taken.includes(field.key))
    .map((field) => ({ value: field.key, label: title(field) }))

  if (stranger(row.name)) {
    const field = props.fields.find((one) => one.key === row.name)

    list.unshift({ value: row.name, label: field ? title(field) : row.name })
  }

  return list
}

function choicesOf(row: Row) {
  const field = sources.value.find((one) => one.key === row.name)

  return field === undefined
    ? []
    : valuesOf(field, ticked.value).map((choice) => ({
        value: choice.value,
        label: labelOf(choice.label, locales.active.value, choice.value),
      }))
}

/** Values the row counts on that its field no longer offers: shown red, until taken out. */
function removed(row: Row): string[] {
  if (stranger(row.name)) return []

  const known = choicesOf(row).map((choice) => choice.value)

  return row.values.filter((value) => !known.includes(value))
}

function write(): void {
  const condition = cleanCondition(
    Object.fromEntries(rows.value.map((row) => [row.name, row.values])),
  )
  const next: FieldOptions = { ...props.options }

  delete next.when
  delete next.unless

  if (mode.value !== 'always' && condition !== null) next[mode.value] = condition
  if (kept.value !== null && kept.value.key !== mode.value)
    next[kept.value.key] = kept.value.condition

  written = next
  emit('update:options', next)
}

function setMode(value: Mode): void {
  mode.value = value
  if (value !== 'always' && rows.value.length === 0) addRow()
  else write()
}

function addRow(): void {
  const free = sources.value.find((field) => !rows.value.some((row) => row.name === field.key))

  rows.value = [...rows.value, { name: free?.key ?? '', values: [] }]
  write()
}

function setName(index: number, name: string): void {
  rows.value = rows.value.map((row, at) => (at === index ? { name, values: [] } : row))
  write()
}

function setValues(index: number, values: string[]): void {
  const row = rows.value[index]!
  // The red ones are not in the list, so a pick keeps them until they are closed on their own.
  const red = removed(row)

  rows.value = rows.value.map((one, at) =>
    at === index ? { ...one, values: [...red, ...values] } : one,
  )
  write()
}

function dropValue(index: number, value: string): void {
  rows.value = rows.value.map((row, at) =>
    at === index ? { ...row, values: row.values.filter((one) => one !== value) } : row,
  )
  write()
}

function removeRow(index: number): void {
  rows.value = rows.value.filter((_, at) => at !== index)
  if (rows.value.length === 0) mode.value = 'always'
  write()
}

const modes = computed(() => [
  { value: 'always', label: t('fields.show-always') },
  { value: 'when', label: t('fields.show-when') },
  { value: 'unless', label: t('fields.show-unless') },
])

const keptText = computed(() =>
  kept.value === null || kept.value.key === mode.value
    ? null
    : t('fields.condition-kept', {
        condition: describeCondition(
          kept.value.condition,
          props.fields,
          locales.active.value,
          ticked.value,
        ),
      }),
)

const canAdd = computed(() => rows.value.length < sources.value.length)
</script>

<template>
  <section class="wx-inbox-condition" :class="{ 'is-invalid': errors?.length }">
    <div class="wx-inbox-condition__head">
      <wx-text size="sm" weight="medium">{{ t('fields.show') }}</wx-text>
      <wx-segmented
        :model-value="mode"
        :options="modes"
        size="sm"
        :aria-label="t('fields.show')"
        :disabled="sources.length === 0 && mode === 'always'"
        @update:model-value="(value) => setMode(value as Mode)"
      />
    </div>

    <wx-text v-if="sources.length === 0 && mode === 'always'" size="sm" tone="placeholder">
      {{ t('fields.condition-none') }}
    </wx-text>

    <template v-if="mode !== 'always'">
      <div
        v-for="(row, index) in rows"
        :key="index"
        class="wx-inbox-condition__row"
        :class="{ 'is-invalid': stranger(row.name) || removed(row).length > 0 }"
      >
        <wx-text v-if="index > 0" size="sm" tone="muted" class="wx-inbox-condition__and">
          {{ t('fields.condition-and') }}
        </wx-text>

        <div class="wx-inbox-condition__controls">
          <wx-select
            :model-value="row.name || null"
            :options="fieldOptions(row)"
            :placeholder="t('fields.condition-field')"
            :aria-label="t('fields.condition-field')"
            :status="stranger(row.name) ? 'error' : undefined"
            @update:model-value="(value) => setName(index, String(value ?? ''))"
          />
          <wx-select
            multiple
            :model-value="row.values.filter((value) => !removed(row).includes(value))"
            :options="choicesOf(row)"
            :placeholder="t('fields.condition-values')"
            :aria-label="t('fields.condition-values')"
            :disabled="row.name === '' || stranger(row.name)"
            @update:model-value="(value) => setValues(index, (value as string[]) ?? [])"
          />
          <wx-action
            icon="trash"
            tone="danger"
            :title="t('fields.condition-remove')"
            @click="removeRow(index)"
          />
        </div>

        <wx-text v-if="stranger(row.name)" size="sm" tone="danger">
          {{ t('fields.condition-stranger') }}
        </wx-text>

        <div v-if="removed(row).length > 0" class="wx-inbox-condition__removed">
          <wx-badge
            v-for="value in removed(row)"
            :key="value"
            type="danger"
            closable
            :close-label="t('fields.condition-remove')"
            @close="dropValue(index, value)"
          >
            {{ t('fields.condition-removed', { value }) }}
          </wx-badge>
        </div>
      </div>

      <wx-button v-if="canAdd" variant="outline" size="sm" @click="addRow">
        <template #icon><wx-icon name="plus" /></template>
        {{ t('fields.condition-add') }}
      </wx-button>

      <wx-text size="sm" tone="muted">{{ t('fields.condition-help') }}</wx-text>
    </template>

    <wx-alert v-if="keptText" type="info" variant="soft" :description="keptText" />

    <wx-text v-for="error in errors ?? []" :key="error" size="sm" tone="danger" role="alert">
      {{ error }}
    </wx-text>
  </section>
</template>

<style scoped>
.wx-inbox-condition {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  align-items: flex-start;
}

.wx-inbox-condition__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-12);
}

.wx-inbox-condition__row {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  width: 100%;
}

.wx-inbox-condition__controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  width: 100%;
}

.wx-inbox-condition__controls > :first-child {
  flex: 1 1 180px;
  min-width: 0;
}

.wx-inbox-condition__controls > :nth-child(2) {
  flex: 2 1 220px;
  min-width: 0;
}

.wx-inbox-condition__removed {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-6);
}
</style>
