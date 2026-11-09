<script setup lang="ts">
import { computed, ref, watch, type Component } from 'vue'
import {
  slugify,
  useAdmin,
  useErrorText,
  useRemoveConfirm,
  useTranslate,
} from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxAction,
  WxAlert,
  WxButton,
  WxIcon,
  WxCheckbox,
  WxDialog,
  WxFormItem,
  WxInput,
  WxInputNumber,
  WxSegmented,
  WxSelect,
  WxSpace,
  WxDatePicker,
  WxTagsInput,
  WxText,
  useLocales,
  type LocalizedValue,
} from '@webx-ui/core'
import FieldCondition from './FieldCondition.vue'
import LocalizedRichText from './LocalizedRichText.vue'
import { createInboxApi } from './api'
import { useInboxMessages } from './i18n'
import { FIELD_TYPES, type FieldChoice, type FieldOptions, type FieldType } from './types'
import type { InboxField, InboxForm } from './types'

/**
 * One question, written.
 *
 * The settings shown are the ones the chosen type has and no others (§4): a `select` has
 * choices, a `textarea` has a height, a `file` has a size and a list of extensions. Changing
 * the type changes what is asked for, and the server keeps only what belongs — a leftover
 * `choices` array on a text field would be a column of empty strings in an export a year from
 * now.
 */
const props = withDefaults(
  defineProps<{
    field: InboxField | null
    form: InboxForm
    /** The names the form's other fields answer to. */
    taken?: string[]
    /** The form's fields in their order: a condition names one of those above this field. */
    fields?: InboxField[]
  }>(),
  { taken: () => [], fields: () => [] },
)

const { resolve, dismiss, open } = useModal<InboxField>()

const context = useAdmin()
const api = createInboxApi(context)
useInboxMessages()

const t = useTranslate('webx-inbox')
const askRemove = useRemoveConfirm()
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const name = ref('')
const type = ref<FieldType>('text')
const title = ref<LocalizedValue>({})
const placeholder = ref<LocalizedValue>({})
const help = ref<LocalizedValue>({})
const options = ref<FieldOptions>({})
/*
 * The name follows the label of a new field until somebody writes one: "Your phone" is
 * `your_phone`, which is what `{{field.your_phone}}` in a channel's template and an integration
 * read. Written by hand — or a field that already has one — it stays as it is.
 */
const named = ref(false)
const locales = useLocales()
const isEnabled = ref(true)
const isRequired = ref(false)
const isFullsize = ref(true)
const inTable = ref(false)

watch(
  () => props.field,
  (field) => {
    name.value = field?.name ?? ''
    type.value = field?.type ?? 'text'
    title.value = { ...(field?.title ?? {}) }
    placeholder.value = { ...(field?.placeholder ?? {}) }
    help.value = { ...(field?.help ?? {}) }
    options.value = { ...(field?.options ?? {}) }
    isEnabled.value = field?.is_enabled ?? true
    isRequired.value = field?.is_required ?? false
    isFullsize.value = field?.is_fullsize ?? true
    inTable.value = field?.in_table ?? false
    named.value = field !== null
    errors.value = {}
  },
  { immediate: true },
)

/** The label in the language being edited, or the first one written — with its language. */
function label(value: LocalizedValue): [string, string] {
  const map = (value ?? {}) as Record<string, string>
  const active = map[locales.active.value]

  if (typeof active === 'string' && active.trim() !== '') return [active, locales.active.value]

  const first = Object.entries(map).find(
    ([, line]) => typeof line === 'string' && line.trim() !== '',
  )

  return first === undefined ? ['', locales.active.value] : [first[1], first[0]]
}

/** A machine name out of a label: Latin, `_` between words, a letter first, `_2` when taken. */
function nameOf([words, locale]: [string, string]): string {
  let base = slugify(words, '_', locale).slice(0, 60).replace(/_+$/, '')

  if (base === '') return ''
  if (!/^[a-z]/.test(base)) base = `field_${base}`

  let candidate = base

  for (let suffix = 2; props.taken.includes(candidate); suffix++) candidate = `${base}_${suffix}`

  return candidate
}

watch(
  () => label(title.value),
  (words) => {
    if (!named.value) name.value = nameOf(words)
  },
)

function writeName(value: string): void {
  name.value = value
  // Emptied, it goes back to following the label; anything else is somebody's choice.
  named.value = value.trim() !== ''
  if (!named.value) name.value = nameOf(label(title.value))
}

/** The name the field had, when it had one and is being given another. */
const renamedFrom = computed(() => {
  const was = props.field?.name ?? ''
  const now = name.value.trim()

  return was !== '' && now !== '' && now !== was ? was : null
})

const types = computed(() =>
  FIELD_TYPES.map((one) => ({ value: one, label: t(`fields.type-${one}`) })),
)

const hasChoices = computed(() => ['select', 'radio', 'checkbox'].includes(type.value))

const choices = computed<FieldChoice[]>(() =>
  Array.isArray(options.value.choices) ? options.value.choices : [],
)

/** Extensions are typed as a list of words, which is what they are — not a sentence. */
const extensions = computed({
  get: () => (Array.isArray(options.value.extensions) ? options.value.extensions : []),
  set: (value: string[]) =>
    set(
      'extensions',
      value.map((one) => one.replace(/^\./, '')),
    ),
})

/** Radios and ticks can be cards; a select and the rest are drawn the one way they have. */
const canCards = computed(() => type.value === 'radio' || type.value === 'checkbox')
const cards = computed(() => canCards.value && options.value.view === 'cards')

const views = computed(() => [
  { value: 'list', label: t('fields.view-list') },
  { value: 'cards', label: t('fields.view-cards') },
])

function setView(view: string): void {
  const next = { ...options.value }

  if (view === 'cards') {
    next.view = 'cards'
  } else {
    // A list is the default and has no columns; what a choice carries stays for the way back.
    delete next.view
    delete next.columns
  }

  options.value = next
}

/*
 * The picture is picked with whatever the panel registered as `wx-media` — the media module's
 * field. This package does not depend on the library: a panel without it shows no picture
 * column, and a picture saved earlier stays as it is.
 */
const picker = computed<Component | undefined>(() => context.types?.['wx-media']?.component)

function set(key: string, value: unknown): void {
  options.value = { ...options.value, [key]: value }
}

function addChoice(): void {
  set('choices', [...choices.value, { value: '', label: {} }])
}

function removeChoice(index: number): void {
  set(
    'choices',
    choices.value.filter((_, at) => at !== index),
  )
}

function setChoice(index: number, patch: Partial<FieldChoice>): void {
  set(
    'choices',
    choices.value.map((choice, at) => (at === index ? { ...choice, ...patch } : choice)),
  )
}

/** Every field before this one; a new field goes to the end, so it is all of them. */
const above = computed(() => {
  const at = props.field === null ? -1 : props.fields.findIndex((one) => one.id === props.field?.id)

  return at < 0
    ? props.fields.filter((one) => one.id !== props.field?.id)
    : props.fields.slice(0, at)
})

const conditionErrors = computed(() => [
  ...(errors.value['options.when'] ?? []),
  ...(errors.value['options.unless'] ?? []),
])

function errorOf(field: string): string | undefined {
  return errors.value[field]?.[0]
}

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  const input = {
    name: name.value.trim() === '' ? null : name.value.trim(),
    type: type.value,
    title: title.value,
    placeholder: placeholder.value,
    help: help.value,
    options: options.value,
    is_enabled: isEnabled.value,
    is_required: isRequired.value,
    is_fullsize: isFullsize.value,
    in_table: inTable.value,
  }

  try {
    resolve(
      props.field === null
        ? await api.createField(props.form.id, input)
        : await api.saveField(props.field.id, input),
    )
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(t('panel.failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="field ? t('fields.edit-field') : t('fields.new-field')"
    :width="720"
  >
    <div class="wx-inbox-field-form">
      <wx-form-item :label="t('fields.type')">
        <wx-select
          v-model="type"
          :options="types"
          :status="errorOf('type') ? 'error' : undefined"
        />
        <!-- Others count on this field's answers: it cannot stop being a choice under them. -->
        <wx-text v-if="errorOf('type')" size="sm" tone="danger">{{ errorOf('type') }}</wx-text>
      </wx-form-item>

      <wx-form-item :label="t('fields.title')" :error="errorOf('title')" required>
        <wx-input v-model="title" localized />
      </wx-form-item>

      <!-- A hidden field has neither: nobody reads a placeholder they cannot see. -->
      <template v-if="type !== 'hidden'">
        <wx-form-item v-if="type !== 'consent'" :label="t('fields.placeholder')">
          <wx-input v-model="placeholder" localized />
        </wx-form-item>

        <wx-form-item :label="t('fields.help')">
          <wx-input v-model="help" localized />
        </wx-form-item>
      </template>

      <!-- The sentence beside the tick, which is printed raw on the site: it is a sentence
           with a link in it, not a caption. -->
      <wx-form-item
        v-if="type === 'consent'"
        :label="t('fields.consent-text')"
        :help="t('fields.consent-text-help')"
      >
        <localized-rich-text
          :model-value="(options.text as LocalizedValue) ?? {}"
          min-height="100px"
          @update:model-value="(value) => set('text', value)"
        />
      </wx-form-item>

      <!-- Cards (FORM_STEPS §5): the same radios or ticks, each with a description and a picture. -->
      <div v-if="canCards" class="wx-inbox-field-form__row">
        <wx-form-item :label="t('fields.view')" :help="cards ? t('fields.view-help') : undefined">
          <wx-segmented
            :model-value="cards ? 'cards' : 'list'"
            :options="views"
            size="sm"
            :aria-label="t('fields.view')"
            @update:model-value="(value) => setView(String(value))"
          />
        </wx-form-item>
        <wx-form-item v-if="cards" :label="t('fields.columns')">
          <wx-input-number
            :model-value="(options.columns as number) ?? 1"
            :min="1"
            :max="4"
            @update:model-value="(value) => set('columns', value)"
          />
        </wx-form-item>
      </div>

      <div v-if="hasChoices" class="wx-inbox-choices">
        <wx-text size="sm" weight="medium">{{ t('fields.choices') }}</wx-text>

        <wx-text v-if="choices.length === 0" size="sm" tone="placeholder">
          {{ t('fields.no-choices') }}
        </wx-text>

        <div
          v-for="(choice, index) in choices"
          :key="index"
          :class="['wx-inbox-choices__item', { 'is-card': cards }]"
        >
          <div class="wx-inbox-choices__row">
            <wx-input
              :model-value="choice.value"
              :placeholder="t('fields.choice-value')"
              @update:model-value="(value) => setChoice(index, { value: String(value) })"
            />
            <wx-input
              :model-value="choice.label"
              localized
              :placeholder="t('fields.choice-label')"
              @update:model-value="(label) => setChoice(index, { label: label as LocalizedValue })"
            />
            <wx-action
              icon="trash"
              tone="danger"
              :title="t('fields.delete')"
              v-bind="askRemove"
              @click="removeChoice(index)"
            />
          </div>

          <div v-if="cards" class="wx-inbox-choices__card">
            <component
              :is="picker"
              v-if="picker"
              class="wx-inbox-choices__image"
              :model-value="choice.image ?? null"
              :label="t('fields.choice-image')"
              accept="image"
              aspect="4/3"
              :width="160"
              :captions="false"
              @update:model-value="(image: FieldChoice['image']) => setChoice(index, { image })"
            />
            <wx-input
              class="wx-inbox-choices__description"
              :model-value="choice.description ?? {}"
              localized
              :placeholder="t('fields.choice-description')"
              :aria-label="t('fields.choice-description')"
              @update:model-value="
                (description) => setChoice(index, { description: description as LocalizedValue })
              "
            />
          </div>
        </div>

        <wx-button variant="outline" size="sm" @click="addChoice">
          <template #icon><wx-icon name="plus" /></template>
          {{ t('fields.add-choice') }}
        </wx-button>

        <!-- A list field with nothing to pick is refused by the server: it could never be sent. -->
        <wx-text v-if="errorOf('options.choices')" size="sm" tone="danger">
          {{ errorOf('options.choices') }}
        </wx-text>
      </div>

      <div class="wx-inbox-field-form__row">
        <wx-form-item v-if="type === 'textarea'" :label="t('fields.rows')">
          <wx-input-number
            :model-value="(options.rows as number) ?? 5"
            :min="2"
            :max="30"
            @update:model-value="(value) => set('rows', value)"
          />
        </wx-form-item>

        <wx-form-item
          v-if="['text', 'email', 'textarea'].includes(type)"
          :label="t('fields.maxlength')"
        >
          <wx-input-number
            :model-value="(options.maxlength as number) ?? null"
            :min="1"
            :max="20000"
            @update:model-value="(value) => set('maxlength', value)"
          />
        </wx-form-item>

        <wx-form-item
          v-if="type === 'tel'"
          :label="t('fields.pattern')"
          :help="t('fields.pattern-help')"
        >
          <wx-input
            :model-value="(options.pattern as string) ?? ''"
            @update:model-value="(value) => set('pattern', value)"
          />
        </wx-form-item>

        <template v-if="type === 'date'">
          <wx-form-item :label="t('fields.earliest')">
            <wx-date-picker
              clearable
              :model-value="(options.min as string) ?? null"
              @update:model-value="(value) => set('min', value)"
            />
          </wx-form-item>
          <wx-form-item :label="t('fields.latest')">
            <wx-date-picker
              clearable
              :model-value="(options.max as string) ?? null"
              @update:model-value="(value) => set('max', value)"
            />
          </wx-form-item>
        </template>

        <template v-if="type === 'checkbox'">
          <wx-form-item :label="t('fields.min-choices')">
            <wx-input-number
              :model-value="(options.min as number) ?? null"
              :min="0"
              @update:model-value="(value) => set('min', value)"
            />
          </wx-form-item>
          <wx-form-item :label="t('fields.max-choices')">
            <wx-input-number
              :model-value="(options.max as number) ?? null"
              :min="0"
              @update:model-value="(value) => set('max', value)"
            />
          </wx-form-item>
        </template>

        <wx-form-item v-if="type === 'file'" :label="t('fields.max-size')">
          <wx-input-number
            :model-value="(options.max_size as number) ?? null"
            :min="1"
            @update:model-value="(value) => set('max_size', value)"
          />
        </wx-form-item>
      </div>

      <template v-if="type === 'file'">
        <wx-form-item :label="t('fields.extensions')" :help="t('fields.extensions-help')">
          <wx-tags-input v-model="extensions" />
        </wx-form-item>

        <wx-checkbox
          :model-value="Boolean(options.multiple)"
          :label="t('fields.multiple')"
          @update:model-value="(value) => set('multiple', value)"
        />
      </template>

      <!-- A hidden field is filled in by the page and never seen, so it has nothing to show by. -->
      <field-condition
        v-if="type !== 'hidden'"
        :options="options"
        :above="above"
        :fields="fields"
        :errors="conditionErrors"
        @update:options="(value) => (options = value)"
      />

      <div class="wx-inbox-field-form__checks">
        <wx-checkbox v-model="isEnabled" :label="t('fields.is-enabled')" />
        <wx-checkbox v-model="isRequired" :label="t('fields.is-required')" />
        <wx-checkbox
          v-if="type !== 'hidden'"
          v-model="isFullsize"
          :label="t('fields.is-fullsize')"
        />
        <wx-checkbox v-model="inTable" :label="t('fields.in-table')" />
      </div>

      <!-- Last and apart: the name is for templates and integrations, and the label above is
           where it comes from. -->
      <section class="wx-inbox-field-form__advanced">
        <wx-text size="sm" weight="medium" tone="muted">{{ t('fields.advanced') }}</wx-text>

        <wx-form-item
          :label="t('fields.name')"
          :help="t('fields.name-help', { name: name.trim() || 'name' })"
          :error="errorOf('name')"
        >
          <wx-input
            :model-value="name"
            placeholder="phone"
            @update:model-value="(value) => writeName(String(value ?? ''))"
          />
        </wx-form-item>

        <wx-alert
          v-if="renamedFrom"
          type="warning"
          variant="soft"
          :description="t('fields.name-renamed', { old: renamedFrom, new: name.trim() })"
        />
      </section>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('fields.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" @click="save">{{ t('fields.save') }}</wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-inbox-field-form {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-inbox-field-form__row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-12);
}

.wx-inbox-field-form__row > * {
  flex: 1 1 200px;
  min-width: 0;
}

/* Empty when the type has nothing to configure, and then it should take no room at all. */
.wx-inbox-field-form__row:empty {
  display: none;
}

.wx-inbox-field-form__advanced {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  padding-block-start: var(--wx-space-12);
  border-block-start: 1px solid var(--wx-border-muted);
}

.wx-inbox-field-form__checks {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-16);
}

.wx-inbox-choices {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  align-items: flex-start;
}

.wx-inbox-choices__item {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  width: 100%;
}

/* A card's choice is a block of its own: the picture and the words belong to the row above. */
.wx-inbox-choices__item.is-card {
  box-sizing: border-box;
  padding: var(--wx-space-8);
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-md);
}

.wx-inbox-choices__row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  width: 100%;
}

.wx-inbox-choices__card {
  container-type: inline-size;
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: var(--wx-space-8);
}

.wx-inbox-choices__image {
  flex: 0 0 160px;
}

.wx-inbox-choices__description {
  flex: 1 1 200px;
  min-width: 0;
}

.wx-inbox-choices__row > :first-child {
  flex: 0 1 180px;
  min-width: 0;
}

.wx-inbox-choices__row > :nth-child(2) {
  flex: 1 1 auto;
  min-width: 0;
}
</style>
