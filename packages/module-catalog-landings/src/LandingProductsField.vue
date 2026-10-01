<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  WxAutocomplete,
  WxBadge,
  WxButton,
  WxIcon,
  WxSortableList,
  WxText,
  type AutocompleteOption,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { createCatalogApi } from '@webx-ui/module-catalog'
import { useLandingEditor } from './editor'
import { NAMESPACE, useCatalogLandingsMessages } from './i18n'
import type { RecommendedItem } from './types'

/**
 * `wx-catalog-landing-products`: the recommended strip of a landing (decision 8) — products found
 * by name or SKU and put in order by dragging. The field's value is the ids in their order; the
 * names come with the landing from the server and with each product found, so the list never asks
 * for products one by one.
 */
defineOptions({ name: 'WxCatalogLandingProducts' })

const value = defineModel<number[] | null>({ default: null })

const admin = useAdmin()
const catalog = createCatalogApi(admin)
const editor = useLandingEditor()
useCatalogLandingsMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const locked = computed(() => editor?.locked.value ?? false)

/** Every product this field has seen a name for: the saved ones and the ones found since. */
const known = ref(new Map<number, RecommendedItem>())

watch(
  () => editor?.landing.value?.recommended_items,
  (items) => {
    for (const item of items ?? []) known.value.set(item.id, item)
  },
  { immediate: true },
)

const items = computed<RecommendedItem[]>({
  get: () =>
    (value.value ?? []).map(
      (id) => known.value.get(id) ?? { id, name: `#${id}`, sku: null, deleted: false },
    ),
  set: (next) => {
    value.value = next.map((item) => item.id)
  },
})

const term = ref('')
const options = ref<AutocompleteOption[]>([])
const searching = ref(false)
const failed = ref('')
let asked = 0

async function search(q: string): Promise<void> {
  const ticket = ++asked

  searching.value = true
  failed.value = ''

  try {
    const page = await catalog.products({ q, per_page: 10 })

    if (ticket !== asked) return

    const taken = new Set(value.value ?? [])

    options.value = page.data
      .filter((product) => !taken.has(product.id))
      .map((product) => {
        known.value.set(product.id, { id: product.id, name: product.name, sku: product.sku })

        return {
          value: String(product.id),
          label: product.name,
          description: product.sku ?? undefined,
        }
      })
  } catch (error) {
    if (ticket === asked) failed.value = message(error)
  } finally {
    if (ticket === asked) searching.value = false
  }
}

function pick(option: AutocompleteOption): void {
  const id = Number(option.value)

  if (!Number.isFinite(id) || (value.value ?? []).includes(id)) return

  value.value = [...(value.value ?? []), id]
  term.value = ''
  options.value = []
}

function drop(id: number): void {
  value.value = (value.value ?? []).filter((one) => one !== id)
}
</script>

<template>
  <div class="wx-catalog-landing-products">
    <wx-autocomplete
      v-if="!locked"
      v-model="term"
      :options="options"
      remote
      :loading="searching"
      :min-length="1"
      clearable
      :placeholder="t('landing.recommended-search')"
      :aria-label="t('landing.recommended-search')"
      @search="search"
      @select="pick"
    />
    <wx-text v-if="failed" size="sm" tone="danger">{{ failed }}</wx-text>

    <wx-sortable-list
      v-model="items"
      item-key="id"
      item-label="name"
      size="sm"
      :disabled="locked"
      :empty-text="t('landing.recommended-empty')"
      :aria-label="t('landing.recommended')"
    >
      <template #default="{ item }">
        <span class="wx-catalog-landing-products__item">
          <span class="wx-catalog-landing-products__name">{{ item.name }}</span>
          <wx-text v-if="item.sku" size="sm" tone="muted">{{ item.sku }}</wx-text>
          <wx-badge v-if="item.deleted" size="sm" type="warning" round>
            {{ t('panel.in-bin') }}
          </wx-badge>
        </span>
      </template>
      <template #actions="{ item }">
        <wx-button
          v-if="!locked"
          variant="text"
          size="sm"
          :aria-label="`${t('landing.recommended-remove')}: ${item.name}`"
          @click="drop(item.id)"
        >
          <template #icon><wx-icon name="close" /></template>
        </wx-button>
      </template>
    </wx-sortable-list>
  </div>
</template>

<style scoped>
.wx-catalog-landing-products {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-catalog-landing-products__item {
  display: flex;
  align-items: baseline;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-catalog-landing-products__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
