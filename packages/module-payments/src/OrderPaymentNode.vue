<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  toast,
  WxAlert,
  WxBadge,
  WxButton,
  WxCard,
  WxDialog,
  WxEmpty,
  WxFormItem,
  WxDatePicker,
  WxInput,
  WxRadio,
  WxRadioGroup,
  WxSkeleton,
  WxSpace,
  WxIcon,
  WxText,
  WxTextarea,
  type BadgeType,
} from '@webx-ui/core'
import { useAdmin, useDates, useErrorText, useHistorySubject, WxDate } from '@webx-ui/module-admin'
import { createPaymentsApi } from './api'
import EventDialog from './EventDialog.vue'
import EventList from './EventList.vue'
import { statusTones } from './format'
import { usePaymentsLines, usePaymentsWords } from './i18n'
import type { EventLine, OrderAttempt, OrderPayment, OrderPaymentState } from './types'

/**
 * `wx-payments-order` — the tab «Payment» of the order's card (spec §13.4), patched onto
 * `orders.show`. The method the order was placed with (its copy, never the live method) with the
 * bank details of a transfer, the state, and the attempts — each opening its events of the exchange
 * by time; an event opens its headers and bodies for whoever may read them.
 *
 * The actions are the order's, for whoever works the orders: «Mark as paid», «Check with the
 * gateway», «Change the method», «Payment link», «Problem resolved». After one the card reads the
 * order again: its milestones and history have moved.
 */
defineOptions({ name: 'WxPaymentsOrder' })

const admin = useAdmin()
const api = createPaymentsApi(admin)
const router = useRouter()
const t = usePaymentsWords()
const w = usePaymentsLines()
const message = useErrorText()
const dates = useDates()
const subject = useHistorySubject()

const state = ref<OrderPayment | null>(null)
const loading = ref(false)
const failed = ref(false)
const busy = ref(false)
const expanded = ref<number[]>([])

function toggle(id: number): void {
  expanded.value = expanded.value.includes(id)
    ? expanded.value.filter((one) => one !== id)
    : [...expanded.value, id]
}
const eventId = ref<number | null>(null)
const eventOpen = ref(false)

const marking = ref(false)
const mark = ref({ reference: '', paid_at: '', note: '' })
const switching = ref(false)
const picked = ref<string | null>(null)
const resolving = ref<OrderAttempt | null>(null)
const note = ref('')

const id = computed(() => {
  const value = Number(subject?.id.value)

  return Number.isInteger(value) && value > 0 ? value : null
})

async function load(): Promise<void> {
  if (id.value === null) return

  loading.value = true
  failed.value = false

  try {
    state.value = await api.orderPayment(id.value)
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

watch(id, () => void load(), { immediate: true })

const tones: Record<OrderPaymentState, BadgeType> = {
  awaiting: 'warning',
  paid: 'success',
  failed: 'danger',
  held: 'info',
  cancelled: 'default',
  none: 'default',
}

async function run(action: () => Promise<OrderPayment>, done: string): Promise<boolean> {
  if (busy.value) return false

  busy.value = true

  try {
    state.value = await action()
    toast.success(done)
    await subject?.reload?.()

    return true
  } catch (error) {
    toast.danger(message(error))

    return false
  } finally {
    busy.value = false
  }
}

function startMark(): void {
  mark.value = { reference: '', paid_at: new Date().toISOString().slice(0, 10), note: '' }
  marking.value = true
}

async function confirmMark(): Promise<void> {
  const one = state.value

  if (!one) return

  const done = await run(
    () =>
      api.markPaid(one.order_id, {
        reference: mark.value.reference || null,
        paid_at: mark.value.paid_at || null,
        note: mark.value.note || null,
      }),
    t('order.marked'),
  )

  if (done) marking.value = false
}

const check = () => run(() => api.checkOrder(state.value!.order_id), t('order.checked'))

function startSwitch(): void {
  picked.value = state.value?.methods.find((one) => one.available)?.key ?? null
  switching.value = true
}

async function confirmSwitch(): Promise<void> {
  const one = state.value

  if (!one || picked.value === null) return

  const done = await run(() => api.switchMethod(one.order_id, picked.value!), t('order.switched'))

  if (done) switching.value = false
}

function startResolve(attempt: OrderAttempt): void {
  resolving.value = attempt
  note.value = ''
}

async function confirmResolve(): Promise<void> {
  const one = state.value
  const attempt = resolving.value

  if (!one || !attempt) return

  const done = await run(
    () => api.resolve(one.order_id, attempt.id, note.value || null),
    t('order.resolved'),
  )

  if (done) resolving.value = null
}

async function copyLink(): Promise<void> {
  const link = state.value?.pay_link

  if (!link) return

  try {
    await navigator.clipboard.writeText(link)
    toast.success(t('order.link-copied'))
  } catch {
    toast.info(link)
  }
}

function show(line: EventLine): void {
  eventId.value = line.id
  eventOpen.value = true
}

function allEvents(): void {
  if (!state.value) return

  void router.push({
    name: 'webx.payments.exchange',
    query: { order_id: String(state.value.order_id) },
  })
}

const hasEvents = computed(
  () =>
    (state.value?.attempts.some((one) => one.events.length > 0) ?? false) ||
    (state.value?.other_events.length ?? 0) > 0,
)
</script>

<template>
  <div class="wx-payments-order">
    <wx-skeleton v-if="loading && !state" :rows="4" />

    <wx-alert v-else-if="failed" type="danger" :description="t('order.failed')" />

    <template v-else-if="state">
      <wx-card :title="t('order.method')">
        <template v-if="state.can_manage" #extra>
          <wx-space size="sm" wrap>
            <wx-button
              v-if="state.can_mark_paid"
              size="sm"
              type="primary"
              :disabled="busy"
              data-test="order-mark-paid"
              @click="startMark"
            >
              {{ t('order.mark-paid') }}
            </wx-button>
            <wx-button
              v-if="state.can_check"
              size="sm"
              variant="outline"
              icon="refresh"
              :loading="busy"
              data-test="order-check"
              @click="check"
            >
              {{ t('order.check') }}
            </wx-button>
            <wx-button
              v-if="state.can_switch && state.methods.length > 0"
              size="sm"
              variant="outline"
              :disabled="busy"
              data-test="order-switch"
              @click="startSwitch"
            >
              {{ t('order.switch') }}
            </wx-button>
          </wx-space>
        </template>

        <wx-empty
          v-if="!state.block"
          :title="t('order.none')"
          :description="t('order.none-help')"
        />

        <div v-else class="wx-payments-order__block">
          <div class="wx-payments-order__line">
            <span class="wx-payments-order__title" data-test="order-method">{{
              state.block.title
            }}</span>
            <wx-badge :type="tones[state.state]" data-test="order-state">
              {{ t(`order.states.${state.state}`) }}
            </wx-badge>
          </div>

          <wx-text v-if="state.paid_at" size="sm" tone="muted">
            {{ t('attempts.paid') }}: {{ state.paid_formatted }} · {{ dates.short(state.paid_at) }}
          </wx-text>
          <wx-text v-if="state.due > 0 && state.state !== 'cancelled'" size="sm">
            {{
              state.top_up
                ? t('order.top-up', { amount: state.due_formatted })
                : t('order.due', { amount: state.due_formatted })
            }}
          </wx-text>

          <dl v-if="state.block.instructions.length > 0" class="wx-payments-order__details">
            <template v-for="line in state.block.instructions" :key="line.label">
              <dt>{{ line.label }}</dt>
              <dd>{{ line.text }}</dd>
            </template>
          </dl>

          <div v-if="state.pay_link" class="wx-payments-order__link" data-test="order-link">
            <wx-text size="sm" tone="muted">
              {{ t('order.link') }} · {{ t('order.link-help', { amount: state.due_formatted }) }}
            </wx-text>
            <div class="wx-payments-order__copy">
              <code class="wx-payments-order__url">{{ state.pay_link }}</code>
              <wx-button size="sm" variant="text" icon="copy" @click="copyLink">
                {{ t('common.copy') }}
              </wx-button>
            </div>
          </div>
        </div>
      </wx-card>

      <wx-card :title="t('order.attempts')">
        <template v-if="hasEvents" #extra>
          <wx-button size="sm" variant="text" icon="external-link" @click="allEvents">
            {{ t('exchange.all-of-order') }}
          </wx-button>
        </template>

        <wx-text v-if="state.attempts.length === 0" size="sm" tone="muted">
          {{ t('order.no-attempts') }}
        </wx-text>

        <ul v-else class="wx-payments-order__attempts" data-test="order-attempts">
          <li
            v-for="row in state.attempts"
            :key="row.id"
            class="wx-payments-order__attempt"
            :class="{ 'is-open': expanded.includes(row.id) }"
          >
            <button
              type="button"
              class="wx-payments-order__head"
              :aria-expanded="expanded.includes(row.id)"
              :data-test="`attempt-${row.id}`"
              @click="toggle(row.id)"
            >
              <span class="wx-payments-order__row">
                <span class="wx-payments-order__title">{{ row.method_title }}</span>
                <span class="wx-payments-order__money">{{ row.amount_formatted }}</span>
              </span>
              <span class="wx-payments-order__row">
                <span class="wx-payments-order__status">
                  <wx-badge :type="statusTones[row.status]" size="sm">
                    {{ w(`statuses.statuses.${row.status}`) }}
                  </wx-badge>
                  <wx-badge
                    v-if="row.problem"
                    :type="row.resolved ? 'default' : 'danger'"
                    size="sm"
                  >
                    {{ w(`statuses.problems.${row.problem}`) }}
                  </wx-badge>
                </span>
                <wx-date :value="row.created_at" compact />
              </span>
              <span v-if="row.gateway_ref" class="wx-payments-order__mono">{{
                row.gateway_ref
              }}</span>
              <wx-icon
                :name="expanded.includes(row.id) ? 'chevron-up' : 'chevron-down'"
                class="wx-payments-order__chevron"
              />
            </button>

            <div v-if="expanded.includes(row.id)" class="wx-payments-order__expanded">
              <wx-text v-if="row.status === 'authorized'" size="sm">
                {{
                  t('attempts.held', {
                    amount: row.authorized_amount_formatted ?? row.amount_formatted,
                    date: dates.short(row.hold_expires_at),
                  })
                }}
              </wx-text>
              <wx-text v-if="row.failure_reason" size="sm" tone="muted">
                {{ t('attempts.failure') }}: {{ row.failure_reason }}
              </wx-text>
              <wx-text v-if="row.reference" size="sm" tone="muted">
                {{ t('attempts.reference') }}: {{ row.reference }}
              </wx-text>
              <div v-if="row.can_resolve">
                <wx-button size="sm" variant="outline" :disabled="busy" @click="startResolve(row)">
                  {{ t('order.resolve') }}
                </wx-button>
              </div>
              <event-list :events="row.events" :empty-text="t('attempts.no-events')" @open="show" />
            </div>
          </li>
        </ul>
      </wx-card>

      <wx-card v-if="state.other_events.length > 0" :title="t('order.other-events')">
        <event-list :events="state.other_events" @open="show" />
      </wx-card>
    </template>

    <event-dialog :id="eventId" v-model:open="eventOpen" />

    <wx-dialog v-model:open="marking" :title="t('order.mark-paid-title')" :width="480">
      <div class="wx-payments-order__form">
        <wx-text size="sm" tone="muted">
          {{ t('order.mark-paid-help', { amount: state?.due_formatted ?? '' }) }}
        </wx-text>
        <wx-form-item :label="t('order.reference')">
          <wx-input v-model="mark.reference" :maxlength="190" data-test="mark-reference" />
        </wx-form-item>
        <wx-form-item :label="t('order.paid-at')">
          <wx-date-picker v-model="mark.paid_at" />
        </wx-form-item>
        <wx-form-item :label="t('order.note')">
          <wx-textarea v-model="mark.note" :rows="2" :maxlength="1000" />
        </wx-form-item>
      </div>
      <template #footer>
        <wx-space size="sm">
          <wx-button variant="outline" @click="marking = false">{{ t('common.cancel') }}</wx-button>
          <wx-button type="primary" :loading="busy" data-test="mark-confirm" @click="confirmMark">
            {{ t('order.mark-paid') }}
          </wx-button>
        </wx-space>
      </template>
    </wx-dialog>

    <wx-dialog v-model:open="switching" :title="t('order.switch-title')" :width="520">
      <div class="wx-payments-order__form">
        <wx-text size="sm" tone="muted">{{ t('order.switch-help') }}</wx-text>
        <wx-radio-group v-model="picked" class="wx-payments-order__choices">
          <wx-radio
            v-for="choice in state?.methods ?? []"
            :key="choice.key"
            :value="choice.key"
            :disabled="!choice.available"
          >
            <span class="wx-payments-order__choice">{{ choice.title }}</span>
            <span v-if="!choice.available" class="wx-payments-order__reason">
              {{ choice.reason ?? t('order.unavailable') }}
            </span>
          </wx-radio>
        </wx-radio-group>
      </div>
      <template #footer>
        <wx-space size="sm">
          <wx-button variant="outline" @click="switching = false">{{
            t('common.cancel')
          }}</wx-button>
          <wx-button
            type="primary"
            :loading="busy"
            :disabled="picked === null"
            @click="confirmSwitch"
          >
            {{ t('order.switch') }}
          </wx-button>
        </wx-space>
      </template>
    </wx-dialog>

    <wx-dialog
      :open="resolving !== null"
      :title="t('order.resolve-title')"
      :width="480"
      @update:open="(value: boolean) => !value && (resolving = null)"
    >
      <div class="wx-payments-order__form">
        <wx-text size="sm" tone="muted">{{ t('order.resolve-help') }}</wx-text>
        <wx-form-item :label="t('order.note')">
          <wx-textarea v-model="note" :rows="3" :maxlength="500" />
        </wx-form-item>
      </div>
      <template #footer>
        <wx-space size="sm">
          <wx-button variant="outline" @click="resolving = null">{{
            t('common.cancel')
          }}</wx-button>
          <wx-button type="primary" :loading="busy" @click="confirmResolve">
            {{ t('order.resolve') }}
          </wx-button>
        </wx-space>
      </template>
    </wx-dialog>
  </div>
</template>

<style scoped>
.wx-payments-order {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  min-width: 0;
}

.wx-payments-order__block,
.wx-payments-order__form,
.wx-payments-order__expanded {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-payments-order__expanded {
  padding: 0 var(--wx-space-12) var(--wx-space-12);
}

.wx-payments-order__attempts {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-payments-order__attempt {
  min-width: 0;
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-md);
}

.wx-payments-order__attempt.is-open {
  border-color: var(--wx-border-default);
}

/* The whole line opens the attempt: a phone has no room for a column of chevrons. */
.wx-payments-order__head {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  width: 100%;
  padding: var(--wx-space-10) var(--wx-space-40) var(--wx-space-10) var(--wx-space-12);
  border: 0;
  border-radius: inherit;
  background: none;
  color: inherit;
  font: inherit;
  text-align: start;
  cursor: pointer;
}

.wx-payments-order__head:hover {
  background: var(--wx-bg-fill);
}

.wx-payments-order__head:focus-visible {
  outline: 2px solid var(--wx-border-focus);
  outline-offset: -2px;
}

.wx-payments-order__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-4) var(--wx-space-12);
  min-width: 0;
}

.wx-payments-order__chevron {
  position: absolute;
  top: var(--wx-space-12);
  right: var(--wx-space-12);
  color: var(--wx-text-muted);
}

.wx-payments-order__line {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-payments-order__title {
  font-weight: var(--wx-font-weight-medium);
}

.wx-payments-order__details {
  display: grid;
  grid-template-columns: minmax(0, max-content) minmax(0, 1fr);
  gap: var(--wx-space-4) var(--wx-space-12);
  margin: 0;
  font-size: var(--wx-font-size-sm);
}

.wx-payments-order__details dt {
  color: var(--wx-text-muted);
}

.wx-payments-order__details dd {
  margin: 0;
  white-space: pre-line;
  overflow-wrap: anywhere;
}

.wx-payments-order__link {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-payments-order__copy {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-payments-order__url,
.wx-payments-order__mono {
  min-width: 0;
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  overflow-wrap: anywhere;
}

.wx-payments-order__url {
  flex: 1 1 200px;
}

.wx-payments-order__money {
  font-variant-numeric: tabular-nums;
}

.wx-payments-order__status {
  display: inline-flex;
  flex-wrap: wrap;
  gap: var(--wx-space-4);
}

.wx-payments-order__choices {
  gap: var(--wx-space-10);
}

.wx-payments-order__choices :deep(.wx-radio) {
  align-items: flex-start;
}

.wx-payments-order__choice {
  display: block;
  font-weight: var(--wx-font-weight-medium);
}

.wx-payments-order__reason {
  display: block;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}
</style>
