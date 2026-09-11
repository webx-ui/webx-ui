<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  WxBadge,
  WxButton,
  WxCard,
  WxDivider,
  WxEntityCard,
  WxIcon,
  WxInput,
  WxListDetail,
  WxMenu,
  WxMenuItem,
  WxScrollbar,
  WxText,
} from '@webx-ui/core'

type Status = 'new' | 'paid' | 'shipped'

interface Order {
  id: string
  customer: string
  initials: string
  total: string
  placed: string
  status: Status
  lines: { title: string; qty: number; price: string }[]
}

const orders: Order[] = [
  {
    id: 'WX-1041',
    customer: 'Nova Build',
    initials: 'NB',
    total: '€4 180',
    placed: 'today, 11:20',
    status: 'new',
    lines: [
      { title: 'Hydraulic breaker HB-20', qty: 1, price: '€3 400' },
      { title: 'Quick coupler', qty: 2, price: '€390' },
    ],
  },
  {
    id: 'WX-1040',
    customer: 'Tomas Weber',
    initials: 'TW',
    total: '€620',
    placed: 'today, 09:05',
    status: 'new',
    lines: [{ title: 'Bucket 300 mm', qty: 1, price: '€620' }],
  },
  {
    id: 'WX-1038',
    customer: 'Lehmann GmbH',
    initials: 'LG',
    total: '€12 900',
    placed: 'yesterday',
    status: 'paid',
    lines: [
      { title: 'Mini excavator U27-4', qty: 1, price: '€12 400' },
      { title: 'Transport', qty: 1, price: '€500' },
    ],
  },
  {
    id: 'WX-1035',
    customer: 'Anna Roth',
    initials: 'AR',
    total: '€245',
    placed: '2 July',
    status: 'shipped',
    lines: [{ title: 'Filter set', qty: 5, price: '€49' }],
  },
]

const labels: Record<Status, { text: string; tone: 'warning' | 'primary' | 'success' }> = {
  new: { text: 'New', tone: 'warning' },
  paid: { text: 'Paid', tone: 'primary' },
  shipped: { text: 'Shipped', tone: 'success' },
}

const status = ref<Status | 'all'>('all')
const query = ref('')
const selectedId = ref(orders[0].id)
const open = ref(true)

const visible = computed(() =>
  orders.filter((order) => {
    if (status.value !== 'all' && order.status !== status.value) return false
    const needle = query.value.trim().toLowerCase()
    if (!needle) return true
    return `${order.id} ${order.customer}`.toLowerCase().includes(needle)
  }),
)

const selected = computed(() => visible.value.find((order) => order.id === selectedId.value))

function countOf(name: Status) {
  return orders.filter((order) => order.status === name).length
}

function pick(order: Order) {
  selectedId.value = order.id
  open.value = true
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div class="pane-demo__scroller">
      <div class="pane-demo">
        <wx-list-detail
          v-model:open="open"
          :list-width="300"
          :filters-width="200"
          :detail-min="320"
        >
          <template #filters="{ close }">
            <wx-menu
              v-model="status"
              size="sm"
              label="Status"
              class="pane-demo__filters"
              @select="close"
            >
              <wx-menu-item value="all" icon="list" label="All orders">
                <template #trailing>{{ orders.length }}</template>
              </wx-menu-item>
              <wx-menu-item value="new" icon="cart" label="New">
                <template #trailing>{{ countOf('new') }}</template>
              </wx-menu-item>
              <wx-menu-item value="paid" icon="check-circle" label="Paid">
                <template #trailing>{{ countOf('paid') }}</template>
              </wx-menu-item>
              <wx-menu-item value="shipped" icon="send" label="Shipped">
                <template #trailing>{{ countOf('shipped') }}</template>
              </wx-menu-item>
            </wx-menu>
          </template>

          <template #list="{ filtersInline, openFilters }">
            <div class="pane-demo__search">
              <wx-input v-model="query" size="sm" clearable placeholder="Order or customer">
                <template #prefix><wx-icon name="search" /></template>
              </wx-input>
              <wx-button v-if="!filtersInline" size="sm" variant="outline" @click="openFilters">
                <template #icon><wx-icon name="filter" /></template>
                Status
              </wx-button>
            </div>

            <wx-scrollbar class="pane-demo__rows">
              <wx-entity-card
                v-for="order in visible"
                :key="order.id"
                class="pane-demo__row"
                variant="plain"
                size="sm"
                shape="circle"
                :selected="order.id === selectedId"
                @click="pick(order)"
              >
                <template #media>
                  <span class="pane-demo__avatar">{{ order.initials }}</span>
                </template>
                <template #title>{{ order.customer }}</template>
                <template #meta>
                  <wx-badge :type="labels[order.status].tone" size="sm">
                    {{ labels[order.status].text }}
                  </wx-badge>
                  <wx-text size="xs" tone="muted">{{ order.id }} · {{ order.placed }}</wx-text>
                </template>
                <template #actions>
                  <wx-text size="sm">{{ order.total }}</wx-text>
                </template>
              </wx-entity-card>

              <div v-if="visible.length === 0" class="pane-demo__empty">
                <wx-icon name="cart" :size="32" />
                <wx-text size="sm" tone="muted">Nothing matches</wx-text>
              </div>
            </wx-scrollbar>
          </template>

          <template #detail="{ inline, back }">
            <div v-if="selected" class="pane-demo__detail">
              <div class="pane-demo__bar">
                <wx-button v-if="!inline" size="sm" variant="text" @click="back">
                  <template #icon><wx-icon name="arrow-left" /></template>
                  Back
                </wx-button>
                <strong>{{ selected.id }}</strong>
                <wx-badge :type="labels[selected.status].tone" size="sm">
                  {{ labels[selected.status].text }}
                </wx-badge>
              </div>

              <wx-scrollbar class="pane-demo__body">
                <wx-card padding="md" bordered shadow="never">
                  <wx-text size="lg" weight="semibold">{{ selected.customer }}</wx-text>
                  <wx-text size="sm" tone="muted" class="pane-demo__placed">
                    Placed {{ selected.placed }}
                  </wx-text>

                  <wx-divider spacing="sm" />

                  <div v-for="line in selected.lines" :key="line.title" class="pane-demo__line">
                    <wx-text size="sm">{{ line.title }}</wx-text>
                    <wx-text size="sm" tone="muted">{{ line.qty }} × {{ line.price }}</wx-text>
                  </div>

                  <wx-divider spacing="sm" />

                  <div class="pane-demo__line">
                    <wx-text size="sm" weight="semibold">Total</wx-text>
                    <wx-text size="sm" weight="semibold">{{ selected.total }}</wx-text>
                  </div>
                </wx-card>
              </wx-scrollbar>
            </div>
          </template>

          <template #empty>
            <wx-icon name="cart" :size="40" />
            <wx-text tone="muted">Pick an order</wx-text>
          </template>
        </wx-list-detail>
      </div>
    </div>

    <span class="wx-demo__label">
      Drag the corner: the filters fold into a button, then the order becomes a screen of its own
    </span>
  </div>
</template>

<style scoped>
.pane-demo__scroller {
  width: 100%;
  overflow-x: auto;
}

.pane-demo {
  width: 100%;
  min-width: 320px;
  height: 380px;
  overflow: hidden;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  resize: horizontal;
}

.pane-demo__filters {
  padding: var(--wx-space-8);
}

.pane-demo__search {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-10);
  border-bottom: 1px solid var(--wx-border-muted);
}

.pane-demo__search :deep(.wx-input) {
  flex: 1 1 auto;
}

.pane-demo__rows {
  flex: 1 1 auto;
  min-height: 0;
}

.pane-demo__row {
  cursor: pointer;
  border-bottom: 1px solid var(--wx-border-muted);
  border-radius: 0;
}

.pane-demo__avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: var(--wx-radius-full);
  background: var(--wx-bg-fill);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  font-weight: var(--wx-font-weight-semibold);
}

.pane-demo__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-40) var(--wx-space-16);
  color: var(--wx-border-default);
}

.pane-demo__detail {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
}

.pane-demo__bar {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  padding: var(--wx-space-10) var(--wx-space-12);
  background: var(--wx-bg-surface);
  border-bottom: 1px solid var(--wx-border-default);
}

.pane-demo__body {
  flex: 1 1 auto;
  min-height: 0;
  padding: var(--wx-space-12);
}

.pane-demo__placed {
  display: block;
  margin-top: var(--wx-space-2);
}

.pane-demo__line {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--wx-space-12);
  padding: var(--wx-space-4) 0;
}
</style>
