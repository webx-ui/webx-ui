<script setup lang="ts">
import { ref } from 'vue'
import type { RowKey, TabItem, TabValue } from '@webx-ui/core'
import RecordsTable from './RecordsTable.vue'

/**
 * The bench the table gets worked on.
 *
 * Deliberately the hardest list the panel has rather than a tidy one — fourteen columns, chips
 * that outgrow their cell, an unbroken UTM tail, prose in a cell, empty values everywhere — and
 * the list itself lives in `RecordsTable`, so the same one can be put in two different boxes.
 *
 * The second box is a drawer. What decides whether the table draws rows or cards is the width of
 * the table, not of the window (`cards-below` against its own measurement), and a drawer is the
 * cheapest way to see that: the same list, the same screen, a box half as wide.
 */
const view = ref<TabValue>('')
const selected = ref<RowKey[]>([])
const total = ref(0)

const drawerSelected = ref<RowKey[]>([])
const drawer = ref(false)

/** The views of the same list, as tabs over the card — the shape every section has (§19). */
const views: TabItem[] = [
  { value: '', label: 'Усі' },
  { value: 'published', label: 'Живі' },
  { value: 'scheduled', label: 'Заплановані' },
  { value: 'draft', label: 'Чернетки' },
  { value: 'unpublished', label: 'Зняті' },
]
</script>

<template>
  <div class="lab">
    <!--
      The frame every list of the panel is drawn in (`WxListScreen`, §19): the section's name on
      its own line with the one action the section exists for beside it, the views of the list as
      tabs under that, and a card holding nothing but the rows.
    -->
    <header class="lab__head">
      <div class="lab__title">
        <wx-heading :level="2">Записи</wx-heading>
        <wx-badge v-if="total" size="sm" round>{{ total }}</wx-badge>
      </div>

      <wx-space size="sm">
        <wx-button variant="outline" @click="drawer = true">
          <template #icon><wx-icon name="sidebar" /></template>
          У ящику
        </wx-button>
        <wx-button type="primary">
          <template #icon><wx-icon name="plus" /></template>
          Новий запис
        </wx-button>
      </wx-space>
    </header>

    <wx-alert v-if="selected.length > 0" type="info" class="lab__bulk">
      Вибрано {{ selected.length }}
      <template #actions>
        <wx-space size="sm">
          <wx-button size="sm" variant="outline">Опублікувати</wx-button>
          <wx-button size="sm" variant="text" type="danger">Видалити</wx-button>
        </wx-space>
      </template>
    </wx-alert>

    <!-- `items` rather than a tab each with its own panel: switching a view must not take the
         table away and put a new one back, or the search somebody typed goes with it. -->
    <wx-tabs v-model="view" class="lab__views" :items="views" aria-label="Записи">
      <wx-card>
        <records-table v-model:selected="selected" :view="view" @loaded="total = $event" />
      </wx-card>
    </wx-tabs>

    <!--
      The same table in a box 560px wide. Nothing is told to change shape: the table measures
      what it was given and drops columns until only the name and the state are left, and below
      640 the rows become cards — in a drawer on a desktop exactly as on a phone.
    -->
    <wx-drawer
      v-model:open="drawer"
      side="right"
      size="70%"
      :min-size="320"
      resizable
      persist="lab-records-drawer"
      closable
      title="Записи"
    >
      <records-table v-model:selected="drawerSelected" :view="view" :per-page="10" />
    </wx-drawer>
  </div>
</template>

<style scoped>
/* The step the panel is spaced by, which the shell declares and everything inherits. */
.lab {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  min-width: 0;
}

.lab__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-12);
}

.lab__title {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
}
</style>
