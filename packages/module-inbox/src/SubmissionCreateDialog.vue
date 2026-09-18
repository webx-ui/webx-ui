<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  localizedValue,
  toast,
  useLocales,
  useModal,
  WxAlert,
  WxButton,
  WxCheckbox,
  WxCheckboxGroup,
  WxDatePicker,
  WxDialog,
  WxFormItem,
  WxInput,
  WxRadioGroup,
  WxSelect,
  WxSkeleton,
  WxSpace,
  WxTextarea,
} from '@webx-ui/core'
import { createInboxApi } from './api'
import { useInboxMessages } from './i18n'
import type { InboxField, InboxForm, InboxSubmission } from './types'

/**
 * A submission typed in by hand — a call that came by telephone, a form filled in on paper.
 *
 * The same questions the site asks, drawn from the same fields, and sent to the same intake:
 * the rules are the form's own, so a required field is required here too and a refusal reads
 * the same way. The one thing the panel cannot do is attach a visitor's file, because there is
 * no visitor — the files a submission carries are what was posted with it.
 */
const props = defineProps<{ form: InboxForm }>()

const { resolve, dismiss, open } = useModal<InboxSubmission>()

const context = useAdmin()
const api = createInboxApi(context)
const locales = useLocales()
useInboxMessages()

const t = useTranslate('webx-inbox')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const fields = ref<InboxField[]>([])
const loading = ref(true)
const saving = ref(false)
const values = ref<Record<string, unknown>>({})
const errors = ref<Record<string, string[]>>({})

/** Everything the form actually asks, files excepted. */
const asked = computed(() => fields.value.filter((field) => field.type !== 'file'))

const hasFiles = computed(() => fields.value.some((field) => field.type === 'file'))

function errorOf(field: InboxField): string | undefined {
  return errors.value[`fields.${field.key}`]?.[0]
}

function label(field: InboxField): string {
  return localizedValue(field.title, locales.active.value, field.key)
}

function text(value: unknown, fallback = ''): string {
  return localizedValue(
    value as Parameters<typeof localizedValue>[0],
    locales.active.value,
    fallback,
  )
}

/** The written-down answers of a `select`, `radio` or `checkbox`, in the panel's language. */
function choices(field: InboxField): { value: string; label: string }[] {
  return (field.options.choices ?? []).map((choice) => ({
    value: choice.value,
    label: text(choice.label, choice.value),
  }))
}

async function load(): Promise<void> {
  try {
    const form = await api.form(props.form.id)

    fields.value = (form.fields ?? []).filter((field) => field.is_enabled)

    for (const field of fields.value) {
      values.value[field.key] =
        field.type === 'checkbox' ? [] : field.type === 'consent' ? false : ''
    }
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

void load()

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  try {
    resolve(await api.createSubmission(props.form.id, values.value))
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
  <wx-dialog v-model:open="open" :title="t('panel.new-submission-title')" :width="560">
    <div class="wx-inbox-by-hand">
      <wx-alert type="info" :closable="false">{{ t('panel.new-submission-help') }}</wx-alert>

      <wx-skeleton v-if="loading" :rows="4" />

      <template v-else>
        <wx-form-item
          v-for="field in asked"
          :key="field.id"
          :label="label(field)"
          :help="text(field.help)"
          :error="errorOf(field)"
          :required="field.is_required"
        >
          <wx-textarea
            v-if="field.type === 'textarea'"
            v-model="values[field.key] as string"
            :rows="field.options.rows ?? 4"
            :placeholder="text(field.placeholder)"
          />
          <wx-select
            v-else-if="field.type === 'select'"
            v-model="values[field.key] as string"
            :options="choices(field)"
            clearable
            :placeholder="text(field.placeholder)"
          />
          <wx-radio-group
            v-else-if="field.type === 'radio'"
            v-model="values[field.key] as string"
            :options="choices(field)"
          />
          <wx-checkbox-group
            v-else-if="field.type === 'checkbox'"
            v-model="values[field.key] as string[]"
            :options="choices(field)"
          />
          <!-- The sentence beside the tick is the form's own and may carry a link, so it is
               the field's label rather than text written here. -->
          <wx-checkbox
            v-else-if="field.type === 'consent'"
            v-model="values[field.key] as boolean"
            :label="text(field.options.text, label(field))"
          />
          <wx-date-picker
            v-else-if="field.type === 'date'"
            v-model="values[field.key] as string"
            :placeholder="text(field.placeholder)"
          />
          <wx-input
            v-else
            v-model="values[field.key] as string"
            :type="field.type === 'email' ? 'email' : field.type === 'tel' ? 'tel' : 'text'"
            :placeholder="text(field.placeholder)"
            :maxlength="field.options.maxlength"
          />
        </wx-form-item>

        <!-- Said rather than silently left out: a form whose point is the attachment would
             otherwise look like it had lost a field. -->
        <wx-alert v-if="hasFiles" type="warning" :closable="false">
          {{ t('panel.no-files-by-hand') }}
        </wx-alert>
      </template>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" :disabled="loading" @click="save">
          {{ t('panel.save') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-inbox-by-hand {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}
</style>
