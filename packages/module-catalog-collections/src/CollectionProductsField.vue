<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import {
  WxAutocomplete,
  WxBadge,
  WxButton,
  WxIcon,
  WxPopconfirm,
  WxSortableList,
  WxText,
  type AutocompleteOption,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useRemoveConfirm, useTranslate } from '@webx-ui/module-admin'
import { createCatalogApi } from '@webx-ui/module-catalog'
import { useCollectionEditor } from './editor'
import { NAMESPACE, useCatalogCollectionsMessages } from './i18n'
import type { CollectionItem } from './types'

/**
 * `wx-catalog-collection-products`: the hand-picked list (§3.1), found by name or SKU and put in
 * order by dragging. The field's value is the ids in their order; the names come with the
 * collection from the server and with each product found, so the list never asks for products one
 * by one. It stops at the ceiling, which the screen says in `max`.
 */
defineOptions({ name: 'WxCatalogCollectionProducts' })

const props = withDefaults(defineProps<{ max?: number; disabled?: boolean }>(), {
  max: 48,
  disabled: false,
})

const value = defineModel<number[] | null>({ default: null })

const admin = useAdmin()
const catalog = createCatalogApi(admin)
const editor = useCollectionEditor()
useCatalogCollectionsMessages()

const t = useTranslate(NAMESPACE)
const askRemove = useRemoveConfirm()
const panel = useTranslate('webx-admin')
const message = useErrorText()

const locked = computed(() => props.disabled || (editor?.locked.value ?? false))
const full = computed(() => (value.value ?? []).length >= props.max)

/** Every product this field has seen a name for: the saved ones and the ones found since. */
const known = ref(new Map<number, CollectionItem>())

watch(
  () => editor?.collection.value?.product_items,
  (items) => {
    for (const item of items ?? []) known.value.set(item.id, item)
  },
  { immediate: true },
)

const items = computed<CollectionItem[]>({
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

  // The box writes the option's value, an id here, into itself after this handler: emptied any
  // earlier, the id stays and prefixes the next search. The id rather than the name, because the
  // box keys its options by value and two products may share a name.
  void nextTick(() => {
    term.value = ''
  })

  if (!Number.isFinite(id) || full.value || (value.value ?? []).includes(id)) return

  value.value = [...(value.value ?? []), id]
  options.value = []
}

function drop(id: number): void {
  value.value = (value.value ?? []).filter((one) => one !== id)
}
</script>

<template>
  <div class="wx-catalog-collection-products">
    <wx-text v-if="full && !locked" size="sm" tone="muted">
      {{ t('collection.products-full', { max: props.max }) }}
    </wx-text>
    <wx-autocomplete
      v-else-if="!locked"
      v-model="term"
      :options="options"
      remote
      :loading="searching"
      :min-length="1"
      clearable
      :placeholder="t('collection.products-search')"
      :aria-label="t('collection.products-search')"
      @search="search"
      @select="pick"
    />
    <wx-text v-if="failed" size="sm" tone="danger">{{ failed }}</wx-text>

    <wx-sortable-list
      v-model="items"
      :drag-label="panel('filters.reorder')"
      item-key="id"
      item-label="name"
      size="sm"
      :disabled="locked"
      :empty-text="t('collection.products-empty')"
      :aria-label="t('collection.products')"
    >
      <template #default="{ item }">
        <span class="wx-catalog-collection-products__item">
          <span class="wx-catalog-collection-products__name">{{ item.name }}</span>
          <wx-text v-if="item.sku" size="sm" tone="muted">{{ item.sku }}</wx-text>
          <wx-badge v-if="item.deleted" size="sm" type="warning" round>
            {{ t('panel.in-bin') }}
          </wx-badge>
        </span>
      </template>
      <template #actions="{ item }">
        <wx-popconfirm
          v-if="!locked"
          :title="askRemove.confirm"
          :confirm-text="askRemove.confirmText"
          :cancel-text="askRemove.cancelText"
          confirm-type="danger"
          @confirm="drop(item.id)"
        >
          <template #trigger>
            <wx-button
              variant="text"
              size="sm"
              :aria-label="`${t('collection.products-remove')}: ${item.name}`"
            >
              <template #icon><wx-icon name="close" /></template>
            </wx-button>
          </template>
        </wx-popconfirm>
      </template>
    </wx-sortable-list>
  </div>
</template>

<style scoped>
.wx-catalog-collection-products {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-catalog-collection-products__item {
  display: flex;
  align-items: baseline;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-catalog-collection-products__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
