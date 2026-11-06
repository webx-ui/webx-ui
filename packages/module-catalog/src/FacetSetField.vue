<script setup lang="ts">
import { computed } from 'vue'
import {
  WxBadge,
  WxButton,
  WxIcon,
  WxInputNumber,
  WxPopconfirm,
  WxSelect,
  WxText,
  type SelectOption,
} from '@webx-ui/core'
import { useRemoveConfirm, useTranslate } from '@webx-ui/module-admin'
import { useCatalogMessages } from './i18n'
import type { BaseFacet, FacetSetValue, SetChoice } from './types'

/**
 * `WxCatalogFacetSet`: a stored set of facets as a form edits it — «add a facet» out of the facets
 * on offer, its values in the same controls the products' filter uses (a list of values with
 * search, a range from–to), and beside them the number of products.
 *
 * It asks nothing of the server: the facets and the number come as props and the set leaves as
 * `v-model`, so that each owner asks in its own way and says its own warnings around it — a
 * landing, that another landing already holds the set.
 *
 * A facet of the set that is not on offer stays, labelled by its key, so that changing the base
 * never drops a choice silently — the save says whether the set still makes sense.
 */
defineOptions({ name: 'WxCatalogFacetSet' })

const props = withDefaults(
  defineProps<{
    /** The facets on offer, with their values counted. */
    facets?: BaseFacet[]
    disabled?: boolean
    /** The products the set finds; `null` — nothing to say yet. */
    count?: number | null
    counting?: boolean
    /** What a chosen value is called when the facets on offer no longer count it. */
    valueLabel?: (key: string, value: string) => string | undefined
  }>(),
  { facets: () => [], disabled: false, count: null, counting: false, valueLabel: undefined },
)

const value = defineModel<FacetSetValue | null>({ default: null })

useCatalogMessages()

const t = useTranslate('webx-catalog')
const panel = useTranslate('webx-admin')
const askRemove = useRemoveConfirm()

const set = computed<FacetSetValue>(() =>
  value.value && !Array.isArray(value.value) ? value.value : {},
)

const facetOf = (key: string) => props.facets.find((facet) => facet.key === key)

const adding = computed<SelectOption[]>(() =>
  props.facets
    .filter((facet) => !(facet.key in set.value))
    .map((facet) => ({ value: facet.key, label: facet.label })),
)

function isRange(key: string, choice: SetChoice): boolean {
  const facet = facetOf(key)

  return facet ? facet.kind === 'range' : !('values' in choice)
}

function write(next: FacetSetValue): void {
  value.value = next
}

function add(key: unknown): void {
  const facet = facetOf(String(key))

  if (!facet) return

  write({
    ...set.value,
    [facet.key]: facet.kind === 'range' ? { min: null, max: null } : { values: [] },
  })
}

function remove(key: string): void {
  const next = { ...set.value }

  delete next[key]
  write(next)
}

function valuesOf(choice: SetChoice): string[] {
  return 'values' in choice ? choice.values : []
}

function rangeOf(choice: SetChoice): { min: number | null; max: number | null } {
  return 'values' in choice ? { min: null, max: null } : choice
}

function optionsOf(key: string, choice: SetChoice): SelectOption[] {
  const known = facetOf(key)?.values ?? []
  const options: SelectOption[] = known.map((one) => ({
    value: one.value,
    label: `${one.label} (${one.count})`,
  }))

  // A chosen value the facets have no products of is still a choice: shown, not dropped.
  for (const chosen of valuesOf(choice)) {
    if (!known.some((one) => one.value === chosen)) {
      options.push({
        value: chosen,
        label: `${props.valueLabel?.(key, chosen) ?? `#${chosen}`} (0)`,
      })
    }
  }

  return options
}

function pick(key: string, picked: unknown): void {
  write({ ...set.value, [key]: { values: Array.isArray(picked) ? picked.map(String) : [] } })
}

function bound(key: string, end: 'min' | 'max', picked: number | null | undefined): void {
  const choice = set.value[key]
  const range = choice ? rangeOf(choice) : { min: null, max: null }

  write({ ...set.value, [key]: { ...range, [end]: picked ?? null } })
}

function labelOf(key: string): string {
  return facetOf(key)?.label ?? key
}
</script>

<template>
  <div class="wx-catalog-facet-set">
    <wx-text v-if="Object.keys(set).length === 0" tone="muted" size="sm">
      {{ t('panel.facet-set-empty') }}
    </wx-text>

    <div
      v-for="(choice, key) in set"
      :key="key"
      class="wx-catalog-facet-set__row"
      :data-facet="key"
    >
      <wx-text class="wx-catalog-facet-set__label" size="sm" weight="medium">
        {{ labelOf(String(key)) }}
      </wx-text>

      <div v-if="isRange(String(key), choice)" class="wx-catalog-facet-set__range">
        <wx-input-number
          :model-value="rangeOf(choice).min"
          :controls="false"
          :disabled="disabled"
          size="sm"
          :placeholder="t('panel.facet-set-from')"
          :aria-label="`${labelOf(String(key))}: ${t('panel.facet-set-from')}`"
          @update:model-value="
            (picked: number | null | undefined) => bound(String(key), 'min', picked)
          "
        />
        <wx-input-number
          :model-value="rangeOf(choice).max"
          :controls="false"
          :disabled="disabled"
          size="sm"
          :placeholder="t('panel.facet-set-to')"
          :aria-label="`${labelOf(String(key))}: ${t('panel.facet-set-to')}`"
          @update:model-value="
            (picked: number | null | undefined) => bound(String(key), 'max', picked)
          "
        />
      </div>

      <wx-select
        v-else
        :empty-text="panel('filters.nothing-found')"
        class="wx-catalog-facet-set__values"
        :model-value="valuesOf(choice)"
        :options="optionsOf(String(key), choice)"
        multiple
        filterable
        clearable
        size="sm"
        :disabled="disabled"
        :placeholder="t('panel.facet-set-values')"
        :aria-label="labelOf(String(key))"
        @update:model-value="(picked: unknown) => pick(String(key), picked)"
      />

      <wx-popconfirm
        :title="askRemove.confirm"
        :confirm-text="askRemove.confirmText"
        :cancel-text="askRemove.cancelText"
        confirm-type="danger"
        :disabled="disabled"
        @confirm="remove(String(key))"
      >
        <template #trigger>
          <wx-button
            variant="text"
            size="sm"
            :disabled="disabled"
            :aria-label="t('panel.facet-set-remove')"
            :title="t('panel.facet-set-remove')"
          >
            <template #icon><wx-icon name="close" /></template>
          </wx-button>
        </template>
      </wx-popconfirm>
    </div>

    <div class="wx-catalog-facet-set__foot">
      <wx-select
        v-if="adding.length > 0"
        :empty-text="panel('filters.nothing-found')"
        class="wx-catalog-facet-set__add"
        :model-value="null"
        :options="adding"
        filterable
        size="sm"
        :disabled="disabled"
        :placeholder="t('panel.facet-set-add')"
        :aria-label="t('panel.facet-set-add')"
        @update:model-value="add"
      />

      <wx-text v-if="counting" size="sm" tone="muted">{{ t('panel.facet-set-counting') }}</wx-text>
      <wx-badge
        v-else-if="count !== null"
        class="wx-catalog-facet-set__count"
        :type="count > 0 ? 'success' : 'warning'"
        size="sm"
        round
      >
        {{ t('panel.facet-set-count', { count }) }}
      </wx-badge>
    </div>
  </div>
</template>

<style scoped>
.wx-catalog-facet-set {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  container-type: inline-size;
  min-width: 0;
}

.wx-catalog-facet-set__row {
  display: grid;
  grid-template-columns: minmax(96px, 160px) minmax(0, 1fr) auto;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-catalog-facet-set__range {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--wx-space-8);
}

.wx-catalog-facet-set__foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-12);
}

.wx-catalog-facet-set__add {
  width: 240px;
  max-width: 100%;
}

@container (max-width: 480px) {
  .wx-catalog-facet-set__row {
    grid-template-columns: minmax(0, 1fr) auto;
  }

  .wx-catalog-facet-set__label {
    grid-column: 1 / -1;
  }
}
</style>
