<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  useModal,
  WxButton,
  WxDialog,
  WxFormItem,
  WxSegmented,
  WxSelect,
  WxSpace,
  WxText,
} from '@webx-ui/core'
import { useTranslate } from '@webx-ui/module-admin'
import { useNotificationsMessages } from './i18n'
import type { NotificationsMeta } from './types'

/**
 * "New channel" (§10.2): the kind first — a channel of another kind is another channel — and the
 * event if it is for one, which is what fills the template with the event's sample.
 */
defineOptions({ name: 'WxNotificationsNewChannel' })

const props = defineProps<{ meta: NotificationsMeta }>()

const { open, resolve, dismiss } = useModal<{ provider: string; event: string | null }>()

useNotificationsMessages()

const t = useTranslate('webx-notifications')

const provider = ref(props.meta.providers[0]?.key ?? '')
const event = ref<string | null>(null)

const providers = computed(() =>
  props.meta.providers.map((one) => ({ value: one.key, label: one.label, icon: one.icon })),
)

const events = computed(() => [
  { value: '', label: t('panel.any-event') },
  ...props.meta.events.map((one) => ({ value: one.key, label: one.label })),
])

function go(): void {
  resolve({ provider: provider.value, event: event.value === '' ? null : event.value })
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('panel.new-title')" :width="460">
    <wx-text size="sm" tone="muted">{{ t('panel.new-help') }}</wx-text>

    <wx-form-item :label="t('panel.new-provider')">
      <wx-segmented v-model="provider" :options="providers" />
    </wx-form-item>

    <wx-form-item :label="t('panel.new-event')">
      <wx-select
        :model-value="event ?? ''"
        :options="events"
        @update:model-value="(value) => (event = value === '' ? null : String(value))"
      />
    </wx-form-item>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.cancel') }}</wx-button>
        <wx-button type="primary" :disabled="provider === ''" @click="go">
          {{ t('panel.new-create') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
