<script setup lang="ts">
import { computed, ref } from 'vue'
import { WxPagination } from '@webx-ui/core'
import type { Paginated } from '@webx-ui/core'

const page = ref(1)
const perPage = ref(15)
const short = ref(2)

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
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Driven by a paginator</span>
      <wx-pagination
        v-model:page="page"
        v-model:per-page="perPage"
        :paginator="paginator"
        :per-page-options="[15, 30, 50]"
      />
    </div>

    <div>
      <span class="wx-demo__label">Few pages, no ellipsis to hide behind</span>
      <wx-pagination v-model:page="short" :total="42" :per-page="10" :show-total="false" />
    </div>

    <div>
      <span class="wx-demo__label">Small</span>
      <wx-pagination :total="60" :per-page="10" :page="2" size="sm" :show-total="false" />
    </div>

    <div>
      <span class="wx-demo__label">Disabled</span>
      <wx-pagination :total="60" :per-page="10" :page="2" disabled :show-total="false" />
    </div>
  </div>
</template>
