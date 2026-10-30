<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  confirm,
  createModal,
  toast,
  WxActionBar,
  WxBadge,
  WxBreadcrumb,
  WxBreadcrumbItem,
  WxButton,
  WxCard,
  WxSkeleton,
} from '@webx-ui/core'
import {
  useAdmin,
  useBodyKeys,
  useDates,
  useErrorText,
  useTranslate,
  WxSaveState,
  WxScreen,
  WxScreenHead,
  type ScreenAction,
} from '@webx-ui/module-admin'
import type { ScreenModel } from '@webx-ui/schema'
import { createOrdersApi } from './api'
import { provideOrderCard } from './card'
import CancelDialog from './CancelDialog.vue'
import { NAMESPACE, useOrdersMessages } from './i18n'
import MarkDialog from './MarkDialog.vue'
import PaymentDialog from './PaymentDialog.vue'
import NotifyDialog from './NotifyDialog.vue'
import RefundDialog from './RefundDialog.vue'
import ReverseRefundDialog from './ReverseRefundDialog.vue'
import StatusDialog from './StatusDialog.vue'
import VisitorDialog from './VisitorDialog.vue'
import { toneOf } from './tones'
import type { OrderCardData, OrderDetail, OrderRefundEntry, PublicStatusGroup } from './types'

/**
 * One order (spec §11.3): the head with the number, the date and the actions; `orders.show`
 * under it — the strip of the status and the marks (`header-actions`), the tabs «Items»,
 * «Buyer» and «History» (`tabs`, where a neighbour adds its own with a patch), the side card of
 * the totals (`summary`). The buyer's contacts and the notes are the screen's fields and saved
 * with the bar at the bottom; everything else is a dialog that asks the server what it will do
 * before it does it.
 */
defineOptions({ name: 'WxOrderPage' })

const props = withDefaults(defineProps<{ base?: string }>(), { base: '/orders' })

const admin = useAdmin()
const api = createOrdersApi(admin)
const route = useRoute()
const router = useRouter()
useOrdersMessages()

const t = useTranslate(NAMESPACE)
const dates = useDates()
const message = useErrorText()

const id = computed(() => Number(route.params.id))

const order = ref<OrderCardData | null>(null)
const values = ref<ScreenModel>({})
const snapshot = ref('')
const loading = ref(true)
const saving = ref(false)
const busy = ref(false)
const errors = ref<Record<string, string[]>>({})

const canEdit = computed(() => order.value?.actions.details ?? false)
const locked = computed(() => !canEdit.value || saving.value)

const current = computed(() => JSON.stringify(values.value))
const dirty = computed(() => snapshot.value !== '' && current.value !== snapshot.value)

const state = computed<'saving' | 'unsaved' | 'saved'>(() => {
  if (saving.value) return 'saving'

  return dirty.value ? 'unsaved' : 'saved'
})

const section = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'orders')?.title ??
    t('module.orders'),
)

const title = computed(() =>
  order.value ? t('panel.order-title', { number: order.value.number ?? `#${order.value.id}` }) : '',
)

const statusDialog = createModal<
  OrderDetail,
  { statuses: PublicStatusGroup[]; orderId: number; current: number | null }
>(StatusDialog)
const markDialog = createModal<OrderDetail, { orderId: number; milestone: 'paid' | 'received' }>(
  MarkDialog,
)
const paymentDialog = createModal<OrderDetail, { order: OrderCardData }>(PaymentDialog)
const cancelDialog = createModal<OrderDetail, { orderId: number; number: string | null }>(
  CancelDialog,
)
const refundDialog = createModal<OrderDetail, { order: OrderCardData }>(RefundDialog)
const reverseDialog = createModal<OrderDetail, { orderId: number; refund: OrderRefundEntry }>(
  ReverseRefundDialog,
)
const notifyDialog = createModal<OrderDetail, { orderId: number }>(NotifyDialog)
const visitorDialog = createModal<OrderDetail, { order: OrderCardData; email: string | null }>(
  VisitorDialog,
)

/** A new answer of the server; what is being typed in the contacts stays unless asked. */
function take(detail: OrderDetail, withValues = true): void {
  order.value = detail.order

  if (withValues || !dirty.value) {
    values.value = { ...detail.values }
    snapshot.value = JSON.stringify(values.value)
  }
}

async function load(): Promise<void> {
  loading.value = true
  errors.value = {}

  try {
    take(await api.get(id.value))
  } catch (error) {
    toast.danger(message(error))
    void router.push(props.base)
  } finally {
    loading.value = false
  }
}

/** A dialog's answer is the card as it stands after: the head, the marks, the history. */
async function after(dialog: Promise<OrderDetail | undefined>, done: string): Promise<void> {
  busy.value = true

  try {
    const answer = await dialog

    if (answer) {
      take(answer, false)
      toast.success(done)
    }
  } finally {
    busy.value = false
  }
}

async function changeStatus(): Promise<void> {
  const open = order.value

  if (!open) return

  await after(
    statusDialog({ statuses: open.statuses, orderId: open.id, current: open.status?.id ?? null }),
    t('panel.status-changed'),
  )
}

async function mark(milestone: 'paid' | 'received'): Promise<void> {
  const open = order.value

  if (!open) return

  await after(
    markDialog({ orderId: open.id, milestone }),
    t(milestone === 'paid' ? 'panel.marked-paid' : 'panel.marked-received'),
  )
}

async function recordPayment(): Promise<void> {
  const open = order.value

  if (!open) return

  await after(paymentDialog({ order: open }), t('panel.payment-toast'))
}

async function cancel(): Promise<void> {
  const open = order.value

  if (!open) return

  await after(cancelDialog({ orderId: open.id, number: open.number }), t('panel.cancelled-toast'))
}

async function refund(): Promise<void> {
  const open = order.value

  if (!open) return

  await after(refundDialog({ order: open }), t('panel.refunded-toast'))
}

/**
 * A refund record made by mistake is reversed, never edited (decision 98); the right one is then
 * recorded as any refund, so the dialog of a refund is offered at once.
 */
async function reverseRefund(refundId: number): Promise<void> {
  const open = order.value
  const record = open?.refunds.find((one) => one.id === refundId)

  if (!open || !record || busy.value) return

  busy.value = true

  try {
    const answer = await reverseDialog({ orderId: open.id, refund: record })

    if (!answer) return

    take(answer, false)
    toast.success(t('panel.reversed-toast'))
  } finally {
    busy.value = false
  }

  if (!order.value?.actions.refund) return

  const again = await confirm({
    title: t('panel.reverse-next-title'),
    message: t('panel.reverse-next-text'),
    confirmText: t('panel.reverse-next-confirm'),
    cancelText: t('panel.dismiss'),
  })

  if (again) await refund()
}

/**
 * «Send again» on a row of «Notifications» (decision 99): the same message to the same recipient,
 * asked first with whom and what. A second press within a minute is refused by the server.
 */
async function resend(deliveryId: number): Promise<void> {
  const open = order.value
  const row = open?.notifications?.find((one) => one.id === deliveryId)

  if (!open || !row || busy.value) return

  const what = row.event_label ?? row.channel
  const agreed = await confirm({
    title: t('panel.resend-title', { event: what }),
    message: t('panel.resend-text', { recipient: row.recipient, channel: row.channel }),
    confirmText: t('panel.resend-confirm'),
    cancelText: t('panel.dismiss'),
  })

  if (!agreed) return

  busy.value = true

  try {
    take(await api.resend(open.id, deliveryId), false)
    toast.success(t('panel.resent-toast'))
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    toast.danger(body?.errors?.delivery?.[0] ?? message(error))
  } finally {
    busy.value = false
  }
}

/** «Send the buyer a letter» (decision 99): the dialog shows the letter before it goes. */
async function notify(): Promise<void> {
  const open = order.value

  if (!open) return

  busy.value = true

  try {
    const answer = await notifyDialog({ orderId: open.id })

    if (answer) {
      take(answer, false)
      // The address it went to is the saved one, not what may be typed in the contacts now.
      toast.success(
        t('panel.notify-sent', { email: String(answer.values['customer.email'] ?? '') }),
      )
    }
  } finally {
    busy.value = false
  }
}

async function linkVisitor(): Promise<void> {
  const open = order.value

  if (!open) return

  const email = values.value['customer.email']

  await after(
    visitorDialog({ order: open, email: typeof email === 'string' ? email : null }),
    t('panel.visitor-linked'),
  )
}

/** Unlinking asks first, with whom the order leaves — the service's dry run says. */
async function unlinkVisitor(): Promise<void> {
  const open = order.value

  if (!open || busy.value) return

  busy.value = true

  try {
    const plan = await api.previewUnlink(open.id)
    const name = plan.previous?.name ?? plan.previous?.email ?? `#${plan.previous?.id ?? ''}`
    const agreed = await confirm({
      title: t('panel.visitor-unlink-title', { number: open.number ?? `#${open.id}` }),
      message: `${t('panel.visitor-unlink-text', { name })} ${t('panel.visitor-no-letter')}`,
      confirmText: t('panel.visitor-unlink'),
      cancelText: t('panel.dismiss'),
      tone: 'danger',
    })

    if (!agreed) return

    take(await api.unlink(open.id), false)
    toast.success(t('panel.visitor-unlinked'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    busy.value = false
  }
}

/**
 * A note goes in at once, beside the contacts' save bar and not through it: it is a line of the
 * history, not a field of the order, and what is being typed in the contacts stays.
 */
async function note(body: string): Promise<boolean> {
  const open = order.value

  if (!open) return false

  try {
    take(await api.note(open.id, body), false)

    return true
  } catch (error) {
    toast.danger(message(error))

    return false
  }
}

/**
 * The pinned note is saved on its own, as a note is: it is the block «Notes»'s, not a field of the
 * screen's save bar, and what is being typed in the contacts stays. The write is the contacts' own
 * (`details`), so the journal and the history keep it as before.
 */
async function pin(text: string | null): Promise<boolean> {
  const open = order.value

  if (!open) return false

  try {
    take(await api.saveDetails(open.id, { manager_note: text }), false)
    toast.success(t(text === null ? 'panel.pin-removed' : 'panel.pin-saved'))

    return true
  } catch (error) {
    toast.danger(message(error))

    return false
  }
}

provideOrderCard({
  order,
  busy,
  changeStatus,
  mark,
  recordPayment,
  cancel,
  refund,
  reverseRefund,
  resend,
  note,
  pin,
  linkVisitor,
  unlinkVisitor,
})

async function save(): Promise<void> {
  const open = order.value

  if (!open || saving.value || !canEdit.value) return

  saving.value = true
  errors.value = {}

  try {
    take(await api.saveDetails(open.id, values.value))
    toast.success(t('panel.saved'))
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors && Object.keys(body.errors).length > 0) {
      errors.value = body.errors
      toast.danger(Object.values(body.errors)[0]?.[0] ?? t('panel.save-failed'))
    } else {
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}

const actions = computed<ScreenAction[]>(() => {
  const open = order.value

  if (!open) return []

  const list: ScreenAction[] = []

  if (open.actions.paid) {
    list.push({
      key: 'paid',
      label: t('panel.mark-paid'),
      icon: 'check',
      run: () => void mark('paid'),
    })
  }

  // The pencil beside the status in the strip opens the same dialog; on a narrow head this one
  // folds into the `···` with the rest.
  if (open.actions.status) {
    list.push({
      key: 'status',
      label: t('panel.status-change'),
      icon: 'edit',
      run: () => void changeStatus(),
    })
  }

  if (open.actions.received) {
    list.push({
      key: 'received',
      label: t('panel.mark-received'),
      icon: 'map-pin',
      menu: open.actions.paid,
      run: () => void mark('received'),
    })
  }

  if (open.actions.refund) {
    list.push({
      key: 'refund',
      label: t('panel.refund'),
      icon: 'arrow-left',
      menu: true,
      run: () => void refund(),
    })
  }

  // A letter to the buyer again (decision 99): greyed with why when no channel writes to the buyer.
  if (open.actions.notify) {
    list.push({
      key: 'notify',
      label: t('panel.notify-buyer'),
      icon: 'mail',
      menu: true,
      disabled: Boolean(open.actions.notify_reason),
      hint: open.actions.notify_reason ?? undefined,
      run: () => void notify(),
    })
  }

  if (open.actions.cancel) {
    list.push({
      key: 'cancel',
      label: t('panel.cancel-order'),
      icon: 'close',
      danger: true,
      run: () => void cancel(),
    })
  }

  return list
})

function leaveGuard(event: BeforeUnloadEvent): void {
  if (dirty.value) event.preventDefault()
}

function onKeydown(event: KeyboardEvent): void {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
    event.preventDefault()
    if (dirty.value) void save()
  }
}

useBodyKeys(onKeydown)

watch(id, () => void load())

onMounted(() => {
  void load()
  window.addEventListener('beforeunload', leaveGuard)
})

onBeforeUnmount(() => window.removeEventListener('beforeunload', leaveGuard))

onBeforeRouteLeave(async () => {
  if (!dirty.value || !canEdit.value) return true

  return await confirm({
    title: t('panel.leave-title'),
    message: t('panel.leave-text'),
    confirmText: t('panel.leave'),
    cancelText: t('panel.dismiss'),
    tone: 'danger',
  })
})
</script>

<template>
  <div class="wx-order-page" @keydown="onKeydown">
    <template v-if="loading">
      <wx-skeleton class="wx-order-page__ghost" title :rows="1" />
      <wx-card><wx-skeleton :rows="6" /></wx-card>
    </template>

    <template v-else-if="order">
      <wx-screen-head
        divider
        :back="props.base"
        :back-label="section"
        :title="title"
        :subtitle="dates.short(order.placed_at)"
        :actions="actions"
      >
        <template #trail>
          <wx-breadcrumb size="sm" :label="t('panel.trail')">
            <wx-breadcrumb-item :as="'router-link'" :to="props.base">
              {{ section }}
            </wx-breadcrumb-item>
            <wx-breadcrumb-item current>{{ title }}</wx-breadcrumb-item>
          </wx-breadcrumb>
        </template>

        <template #title-after>
          <wx-badge :type="toneOf(order.status?.color)" dot size="sm">
            {{ order.status?.name ?? '—' }}
          </wx-badge>
        </template>
      </wx-screen-head>

      <wx-screen v-model="values" name="orders.show" :errors="errors" :disabled="locked" />

      <wx-action-bar v-if="canEdit && dirty">
        <template #state>
          <wx-save-state :state="state" />
        </template>

        <wx-button type="primary" :loading="saving" :disabled="!dirty" @click="save">
          {{ t('panel.save') }}
        </wx-button>
      </wx-action-bar>
    </template>
  </div>
</template>

<style scoped>
.wx-order-page {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-order-page__ghost {
  max-width: 420px;
}
</style>
