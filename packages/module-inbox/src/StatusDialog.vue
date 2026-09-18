<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxButton,
  WxCheckbox,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSelect,
  WxSpace,
  type LocalizedValue,
} from '@webx-ui/core'
import { createInboxApi } from './api'
import { useInboxMessages } from './i18n'
import { STATUS_COLORS, type InboxStatus, type StatusColor } from './types'

/**
 * One state, written.
 *
 * The colour is a tone rather than a swatch: the panel has a light theme and a dark one, and
 * a colour picked in one of them is unreadable in the other.
 *
 * Nothing here refuses a second default — the server clears the flag on whoever had it, which
 * is what somebody moving the default from one status to another means, and refusing the save
 * would be the other reading of the same click.
 */
const props = defineProps<{ status: InboxStatus | null }>()

const { resolve, dismiss, open } = useModal<InboxStatus>()

const context = useAdmin()
const api = createInboxApi(context)
useInboxMessages()

const t = useTranslate('webx-inbox')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const key = ref('')
const title = ref<LocalizedValue>({})
const color = ref<StatusColor>('default')
const isDefault = ref(false)
const isSpam = ref(false)
const isClosed = ref(false)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

watch(
  () => props.status,
  (status) => {
    key.value = status?.key ?? ''
    title.value = { ...(status?.title ?? {}) }
    color.value = status?.color ?? 'default'
    isDefault.value = status?.is_default ?? false
    isSpam.value = status?.is_spam ?? false
    isClosed.value = status?.is_closed ?? false
    errors.value = {}
  },
  { immediate: true },
)

const colors = computed(() =>
  STATUS_COLORS.map((one) => ({ value: one, label: t(`panel.color-${one}`) })),
)

function errorOf(field: string): string | undefined {
  return errors.value[field]?.[0]
}

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  const input = {
    key: key.value.trim(),
    title: title.value,
    color: color.value,
    is_default: isDefault.value,
    is_spam: isSpam.value,
    is_closed: isClosed.value,
  }

  try {
    resolve(
      props.status === null
        ? await api.createStatus(input)
        : await api.saveStatus(props.status.id, input),
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
    :title="status ? t('panel.settings') : t('panel.new-status')"
    :width="520"
  >
    <div class="wx-inbox-status-form">
      <wx-form-item :label="t('panel.title')" :error="errorOf('title')" required>
        <wx-input v-model="title" localized />
      </wx-form-item>

      <wx-form-item :label="t('panel.status-key')" :error="errorOf('key')" required>
        <wx-input v-model="key" placeholder="in-progress" />
      </wx-form-item>

      <wx-form-item :label="t('panel.status-color')" :error="errorOf('color')">
        <wx-select v-model="color" :options="colors" />
      </wx-form-item>

      <div class="wx-inbox-status-form__checks">
        <wx-checkbox v-model="isDefault" :label="t('panel.status-default')" />
        <wx-checkbox v-model="isSpam" :label="t('panel.status-spam')" />
        <wx-checkbox v-model="isClosed" :disabled="isSpam" :label="t('panel.status-closed')" />
      </div>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" @click="save">{{ t('panel.save') }}</wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-inbox-status-form {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-inbox-status-form__checks {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-16);
}
</style>
