<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import {
  toast,
  useModal,
  WxAlert,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxInputNumber,
  WxSpace,
  WxText,
  WxTextarea,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type { OrderCardData, OrderDetail, OrderLine, RefundInput, RefundPreview } from './types'

/**
 * «Record a refund» (spec §9.3, §9.4): money given back outside the site — the bank, the till,
 * the ledger. Nothing is split for the manager (decision 26): the money, each tender and each
 * line's units are typed in, and the dialog shows what is left of each and, from the service's
 * own dry run, whether this refund gives the order back whole. No gateway is called from here.
 */
const props = defineProps<{ order: OrderCardData }>()

const { resolve, dismiss, open } = useModal<OrderDetail>()

const admin = useAdmin()
const api = createOrdersApi(admin)
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const input = reactive<RefundInput>({
  amount: '',
  tenders: {},
  items: {},
  reference: '',
  reason: '',
})
const preview = ref<RefundPreview | null>(null)
const applying = ref(false)
const errors = ref<Record<string, string[]>>({})

/** Every line with units left to return, a set's parts too. */
const lines = computed(() => {
  const flat: OrderLine[] = []
  const walk = (items: OrderLine[]) => {
    for (const line of items) {
      if (line.qty - line.refunded_qty > 0) flat.push(line)
      walk(line.children)
    }
  }

  walk(props.order.items)

  return flat
})

const tenders = computed(() => props.order.tenders.filter((tender) => tender.left_minor > 0))

/** A minor amount in the currency's own units, as the field takes it: 4999 → «49.99». */
const major = (minor: number) => (minor / 10 ** props.order.exponent).toFixed(props.order.exponent)

const empty = computed(
  () =>
    (input.amount === '' || Number(input.amount) === 0) &&
    Object.values(input.tenders).every((amount) => amount === '' || Number(amount) === 0) &&
    Object.values(input.items).every((qty) => !qty),
)

let timer: ReturnType<typeof setTimeout> | undefined
let asked = 0

/** The dry run, a moment after the typing stops: what is left, and whether it is whole. */
watch(
  () => JSON.stringify(input),
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
        const answer = await api.previewRefund(props.order.id, input)

        if (mine === asked) preview.value = answer
      } catch (failure) {
        if (mine !== asked) return

        const body = (failure as { body?: { errors?: Record<string, string[]> } }).body

        preview.value = null
        errors.value = body?.errors ?? {}
      }
    }, 300)
  },
)

onBeforeUnmount(() => clearTimeout(timer))

function fill(): void {
  input.amount = major(props.order.totals.refundable_minor)

  for (const tender of tenders.value) input.tenders[tender.id] = major(tender.left_minor)
}

function errorOf(field: string): string | undefined {
  if (preview.value?.field === field && preview.value.message) return preview.value.message

  return errors.value[field]?.[0]
}

const canApply = computed(
  () => !empty.value && !applying.value && preview.value !== null && preview.value.refusal === null,
)

async function apply(): Promise<void> {
  if (!canApply.value) return

  applying.value = true

  try {
    resolve(await api.refund(props.order.id, input))
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
  <wx-dialog
    v-model:open="open"
    :title="t('panel.refund-title', { number: order.number ?? '' })"
    :width="600"
  >
    <div class="wx-orders-refund">
      <wx-text size="sm" tone="muted">{{ t('panel.refund-help') }}</wx-text>

      <wx-form-item
        :label="t('panel.refund-amount')"
        :help="t('panel.refund-left', { amount: order.totals.refundable })"
        :error="errorOf('amount')"
      >
        <div class="wx-orders-refund__amount">
          <wx-input v-model="input.amount" inputmode="decimal" :placeholder="major(0)" />
          <wx-button
            v-if="order.totals.refundable_minor > 0 || tenders.length > 0"
            variant="text"
            size="sm"
            @click="fill"
          >
            {{ t('panel.refund-all') }}
          </wx-button>
        </div>
      </wx-form-item>

      <wx-form-item v-if="tenders.length > 0" :label="t('panel.refund-tenders')">
        <div class="wx-orders-refund__parts">
          <div v-for="tender in tenders" :key="tender.id" class="wx-orders-refund__part">
            <wx-text class="wx-orders-refund__label">{{ tender.label }}</wx-text>
            <wx-form-item
              :error="errorOf(`tenders.${tender.id}`)"
              :help="t('panel.left-of', { amount: major(tender.left_minor) })"
            >
              <wx-input v-model="input.tenders[tender.id]" inputmode="decimal" />
            </wx-form-item>
          </div>
        </div>
      </wx-form-item>

      <wx-form-item
        v-if="lines.length > 0"
        :label="t('panel.refund-items')"
        :help="t('panel.refund-items-help')"
      >
        <div class="wx-orders-refund__parts">
          <div v-for="line in lines" :key="line.id" class="wx-orders-refund__part">
            <wx-text class="wx-orders-refund__label">{{ line.name }}</wx-text>
            <wx-form-item
              :error="errorOf(`items.${line.id}`)"
              :help="t('panel.left-units', { count: line.qty - line.refunded_qty })"
            >
              <wx-input-number
                v-model="input.items[line.id]"
                :min="0"
                :max="line.qty - line.refunded_qty"
                :step="1"
              />
            </wx-form-item>
          </div>
        </div>
      </wx-form-item>

      <wx-form-item
        :label="t('panel.reference')"
        :help="t('panel.reference-help')"
        :error="errorOf('reference')"
      >
        <wx-input v-model="input.reference" :maxlength="190" autocomplete="off" />
      </wx-form-item>

      <wx-form-item :label="t('panel.refund-reason')" :error="errorOf('reason')">
        <wx-textarea v-model="input.reason" :rows="2" :maxlength="500" />
      </wx-form-item>

      <wx-alert
        v-if="preview && preview.refusal === null"
        :type="preview.full ? 'warning' : 'info'"
        :description="t(preview.full ? 'panel.refund-full' : 'panel.refund-partial')"
        aria-live="polite"
      />
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.dismiss') }}</wx-button>
        <wx-button type="primary" :loading="applying" :disabled="!canApply" @click="apply">
          {{ t('panel.refund-confirm') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-orders-refund {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-orders-refund__amount {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-orders-refund__amount > :first-child {
  max-width: 200px;
}

.wx-orders-refund__parts {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  width: 100%;
}

.wx-orders-refund__part {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 160px;
  align-items: start;
  gap: var(--wx-space-12);
}

.wx-orders-refund__label {
  padding-top: var(--wx-space-6);
  overflow-wrap: anywhere;
}
</style>
