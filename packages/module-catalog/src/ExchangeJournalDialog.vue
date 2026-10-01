<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import {
  createHistoryApi,
  useAdmin,
  useErrorText,
  useTranslate,
  type HistoryEntry,
} from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxButton,
  WxDialog,
  WxEmpty,
  WxLink,
  WxSkeleton,
  WxText,
} from '@webx-ui/core'
import { useCatalogMessages } from './i18n'

/**
 * What an import changed, as the journal has it (§1 п. 12 of the exchange spec): the run's rows,
 * one per product, with the fields each got. The record's own history links back to the run;
 * this is the other direction — from the run to the records.
 */
const props = defineProps<{ runId: number; base: string }>()

const { open, dismiss } = useModal<void>()

const admin = useAdmin()
const history = createHistoryApi(admin, 'catalog.product', 0)
useCatalogMessages()

const t = useTranslate('webx-catalog')
const message = useErrorText()

const rows = ref<HistoryEntry[] | null>(null)
const page = ref(1)
const last = ref(1)
const loading = ref(false)

async function load(next: number): Promise<void> {
  loading.value = true

  try {
    const answer = await history.run(props.runId, next)

    rows.value = next === 1 ? answer.rows.data : [...(rows.value ?? []), ...answer.rows.data]
    page.value = answer.rows.current_page
    last.value = answer.rows.last_page
  } catch (error) {
    toast.danger(message(error))
    rows.value ??= []
  } finally {
    loading.value = false
  }
}

onMounted(() => void load(1))

function fields(row: HistoryEntry): string {
  return row.changes.map((change) => change.label).join(', ')
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('panel.exchange-journal-title')" :width="560">
    <wx-skeleton v-if="rows === null" :rows="5" />
    <wx-empty v-else-if="rows.length === 0" :description="t('panel.exchange-journal-empty')" />
    <ul v-else class="wx-catalog-journal">
      <li v-for="row in rows" :key="row.id" class="wx-catalog-journal__row">
        <wx-link
          v-if="row.subject.id !== null"
          :as="RouterLink"
          :to="`${base}/products/${row.subject.id}`"
          class="wx-catalog-journal__subject"
          @click="dismiss()"
        >
          #{{ row.subject.id }}
        </wx-link>
        <wx-text size="sm" tone="muted" class="wx-catalog-journal__fields">
          {{ fields(row) }}
        </wx-text>
      </li>
    </ul>

    <template v-if="rows !== null && page < last" #footer>
      <wx-button variant="outline" :loading="loading" @click="load(page + 1)">
        {{ t('panel.exchange-journal-more') }}
      </wx-button>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-catalog-journal {
  display: grid;
  gap: var(--wx-space-8);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-catalog-journal__row {
  display: flex;
  align-items: baseline;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-catalog-journal__subject {
  flex: none;
  font-variant-numeric: tabular-nums;
}

.wx-catalog-journal__fields {
  min-width: 0;
}
</style>
