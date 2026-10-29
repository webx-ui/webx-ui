<script setup lang="ts">
/**
 * `wx-orders-layout` — the card of an order as two columns (spec §11.3): the tabs, and beside them
 * the side card of the totals (`summary`, in the slot `side`). A row of the screen is capped at a
 * field's width on purpose, so the columns of a page are a container of their own; they stand
 * side by side once the card is wide enough for both, decided by its width, not the window's.
 */
defineOptions({ name: 'WxOrdersLayout' })
</script>

<template>
  <div class="wx-orders-layout">
    <div class="wx-orders-layout__grid">
      <div class="wx-orders-layout__main"><slot /></div>
      <aside v-if="$slots.side" class="wx-orders-layout__side"><slot name="side" /></aside>
    </div>
  </div>
</template>

<style scoped>
.wx-orders-layout {
  container: wx-orders-layout / inline-size;
}

.wx-orders-layout__grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  align-items: start;
  gap: var(--wx-gap, var(--wx-space-16));
}

.wx-orders-layout__main,
.wx-orders-layout__side {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
}

@container wx-orders-layout (min-width: 900px) {
  .wx-orders-layout__grid {
    grid-template-columns: minmax(0, 1fr) 320px;
  }
}
</style>
