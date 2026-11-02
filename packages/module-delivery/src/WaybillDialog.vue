<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  useModal,
  WxAlert,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxInputNumber,
  WxSpace,
  WxText,
} from '@webx-ui/core'
import { useAdmin, useErrorText } from '@webx-ui/module-admin'
import { createDeliveryApi } from './api'
import { useDeliveryWords } from './i18n'
import type { OrderDelivery, WaybillDraft } from './types'

/**
 * «Create waybill» (§11.2, §14.2): the request the carrier gets, looked over first. The manager
 * may change who receives the parcel, what it is declared to be worth and the cash to collect on
 * receipt; who pays the carrier and what is inside are the order's and are shown as they are.
 *
 * Resolves with the tab's new state once the carrier made the waybill.
 */
defineOptions({ name: 'WxDeliveryWaybillDialog' })

const props = defineProps<{ orderId: number; draft: WaybillDraft; exponent: number }>()

const { open, resolve } = useModal<OrderDelivery>()

const api = createDeliveryApi(useAdmin())
const t = useDeliveryWords()
const message = useErrorText()

const scale = 10 ** props.exponent
const major = (minor: number | null) => (minor === null ? null : minor / scale)
const minor = (value: number | null | undefined) =>
  value === null || value === undefined ? null : Math.round(value * scale)

const name = ref(props.draft.recipient.name)
const phone = ref(props.draft.recipient.phone ?? '')
const declared = ref<number | null>(major(props.draft.declared_value))
const cod = ref<number | null>(major(props.draft.cod))
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)

const money = (amount: number) =>
  new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: props.draft.currency,
    minimumFractionDigits: props.exponent,
    maximumFractionDigits: props.exponent,
  }).format(amount / scale)

const payer = computed(() =>
  props.draft.payer === 'sender' ? t('order.payer-sender') : t('order.payer-recipient'),
)

const errorOf = (key: string) => errors.value[key]?.[0]

async function submit(): Promise<void> {
  if (saving.value || name.value.trim() === '') return

  saving.value = true
  errors.value = {}

  try {
    resolve(
      await api.createWaybill(props.orderId, {
        recipient_name: name.value.trim(),
        recipient_phone: phone.value.trim() || null,
        declared_value: minor(declared.value),
        cod: minor(cod.value),
      }),
    )
  } catch (error) {
    errors.value = (error as { errors?: Record<string, string[]> }).errors ?? {}
    if (Object.keys(errors.value).length === 0) errors.value = { waybill: [message(error)] }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('order.create-title')" :width="560">
    <div class="wx-delivery-waybill">
      <wx-text tone="muted" size="sm">{{ t('order.create-help') }}</wx-text>

      <wx-alert v-if="errorOf('waybill')" type="danger" :description="errorOf('waybill')" />

      <div class="wx-delivery-waybill__pair">
        <wx-form-item
          :label="t('order.recipient-name')"
          :error="errorOf('recipient_name')"
          required
        >
          <wx-input v-model="name" :maxlength="190" autocomplete="off" data-test="waybill-name" />
        </wx-form-item>
        <wx-form-item :label="t('order.recipient-phone')" :error="errorOf('recipient_phone')">
          <wx-input v-model="phone" :maxlength="32" autocomplete="off" />
        </wx-form-item>
      </div>

      <div class="wx-delivery-waybill__pair">
        <wx-form-item :label="t('order.declared')" :error="errorOf('declared_value')">
          <wx-input-number v-model="declared" :min="0" :precision="exponent" :step="1" />
        </wx-form-item>
        <wx-form-item :label="t('order.cod')" :help="t('order.cod-help')" :error="errorOf('cod')">
          <wx-input-number v-model="cod" :min="0" :precision="exponent" :step="1" />
        </wx-form-item>
      </div>

      <div class="wx-delivery-waybill__fact">
        <span class="wx-delivery-waybill__label">{{ t('order.payer') }}</span>
        <span>{{ payer }}</span>
      </div>

      <div v-if="draft.lines.length > 0" class="wx-delivery-waybill__fact">
        <span class="wx-delivery-waybill__label">{{ t('order.lines') }}</span>
        <ul class="wx-delivery-waybill__lines">
          <li v-for="(line, index) in draft.lines" :key="index">
            <span class="wx-delivery-waybill__line-name">{{ line.name }}</span>
            <span class="wx-delivery-waybill__line-qty">× {{ line.qty }}</span>
            <span class="wx-delivery-waybill__line-price">{{ money(line.price) }}</span>
          </li>
        </ul>
      </div>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="open = false">{{ t('common.cancel') }}</wx-button>
        <wx-button
          type="primary"
          :loading="saving"
          :disabled="name.trim() === ''"
          data-test="waybill-create"
          @click="submit"
        >
          {{ t('order.create') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-delivery-waybill {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  container-type: inline-size;
}

/* Two fields in a row once the dialog has room for them: the panel's width decides, not the window's. */
.wx-delivery-waybill__pair {
  display: grid;
  gap: 0 var(--wx-space-16);
  grid-template-columns: minmax(0, 1fr);
}

@container (min-width: 440px) {
  .wx-delivery-waybill__pair {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.wx-delivery-waybill__fact {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}

.wx-delivery-waybill__label {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-delivery-waybill__lines {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-delivery-waybill__lines li {
  display: flex;
  align-items: baseline;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-delivery-waybill__line-name {
  flex: 1 1 auto;
  min-width: 0;
  overflow-wrap: anywhere;
}

.wx-delivery-waybill__line-qty,
.wx-delivery-waybill__line-price {
  flex: none;
  color: var(--wx-text-muted);
  font-variant-numeric: tabular-nums;
}
</style>
