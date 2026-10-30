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
import type { CancelConsequences, MassCancelAnswer, OrderDetail } from './types'

/**
 * The cancellation (spec §9.1) — of one order from its card, or of the selection of the list.
 * The reason is required: it goes into the history, and an agent reading it later has nothing
 * else. Before the button, what the cancellation does from the service's own dry run: a paid
 * order says that no money goes back with it — a refund is recorded after — and which tenders
 * return to their wallets.
 */
const props = defineProps<{
  /** The order of the card; without it, `ids` is the selection. */
  orderId?: number
  number?: string | null
  ids?: number[]
}>()

const { resolve, dismiss, open } = useModal<OrderDetail | MassCancelAnswer>()

const admin = useAdmin()
const api = createOrdersApi(admin)
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const reason = ref('')
const single = ref<CancelConsequences | null>(null)
const batch = ref<MassCancelAnswer | null>(null)
const loading = ref(false)
const applying = ref(false)
const errors = ref<Record<string, string[]>>({})

const mass = computed(() => props.orderId === undefined)
const count = computed(() => props.ids?.length ?? 0)

const title = computed(() =>
  mass.value
    ? t('panel.mass-cancel-title', { count: count.value })
    : t('panel.cancel-title', { number: props.number ?? '' }),
)

async function preview(): Promise<void> {
  loading.value = true

  try {
    if (mass.value) {
      // The batch is previewed with the reason it will be cancelled with, once one is typed.
      batch.value = await api.massCancel(props.ids ?? [], reason.value.trim() || '…', true)
    } else {
      single.value = await api.previewCancel(props.orderId as number)
    }
  } catch (failure) {
    toast.danger(message(failure))
  } finally {
    loading.value = false
  }
}

onMounted(() => void preview())

watch(reason, () => (errors.value = {}))

async function apply(): Promise<void> {
  if (applying.value) return

  if (reason.value.trim() === '') {
    errors.value = { reason: [t('panel.cancel-reason-required')] }

    return
  }

  applying.value = true

  try {
    resolve(
      mass.value
        ? await api.massCancel(props.ids ?? [], reason.value.trim(), false)
        : await api.cancel(props.orderId as number, reason.value.trim()),
    )
  } catch (failure) {
    const body = (failure as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors && Object.keys(body.errors).length > 0) errors.value = body.errors
    else toast.danger(message(failure))
  } finally {
    applying.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="title" :width="520">
    <div class="wx-orders-cancel">
      <wx-skeleton v-if="loading" :rows="2" />

      <template v-else-if="single">
        <wx-alert
          v-if="single.was_paid && single.money_to_refund_text"
          type="warning"
          :description="t('panel.cancel-paid', { amount: single.money_to_refund_text })"
        />
        <wx-alert
          v-if="single.tenders.length > 0"
          type="info"
          :description="
            t('panel.cancel-tenders', {
              tenders: single.tenders.map((one) => `${one.label} ${one.amount}`).join(', '),
            })
          "
        />
      </template>

      <template v-else-if="batch">
        <wx-alert
          v-if="batch.paid > 0"
          type="warning"
          :description="t('panel.mass-cancel-paid', { count: batch.paid })"
        />
        <wx-alert
          v-if="batch.unchanged > 0"
          type="info"
          :description="t('panel.mass-cancel-unchanged', { count: batch.unchanged })"
        />
      </template>

      <wx-form-item
        :label="t('panel.cancel-reason')"
        :help="t('panel.cancel-reason-help')"
        :error="errors.reason"
        required
      >
        <wx-textarea v-model="reason" :rows="3" :maxlength="500" :disabled="applying" />
      </wx-form-item>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.dismiss') }}</wx-button>
        <wx-button type="danger" :loading="applying" :disabled="loading" @click="apply">
          {{ t('panel.cancel-confirm') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-orders-cancel {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}
</style>
