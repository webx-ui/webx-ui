<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  createModal,
  toast,
  WxAlert,
  WxBadge,
  WxButton,
  WxCard,
  WxEmpty,
  WxPopconfirm,
  WxSkeleton,
  WxSpace,
  WxText,
  type BadgeType,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useHistorySubject, WxDate } from '@webx-ui/module-admin'
import { createDeliveryApi } from './api'
import { useDeliveryWords } from './i18n'
import type { OrderDelivery, ShipmentRow, ShipmentStatus } from './types'
import WaybillDialog from './WaybillDialog.vue'

/**
 * `wx-delivery-order` — the tab «Delivery» of the order's card (§14.2), patched onto `orders.show`
 * where the orders are installed. What the buyer chose comes from the order's copy (§11.1), never
 * from the live method. Under it — the parcels: «Create waybill» with the request to look over,
 * the statuses the tracking brought, «Refresh tracking», the label and «Cancel waybill».
 *
 * The card does not know the module: the order's id is the one it gives its history.
 */
defineOptions({ name: 'WxDeliveryOrder' })

const admin = useAdmin()
const api = createDeliveryApi(admin)
const t = useDeliveryWords()
const message = useErrorText()
const subject = useHistorySubject()

const state = ref<OrderDelivery | null>(null)
const loading = ref(false)
const failed = ref(false)
const busy = ref(false)

const id = computed(() => {
  const value = Number(subject?.id.value)

  return Number.isInteger(value) && value > 0 ? value : null
})

const waybillDialog = createModal<
  OrderDelivery,
  { orderId: number; draft: NonNullable<OrderDelivery['draft']>; exponent: number }
>(WaybillDialog)

async function load(): Promise<void> {
  if (id.value === null) return

  loading.value = true
  failed.value = false

  try {
    state.value = await api.orderDelivery(id.value)
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

watch(id, () => void load(), { immediate: true })

const delivery = computed(() => state.value?.delivery ?? null)
const tracking = computed(() => state.value?.shipments.some((one) => !one.final) ?? false)
const canCreate = computed(
  () =>
    !!state.value?.can_ship &&
    !!state.value.waybills &&
    state.value.active === null &&
    !!state.value.draft,
)

const tones: Record<ShipmentStatus, BadgeType> = {
  created: 'info',
  in_transit: 'primary',
  at_point: 'warning',
  out_for_delivery: 'warning',
  received: 'success',
  refused: 'danger',
  returning: 'danger',
  returned: 'default',
  cancelled: 'default',
}

async function create(): Promise<void> {
  const open = state.value

  if (!open?.draft) return

  const next = await waybillDialog({
    orderId: open.order_id,
    draft: open.draft,
    exponent: open.exponent,
  })

  if (next) {
    state.value = next
    toast.success(t('order.created', { number: next.shipments[0]?.number ?? '' }))
  }
}

async function run(action: () => Promise<OrderDelivery>, done: string): Promise<void> {
  if (busy.value) return

  busy.value = true

  try {
    state.value = await action()
    toast.success(done)
  } catch (error) {
    toast.danger(message(error))
  } finally {
    busy.value = false
  }
}

const track = () => run(() => api.trackOrder(state.value!.order_id), t('order.tracked'))
const cancel = (row: ShipmentRow) => run(() => api.cancelShipment(row.id), t('order.cancelled'))

async function label(row: ShipmentRow): Promise<void> {
  try {
    const found = await api.shipmentLabel(row.id)

    if ('url' in found) {
      window.open(found.url, '_blank', 'noopener')

      return
    }

    const bytes = Uint8Array.from(atob(found.pdf), (char) => char.charCodeAt(0))
    window.open(URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' })), '_blank')
  } catch (error) {
    toast.danger(message(error))
  }
}
</script>

<template>
  <div class="wx-delivery-order">
    <wx-skeleton v-if="loading && !state" :rows="4" />

    <wx-alert v-else-if="failed" type="danger" :description="t('order.failed')" />

    <template v-else-if="state">
      <wx-card v-if="!delivery">
        <wx-empty :title="t('order.none')" :description="t('order.none-help')" />
      </wx-card>

      <template v-else>
        <wx-card :title="t('order.chosen')">
          <dl class="wx-delivery-order__facts" data-test="order-delivery-facts">
            <div class="wx-delivery-order__fact">
              <dt>{{ t('order.method') }}</dt>
              <dd>{{ delivery.method }}</dd>
            </div>
            <div v-if="delivery.locality" class="wx-delivery-order__fact">
              <dt>{{ t('order.locality') }}</dt>
              <dd>{{ delivery.locality }}</dd>
            </div>
            <div v-if="delivery.point" class="wx-delivery-order__fact">
              <dt>{{ t('order.point') }}</dt>
              <dd>
                <span class="wx-delivery-order__strong">{{ delivery.point.title }}</span>
                <span v-if="delivery.point.address">{{ delivery.point.address }}</span>
                <span v-if="delivery.point.schedule" class="wx-delivery-order__muted">
                  {{ delivery.point.schedule }}
                </span>
              </dd>
            </div>
            <div v-if="delivery.address" class="wx-delivery-order__fact">
              <dt>{{ t('order.address') }}</dt>
              <dd>
                <span>{{ delivery.address }}</span>
                <span v-if="delivery.note" class="wx-delivery-order__muted">{{
                  delivery.note
                }}</span>
              </dd>
            </div>
            <div class="wx-delivery-order__fact">
              <dt>{{ t('order.cost') }}</dt>
              <dd>
                <span>
                  <s v-if="delivery.was" class="wx-delivery-order__muted">{{ delivery.was }}</s>
                  {{ delivery.price ?? '—' }}
                  <span v-if="delivery.estimate" class="wx-delivery-order__muted">
                    ≈ {{ delivery.estimate }}
                  </span>
                </span>
                <wx-badge v-if="delivery.paid_by_store" size="sm" type="success">
                  {{ t('order.paid-by-store') }}
                </wx-badge>
              </dd>
            </div>
            <div v-if="delivery.payer" class="wx-delivery-order__fact">
              <dt>{{ t('order.payer') }}</dt>
              <dd>
                {{
                  delivery.payer === 'sender' ? t('order.payer-sender') : t('order.payer-recipient')
                }}
              </dd>
            </div>
          </dl>
        </wx-card>

        <wx-card :title="t('order.parcels')">
          <template v-if="state.can_ship && (canCreate || tracking)" #extra>
            <wx-space :size="8" wrap>
              <wx-button
                v-if="tracking"
                size="sm"
                variant="outline"
                icon="refresh"
                :loading="busy"
                data-test="order-track"
                @click="track"
              >
                {{ t('order.track') }}
              </wx-button>
              <wx-button
                v-if="canCreate"
                size="sm"
                type="primary"
                icon="plus"
                data-test="order-waybill"
                @click="create"
              >
                {{ t('order.create') }}
              </wx-button>
            </wx-space>
          </template>

          <wx-text v-if="!state.waybills" tone="muted">{{ t('order.own') }}</wx-text>

          <wx-text v-else-if="state.shipments.length === 0" tone="muted">
            {{ t('order.no-parcels') }}
          </wx-text>

          <ul v-else class="wx-delivery-order__parcels" data-test="order-parcels">
            <li v-for="row in state.shipments" :key="row.id" class="wx-delivery-order__parcel">
              <div class="wx-delivery-order__parcel-head">
                <span class="wx-delivery-order__strong">{{ row.number ?? '—' }}</span>
                <wx-badge size="sm" :type="tones[row.status]">{{ row.status_label }}</wx-badge>
                <span v-if="row.cost_formatted" class="wx-delivery-order__muted">
                  {{ t('order.carrier-cost') }}: {{ row.cost_formatted }}
                </span>
                <span class="wx-delivery-order__gap" />
                <wx-space :size="8" wrap>
                  <wx-button
                    v-if="row.label"
                    size="sm"
                    variant="text"
                    icon="download"
                    @click="label(row)"
                  >
                    {{ t('order.label') }}
                  </wx-button>
                  <wx-popconfirm
                    v-if="state.can_ship && row.cancellable"
                    :title="t('order.cancel-confirm', { number: row.number ?? '' })"
                    :confirm-text="t('order.cancel')"
                    :cancel-text="t('common.cancel')"
                    confirm-type="danger"
                    align="end"
                    @confirm="cancel(row)"
                  >
                    <template #trigger>
                      <wx-button size="sm" variant="text" type="danger" :disabled="busy">
                        {{ t('order.cancel') }}
                      </wx-button>
                    </template>
                  </wx-popconfirm>
                </wx-space>
              </div>

              <ol class="wx-delivery-order__events">
                <li
                  v-for="(event, index) in row.events"
                  :key="index"
                  class="wx-delivery-order__event"
                >
                  <wx-date :value="event.at" size="sm" tone="muted" />
                  <span>{{ event.label }}</span>
                  <span v-if="event.text" class="wx-delivery-order__muted">{{ event.text }}</span>
                </li>
              </ol>

              <wx-text v-if="row.final" size="sm" tone="muted">{{ t('order.final') }}</wx-text>
            </li>
          </ul>

          <wx-text
            v-if="state.can_ship && state.waybills && state.active !== null"
            size="sm"
            tone="muted"
          >
            {{ t('order.made-already') }}
          </wx-text>
        </wx-card>
      </template>
    </template>
  </div>
</template>

<style scoped>
.wx-delivery-order {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
}

.wx-delivery-order__facts {
  display: grid;
  gap: var(--wx-space-12);
  margin: 0;
  container-type: inline-size;
}

.wx-delivery-order__fact {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--wx-space-2);
}

/* The label beside its value once the card has room for both: the panel's width decides. */
@container (min-width: 480px) {
  .wx-delivery-order__fact {
    grid-template-columns: 160px minmax(0, 1fr);
    gap: var(--wx-space-16);
  }
}

.wx-delivery-order__fact dt {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-delivery-order__fact dd {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wx-space-2);
  min-width: 0;
  margin: 0;
  overflow-wrap: anywhere;
}

.wx-delivery-order__strong {
  color: var(--wx-text-strong);
  font-weight: var(--wx-font-weight-semibold);
}

.wx-delivery-order__muted {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-delivery-order__parcels {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-delivery-order__parcel {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-delivery-order__parcel + .wx-delivery-order__parcel {
  padding-top: var(--wx-space-16);
  border-top: 1px solid var(--wx-border-default);
}

.wx-delivery-order__parcel-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-delivery-order__gap {
  flex: 1 1 auto;
}

.wx-delivery-order__events {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-delivery-order__event {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: var(--wx-space-4) var(--wx-space-8);
  min-width: 0;
}
</style>
