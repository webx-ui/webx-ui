<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { WxAction, WxAutocomplete, WxBadge, type AutocompleteOption } from '@webx-ui/core'
import { createRelationsApi, useAdmin, useTranslate } from '@webx-ui/module-admin'
import { NAMESPACE, useCatalogCollectionsMessages } from './i18n'
import type { CollectionCandidate } from './types'

/**
 * `wx-catalog-collection`: one collection, chosen by title and kept by its key, which is what the
 * tag takes and the block `product-collection` keeps (§4.2).
 *
 * The rows come from the panel's relation target `catalog-collection`: no permission of the section
 * is needed to put one on a page. The target searches the key too and puts the exact key first,
 * which is how a stored key is named when the field opens.
 *
 * A key nobody has stays in the field, marked «no such collection», which is also how one in the
 * bin reads (§4.4): dropping it silently would change the block without anybody touching it, and
 * restoring the collection brings it back.
 */
defineOptions({ name: 'WxCatalogCollectionField' })

const props = withDefaults(defineProps<{ disabled?: boolean; placeholder?: string }>(), {
  disabled: false,
  placeholder: undefined,
})

const value = defineModel<string | null>({ default: null })

useCatalogCollectionsMessages()

const admin = useAdmin()
const t = useTranslate(NAMESPACE)
const api = createRelationsApi(admin, 'catalog-collection')

const key = computed(() => (typeof value.value === 'string' ? value.value.trim() : ''))

/** Collections the server described, by key, kept for the life of the field. */
const known = ref(new Map<string, CollectionCandidate>())
/** Keys the server was asked about and has no collection for. */
const missing = ref(new Set<string>())

const candidates = ref<CollectionCandidate[]>([])
const searching = ref(false)
const term = ref('')

function remember(rows: CollectionCandidate[]): void {
  const next = new Map(known.value)

  for (const row of rows) {
    if (row.key) next.set(row.key, row)
  }

  known.value = next
}

async function name(wanted: string): Promise<void> {
  if (wanted === '' || known.value.has(wanted) || missing.value.has(wanted)) return

  try {
    const rows = (await api.search(wanted)) as CollectionCandidate[]
    remember(rows)

    if (!rows.some((row) => row.key === wanted)) missing.value = new Set([...missing.value, wanted])
  } catch {
    // A request that did not come back is not an answer: the key shows as it is until the next try.
  }
}

watch(key, (wanted) => void name(wanted), { immediate: true })

const chosen = computed(() => (key.value === '' ? null : (known.value.get(key.value) ?? null)))

/** A mark for the chosen one: the server has none by that key, or the site does not show it. */
const mark = computed(() => {
  if (key.value === '') return null
  if (chosen.value === null)
    return missing.value.has(key.value) ? t('collection.picker-missing') : null

  return chosen.value.visible ? null : t('collection.picker-off')
})

const options = computed<AutocompleteOption[]>(() =>
  candidates.value
    .filter((one) => one.key && one.key !== key.value)
    .map((one) => ({
      value: one.title,
      description: [one.key, one.visible ? null : t('collection.picker-off')]
        .filter((part): part is string => typeof part === 'string' && part !== '')
        .join(' · '),
      id: one.key,
    })),
)

async function search(query: string): Promise<void> {
  searching.value = true

  try {
    const found = (await api.search(query)) as CollectionCandidate[]
    candidates.value = found
    remember(found)
  } catch {
    candidates.value = []
  } finally {
    searching.value = false
  }
}

/** A site has a handful: the list is there on the first click, before anything is typed. */
function opened(): void {
  if (candidates.value.length === 0 && !searching.value) void search(term.value)
}

function pick(option: AutocompleteOption): void {
  if (typeof option.id !== 'string' || option.id === '') return

  value.value = option.id

  // The box writes the picked name into itself after this handler; emptied on the next tick it
  // reads as a search box again rather than as a second copy of the choice above it.
  void nextTick(() => {
    term.value = ''
  })
}

const placeholder = computed(() => props.placeholder ?? t('collection.picker-search'))
</script>

<template>
  <div class="wx-catalog-collection-picker">
    <div v-if="key !== ''" class="wx-catalog-collection-picker__chosen">
      <span class="wx-catalog-collection-picker__text">
        <span class="wx-catalog-collection-picker__title">{{ chosen?.title ?? key }}</span>
        <span v-if="chosen" class="wx-catalog-collection-picker__key">{{ key }}</span>
      </span>
      <wx-badge v-if="mark" type="warning">{{ mark }}</wx-badge>
      <wx-action
        v-if="!props.disabled"
        type="remove"
        size="sm"
        :title="t('collection.picker-remove')"
        @click="value = null"
      />
    </div>
    <p v-else class="wx-catalog-collection-picker__empty">{{ t('collection.picker-empty') }}</p>

    <!-- A wrapper rather than a class on the box: its root is a fragment, and a scoped rule on it
         would reach nothing. -->
    <div class="wx-catalog-collection-picker__search">
      <wx-autocomplete
        v-model="term"
        :options="options"
        remote
        teleport
        :loading="searching"
        :loading-text="t('collection.picker-searching')"
        :empty-text="t('collection.picker-nothing')"
        :placeholder="placeholder"
        :aria-label="placeholder"
        :disabled="props.disabled"
        @search="search"
        @open="opened"
        @select="pick"
      />
    </div>
  </div>
</template>

<style scoped>
.wx-catalog-collection-picker {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-catalog-collection-picker__chosen {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
  padding: var(--wx-space-6) var(--wx-space-8) var(--wx-space-6) var(--wx-space-12);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  background: var(--wx-bg-surface);
}

.wx-catalog-collection-picker__text {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  min-width: 0;
}

.wx-catalog-collection-picker__title,
.wx-catalog-collection-picker__key {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-catalog-collection-picker__key,
.wx-catalog-collection-picker__empty {
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-catalog-collection-picker__empty {
  margin: 0;
}

.wx-catalog-collection-picker :deep(.wx-badge) {
  flex: none;
}
</style>
