<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import {
  toast,
  useModal,
  WxAlert,
  WxButton,
  WxDatePicker,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSpace,
  WxText,
  WxTextarea,
  type DatePickerModelValue,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type {
  OrderBalance,
  OrderCardData,
  OrderDetail,
  PaymentInput,
  PaymentPreview,
} from './types'

/**
 * «Record a top-up» (spec §36.3, §42.3): money that came in for an order that is paid already —
 * after its lines were changed, by a transfer or at the till. The mark «Paid» stays one; the
 * balance goes down. The amount starts at what is owed, and the service's own dry run says what
 * is left after it — a top-up over what is owed shows as money to give back.
 */
const props = defineProps<{ order: OrderCardData }>()

const { resolve, dismiss, open } = useModal<OrderDetail>()

const admin = useAdmin()
const api = createOrdersApi(admin)
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

/** A minor amount in the currency's own units, as the field takes it: 1500 → «15.00». */
const major = (minor: number) => (minor / 10 ** props.order.exponent).toFixed(props.order.exponent)

const due =
  props.order.totals.balance?.kind === 'topup' ? props.order.totals.balance.amount_minor : 0

const input = reactive<PaymentInput>({
  amount: due > 0 ? major(due) : '',
  reference: '',
  at: null,
  note: '',
})
const preview = ref<PaymentPreview | null>(null)
const applying = ref(false)
const errors = ref<Record<string, string[]>>({})

const empty = computed(() => input.amount.trim() === '' || !(Number(input.amount) > 0))

let timer: ReturnType<typeof setTimeout> | undefined
let asked = 0

/** The dry run a moment after the typing stops — the amount alone changes what it says. */
watch(
  () => input.amount,
  () => {
    errors.value = {}
    clearTimeout(timer)

    if (empty.value) {
      preview.value = null

      return
    }

    timer = setTimeout(async () => {
      const mine = ++asked

      try {
        const answer = await api.previewPayment(props.order.id, input)

        if (mine === asked) preview.value = answer
      } catch (failure) {
        if (mine !== asked) return

        const body = (failure as { body?: { errors?: Record<string, string[]> } }).body

        preview.value = null
        errors.value = body?.errors ?? {}
      }
    }, 300)
  },
  { immediate: true },
)

onBeforeUnmount(() => clearTimeout(timer))

/** What the balance says after it, in a line: owes more, money to give back, or settled. */
function balanceLine(balance: OrderBalance): string {
  if (balance.over > 0) return t('panel.payment-after-over', { amount: balance.over_formatted })
  if (balance.due > 0) return t('panel.payment-after-due', { amount: balance.due_formatted })

  return t('panel.payment-after-settled')
}

const outcome = computed(() =>
  preview.value && preview.value.refusal === null ? preview.value.after : null,
)

function errorOf(field: string): string | undefined {
  if (field === 'amount' && preview.value?.refusal && preview.value.message) {
    return preview.value.message
  }

  return errors.value[field]?.[0]
}

const canApply = computed(
  () => !empty.value && !applying.value && preview.value !== null && preview.value.refusal === null,
)

async function apply(): Promise<void> {
  if (!canApply.value) return

  applying.value = true

  try {
    resolve(await api.recordPayment(props.order.id, input))
  } catch (failure) {
    const body = (failure as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors && Object.keys(body.errors).length > 0) errors.value = body.errors
    else toast.danger(message(failure))
  } finally {
    applying.value = false
  }
}

const day = (value: DatePickerModelValue): string | null =>
  typeof value === 'string' && value !== '' ? value.slice(0, 10) : null
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="t('panel.payment-title', { number: order.number ?? '' })"
    :width="480"
  >
    <div class="wx-orders-payment">
      <wx-text size="sm" tone="muted">{{ t('panel.payment-help') }}</wx-text>

      <wx-form-item
        :label="t('panel.payment-amount')"
        :help="
          due > 0
            ? t('panel.payment-due', { amount: order.totals.balance?.amount ?? '' })
            : undefined
        "
        :error="errorOf('amount')"
      >
        <wx-input
          v-model="input.amount"
          class="wx-orders-payment__amount"
          inputmode="decimal"
          :placeholder="major(0)"
          data-test="payment-amount"
        />
      </wx-form-item>

      <wx-form-item
        :label="t('panel.reference')"
        :help="t('panel.reference-help')"
        :error="errorOf('reference')"
      >
        <wx-input v-model="input.reference" :maxlength="190" autocomplete="off" />
      </wx-form-item>

      <wx-form-item
        :label="t('panel.payment-at')"
        :help="t('panel.payment-at-help')"
        :error="errorOf('at')"
      >
        <wx-date-picker
          :model-value="input.at"
          clearable
          @update:model-value="(value: DatePickerModelValue) => (input.at = day(value))"
        />
      </wx-form-item>

      <wx-form-item :label="t('panel.note')" :error="errorOf('note')">
        <wx-textarea v-model="input.note" :rows="2" :maxlength="500" />
      </wx-form-item>

      <wx-alert
        v-if="outcome"
        :type="outcome.over > 0 ? 'warning' : 'info'"
        :description="balanceLine(outcome)"
        aria-live="polite"
        data-test="payment-outcome"
      />
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.dismiss') }}</wx-button>
        <wx-button type="primary" :loading="applying" :disabled="!canApply" @click="apply">
          {{ t('panel.payment-confirm') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-orders-payment {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-orders-payment__amount {
  max-width: 200px;
}
</style>
