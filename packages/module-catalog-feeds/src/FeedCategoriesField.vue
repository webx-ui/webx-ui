<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue'
import {
  toast,
  WxAlert,
  WxAutocomplete,
  WxButton,
  WxInput,
  WxSpace,
  WxText,
  type AutocompleteOption,
} from '@webx-ui/core'
import {
  useAdmin,
  useChunkedUpload,
  useErrorText,
  useTranslate,
  WxDate,
} from '@webx-ui/module-admin'
import { useFeedEditor } from './editor'
import { NAMESPACE, useCatalogFeedsMessages } from './i18n'
import type { MappedCategories, MappedCategory, TaxonomyState } from './types'

/**
 * `wx-catalog-feed-categories`: our tree against a marketplace's list (§6.2 of the feeds spec). A
 * category holds a line of the list or a value typed by hand; with neither it takes after its
 * nearest ancestor, shown grey, so a whole branch is mapped at its top.
 *
 * Each row is written at once, like the rows of any list: a mapping lives beside the feed, not in
 * its form. The list itself comes by its address, through the queue, or as a file.
 */
defineOptions({ name: 'WxCatalogFeedCategories' })

const UPLOAD_PURPOSE = 'catalog.feed-taxonomy'

const POLL = 3000

const admin = useAdmin()
const editor = useFeedEditor()
const upload = useChunkedUpload({ admin })
useCatalogFeedsMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const feedId = computed(() => editor.feed.value?.id ?? null)
const taxonomyKey = computed(() => editor.values.value.taxonomy)
const locale = computed(() => editor.values.value.locale)
const locked = computed(() => editor.locked.value)

const mapped = ref<MappedCategories | null>(null)
const taxonomy = ref<TaxonomyState | null>(null)
const failed = ref('')
const importing = ref(false)
const fileInput = useTemplateRef<HTMLInputElement>('file')

const options = ref<Record<number, AutocompleteOption[]>>({})
const terms = ref<Record<number, string>>({})
const manual = ref<Record<number, boolean>>({})

let timer: ReturnType<typeof setTimeout> | undefined

const depths = computed(() => {
  const rows = mapped.value?.data ?? []
  const parents = new Map(rows.map((row) => [row.id, row.parent_id]))
  const found = new Map<number, number>()

  for (const row of rows) {
    let depth = 0
    let parent = row.parent_id

    while (parent !== null && parent !== undefined && depth < 32) {
      depth++
      parent = parents.get(parent) ?? null
    }

    found.set(row.id, depth)
  }

  return found
})

const waiting = computed(
  () => taxonomy.value?.status === 'queued' || taxonomy.value?.status === 'running',
)

function take(answer: MappedCategories): void {
  mapped.value = answer
  if (answer.taxonomy) taxonomy.value = answer.taxonomy
}

async function load(): Promise<void> {
  const id = feedId.value

  if (id === null) return

  failed.value = ''

  try {
    take(await editor.api.categories(id))
  } catch (error) {
    failed.value = message(error)
  }
}

/** The saved feed answers about the saved list; a list chosen just now is asked about by itself. */
async function readTaxonomy(): Promise<void> {
  const key = taxonomyKey.value

  clearTimeout(timer)

  if (key === null) {
    taxonomy.value = null

    return
  }

  try {
    taxonomy.value = (await editor.api.taxonomySearch(key, locale.value, '')).taxonomy
  } catch (error) {
    failed.value = message(error)
  }

  if (waiting.value) {
    timer = setTimeout(() => void readTaxonomy(), POLL)
  } else if (importing.value) {
    importing.value = false
    await load()
  }
}

onMounted(() => {
  void load()
  void readTaxonomy()
})

watch([feedId], () => void load())
watch([taxonomyKey, locale], () => void readTaxonomy())

onBeforeUnmount(() => clearTimeout(timer))

async function importByAddress(): Promise<void> {
  const key = taxonomyKey.value

  if (key === null) return

  importing.value = true

  try {
    taxonomy.value = (await editor.api.taxonomyImport(key, { locale: locale.value })).taxonomy
    await readTaxonomy()
  } catch (error) {
    importing.value = false
    toast.danger(message(error))
  }
}

async function importFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  const key = taxonomyKey.value

  input.value = ''

  if (!file || key === null) return

  try {
    const id = await upload.start(file, UPLOAD_PURPOSE)

    if (id === null) return

    taxonomy.value = (
      await editor.api.taxonomyImport(key, { locale: locale.value, upload: id })
    ).taxonomy
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function find(row: MappedCategory, q: string): Promise<void> {
  const key = taxonomyKey.value

  if (key === null) return

  try {
    const answer = await editor.api.taxonomySearch(key, locale.value, q)

    options.value = {
      ...options.value,
      [row.id]: answer.data.map((item) => ({
        value: String(item.id),
        label: item.path,
        description: item.external_id,
      })),
    }
  } catch (error) {
    toast.danger(message(error))
  }
}

async function write(
  row: MappedCategory,
  mapping: { taxonomy_item_id?: number | null; value?: string | null },
): Promise<void> {
  const id = feedId.value

  if (id === null) return

  try {
    take(await editor.api.mapCategories(id, [{ category_id: row.id, ...mapping }]))
    terms.value = { ...terms.value, [row.id]: '' }
  } catch (error) {
    toast.danger(message(error))
  }
}

function typed(row: MappedCategory, text: string): void {
  const value = text.trim()

  void write(row, { value: value === '' ? null : value })
}

const shownOf = (row: MappedCategory) => row.label ?? row.value ?? ''
const inheritedOf = (row: MappedCategory) => row.inherited?.label ?? row.inherited?.value ?? ''
const held = (row: MappedCategory) => row.value !== null || row.taxonomy_item_id !== null

/*
 * A list can be there before the feed has been saved with it: what a row can be mapped to is
 * decided by the form, whatever the saved feed still says.
 */
const unsavedList = computed(() => taxonomyKey.value !== (editor.feed.value?.taxonomy ?? null))
</script>

<template>
  <div class="wx-catalog-feed-categories">
    <wx-alert v-if="failed" type="danger" variant="soft" :description="failed" />

    <wx-text v-if="taxonomyKey === null" size="sm" tone="muted">
      {{ t('panel.categories-no-list') }}
    </wx-text>

    <template v-else>
      <div class="wx-catalog-feed-categories__list">
        <wx-text v-if="taxonomy && taxonomy.items > 0" size="sm" tone="muted">
          {{ t('panel.taxonomy-items', { count: String(taxonomy.items) }) }}
          <template v-if="taxonomy.version"> · {{ taxonomy.version }}</template>
          <template v-if="taxonomy.imported_at">
            · <wx-date :value="taxonomy.imported_at" />
          </template>
        </wx-text>
        <wx-text v-else size="sm" tone="muted">{{ t('panel.taxonomy-empty') }}</wx-text>

        <wx-space v-if="!locked" size="sm" wrap>
          <wx-button size="sm" variant="outline" :loading="waiting" @click="importByAddress">
            {{ t('panel.taxonomy-import') }}
          </wx-button>
          <wx-button size="sm" variant="outline" icon="upload" @click="fileInput?.click()">
            {{ t('panel.taxonomy-file') }}
          </wx-button>
          <input ref="file" type="file" accept=".txt" hidden @change="importFile" />
        </wx-space>
      </div>

      <wx-alert
        v-if="taxonomy?.fallback"
        type="info"
        variant="soft"
        :description="t('panel.taxonomy-fallback')"
      />

      <wx-alert
        v-if="taxonomy?.status === 'failed' && taxonomy.error"
        type="danger"
        variant="soft"
        :description="taxonomy.error"
      />
    </template>

    <wx-text v-if="feedId === null" size="sm" tone="muted">{{ t('panel.after-save') }}</wx-text>

    <wx-text v-else-if="unsavedList" size="sm" tone="muted">
      {{ t('panel.categories-unsaved') }}
    </wx-text>

    <template v-else-if="taxonomyKey !== null && mapped">
      <wx-text v-if="mapped.unmapped > 0" size="sm" tone="muted">
        {{ t('panel.categories-unmapped', { count: String(mapped.unmapped) }) }}
      </wx-text>

      <div class="wx-catalog-feed-categories__tree">
        <div
          v-for="row in mapped.data"
          :key="row.id"
          class="wx-catalog-feed-categories__row"
          :style="{ '--wx-catalog-feed-depth': depths.get(row.id) ?? 0 }"
        >
          <wx-text class="wx-catalog-feed-categories__name" size="sm">{{ row.name }}</wx-text>

          <div class="wx-catalog-feed-categories__value">
            <template v-if="held(row)">
              <wx-text class="wx-catalog-feed-categories__held" size="sm">
                {{ shownOf(row) }}
              </wx-text>
              <wx-button
                v-if="!locked"
                icon="x"
                variant="text"
                size="sm"
                :aria-label="t('panel.categories-clear')"
                @click="write(row, { taxonomy_item_id: null, value: null })"
              />
            </template>

            <template v-else>
              <wx-input
                v-if="manual[row.id]"
                size="sm"
                :disabled="locked"
                :placeholder="t('panel.categories-manual')"
                :aria-label="t('panel.categories-manual')"
                @change="(text: string) => typed(row, text)"
              />
              <wx-autocomplete
                v-else
                v-model="terms[row.id]"
                :options="options[row.id] ?? []"
                remote
                size="sm"
                :min-length="2"
                :disabled="locked"
                :placeholder="inheritedOf(row) || t('panel.categories-search')"
                :aria-label="`${row.name}: ${t('panel.categories-search')}`"
                @search="(q: string) => find(row, q)"
                @select="
                  (option: AutocompleteOption) =>
                    write(row, { taxonomy_item_id: Number(option.value) })
                "
              />
              <wx-button
                v-if="!locked"
                variant="text"
                size="sm"
                @click="manual = { ...manual, [row.id]: !manual[row.id] }"
              >
                {{ t(manual[row.id] ? 'panel.categories-from-list' : 'panel.categories-by-hand') }}
              </wx-button>
            </template>

            <wx-text
              v-if="!held(row) && inheritedOf(row)"
              class="wx-catalog-feed-categories__inherited"
              size="sm"
              tone="muted"
            >
              {{ t('panel.categories-inherited', { value: inheritedOf(row) }) }}
            </wx-text>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.wx-catalog-feed-categories {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
  container-type: inline-size;
}

.wx-catalog-feed-categories__list {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
}

.wx-catalog-feed-categories__tree {
  display: flex;
  flex-direction: column;
}

.wx-catalog-feed-categories__row {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--wx-space-4);
  padding-block: var(--wx-space-8);
  border-top: 1px solid var(--wx-border-muted);
}

@container (min-width: 640px) {
  .wx-catalog-feed-categories__row {
    grid-template-columns: minmax(0, 2fr) minmax(0, 3fr);
    gap: var(--wx-space-12);
    align-items: center;
  }
}

/* A step of the tree: only the name moves, so the values stay one column. */
.wx-catalog-feed-categories__name {
  padding-inline-start: calc(var(--wx-catalog-feed-depth, 0) * var(--wx-space-16));
  overflow-wrap: anywhere;
}

.wx-catalog-feed-categories__value {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-4) var(--wx-space-8);
  min-width: 0;
}

.wx-catalog-feed-categories__value > :deep(.wx-autocomplete),
.wx-catalog-feed-categories__value > :deep(.wx-input) {
  flex: 1 1 200px;
  min-width: 0;
}

.wx-catalog-feed-categories__held {
  flex: 1 1 200px;
  min-width: 0;
  overflow-wrap: anywhere;
}

.wx-catalog-feed-categories__inherited {
  flex: 1 1 100%;
  min-width: 0;
  overflow-wrap: anywhere;
}
</style>
