<script setup lang="ts">
import { computed } from 'vue'
import { WxAlert, WxSelect, WxText, type SelectOption } from '@webx-ui/core'
import { useTranslate } from '@webx-ui/module-admin'
import { useFeedEditor } from './editor'
import { NAMESPACE, useCatalogFeedsMessages } from './i18n'
import type { StockStatusRow } from './types'

/**
 * `wx-catalog-feed-stock-map`: each stock status against what a marketplace calls it (§6.1 of the
 * feeds spec). A status left alone goes out by what it says of buying, and a status added later
 * breaks nothing. Without the stock satellite there are no statuses: all products take the default.
 */
defineOptions({ name: 'WxCatalogFeedStockMap' })

const VALUES = ['in_stock', 'out_of_stock', 'preorder', 'backorder']

/** What Google takes only with `availability_date`, which the catalogue does not have. */
const DATED = ['preorder', 'backorder']

const props = defineProps<{ disabled?: boolean }>()

const value = defineModel<Record<string, string> | null>({ default: null })

const editor = useFeedEditor()
useCatalogFeedsMessages()

const t = useTranslate(NAMESPACE)

const statuses = computed(() => editor.meta.value?.stock_statuses ?? [])

const map = computed<Record<string, string>>(() =>
  value.value && !Array.isArray(value.value) ? value.value : {},
)
const locked = computed(() => props.disabled || editor.locked.value)
const hasStock = computed(() => editor.meta.value?.stock ?? false)

const options = computed<SelectOption[]>(() =>
  VALUES.map((one) => ({ value: one, label: t(`panel.availability-${one}`) })),
)

const dated = computed(() => Object.values(map.value).some((one) => DATED.includes(one)))

const fallback = computed({
  get: () => String(editor.values.value.options.default_availability ?? 'in_stock'),
  set: (next: string) => {
    editor.values.value = {
      ...editor.values.value,
      options: { ...editor.values.value.options, default_availability: next },
    }
  },
})

const defaultOf = (status: StockStatusRow) => (status.is_purchasable ? 'in_stock' : 'out_of_stock')

function pick(status: StockStatusRow, picked: unknown): void {
  const next = { ...map.value }

  if (picked === null || picked === undefined || picked === defaultOf(status)) {
    delete next[status.code]
  } else {
    next[status.code] = String(picked)
  }

  value.value = next
}
</script>

<template>
  <div class="wx-catalog-feed-stock">
    <template v-if="hasStock">
      <div v-for="status in statuses" :key="status.code" class="wx-catalog-feed-stock__row">
        <wx-text class="wx-catalog-feed-stock__name" size="sm">{{ status.title }}</wx-text>
        <wx-select
          class="wx-catalog-feed-stock__value"
          :model-value="map[status.code] ?? defaultOf(status)"
          :options="options"
          :disabled="locked"
          :aria-label="status.title"
          @update:model-value="(picked: unknown) => pick(status, picked)"
        />
      </div>

      <wx-alert v-if="dated" type="warning" variant="soft" :description="t('panel.stock-dated')" />
    </template>

    <div v-else class="wx-catalog-feed-stock__row">
      <wx-text class="wx-catalog-feed-stock__name" size="sm">
        {{ t('panel.stock-default') }}
      </wx-text>
      <wx-select
        v-model="fallback"
        class="wx-catalog-feed-stock__value"
        :options="options"
        :disabled="locked"
        :aria-label="t('panel.stock-default')"
      />
    </div>
  </div>
</template>

<style scoped>
.wx-catalog-feed-stock {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-catalog-feed-stock__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4) var(--wx-space-12);
}

.wx-catalog-feed-stock__name {
  flex: 1 1 160px;
  min-width: 0;
}

.wx-catalog-feed-stock__value {
  flex: 1 1 200px;
  min-width: 0;
}
</style>
