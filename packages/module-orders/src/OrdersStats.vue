<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { WxIcon, WxSegmented, WxSkeleton, WxStatistic, WxText } from '@webx-ui/core'
import { useAdmin, useDates, useTranslate } from '@webx-ui/module-admin'
import { createOrdersApi } from './api'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type { OrdersSummaryData } from './types'

/**
 * The figures over «Orders» (decision 93): orders, revenue, the average order and what awaits
 * payment, for today, 7 or 30 calendar days of the site (decision 95), and a line of orders by day
 * under them. The count is the agent's `orders_summary` — one class on the server — and it is
 * asked apart from the rows, which never wait for it. Money stays per currency: a shop selling in
 * two never sees them added.
 *
 * Folded or not, and the period, are remembered for the manager in this browser.
 */
defineOptions({ name: 'WxOrdersStats' })

const admin = useAdmin()
const api = createOrdersApi(admin)
const dates = useDates()
useOrdersMessages()

const t = useTranslate(NAMESPACE)

const PERIODS = [1, 7, 30] as const
type Period = (typeof PERIODS)[number]

const key = computed(() => `webx-orders:stats:${admin.state.user?.id ?? 'anyone'}`)

function recall(): { folded: boolean; days: Period } {
  try {
    const stored = JSON.parse(localStorage.getItem(key.value) ?? 'null') as {
      folded?: unknown
      days?: unknown
    } | null

    return {
      folded: stored?.folded === true,
      days: PERIODS.includes(stored?.days as Period) ? (stored?.days as Period) : 7,
    }
  } catch {
    return { folded: false, days: 7 }
  }
}

const remembered = recall()
const folded = ref(remembered.folded)
const days = ref<Period>(remembered.days)

watch([folded, days], () => {
  try {
    localStorage.setItem(key.value, JSON.stringify({ folded: folded.value, days: days.value }))
  } catch {
    // Private windows, blocked site data: the figures open as they did, nothing worse.
  }
})

const summary = ref<OrdersSummaryData | null>(null)
const failed = ref(false)

onMounted(async () => {
  try {
    summary.value = await api.summary()
  } catch {
    failed.value = true
  }
})

const periods = computed(() =>
  PERIODS.map((value) => ({ value, label: t(`panel.stats-period-${value}`) })),
)

const period = computed(
  () => summary.value?.periods.find((one) => one.days === days.value)?.by_currency ?? [],
)

const placed = computed(() => period.value.reduce((sum, one) => sum + one.placed, 0))
const paid = computed(() => period.value.reduce((sum, one) => sum + one.paid, 0))

/** One line a currency; a period without orders still shows a dash rather than nothing. */
const revenue = computed(() => period.value.map((one) => one.revenue_formatted))
const average = computed(() =>
  period.value.filter((one) => one.paid > 0).map((one) => one.average_formatted),
)
const awaiting = computed(() => summary.value?.awaiting_by_currency ?? [])
const awaitingCount = computed(() => awaiting.value.reduce((sum, one) => sum + one.orders, 0))
/** Paid orders still owing a top-up after an edit (§42.4): a second line of «Awaiting payment». */
const topups = computed(() => summary.value?.topup_by_currency ?? [])

/* The line of the last thirty days: a bar a day, as tall as the busiest day allows. */
const daily = computed(() => summary.value?.daily ?? [])
const busiest = computed(() => Math.max(1, ...daily.value.map((day) => day.orders)))
const BAR = 6
const GAP = 2
const HEIGHT = 40
const chartWidth = computed(() => Math.max(1, daily.value.length * (BAR + GAP) - GAP))

const bars = computed(() =>
  daily.value.map((day, index) => {
    const height = day.orders === 0 ? 1 : Math.max(3, (day.orders / busiest.value) * HEIGHT)

    return {
      key: day.date,
      x: index * (BAR + GAP),
      y: HEIGHT - height,
      height,
      empty: day.orders === 0,
      label: t('panel.stats-day', { date: dates.day(day.date), count: day.orders }),
    }
  }),
)

const chartLabel = computed(
  () => `${t('panel.stats-chart')}: ${daily.value.reduce((sum, day) => sum + day.orders, 0)}`,
)
</script>

<template>
  <section class="wx-orders-stats" :class="{ 'is-folded': folded }">
    <div class="wx-orders-stats__head">
      <button
        class="wx-orders-stats__toggle"
        type="button"
        :aria-expanded="!folded"
        :aria-label="t(folded ? 'panel.stats-show' : 'panel.stats-hide')"
        @click="folded = !folded"
      >
        <wx-icon :name="folded ? 'chevron-right' : 'chevron-down'" size="sm" />
        <wx-text weight="medium">{{ t('panel.stats-title') }}</wx-text>
      </button>

      <wx-segmented
        v-if="!folded"
        v-model="days"
        :options="periods"
        size="sm"
        :aria-label="t('panel.stats-period')"
      />
    </div>

    <template v-if="!folded">
      <wx-text v-if="failed" size="sm" tone="muted">{{ t('panel.stats-failed') }}</wx-text>

      <wx-skeleton v-else-if="!summary" :rows="2" />

      <div v-else class="wx-orders-stats__body">
        <div class="wx-orders-stats__grid">
          <wx-statistic
            class="wx-orders-stats__figure"
            :title="t('panel.stats-orders')"
            :value="placed"
          >
            <wx-text size="sm" tone="muted">{{ t('panel.stats-paid', { count: paid }) }}</wx-text>
          </wx-statistic>

          <wx-statistic class="wx-orders-stats__figure" :title="t('panel.stats-revenue')">
            <template #value>
              <span v-if="revenue.length === 0">—</span>
              <span v-for="line in revenue" :key="line" class="wx-orders-stats__money">{{
                line
              }}</span>
            </template>
            <wx-text size="sm" tone="muted">{{ t('panel.stats-revenue-help') }}</wx-text>
          </wx-statistic>

          <wx-statistic class="wx-orders-stats__figure" :title="t('panel.stats-average')">
            <template #value>
              <span v-if="average.length === 0">—</span>
              <span v-for="line in average" :key="line" class="wx-orders-stats__money">{{
                line
              }}</span>
            </template>
            <wx-text size="sm" tone="muted">{{ t('panel.stats-average-help') }}</wx-text>
          </wx-statistic>

          <wx-statistic
            class="wx-orders-stats__figure"
            :title="t('panel.stats-awaiting')"
            :value="awaitingCount"
          >
            <wx-text
              v-for="one in awaiting"
              :key="one.currency"
              class="wx-orders-stats__money"
              size="sm"
            >
              {{ one.amount_formatted }}
            </wx-text>
            <wx-text size="sm" tone="muted">{{ t('panel.stats-awaiting-help') }}</wx-text>
            <wx-text
              v-if="(summary?.awaiting_topup ?? 0) > 0"
              size="sm"
              tone="warning"
              data-test="stats-topup"
            >
              {{ t('panel.stats-topup', { count: summary?.awaiting_topup ?? 0 }) }}
              <template v-for="one in topups" :key="one.currency">
                · {{ one.amount_formatted }}
              </template>
            </wx-text>
          </wx-statistic>
        </div>

        <figure class="wx-orders-stats__chart">
          <figcaption class="wx-orders-stats__caption">{{ t('panel.stats-chart') }}</figcaption>
          <svg
            class="wx-orders-stats__bars"
            :viewBox="`0 0 ${chartWidth} ${HEIGHT}`"
            preserveAspectRatio="none"
            role="img"
            :aria-label="chartLabel"
          >
            <rect
              v-for="bar in bars"
              :key="bar.key"
              :class="{ 'is-empty': bar.empty }"
              :x="bar.x"
              :y="bar.y"
              :width="BAR"
              :height="bar.height"
              rx="1"
            >
              <title>{{ bar.label }}</title>
            </rect>
          </svg>
        </figure>
      </div>
    </template>
  </section>
</template>

<style scoped>
.wx-orders-stats {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
  padding: var(--wx-space-12) var(--wx-space-16);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  background: var(--wx-bg-surface);
  /* Four figures in a row or two by two: the width of the list decides, not the window's. */
  container-type: inline-size;
}

.wx-orders-stats.is-folded {
  padding-block: var(--wx-space-8);
}

.wx-orders-stats__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-orders-stats__toggle {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-6);
  min-height: 32px;
  padding: 0;
  border: 0;
  border-radius: var(--wx-radius-sm);
  background: transparent;
  color: var(--wx-text-default);
  cursor: pointer;
}

.wx-orders-stats__toggle:focus-visible {
  outline: none;
  box-shadow: var(--wx-ring-focus);
}

.wx-orders-stats__body {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: end;
  gap: var(--wx-space-16);
  min-width: 0;
}

.wx-orders-stats__grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--wx-space-16);
  min-width: 0;
}

.wx-orders-stats__figure {
  min-width: 0;
}

.wx-orders-stats__figure :deep(.wx-statistic__value) {
  flex-wrap: wrap;
}

.wx-orders-stats__figure :deep(.wx-statistic__number) {
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow-wrap: anywhere;
}

.wx-orders-stats__money {
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}

.wx-orders-stats__figure :deep(.wx-statistic__footer) {
  display: flex;
  flex-direction: column;
}

.wx-orders-stats__chart {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  margin: 0;
  min-width: 0;
}

.wx-orders-stats__caption {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}

.wx-orders-stats__bars {
  display: block;
  inline-size: 238px;
  max-inline-size: 100%;
  block-size: 40px;
}

.wx-orders-stats__bars rect {
  fill: var(--wx-color-primary);
}

.wx-orders-stats__bars rect.is-empty {
  fill: var(--wx-border-default);
}

/* A list narrower than four figures: the line goes under them, the figures two by two. */
@container (max-width: 860px) {
  .wx-orders-stats__body {
    grid-template-columns: minmax(0, 1fr);
  }

  .wx-orders-stats__bars {
    inline-size: 100%;
  }
}

@container (max-width: 560px) {
  .wx-orders-stats__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--wx-space-12);
  }
}
</style>
