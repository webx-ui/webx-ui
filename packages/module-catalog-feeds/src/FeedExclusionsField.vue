<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import {
  toast,
  WxAction,
  WxAutocomplete,
  WxTable,
  WxText,
  type AutocompleteOption,
  type TableColumn,
  type TableState,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useRemoveConfirm, useTranslate } from '@webx-ui/module-admin'
import { createCatalogApi } from '@webx-ui/module-catalog'
import { useFeedEditor } from './editor'
import { NAMESPACE, useCatalogFeedsMessages } from './i18n'
import type { ExcludedProduct, ExclusionsPage } from './types'

/**
 * `wx-catalog-feed-exclusions`: the products taken out of this feed by hand (§5.2 of the feeds spec).
 * They stay in the others. The list is rows of a table of its own, because it gets long, and each
 * change is written at once, as it is on any list; the file follows with the next build.
 */
defineOptions({ name: 'WxCatalogFeedExclusions' })

const admin = useAdmin()
const catalog = createCatalogApi(admin)
const editor = useFeedEditor()
useCatalogFeedsMessages()

const t = useTranslate(NAMESPACE)
const panel = useTranslate('webx-admin')
const askRemove = useRemoveConfirm()
const message = useErrorText()

const feedId = computed(() => editor.feed.value?.id ?? null)

const page = ref<ExclusionsPage | null>(null)
const loading = ref(false)
const search = ref('')
const pageNumber = ref(1)

const term = ref('')
const options = ref<AutocompleteOption[]>([])
const searching = ref(false)

let asked = 0

async function load(): Promise<void> {
  const id = feedId.value

  if (id === null) return

  const ticket = ++asked

  loading.value = true

  try {
    const answer = await editor.api.exclusions(id, search.value, pageNumber.value)

    if (ticket === asked) page.value = answer
  } catch (error) {
    if (ticket === asked) toast.danger(message(error))
  } finally {
    if (ticket === asked) loading.value = false
  }
}

function onState(state: TableState): void {
  pageNumber.value = state.page
  search.value = state.search ?? ''
  void load()
}

let found = 0

async function find(q: string): Promise<void> {
  const ticket = ++found

  searching.value = true

  try {
    const answer = await catalog.products({ q, per_page: 10 })

    if (ticket !== found) return

    options.value = answer.data.map((product) => ({
      value: String(product.id),
      label: product.name,
      description: product.sku ?? undefined,
    }))
  } catch (error) {
    if (ticket === found) toast.danger(message(error))
  } finally {
    if (ticket === found) searching.value = false
  }
}

async function add(option: AutocompleteOption): Promise<void> {
  const id = feedId.value

  if (id === null) return

  options.value = []

  try {
    await editor.api.exclude(id, [Number(option.value)])
    // The list writes what was picked into the field after this event, so it is cleared after it.
    await nextTick()
    term.value = ''
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function bringBack(row: ExcludedProduct): Promise<void> {
  const id = feedId.value

  if (id === null) return

  try {
    await editor.api.include(id, row.id)
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

const columns = computed<TableColumn<ExcludedProduct>[]>(() => [
  { key: 'name', label: t('panel.exclusions-name'), minWidth: 200 },
  { key: 'sku', label: t('panel.exclusions-sku'), width: 160, hideBelow: 480 },
  { key: 'actions', label: '', width: 56, align: 'right' },
])
</script>

<template>
  <div class="wx-catalog-feed-exclusions">
    <wx-text v-if="feedId === null" size="sm" tone="muted">{{ t('panel.after-save') }}</wx-text>

    <template v-else>
      <wx-text size="sm" tone="muted">{{ t('panel.exclusions-help') }}</wx-text>

      <wx-autocomplete
        v-if="!editor.locked.value"
        v-model="term"
        :options="options"
        remote
        :loading="searching"
        :min-length="1"
        clearable
        :placeholder="t('panel.exclusions-add')"
        :aria-label="t('panel.exclusions-add')"
        @search="find"
        @select="add"
      />

      <wx-table
        :loading-text="panel('filters.loading')"
        :data="page ?? []"
        :columns="columns"
        row-key="id"
        searchable
        flush
        layout="fixed"
        :loading="loading"
        :empty-text="t('panel.exclusions-empty')"
        :aria-label="t('panel.exclusions')"
        @state-change="onState"
      >
        <template #cell-actions="{ row }">
          <wx-action
            v-if="!editor.locked.value"
            type="remove"
            v-bind="askRemove"
            @click="bringBack(row)"
          />
        </template>
      </wx-table>
    </template>
  </div>
</template>

<style scoped>
.wx-catalog-feed-exclusions {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}
</style>
