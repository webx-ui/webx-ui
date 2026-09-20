<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxAction,
  WxButton,
  WxIcon,
  WxCheckbox,
  WxDialog,
  WxFormItem,
  WxInput,
  WxInputNumber,
  WxSelect,
  WxSpace,
  WxDatePicker,
  WxTagsInput,
  WxText,
  type LocalizedValue,
} from '@webx-ui/core'
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
const props = defineProps<{ field: InboxField | null; form: InboxForm }>()

const { resolve, dismiss, open } = useModal<InboxField>()

const context = useAdmin()
const api = createInboxApi(context)
useInboxMessages()

const t = useTranslate('webx-inbox')
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
    errors.value = {}
  },
  { immediate: true },
)

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
      <div class="wx-inbox-field-form__row">
        <wx-form-item :label="t('fields.type')">
          <wx-select v-model="type" :options="types" />
        </wx-form-item>

        <wx-form-item
          :label="t('fields.name')"
          :help="t('fields.name-help')"
          :error="errorOf('name')"
        >
          <wx-input v-model="name" placeholder="email" />
        </wx-form-item>
      </div>

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

      <div v-if="hasChoices" class="wx-inbox-choices">
        <wx-text size="sm" weight="medium">{{ t('fields.choices') }}</wx-text>

        <wx-text v-if="choices.length === 0" size="sm" tone="placeholder">
          {{ t('fields.no-choices') }}
        </wx-text>

        <div v-for="(choice, index) in choices" :key="index" class="wx-inbox-choices__row">
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
          <wx-action icon="trash" :title="t('fields.delete')" @click="removeChoice(index)" />
        </div>

        <wx-button variant="outline" size="sm" @click="addChoice">
          <template #icon><wx-icon name="plus" /></template>
          {{ t('fields.add-choice') }}
        </wx-button>
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

.wx-inbox-choices__row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  width: 100%;
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
