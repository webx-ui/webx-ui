<script setup lang="ts">
import { ref, watch } from 'vue'
import {
  toast,
  WxAlert,
  WxBadge,
  WxButton,
  WxDescriptions,
  WxDescriptionsItem,
  WxDialog,
  WxHeading,
  WxSkeleton,
} from '@webx-ui/core'
import { useAdmin, useDates, useErrorText, WxDate } from '@webx-ui/module-admin'
import { createPaymentsApi } from './api'
import EventDialog from './EventDialog.vue'
import EventList from './EventList.vue'
import { statusTones } from './format'
import { usePaymentsLines, usePaymentsWords } from './i18n'
import type { AttemptDetail, EventLine } from './types'

/**
 * One attempt (spec §13.2 `payments.show`): its statuses and amounts, the hold, the gateway's
 * reference, its events of the exchange by time, and «Check with the gateway» for whoever works
 * the orders.
 */
const props = defineProps<{ id: number | null }>()
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ changed: [] }>()

const admin = useAdmin()
const api = createPaymentsApi(admin)
const t = usePaymentsWords()
const w = usePaymentsLines()
const message = useErrorText()
const dates = useDates()

const attempt = ref<AttemptDetail | null>(null)
const loading = ref(false)
const failed = ref('')
const checking = ref(false)
const eventId = ref<number | null>(null)
const eventOpen = ref(false)

watch(
  () => [props.id, open.value] as const,
  async ([id, shown]) => {
    if (!shown || id === null) return

    loading.value = true
    failed.value = ''

    try {
      attempt.value = await api.attempt(id)
    } catch (error) {
      failed.value = message(error)
    } finally {
      loading.value = false
    }
  },
  { immediate: true },
)

async function check(): Promise<void> {
  if (!attempt.value || checking.value) return

  checking.value = true

  try {
    attempt.value = await api.checkAttempt(attempt.value.id)
    toast.success(t('attempts.checked'))
    emit('changed')
  } catch (error) {
    toast.danger(message(error))
  } finally {
    checking.value = false
  }
}

function show(line: EventLine): void {
  eventId.value = line.id
  eventOpen.value = true
}
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="
      attempt ? t('attempts.title', { uuid: attempt.uuid }) : t('attempts.title', { uuid: '' })
    "
    :width="880"
    scroll="body"
  >
    <wx-skeleton v-if="loading && !attempt" :rows="6" />

    <wx-alert v-else-if="failed" type="danger" :description="failed" />

    <div v-else-if="attempt" class="wx-payments-attempt">
      <wx-descriptions :columns="2" size="sm">
        <wx-descriptions-item :label="t('attempts.status')">
          <wx-badge :type="statusTones[attempt.status]" size="sm">
            {{ w(`statuses.statuses.${attempt.status}`) }}
          </wx-badge>
          <wx-badge v-if="attempt.problem" type="danger" size="sm">
            {{ w(`statuses.problems.${attempt.problem}`) }}
          </wx-badge>
          <wx-badge v-if="attempt.resolved" size="sm">{{ t('attempts.resolved') }}</wx-badge>
        </wx-descriptions-item>
        <wx-descriptions-item :label="t('attempts.order')">
          {{ attempt.order_number ?? attempt.order_id }}
        </wx-descriptions-item>
        <wx-descriptions-item :label="t('attempts.method')">{{
          attempt.method_title
        }}</wx-descriptions-item>
        <wx-descriptions-item :label="t('attempts.amount')">{{
          attempt.amount_formatted
        }}</wx-descriptions-item>
        <wx-descriptions-item v-if="attempt.paid_amount_formatted" :label="t('attempts.paid')">
          {{ attempt.paid_amount_formatted }}
          <wx-date v-if="attempt.paid_at" :value="attempt.paid_at" />
        </wx-descriptions-item>
        <wx-descriptions-item
          v-if="attempt.status === 'authorized'"
          :label="t('order.states.held')"
        >
          {{
            t('attempts.held', {
              amount: attempt.authorized_amount_formatted ?? attempt.amount_formatted,
              date: dates.short(attempt.hold_expires_at),
            })
          }}
        </wx-descriptions-item>
        <wx-descriptions-item v-if="attempt.gateway_ref" :label="t('attempts.gateway-ref')">
          <span class="wx-payments-attempt__mono">{{ attempt.gateway_ref }}</span>
        </wx-descriptions-item>
        <wx-descriptions-item v-if="attempt.reference" :label="t('attempts.reference')">
          {{ attempt.reference }}
        </wx-descriptions-item>
        <wx-descriptions-item v-if="attempt.failure_reason" :label="t('attempts.failure')">
          {{ attempt.failure_reason }}
        </wx-descriptions-item>
        <wx-descriptions-item :label="t('attempts.source')">
          {{ w(`statuses.sources.${attempt.source}`) }}
        </wx-descriptions-item>
        <wx-descriptions-item :label="t('attempts.date')">
          <wx-date :value="attempt.created_at" />
        </wx-descriptions-item>
      </wx-descriptions>

      <div class="wx-payments-attempt__head">
        <wx-heading :level="4">{{ t('attempts.events') }}</wx-heading>
        <wx-button
          v-if="attempt.can_check"
          size="sm"
          variant="outline"
          icon="refresh"
          :loading="checking"
          data-test="attempt-check"
          @click="check"
        >
          {{ t('attempts.check') }}
        </wx-button>
      </div>

      <event-list :events="attempt.events" :empty-text="t('attempts.no-events')" @open="show" />
    </div>

    <event-dialog :id="eventId" v-model:open="eventOpen" />
  </wx-dialog>
</template>

<style scoped>
.wx-payments-attempt {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
}

.wx-payments-attempt__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
}

.wx-payments-attempt__mono {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  overflow-wrap: anywhere;
}
</style>
