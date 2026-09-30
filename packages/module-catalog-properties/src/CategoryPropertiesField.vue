<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  toast,
  WxAlert,
  WxButton,
  WxSelect,
  WxSkeleton,
  WxSortableList,
  WxText,
  type SelectModelValue,
  type SelectOption,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { useCatalogCategoryEditor } from '@webx-ui/module-catalog'
import { createPropertiesApi } from './api'
import { propertyName } from './format'
import { NAMESPACE, useCatalogPropertiesMessages } from './i18n'
import type { CategorySet, PropertyRow } from './types'

/**
 * `wx-catalog-category-properties`: the «Properties» tab of a category (§7.2) — its set.
 *
 * The set is inherited (decision 5): at the top, muted, what the categories above give it, each
 * with where it comes from; below, what it adds itself — found by search, dragged into order,
 * taken away. An inherited one cannot be taken away here, and cannot be added again: it is taken
 * away where it was added.
 *
 * Saved at once, each change a request, like the gallery of a product: the set is a record of its
 * own, the products of the whole branch are marked for the index when it changes, and the
 * category's own button is about its fields.
 */
defineOptions({ name: 'WxCatalogCategoryProperties' })

const admin = useAdmin()
const api = createPropertiesApi(admin)
const editor = useCatalogCategoryEditor()
useCatalogPropertiesMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const locale = computed(() => admin.i18n.state.locale)
const category = computed(() => editor?.category.value?.id ?? null)
const locked = computed(() => (editor?.locked.value ?? true) || !admin.can('catalog.manage'))

const set = ref<CategorySet | null>(null)
const own = ref<PropertyRow[]>([])
const all = ref<PropertyRow[]>([])
const loading = ref(true)
const failed = ref(false)
const saving = ref(false)
const picked = ref<number | null>(null)

const taken = computed(
  () =>
    new Set([
      ...(set.value?.inherited ?? []).map((row) => row.property.id),
      ...own.value.map((row) => row.id),
    ]),
)

const offered = computed<SelectOption[]>(() =>
  all.value
    .filter((property) => !taken.value.has(property.id))
    .map((property) => ({ value: property.id, label: propertyName(property, locale.value) })),
)

function take(answer: CategorySet): void {
  set.value = answer
  own.value = [...answer.own]
}

async function load(): Promise<void> {
  if (category.value === null) return

  loading.value = true
  failed.value = false

  try {
    const [answer, page] = await Promise.all([api.categorySet(category.value), api.list()])

    take(answer)
    all.value = page.data
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

watch(category, () => void load(), { immediate: true })

/* A category moved under another inherits another branch's set; the editor hands the move over. */
watch(
  () => editor?.category.value?.parent_id,
  (now, before) => {
    if (before !== undefined && now !== before) void load()
  },
)

async function save(ids: number[]): Promise<void> {
  if (category.value === null) return

  saving.value = true

  try {
    take(await api.saveCategorySet(category.value, ids))
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body
    const first = body?.errors ? Object.values(body.errors)[0]?.[0] : undefined

    toast.danger(first ?? message(error))
    // Back to what the server has: the screen must not disagree with the set.
    if (set.value) own.value = [...set.value.own]
  } finally {
    saving.value = false
  }
}

function add(value: SelectModelValue): void {
  if (typeof value !== 'number') return

  picked.value = null
  void save([...own.value.map((row) => row.id), value])
}

function remove(id: number): void {
  void save(own.value.filter((row) => row.id !== id).map((row) => row.id))
}

function reorder(): void {
  void save(own.value.map((row) => row.id))
}

const typeOf = (row: PropertyRow) => t(`property.types.${row.type}`)
</script>

<template>
  <div class="wx-catalog-category-properties">
    <wx-alert
      v-if="category === null"
      type="info"
      variant="soft"
      :description="t('panel.set-unsaved')"
    />

    <wx-skeleton v-else-if="loading" :rows="4" />

    <wx-alert
      v-else-if="failed"
      type="warning"
      variant="soft"
      :description="t('panel.set-failed')"
    />

    <template v-else>
      <wx-text size="sm" tone="muted">{{ t('panel.set-help') }}</wx-text>

      <ul v-if="set && set.inherited.length > 0" class="wx-catalog-category-properties__inherited">
        <li
          v-for="row in set.inherited"
          :key="row.property.id"
          class="wx-catalog-category-properties__row is-inherited"
        >
          <span class="wx-catalog-category-properties__name">
            {{ propertyName(row.property, locale) }}
          </span>
          <wx-text size="sm" tone="muted" truncate>
            {{ typeOf(row.property) }} · {{ t('panel.set-from', { name: row.from.name }) }}
          </wx-text>
        </li>
      </ul>

      <wx-sortable-list
        v-model="own"
        size="sm"
        item-key="id"
        :item-label="(row: PropertyRow) => propertyName(row, locale)"
        :disabled="locked || saving"
        :empty-text="t('panel.set-empty')"
        @move="reorder"
      >
        <template #default="{ item }">
          <span class="wx-catalog-category-properties__row">
            <span class="wx-catalog-category-properties__name">
              {{ propertyName(item as PropertyRow, locale) }}
            </span>
            <wx-text size="sm" tone="muted" truncate>{{ typeOf(item as PropertyRow) }}</wx-text>
          </span>
        </template>

        <template #actions="{ item }">
          <wx-button
            v-if="!locked"
            size="sm"
            variant="text"
            icon="close"
            :disabled="saving"
            :aria-label="t('panel.set-remove', { name: propertyName(item as PropertyRow, locale) })"
            @click="remove((item as PropertyRow).id)"
          />
        </template>
      </wx-sortable-list>

      <wx-select
        v-if="!locked"
        class="wx-catalog-category-properties__add"
        :model-value="picked"
        :options="offered"
        :placeholder="t('panel.set-add')"
        :empty-text="t('panel.nothing-found')"
        :disabled="saving"
        filterable
        @update:model-value="add"
      />
    </template>
  </div>
</template>

<style scoped>
.wx-catalog-category-properties {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-catalog-category-properties__inherited {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wx-catalog-category-properties__row {
  display: flex;
  align-items: baseline;
  gap: var(--wx-space-8);
  min-width: 0;
}

/* Inherited rows start where the own rows' names do — past the list's frame and its grips — so
   the set reads as one column of names. */
.wx-catalog-category-properties__row.is-inherited {
  padding-inline-start: var(--wx-space-48);
  color: var(--wx-text-muted);
}

.wx-catalog-category-properties__name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-catalog-category-properties__add {
  max-width: var(--wx-field-max-width, 480px);
}
</style>
