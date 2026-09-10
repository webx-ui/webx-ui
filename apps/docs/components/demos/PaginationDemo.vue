<script setup lang="ts">
import { computed, ref } from 'vue'
import { WxPagination } from '@webx-ui/core'
import type { Paginated } from '@webx-ui/core'

const page = ref(1)
const perPage = ref(15)
const short = ref(2)
const small = ref(2)

const paginator = computed<Paginated<unknown>>(() => {
  const total = 128
  const lastPage = Math.ceil(total / perPage.value)
  const current = Math.min(page.value, lastPage)
  const from = (current - 1) * perPage.value + 1

  return {
    data: [],
    current_page: current,
    last_page: lastPage,
    per_page: perPage.value,
    total,
    from,
    to: Math.min(total, current * perPage.value),
  }
})
</script>

<template>
  <div class="wx-demo">
    <span class="wx-demo__label">Driven by a paginator, with a page-size control</span>
    <wx-pagination
      v-model:page="page"
      v-model:per-page="perPage"
      :paginator="paginator"
      :per-page-options="[15, 30, 50]"
    />
  </div>

  <div class="wx-demo wx-demo--stack demo-rows">
    <div>
      <span class="wx-demo__label">Few pages, no ellipsis to hide behind</span>
      <wx-pagination v-model:page="short" :total="42" :per-page="10" />
    </div>

    <div>
      <span class="wx-demo__label">Small</span>
      <wx-pagination v-model:page="small" :total="42" :per-page="10" size="sm" />
    </div>

    <div>
      <span class="wx-demo__label">Without the count</span>
      <wx-pagination :total="42" :per-page="10" :page="2" :show-total="false" />
    </div>

    <div>
      <span class="wx-demo__label">Disabled</span>
      <wx-pagination :total="42" :per-page="10" :page="2" disabled />
    </div>
  </div>
</template>

<style scoped>
/*
 * Demo layout only. Stacked one under another, five paginations of different widths
 * each pinned to the right edge step down the page and are awkward to compare, so the
 * counts get a column of their own and every button group starts in the same place —
 * including the row that has no count to hold the column open.
 *
 * The component's own layout is count on the left, controls on the right; that one is
 * under the table on the Table page.
 */
.demo-rows :deep(.wx-pagination) {
  display: grid;
  grid-template-columns: 120px 1fr;
  align-items: center;
}

.demo-rows :deep(.wx-pagination__total) {
  grid-column: 1;
}

.demo-rows :deep(.wx-pagination__controls) {
  grid-column: 2;
  margin-left: 0;
}
</style>
