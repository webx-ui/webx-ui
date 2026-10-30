<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import {
  toast,
  useModal,
  WxAlert,
  WxButton,
  WxDialog,
  WxFormItem,
  WxSelect,
  WxSkeleton,
  WxSpace,
  WxText,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type { BuyerEvents, NotifyPreview, OrderDetail } from './types'

/**
 * «Send the buyer a letter» (decision 99): «the letter never came». An event the order has a reason
 * for — placed, the public status it stands in, paid… — goes again to the order's e-mail as it is
 * now, through the event's channels that write to the buyer. The letter is shown first, from the
 * service's own dry run; an event with no reason or no such channel is offered greyed with why.
 */
const props = defineProps<{ orderId: number }>()

const { resolve, dismiss, open } = useModal<OrderDetail>()

const admin = useAdmin()
const api = createOrdersApi(admin)
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const choices = ref<BuyerEvents | null>(null)
const event = ref<string | null>(null)
const preview = ref<NotifyPreview | null>(null)
const loading = ref(true)
const previewing = ref(false)
const sending = ref(false)
const refusal = ref<string | null>(null)

const options = computed(() =>
  (choices.value?.events ?? []).map((one) => ({
    value: one.key,
    label: one.available ? one.label : `${one.label} — ${one.reason_label ?? ''}`,
    disabled: !one.available,
  })),
)

const chosen = computed(() => choices.value?.events.find((one) => one.key === event.value) ?? null)

async function load(): Promise<void> {
  loading.value = true

  try {
    choices.value = await api.buyerEvents(props.orderId)
    event.value = choices.value.events.find((one) => one.available)?.key ?? null
  } catch (failure) {
    toast.danger(message(failure))
  } finally {
    loading.value = false
  }
}

/** Each change of the event asks again what would go; an answer to an older choice is dropped. */
async function show(key: string | null): Promise<void> {
  preview.value = null
  refusal.value = null

  if (key === null) return

  previewing.value = true

  try {
    const answer = await api.previewNotify(props.orderId, key)

    if (event.value === key) preview.value = answer
  } catch (failure) {
    if (event.value === key) refusal.value = said(failure)
  } finally {
    previewing.value = false
  }
}

function said(failure: unknown): string {
  const body = (failure as { body?: { errors?: Record<string, string[]> } }).body

  return body?.errors?.event?.[0] ?? message(failure)
}

watch(event, (key) => void show(key))

onMounted(() => void load())

async function send(): Promise<void> {
  if (sending.value || event.value === null) return

  sending.value = true

  try {
    resolve(await api.notify(props.orderId, event.value))
  } catch (failure) {
    refusal.value = said(failure)
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('panel.notify-title')" :width="560">
    <div class="wx-orders-notify">
      <wx-skeleton v-if="loading" :rows="3" />

      <template v-else-if="choices">
        <wx-alert
          v-if="choices.reason"
          type="warning"
          data-test="notify-reason"
          :description="choices.reason_label ?? ''"
        />
        <wx-alert
          v-else
          type="info"
          :description="t('panel.notify-text', { email: choices.email ?? '' })"
        />

        <wx-form-item :label="t('panel.notify-event')">
          <wx-select v-model="event" :options="options" :disabled="sending" />
        </wx-form-item>

        <wx-text v-if="chosen && chosen.channels.length > 0" size="sm" tone="muted">
          {{
            t('panel.notify-through', {
              channels: chosen.channels.map((one) => one.name).join(', '),
            })
          }}
        </wx-text>

        <div aria-live="polite" class="wx-orders-notify__said">
          <wx-alert
            v-if="refusal"
            type="danger"
            data-test="notify-refusal"
            :description="refusal"
          />
          <wx-skeleton v-else-if="previewing" :rows="4" />
          <template v-else-if="preview">
            <section
              v-for="(letter, index) in preview.previews"
              :key="index"
              class="wx-orders-notify__letter"
              data-test="notify-preview"
            >
              <wx-text size="sm" tone="muted"
                >{{ letter.channel }} · {{ letter.recipient }}</wx-text
              >
              <p v-if="letter.subject" class="wx-orders-notify__subject">
                <span class="wx-orders-notify__label">{{ t('panel.notify-subject') }}</span>
                {{ letter.subject }}
              </p>
              <p class="wx-orders-notify__body">{{ letter.plain }}</p>
            </section>
          </template>
        </div>
      </template>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.dismiss') }}</wx-button>
        <wx-button
          type="primary"
          :loading="sending"
          :disabled="loading || previewing || !preview || Boolean(refusal)"
          data-test="notify-send"
          @click="send"
        >
          {{ t('panel.notify-send') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-orders-notify {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-orders-notify__said {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

/* The letter as the buyer reads it: its plain text, the subject over it. */
.wx-orders-notify__letter {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  max-height: 320px;
  padding: var(--wx-space-12);
  overflow: auto;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-subtle);
}

.wx-orders-notify__subject {
  margin: 0;
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-medium);
  overflow-wrap: anywhere;
}

.wx-orders-notify__label {
  margin-inline-end: var(--wx-space-4);
  color: var(--wx-text-muted);
  font-weight: var(--wx-font-weight-regular);
}

.wx-orders-notify__body {
  margin: 0;
  color: var(--wx-text-default);
  font-size: var(--wx-font-size-sm);
  overflow-wrap: anywhere;
  white-space: pre-line;
}
</style>
