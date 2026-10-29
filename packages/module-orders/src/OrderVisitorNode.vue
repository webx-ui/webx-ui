<script setup lang="ts">
import { computed } from 'vue'
import { WxButton, WxSpace, WxText } from '@webx-ui/core'
import { useTranslate } from '@webx-ui/module-admin'
import { useOrderCard } from './card'
import { NAMESPACE, useOrdersMessages } from './i18n'

/**
 * `wx-orders-visitor` — whose account the order is in (spec §13.3), on the tab «Buyer»: the
 * visitor with «Link to another» and «Unlink», or «Placed as a guest» with «Link to a visitor».
 * Patched onto `orders.show` only where the visitors module is installed; the buttons are there
 * for whoever may manage orders, the search behind «Link» for whoever may read customers too.
 */
defineOptions({ name: 'WxOrdersVisitor' })

useOrdersMessages()

const t = useTranslate(NAMESPACE)
const card = useOrderCard()
const order = computed(() => card?.order.value ?? null)
const visitor = computed(() => order.value?.visitor ?? null)
const canLink = computed(() => order.value?.actions.visitor_search ?? false)
const canUnlink = computed(() => order.value?.actions.visitor ?? false)
</script>

<template>
  <div v-if="order" class="wx-orders-visitor">
    <div class="wx-orders-visitor__who">
      <template v-if="visitor">
        <span class="wx-orders-visitor__name">
          {{ visitor.name ?? t('panel.visitor-gone', { id: visitor.id }) }}
        </span>
        <wx-text v-if="visitor.email" size="sm" tone="muted" class="wx-orders-visitor__email">
          {{ visitor.email }}
        </wx-text>
      </template>
      <wx-text v-else tone="muted">{{ t('panel.visitor-guest') }}</wx-text>
    </div>

    <wx-space v-if="canLink || (visitor && canUnlink)" :size="8" wrap>
      <wx-button v-if="canLink" size="sm" :disabled="card?.busy.value" @click="card?.linkVisitor()">
        {{ visitor ? t('panel.visitor-relink') : t('panel.visitor-link') }}
      </wx-button>
      <wx-button
        v-if="visitor && canUnlink"
        size="sm"
        variant="text"
        :disabled="card?.busy.value"
        @click="card?.unlinkVisitor()"
      >
        {{ t('panel.visitor-unlink') }}
      </wx-button>
    </wx-space>
    <wx-text v-else-if="canUnlink" size="sm" tone="muted">{{
      t('panel.visitor-no-search')
    }}</wx-text>
  </div>
</template>

<style scoped>
.wx-orders-visitor {
  display: grid;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-orders-visitor__who {
  display: grid;
  gap: var(--wx-space-2);
  min-width: 0;
}

.wx-orders-visitor__name {
  color: var(--wx-text-strong);
  font-weight: var(--wx-font-weight-semibold);
  overflow-wrap: anywhere;
}

.wx-orders-visitor__email {
  overflow-wrap: anywhere;
}
</style>
