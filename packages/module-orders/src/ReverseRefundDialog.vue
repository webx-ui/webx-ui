<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import {
  toast,
  useModal,
  WxAlert,
  WxButton,
  WxDialog,
  WxFormItem,
  WxSkeleton,
  WxSpace,
  WxTextarea,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type { OrderDetail, OrderRefundEntry, RefundReversalPreview } from './types'

/**
 * «Reverse the record» (decision 98): a refund is never edited. The record recorded by mistake is
 * reversed with its reason — it stays in the history, struck through — and what it gave back
 * counts no more. Before the button, from the service's own dry run: what is given back and left
 * after it, the units and tenders that count as not returned again, and whether the mark
 * «Refunded» comes off. The right refund is offered by the card once this one closes.
 */
const props = defineProps<{ orderId: number; refund: OrderRefundEntry }>()

const { resolve, dismiss, open } = useModal<OrderDetail>()

const admin = useAdmin()
const api = createOrdersApi(admin)
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const reason = ref('')
const preview = ref<RefundReversalPreview | null>(null)
const loading = ref(false)
const applying = ref(false)
const errors = ref<Record<string, string[]>>({})

const title = computed(() => t('panel.reverse-title', { amount: props.refund.amount }))
const tenders = computed(() =>
  Object.values(preview.value?.tenders ?? {}).some((amount) => amount > 0),
)

async function load(): Promise<void> {
  loading.value = true

  try {
    preview.value = await api.previewReverse(props.orderId, props.refund.id)
  } catch (failure) {
    toast.danger(message(failure))
  } finally {
    loading.value = false
  }
}

onMounted(() => void load())

watch(reason, () => (errors.value = {}))

async function apply(): Promise<void> {
  if (applying.value) return

  if (reason.value.trim() === '') {
    errors.value = { reason: [t('panel.reverse-reason-required')] }

    return
  }

  applying.value = true

  try {
    resolve(await api.reverseRefund(props.orderId, props.refund.id, reason.value.trim()))
  } catch (failure) {
    const body = (failure as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors?.reason) errors.value = body.errors
    else toast.danger(body?.errors?.refund?.[0] ?? message(failure))
  } finally {
    applying.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="title" :width="520">
    <div class="wx-orders-reverse">
      <wx-alert type="info" :description="t('panel.reverse-help')" />

      <wx-skeleton v-if="loading" :rows="2" />

      <template v-else-if="preview">
        <wx-alert
          v-if="preview.refusal"
          type="danger"
          data-test="reverse-refusal"
          :description="preview.message ?? ''"
        />
        <template v-else>
          <wx-alert
            type="warning"
            data-test="reverse-outcome"
            :description="
              [
                t('panel.reverse-outcome', {
                  refunded: preview.refunded_total_formatted,
                  left: preview.money_left_formatted,
                }),
                preview.units > 0 ? t('panel.reverse-units', { count: preview.units }) : '',
                tenders ? t('panel.reverse-tenders') : '',
              ]
                .filter(Boolean)
                .join(' ')
            "
          />
          <wx-alert
            v-if="preview.milestone_removed"
            type="warning"
            data-test="reverse-milestone"
            :description="
              t('panel.reverse-milestone', { milestone: preview.milestone_removed.label })
            "
          />
        </template>
      </template>

      <wx-form-item :label="t('panel.reverse-reason')" :error="errors.reason" required>
        <wx-textarea v-model="reason" :rows="3" :maxlength="500" :disabled="applying" />
      </wx-form-item>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.dismiss') }}</wx-button>
        <wx-button
          type="danger"
          :loading="applying"
          :disabled="loading || Boolean(preview?.refusal)"
          @click="apply"
        >
          {{ t('panel.reverse-confirm') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-orders-reverse {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}
</style>
