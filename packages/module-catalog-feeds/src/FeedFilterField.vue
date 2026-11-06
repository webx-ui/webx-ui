<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  WxAlert,
  WxButton,
  WxFormItem,
  WxInputNumber,
  WxSelect,
  WxSwitch,
  WxTagsInput,
  WxText,
  WxTreeSelect,
  type SelectOption,
  type TreeSelectValue,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { useCategoryTree } from '@webx-ui/module-catalog'
import { useFeedEditor } from './editor'
import { NAMESPACE, useCatalogFeedsMessages } from './i18n'
import type { FeedCount, FeedFacet, FeedFilter } from './types'

/**
 * `wx-catalog-feed-filter`: the first layer of what goes into a feed (§5.1 of the feeds spec) and
 * how many products it passes now. A control stands here only for a facet the site has: without the
 * brands' satellite there is no «Brands» to choose from. A property is added on demand, in or out of
 * the storefront's filter, since only an administrator sees this one.
 */
defineOptions({ name: 'WxCatalogFeedFilter' })

/** What has a place of its own in the form: the rest are properties. */
const OWN = ['brand', 'label', 'price']

const DEBOUNCE = 350

const props = defineProps<{ disabled?: boolean }>()

const value = defineModel<FeedFilter | null>({ default: null })

const admin = useAdmin()
const editor = useFeedEditor()
const tree = useCategoryTree(admin)
useCatalogFeedsMessages()

const t = useTranslate(NAMESPACE)
const panel = useTranslate('webx-admin')
const message = useErrorText()

const facets = ref<FeedFacet[]>([])
const failed = ref('')
const counted = ref<FeedCount | null>(null)
const countFailed = ref('')

const filter = computed<FeedFilter>(() =>
  value.value && !Array.isArray(value.value) ? value.value : {},
)
const locale = computed(() => editor.values.value.locale)
const locked = computed(() => props.disabled || editor.locked.value)

const facetOf = (key: string) => facets.value.find((facet) => facet.key === key)
const brands = computed(() => facetOf('brand'))
const labels = computed(() => facetOf('label'))
const price = computed(() => facetOf('price'))

const chosen = computed(() => filter.value.properties ?? {})
const properties = computed(() => facets.value.filter((facet) => !OWN.includes(facet.key)))
const adding = computed<SelectOption[]>(() =>
  properties.value
    .filter((facet) => !(facet.key in chosen.value))
    .map((facet) => ({ value: facet.key, label: facet.label })),
)

function optionsOf(facet: FeedFacet | undefined): SelectOption[] {
  return (facet?.values ?? []).map((one) => ({ value: one.value, label: one.label }))
}

/** An empty part of a filter is no part of it: `{}` is what a feed of everything holds. */
function write(next: FeedFilter): void {
  const clean: FeedFilter = { ...next }

  if (!clean.categories?.include?.length && !clean.categories?.exclude?.length) {
    delete clean.categories
  }
  if (!clean.brands?.length) delete clean.brands
  if (!clean.labels?.length) delete clean.labels
  if (clean.price?.min == null && clean.price?.max == null) delete clean.price
  if (!clean.only_in_stock) delete clean.only_in_stock
  if (Object.keys(clean.properties ?? {}).length === 0) delete clean.properties

  value.value = clean
}

function branch(side: 'include' | 'exclude', picked: TreeSelectValue): void {
  const ids = (Array.isArray(picked) ? picked : []).map(Number)

  write({ ...filter.value, categories: { ...filter.value.categories, [side]: ids } })
}

function branchOf(side: 'include' | 'exclude'): number[] {
  return filter.value.categories?.[side] ?? []
}

function strings(picked: unknown): string[] {
  return Array.isArray(picked) ? picked.map(String) : []
}

function bound(end: 'min' | 'max', picked: number | null | undefined): void {
  write({ ...filter.value, price: { ...filter.value.price, [end]: picked ?? null } })
}

function addProperty(key: unknown): void {
  const facet = facetOf(String(key))

  if (!facet) return

  write({
    ...filter.value,
    properties: {
      ...chosen.value,
      [facet.key]: facet.kind === 'range' ? { min: null, max: null } : [],
    },
  })
}

function dropProperty(key: string): void {
  const next = { ...chosen.value }

  delete next[key]
  write({ ...filter.value, properties: next })
}

function setProperty(key: string, picked: string[] | { min?: number | null; max?: number | null }) {
  write({ ...filter.value, properties: { ...chosen.value, [key]: picked } })
}

function listOf(key: string): string[] {
  const held = chosen.value[key]

  return Array.isArray(held) ? held : []
}

function rangeOf(key: string): { min?: number | null; max?: number | null } {
  const held = chosen.value[key]

  return held && !Array.isArray(held) ? held : {}
}

function toggled(key: string): boolean {
  return listOf(key).length > 0
}

let facetsAsked = 0

async function loadFacets(): Promise<void> {
  const ticket = ++facetsAsked

  failed.value = ''

  try {
    const answer = await editor.api.facets(locale.value)

    if (ticket === facetsAsked) facets.value = answer
  } catch (error) {
    if (ticket === facetsAsked) failed.value = message(error)
  }
}

watch(locale, () => void loadFacets(), { immediate: true })

onMounted(() => void tree.load())

let timer: ReturnType<typeof setTimeout> | undefined
let countAsked = 0

async function count(): Promise<void> {
  const ticket = ++countAsked

  countFailed.value = ''

  try {
    const answer = await editor.api.count(filter.value, locale.value, editor.feed.value?.id ?? null)

    if (ticket === countAsked) counted.value = answer
  } catch (error) {
    if (ticket === countAsked) {
      counted.value = null
      countFailed.value = message(error)
    }
  }
}

void count()

watch(
  () => JSON.stringify([filter.value, locale.value]),
  () => {
    clearTimeout(timer)
    timer = setTimeout(() => void count(), DEBOUNCE)
  },
)

onBeforeUnmount(() => {
  countAsked++
  clearTimeout(timer)
})
</script>

<template>
  <div class="wx-catalog-feed-filter">
    <wx-alert v-if="failed" type="danger" variant="soft" :description="failed" />

    <div class="wx-catalog-feed-filter__grid">
      <wx-form-item :label="t('panel.filter-include')" :help="t('panel.filter-include-help')">
        <wx-tree-select
          :model-value="branchOf('include')"
          :nodes="tree.nodes.value ?? []"
          node-key="id"
          label-key="name"
          children-key="children"
          multiple
          check-strictly
          filterable
          clearable
          :disabled="locked"
          :placeholder="t('panel.filter-all')"
          :filter-placeholder="panel('filters.search')"
          :empty-text="panel('filters.nothing-found')"
          @update:model-value="(picked: TreeSelectValue) => branch('include', picked)"
        />
      </wx-form-item>

      <wx-form-item :label="t('panel.filter-exclude')" :help="t('panel.filter-exclude-help')">
        <wx-tree-select
          :model-value="branchOf('exclude')"
          :nodes="tree.nodes.value ?? []"
          node-key="id"
          label-key="name"
          children-key="children"
          multiple
          check-strictly
          filterable
          clearable
          :disabled="locked"
          :placeholder="t('panel.filter-none')"
          :filter-placeholder="panel('filters.search')"
          :empty-text="panel('filters.nothing-found')"
          @update:model-value="(picked: TreeSelectValue) => branch('exclude', picked)"
        />
      </wx-form-item>

      <wx-form-item v-if="brands" :label="brands.label">
        <wx-select
          :model-value="(filter.brands ?? []).map(String)"
          :options="optionsOf(brands)"
          multiple
          filterable
          clearable
          :disabled="locked"
          :placeholder="t('panel.filter-all')"
          @update:model-value="(picked: unknown) => write({ ...filter, brands: strings(picked) })"
        />
      </wx-form-item>

      <wx-form-item v-if="labels" :label="labels.label">
        <wx-select
          :model-value="filter.labels ?? []"
          :options="optionsOf(labels)"
          multiple
          filterable
          clearable
          :disabled="locked"
          :placeholder="t('panel.filter-all')"
          @update:model-value="(picked: unknown) => write({ ...filter, labels: strings(picked) })"
        />
      </wx-form-item>

      <wx-form-item v-if="price" :label="price.label">
        <div class="wx-catalog-feed-filter__range">
          <wx-input-number
            :model-value="filter.price?.min ?? null"
            :controls="false"
            :precision="2"
            :disabled="locked"
            :placeholder="t('panel.filter-from')"
            :aria-label="`${price.label}: ${t('panel.filter-from')}`"
            @update:model-value="(picked: number | null | undefined) => bound('min', picked)"
          />
          <wx-input-number
            :model-value="filter.price?.max ?? null"
            :controls="false"
            :precision="2"
            :disabled="locked"
            :placeholder="t('panel.filter-to')"
            :aria-label="`${price.label}: ${t('panel.filter-to')}`"
            @update:model-value="(picked: number | null | undefined) => bound('max', picked)"
          />
        </div>
      </wx-form-item>
    </div>

    <div v-for="(_, key) in chosen" :key="key" class="wx-catalog-feed-filter__row">
      <wx-text class="wx-catalog-feed-filter__label" size="sm" weight="medium">
        {{ facetOf(String(key))?.label ?? key }}
      </wx-text>

      <div
        v-if="facetOf(String(key))?.kind === 'range'"
        class="wx-catalog-feed-filter__range wx-catalog-feed-filter__control"
      >
        <wx-input-number
          :model-value="rangeOf(String(key)).min ?? null"
          :controls="false"
          :disabled="locked"
          :placeholder="t('panel.filter-from')"
          :aria-label="t('panel.filter-from')"
          @update:model-value="
            (picked: number | null | undefined) =>
              setProperty(String(key), { ...rangeOf(String(key)), min: picked ?? null })
          "
        />
        <wx-input-number
          :model-value="rangeOf(String(key)).max ?? null"
          :controls="false"
          :disabled="locked"
          :placeholder="t('panel.filter-to')"
          :aria-label="t('panel.filter-to')"
          @update:model-value="
            (picked: number | null | undefined) =>
              setProperty(String(key), { ...rangeOf(String(key)), max: picked ?? null })
          "
        />
      </div>

      <wx-switch
        v-else-if="facetOf(String(key))?.kind === 'toggle'"
        class="wx-catalog-feed-filter__control"
        :model-value="toggled(String(key))"
        :disabled="locked"
        @update:model-value="(on: unknown) => setProperty(String(key), on ? ['1'] : [])"
      />

      <wx-tags-input
        v-else-if="facetOf(String(key))?.typed"
        class="wx-catalog-feed-filter__control"
        :model-value="listOf(String(key))"
        :disabled="locked"
        :placeholder="t('panel.filter-typed')"
        @update:model-value="(picked: unknown) => setProperty(String(key), strings(picked))"
      />

      <wx-select
        v-else
        class="wx-catalog-feed-filter__control"
        :model-value="listOf(String(key))"
        :options="optionsOf(facetOf(String(key)))"
        multiple
        filterable
        :disabled="locked"
        @update:model-value="(picked: unknown) => setProperty(String(key), strings(picked))"
      />

      <wx-button
        icon="x"
        variant="text"
        size="sm"
        :disabled="locked"
        :aria-label="t('panel.filter-remove')"
        @click="dropProperty(String(key))"
      />
    </div>

    <div v-if="adding.length > 0" class="wx-catalog-feed-filter__add">
      <wx-select
        :model-value="null"
        :options="adding"
        filterable
        :disabled="locked"
        :placeholder="t('panel.filter-add')"
        :aria-label="t('panel.filter-add')"
        @update:model-value="addProperty"
      />
    </div>

    <wx-switch
      v-if="editor.meta.value?.stock"
      :model-value="filter.only_in_stock ?? false"
      :disabled="locked"
      :label="t('panel.filter-in-stock')"
      @update:model-value="(on: unknown) => write({ ...filter, only_in_stock: on === true })"
    />

    <wx-text v-if="countFailed" size="sm" tone="danger">{{ countFailed }}</wx-text>

    <div v-else-if="counted" class="wx-catalog-feed-filter__count" role="status">
      <wx-text size="sm" weight="medium">
        {{ t('panel.filter-count', { count: String(counted.selected) }) }}
      </wx-text>
      <wx-text v-if="counted.excluded > 0" size="sm" tone="muted">
        {{ t('panel.filter-excluded', { count: String(counted.excluded) }) }}
      </wx-text>
      <wx-text size="sm" tone="muted">{{ t('panel.filter-count-help') }}</wx-text>
    </div>
  </div>
</template>

<style scoped>
.wx-catalog-feed-filter {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
  container-type: inline-size;
}

.wx-catalog-feed-filter__grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--wx-space-12) var(--wx-space-16);
}

@container (min-width: 640px) {
  .wx-catalog-feed-filter__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.wx-catalog-feed-filter__range {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--wx-space-8);
}

.wx-catalog-feed-filter__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-catalog-feed-filter__label {
  flex: 0 0 160px;
  min-width: 0;
  overflow-wrap: anywhere;
}

.wx-catalog-feed-filter__control {
  flex: 1 1 200px;
  min-width: 0;
}

.wx-catalog-feed-filter__add {
  max-width: 320px;
}

.wx-catalog-feed-filter__count {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-2);
}
</style>
