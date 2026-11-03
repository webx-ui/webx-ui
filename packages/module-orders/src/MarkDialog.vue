<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  toast,
  useModal,
  WxButton,
  WxDatePicker,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSpace,
  WxTextarea,
  type DatePickerModelValue,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type { OrderDetail } from './types'

/**
 * «Mark as paid», «Mark as received» (spec §5.1): the money came outside a gateway — the till,
 * a transfer — or the parcel was handed over. A mark is set once and never taken back, so the
 * dialog asks before it; the operation's number is what a refund or an argument later goes by.
 *
 * The one «Mark as paid» of the card: with the payments installed the server writes their attempt
 * and journal beside the milestone, so the tab «Payment» has no button of its own.
 */
const props = defineProps<{ orderId: number; milestone: 'paid' | 'received' }>()

const { resolve, dismiss, open } = useModal<OrderDetail>()

const admin = useAdmin()
const api = createOrdersApi(admin)
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const reference = ref('')
const note = ref('')
const paidAt = ref<string | null>(null)

const day = (value: DatePickerModelValue): string | null =>
  typeof value === 'string' && value !== '' ? value.slice(0, 10) : null
const applying = ref(false)
const errors = ref<Record<string, string[]>>({})

const paid = computed(() => props.milestone === 'paid')

async function apply(): Promise<void> {
  if (applying.value) return

  applying.value = true

  try {
    resolve(
      await api.mark(props.orderId, props.milestone, reference.value, note.value, paidAt.value),
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
  <wx-dialog
    v-model:open="open"
    :title="t(paid ? 'panel.mark-paid-title' : 'panel.mark-received-title')"
    :width="480"
  >
    <div class="wx-orders-mark">
      <p class="wx-orders-mark__text">
        {{ t(paid ? 'panel.mark-paid-text' : 'panel.mark-received-text') }}
      </p>

      <wx-form-item
        v-if="paid"
        :label="t('panel.reference')"
        :help="t('panel.reference-help')"
        :error="errors.reference"
      >
        <wx-input v-model="reference" :maxlength="190" autocomplete="off" />
      </wx-form-item>

      <wx-form-item
        v-if="paid"
        :label="t('panel.payment-at')"
        :help="t('panel.payment-at-help')"
        :error="errors.paid_at"
      >
        <wx-date-picker
          :model-value="paidAt"
          clearable
          @update:model-value="(value: DatePickerModelValue) => (paidAt = day(value))"
        />
      </wx-form-item>

      <wx-form-item
        :label="t('panel.note')"
        :error="errors.note ?? errors.milestone"
        :help="t('panel.note-help')"
      >
        <wx-textarea v-model="note" :rows="2" :maxlength="500" />
      </wx-form-item>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.dismiss') }}</wx-button>
        <wx-button type="primary" :loading="applying" @click="apply">
          {{ t(paid ? 'panel.mark-paid' : 'panel.mark-received') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-orders-mark {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-orders-mark__text {
  margin: 0;
  color: var(--wx-text-muted);
}
</style>
