<script setup lang="ts">
import { computed } from 'vue'
import { WxBadge, WxButton, WxCard, WxEmpty, WxIcon, WxText } from '@webx-ui/core'
import { useTranslate } from '@webx-ui/module-admin'
import { useOrderCard } from './card'
import { NAMESPACE, useOrdersMessages } from './i18n'
import type { OrderLine } from './types'

/**
 * `wx-orders-items` — «Items» of the card (spec §11.3): the lines as the cart had them, a set
 * with its parts under it, each line's discounts beside it; then what applies to the order as a
 * whole — its discounts and charges, the tenders, the privileges as marks. A block whose owner
 * has no tab of its own on this card is here too, its line and the block itself folded.
 *
 * Over the lines, «Change the lines» (§42.1) for whoever works the order — greyed with why once
 * the order is past editing (§37.2) — and a line added by an edit is marked so.
 */
defineOptions({ name: 'WxOrdersItems' })

useOrdersMessages()

const t = useTranslate(NAMESPACE)
const card = useOrderCard()
const order = computed(() => card?.order.value ?? null)

/** The lines with their depth, flat: a set is one level deep, so no recursion in the template. */
const lines = computed(() => {
  const flat: { line: OrderLine; depth: number }[] = []
  const walk = (items: OrderLine[], depth: number) => {
    for (const line of items) {
      flat.push({ line, depth })
      walk(line.children, depth + 1)
    }
  }

  walk(order.value?.items ?? [], 0)

  return flat
})

const json = (value: unknown) => JSON.stringify(value, null, 2)

const editable = computed(() => Boolean(order.value?.actions.edit_items))
const editReason = computed(() => order.value?.actions.edit_items_reason ?? null)
</script>

<template>
  <div v-if="order" class="wx-orders-items">
    <div v-if="editable" class="wx-orders-items__bar">
      <wx-text v-if="editReason" size="sm" tone="muted" class="wx-orders-items__why">
        {{ editReason }}
      </wx-text>
      <wx-button
        size="sm"
        variant="outline"
        icon="edit"
        :disabled="Boolean(editReason) || card?.busy.value"
        data-test="edit-items"
        @click="card?.editItems()"
      >
        {{ t('panel.items-edit') }}
      </wx-button>
    </div>

    <wx-card class="wx-orders-items__lines">
      <wx-empty v-if="lines.length === 0" :title="t('panel.items-empty')" size="sm" />

      <ul v-else class="wx-orders-items__list">
        <li
          v-for="{ line, depth } in lines"
          :key="line.id"
          class="wx-orders-items__line"
          :class="{ 'is-child': depth > 0 }"
        >
          <div class="wx-orders-items__what">
            <span class="wx-orders-items__name">
              <wx-icon v-if="depth > 0" name="chevron-right" size="sm" />
              <wx-text weight="medium">{{ line.name }}</wx-text>
              <wx-badge v-if="line.label" size="sm" variant="soft">{{ line.label }}</wx-badge>
              <wx-badge
                v-if="line.generated && !line.label && depth === 0"
                size="sm"
                type="success"
                variant="soft"
              >
                {{ t('panel.items-gift') }}
              </wx-badge>
              <wx-badge
                v-if="(line.revision ?? 0) > 0 && depth === 0"
                size="sm"
                type="primary"
                variant="soft"
              >
                {{ t('panel.items-added') }}
              </wx-badge>
              <wx-badge v-if="line.removed" size="sm" type="danger" variant="soft">
                {{ t('panel.items-removed') }}
              </wx-badge>
            </span>
            <wx-text v-if="line.sku || line.options" size="sm" tone="muted">
              {{ [line.sku, line.options].filter(Boolean).join(' · ') }}
            </wx-text>
            <wx-text
              v-for="(discount, index) in line.discounts"
              :key="index"
              size="sm"
              tone="muted"
            >
              {{ discount.label }}: {{ discount.amount }}
            </wx-text>
            <wx-text v-if="line.refunded_qty > 0" size="sm" tone="warning">
              {{ t('panel.refunded-qty', { count: line.refunded_qty }) }}
            </wx-text>
          </div>

          <div class="wx-orders-items__money">
            <wx-text size="sm" tone="muted">{{ line.price }} × {{ line.qty }}</wx-text>
            <wx-text weight="medium">{{ line.total }}</wx-text>
          </div>
        </li>
      </ul>
    </wx-card>

    <wx-card
      v-if="order.adjustments.length > 0 || order.tenders.length > 0 || order.benefits.length > 0"
    >
      <dl class="wx-orders-items__rows">
        <template v-for="adjustment in order.adjustments" :key="`a${adjustment.id}`">
          <dt>{{ adjustment.label }}</dt>
          <dd>{{ adjustment.amount }}</dd>
        </template>
        <template v-for="tender in order.tenders" :key="`t${tender.id}`">
          <dt>{{ tender.label }}</dt>
          <dd>
            −{{ tender.amount }}
            <wx-text v-if="tender.refunded" size="sm" tone="muted">
              ({{ t('panel.tender-returned', { amount: tender.refunded }) }})
            </wx-text>
          </dd>
        </template>
      </dl>

      <div v-if="order.benefits.length > 0" class="wx-orders-items__benefits">
        <wx-badge v-for="benefit in order.benefits" :key="benefit.key" type="success" size="sm">
          {{ benefit.label }}
        </wx-badge>
      </div>
    </wx-card>

    <wx-card v-for="block in order.blocks" :key="block.key" :title="t(`panel.block-${block.key}`)">
      <wx-text>{{ block.summary ?? '—' }}</wx-text>
      <details class="wx-orders-items__raw">
        <summary>{{ t('panel.block-raw') }}</summary>
        <pre>{{ json(block.value) }}</pre>
      </details>
    </wx-card>
  </div>
</template>

<style scoped>
.wx-orders-items {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
}

.wx-orders-items__bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: var(--wx-space-8) var(--wx-space-12);
}

.wx-orders-items__bar > :last-child {
  flex: none;
  margin-inline-start: auto;
}

.wx-orders-items__why {
  flex: 1 1 240px;
  min-width: 0;
  overflow-wrap: anywhere;
}

.wx-orders-items__list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-orders-items__line {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--wx-space-16);
  padding-block: var(--wx-space-10);
  border-top: 1px solid var(--wx-border-muted);
}

.wx-orders-items__line:first-child {
  padding-top: 0;
  border-top: 0;
}

.wx-orders-items__line:last-child {
  padding-bottom: 0;
}

.wx-orders-items__line.is-child {
  padding-inline-start: var(--wx-space-24);
  border-top-style: dashed;
}

.wx-orders-items__what {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2, 2px);
  min-width: 0;
}

.wx-orders-items__name {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
  overflow-wrap: anywhere;
}

.wx-orders-items__money {
  display: flex;
  flex: none;
  flex-direction: column;
  align-items: flex-end;
  text-align: end;
  white-space: nowrap;
}

.wx-orders-items__rows {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: var(--wx-space-8) var(--wx-space-16);
  margin: 0;
}

.wx-orders-items__rows dt {
  min-width: 0;
  color: var(--wx-text-muted);
  overflow-wrap: anywhere;
}

.wx-orders-items__rows dd {
  margin: 0;
  text-align: end;
  white-space: nowrap;
}

.wx-orders-items__benefits {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-6);
  margin-top: var(--wx-space-12);
}

.wx-orders-items__rows:empty + .wx-orders-items__benefits {
  margin-top: 0;
}

.wx-orders-items__raw {
  margin-top: var(--wx-space-8);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.wx-orders-items__raw pre {
  overflow-x: auto;
  margin: var(--wx-space-8) 0 0;
  padding: var(--wx-space-8);
  border-radius: var(--wx-radius-xs);
  background: var(--wx-bg-muted);
  font-family: var(--wx-font-family-mono);
}
</style>
