<script setup lang="ts">
import { computed } from 'vue'
import { useI18n, useTranslate } from '@webx-ui/module-admin'
import { useConfiguratorsMessages } from './i18n'
import { formatMoney, formatNumber } from './money'

/**
 * The configuration of a request, in the inbox's card (inbox §2.20) — the panel type
 * `inbox-enclosure:configurator`. What was chosen, the sizes, the area, the lines of the price and
 * the total, all as the server counted them when the request arrived; the labels are the snapshot
 * of that moment, so a request reads the same after an option is renamed.
 */
defineOptions({ name: 'WxConfiguratorRequest' })

interface Payload {
  configurator?: { id?: number; key?: string; title?: string }
  version?: number | null
  selection?: { step: string; label: string; value: string; option: string }[]
  dimensions?: { key: string; label: string; value: number; unit: string }[]
  area?: number
  currency?: string
  total_before_discount?: number
  discount?: { percent: number; amount: number; label: string } | null
  total?: number
  lines?: { kind: string; amount: number; line?: string }[]
}

const props = defineProps<{ payload?: unknown; value?: string | null }>()

useConfiguratorsMessages()

const t = useTranslate('webx-configurator')
const i18n = useI18n()

const data = computed<Payload>(() =>
  typeof props.payload === 'object' && props.payload !== null ? (props.payload as Payload) : {},
)
/* The panel's language: the request is read by a manager, in the words of the panel. */
const locale = computed(() => i18n.state.locale)
const currency = computed(() => data.value.currency ?? '')
const money = (amount: number | undefined) =>
  formatMoney(Number(amount ?? 0), currency.value, locale.value)
</script>

<template>
  <div class="wx-configurator-request">
    <p v-if="data.version" class="wx-configurator-request__meta">
      {{ t('configurator.request-version', { number: data.version }) }}
    </p>

    <dl class="wx-configurator-request__list">
      <template v-for="one in data.selection ?? []" :key="one.step">
        <dt>{{ one.label }}</dt>
        <dd>{{ one.option }}</dd>
      </template>
      <template v-for="size in data.dimensions ?? []" :key="size.key">
        <dt>{{ size.label }}</dt>
        <dd>{{ formatNumber(size.value, locale, false) }} {{ size.unit }}</dd>
      </template>
      <template v-if="(data.area ?? 0) > 0">
        <dt>{{ t('site.area') }}</dt>
        <dd>{{ formatNumber(data.area ?? 0, locale, false) }} m²</dd>
      </template>
    </dl>

    <ul v-if="(data.lines ?? []).length > 0" class="wx-configurator-request__lines">
      <li
        v-for="(line, at) in data.lines ?? []"
        :key="at"
        :class="['wx-configurator-request__line', { 'is-discount': line.kind === 'discount' }]"
      >
        <span>{{ line.line ?? line.kind }}</span>
        <span class="wx-configurator-request__amount">{{ money(line.amount) }}</span>
      </li>
    </ul>

    <p class="wx-configurator-request__total">
      <span v-if="data.discount" class="wx-configurator-request__before">
        {{ t('configurator.request-before') }}: <s>{{ money(data.total_before_discount) }}</s>
      </span>
      <strong>{{ t('site.total') }}: {{ money(data.total) }}</strong>
    </p>
  </div>
</template>

<style scoped>
.wx-configurator-request {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-configurator-request__meta {
  margin: 0;
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-configurator-request__list {
  display: grid;
  grid-template-columns: minmax(0, max-content) minmax(0, 1fr);
  gap: var(--wx-space-4) var(--wx-space-16);
  margin: 0;
}

.wx-configurator-request__list dt {
  color: var(--wx-text-muted);
  overflow-wrap: anywhere;
}

.wx-configurator-request__list dd {
  margin: 0;
  overflow-wrap: anywhere;
}

.wx-configurator-request__lines {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  margin: 0;
  padding: var(--wx-space-12) 0 0;
  border-top: 1px solid var(--wx-border-default);
  list-style: none;
  font-size: var(--wx-font-size-sm);
}

.wx-configurator-request__line {
  display: flex;
  justify-content: space-between;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-configurator-request__line.is-discount {
  color: var(--wx-color-success);
}

.wx-configurator-request__amount {
  flex: none;
  font-variant-numeric: tabular-nums;
}

.wx-configurator-request__total {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: var(--wx-space-8);
  margin: 0;
}

.wx-configurator-request__before {
  color: var(--wx-text-muted);
}
</style>
