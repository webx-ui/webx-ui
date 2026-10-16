<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  useModal,
  WxAlert,
  WxButton,
  WxDialog,
  WxFormItem,
  WxSelect,
  WxSpace,
  WxText,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import { createNotificationsApi } from './api'
import { useNotificationsMessages } from './i18n'
import type { TestResult } from './types'

/**
 * "Send a test" (§8.5): now, past the queue, with the event's example values, to everybody on the
 * list or to one of them — through the form's values, saved or not, and through a channel that
 * is switched off, which is what a test is for. What happened to each recipient stays in the
 * dialog: a test that failed is read, not dismissed with a toast.
 */
defineOptions({ name: 'WxNotificationsTestDialog' })

const props = withDefaults(
  defineProps<{
    channel?: number | null
    values: ScreenModel
    /** The fixed recipients, as the server knows them by: a chat id, an address. */
    recipients?: { value: string; label: string }[]
  }>(),
  { channel: null, recipients: () => [] },
)

const { open, dismiss } = useModal<void>()

const api = createNotificationsApi(useAdmin())
useNotificationsMessages()

const t = useTranslate('webx-notifications')
const message = useErrorText()

const to = ref('')
const sending = ref(false)
const results = ref<TestResult[] | null>(null)
const failure = ref<string | null>(null)

const options = computed(() => [
  { value: '', label: t('panel.test-everybody') },
  ...props.recipients,
])

async function send(): Promise<void> {
  sending.value = true
  failure.value = null

  try {
    results.value = await api.test(props.channel, props.values, to.value === '' ? null : to.value)
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    failure.value = body?.errors ? Object.values(body.errors).flat().join(' ') : message(error)
  } finally {
    sending.value = false
  }
}

function lineOf(result: TestResult): string {
  const recipient = result.label ? `${result.label} (${result.recipient})` : result.recipient

  if (result.status === 'sent') return t('panel.test-sent', { recipient })

  return t('panel.test-failed', {
    recipient,
    error: result.error ?? (result.reason ? t(`module.reasons.${result.reason}`) : result.status),
  })
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('panel.test-title')" :width="480">
    <wx-text size="sm" tone="muted">{{ t('panel.test-help') }}</wx-text>

    <wx-form-item :label="t('panel.test-to')">
      <wx-select v-model="to" :options="options" :disabled="sending" />
    </wx-form-item>

    <wx-alert v-if="failure" type="danger" variant="soft" :description="failure" />

    <template v-if="results">
      <wx-text v-if="results.length === 0" size="sm" tone="muted">{{
        t('panel.test-none')
      }}</wx-text>
      <wx-alert
        v-for="(result, index) in results"
        :key="index"
        :type="result.status === 'sent' ? 'success' : 'danger'"
        variant="soft"
        :description="lineOf(result)"
      />
    </template>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.cancel') }}</wx-button>
        <wx-button type="primary" :loading="sending" @click="send">
          {{ t('panel.test-send') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
