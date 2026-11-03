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
  WxSelect,
  WxSpace,
  WxSwitch,
  WxText,
} from '@webx-ui/core'
import { useAdmin, useErrorText } from '@webx-ui/module-admin'
import { createDeliveryApi } from './api'
import { useDeliveryWords } from './i18n'
import type { CarrierField, OrderDelivery, WaybillDraft } from './types'

/**
 * «Create waybill» (§11.2, §14.2): the request the carrier gets, looked over first. The manager
 * may change who receives the parcel — first and last name apart, as the order keeps them — what
 * it is declared to be worth, the cash to collect on receipt and the carrier's own fields of its
 * type (weight, what is inside: `DescribesWaybill`); a type that takes no cash on delivery has no
 * such field. Who pays the carrier and what is inside are the order's and are shown as they are.
 *
 * Resolves with the tab's new state once the carrier made the waybill.
 */
defineOptions({ name: 'WxDeliveryWaybillDialog' })

const props = defineProps<{ orderId: number; draft: WaybillDraft; exponent: number }>()

const { open, resolve } = useModal<OrderDelivery>()

const context = useAdmin()
const api = createDeliveryApi(context)
const t = useDeliveryWords()
const message = useErrorText()

const scale = 10 ** props.exponent
const major = (minor: number | null) => (minor === null ? null : minor / scale)
const minor = (value: number | null | undefined) =>
  value === null || value === undefined ? null : Math.round(value * scale)

const firstName = ref(props.draft.recipient.first_name)
const lastName = ref(props.draft.recipient.last_name)
const phone = ref(props.draft.recipient.phone ?? '')
const declared = ref<number | null>(major(props.draft.declared_value))
const cod = ref<number | null>(major(props.draft.cod))
const errors = ref<Record<string, string[]>>({})

/** The type's own fields, and whether it takes cash on delivery at all (no form — the base's dialog). */
const extraFields = computed<CarrierField[]>(() => props.draft.form?.fields ?? [])
const takesCod = computed(() => props.draft.form?.cod ?? true)
const extra = ref<Record<string, unknown>>(
  Object.fromEntries(
    extraFields.value.map((field) => [
      field.name,
      props.draft.extra?.[field.name] ?? field.default ?? null,
    ]),
  ),
)

function fieldLabel(field: CarrierField): string {
  if (typeof field.label === 'string') return field.label
  if (field.label && typeof field.label === 'object') {
    return field.label[context.i18n.state.locale] ?? Object.values(field.label)[0] ?? field.name
  }

  return field.name
}
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
  if (saving.value || firstName.value.trim() === '') return

  saving.value = true
  errors.value = {}

  try {
    resolve(
      await api.createWaybill(props.orderId, {
        recipient_first_name: firstName.value.trim(),
        recipient_last_name: lastName.value.trim(),
        recipient_phone: phone.value.trim() || null,
        declared_value: minor(declared.value),
        ...(takesCod.value ? { cod: minor(cod.value) } : {}),
        ...(extraFields.value.length > 0 ? { extra: extra.value } : {}),
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
      <wx-text tone="muted" size="sm">{{
        t(takesCod ? 'order.create-help' : 'order.create-help-no-cod')
      }}</wx-text>

      <wx-alert v-if="errorOf('waybill')" type="danger" :description="errorOf('waybill')" />

      <div class="wx-delivery-waybill__pair">
        <wx-form-item
          :label="t('order.recipient-first-name')"
          :error="errorOf('recipient_first_name')"
          required
        >
          <wx-input
            v-model="firstName"
            :maxlength="190"
            autocomplete="off"
            data-test="waybill-first-name"
          />
        </wx-form-item>
        <wx-form-item
          :label="t('order.recipient-last-name')"
          :error="errorOf('recipient_last_name')"
        >
          <wx-input
            v-model="lastName"
            :maxlength="190"
            autocomplete="off"
            data-test="waybill-last-name"
          />
        </wx-form-item>
      </div>

      <div class="wx-delivery-waybill__pair">
        <wx-form-item :label="t('order.recipient-phone')" :error="errorOf('recipient_phone')">
          <wx-input v-model="phone" :maxlength="32" autocomplete="off" />
        </wx-form-item>
        <wx-form-item :label="t('order.declared')" :error="errorOf('declared_value')">
          <wx-input-number v-model="declared" :min="0" :precision="exponent" :step="1" />
        </wx-form-item>
      </div>

      <wx-form-item
        v-if="takesCod"
        :label="t('order.cod')"
        :help="t('order.cod-help')"
        :error="errorOf('cod')"
        data-test="waybill-cod"
      >
        <wx-input-number v-model="cod" :min="0" :precision="exponent" :step="1" />
      </wx-form-item>

      <div
        v-if="extraFields.length > 0"
        class="wx-delivery-waybill__fact"
        data-test="waybill-extra"
      >
        <span class="wx-delivery-waybill__label">{{ t('order.carrier-fields') }}</span>
        <div class="wx-delivery-waybill__pair">
          <wx-form-item
            v-for="field in extraFields"
            :key="field.name"
            :label="fieldLabel(field)"
            :required="field.required === true"
            :error="errorOf(`extra.${field.name}`)"
          >
            <wx-input-number
              v-if="field.type === 'number'"
              v-model="extra[field.name] as number"
              :min="field.min"
              :max="field.max"
              :step="field.step"
              :precision="field.precision"
            />
            <wx-switch
              v-else-if="field.type === 'boolean'"
              v-model="extra[field.name] as boolean"
            />
            <wx-select
              v-else-if="field.type === 'select' && Array.isArray(field.options)"
              v-model="extra[field.name] as string"
              :options="field.options"
            />
            <wx-input v-else v-model="extra[field.name] as string" autocomplete="off" />
          </wx-form-item>
        </div>
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
          :disabled="firstName.trim() === ''"
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
