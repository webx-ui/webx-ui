<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import {
  WxAlert,
  WxBadge,
  WxButton,
  WxIcon,
  WxInputNumber,
  WxLink,
  WxSelect,
  WxText,
  type SelectOption,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { cleanSet, createLandingsApi } from './api'
import { useLandingEditor } from './editor'
import { NAMESPACE, useCatalogLandingsMessages } from './i18n'
import type { BaseFacet, LandingFilters, SetChoice, SetCount } from './types'

/**
 * `wx-catalog-landing-set`: the set of a landing (§8.2 of the landings spec) — «add a facet» out of
 * the facets the base offers, its values in the same controls the products' filter uses (a list
 * of values with search, a range from–to), and beside them the live number of products and a
 * warning when another landing of the base already holds the set.
 *
 * The facets are asked again when the base changes; a facet of the set the new base does not offer
 * stays, labelled by its key, so that changing the base never drops a choice silently — the save
 * says whether the set still makes sense.
 */
defineOptions({ name: 'WxCatalogLandingSet' })

const value = defineModel<LandingFilters | null>({ default: null })

const admin = useAdmin()
const api = createLandingsApi(admin)
const editor = useLandingEditor()
useCatalogLandingsMessages()

const t = useTranslate(NAMESPACE)
const message = useErrorText()

const locked = computed(() => editor?.locked.value ?? false)

const category = computed<number | null>(() => {
  const id = editor?.values.value.category_id

  return typeof id === 'number' ? id : id ? Number(id) : null
})

const offered = ref<BaseFacet[]>([])
const loadFailed = ref('')
const set = computed<LandingFilters>(() =>
  value.value && !Array.isArray(value.value) ? value.value : {},
)

const facetOf = (key: string) => offered.value.find((facet) => facet.key === key)

const adding = computed<SelectOption[]>(() =>
  offered.value
    .filter((facet) => !(facet.key in set.value))
    .map((facet) => ({ value: facet.key, label: facet.label })),
)

let asked = 0

async function loadFacets(): Promise<void> {
  const ticket = ++asked

  loadFailed.value = ''

  try {
    const answer = await api.facets(category.value)

    if (ticket === asked) offered.value = answer
  } catch (error) {
    if (ticket === asked) loadFailed.value = message(error)
  }
}

watch(category, () => void loadFacets(), { immediate: true })

function isRange(key: string, choice: SetChoice): boolean {
  const facet = facetOf(key)

  return facet ? facet.kind === 'range' : !('values' in choice)
}

function write(next: LandingFilters): void {
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

  // A chosen value the base has no products of is still a choice: shown, not dropped.
  for (const chosen of valuesOf(choice)) {
    if (!known.some((one) => one.value === chosen)) {
      const word = chipWord(key, chosen)
      options.push({ value: chosen, label: `${word} (0)` })
    }
  }

  return options
}

/** The saved landing's chip says what a value is called when the base no longer counts it. */
function chipWord(key: string, chosen: string): string {
  return editor?.landing.value?.chips.find((chip) => chip.key === key)?.text ?? `#${chosen}`
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

/* -------------------------------------------------------------------- the count --- */

const counted = ref<SetCount | null>(null)
const counting = ref(false)
const countFailed = ref('')
let timer: ReturnType<typeof setTimeout> | undefined
let countTicket = 0

const question = computed(() => JSON.stringify([category.value, cleanSet(set.value)]))

async function count(): Promise<void> {
  const ticket = ++countTicket
  const clean = cleanSet(set.value)

  if (Object.keys(clean).length === 0) {
    counted.value = null
    counting.value = false
    if (editor) editor.suggested.value = {}

    return
  }

  counting.value = true
  countFailed.value = ''

  try {
    const answer = await api.count(category.value, clean, editor?.landing.value?.id ?? null)

    if (ticket !== countTicket) return

    counted.value = answer
    if (editor) editor.suggested.value = Array.isArray(answer.suggested) ? {} : answer.suggested
  } catch (error) {
    if (ticket === countTicket) {
      counted.value = null
      countFailed.value = message(error)
    }
  } finally {
    if (ticket === countTicket) counting.value = false
  }
}

watch(
  question,
  () => {
    clearTimeout(timer)
    timer = setTimeout(() => void count(), 350)
  },
  { immediate: true },
)

onBeforeUnmount(() => clearTimeout(timer))

const takenLink = computed(() =>
  counted.value?.taken && editor ? `${editor.list}/${counted.value.taken.id}` : null,
)
</script>

<template>
  <div class="wx-catalog-landing-set">
    <wx-alert v-if="loadFailed" type="danger">{{ loadFailed }}</wx-alert>

    <wx-text v-if="Object.keys(set).length === 0" tone="muted" size="sm">
      {{ t('landing.set-empty') }}
    </wx-text>

    <div
      v-for="(choice, key) in set"
      :key="key"
      class="wx-catalog-landing-set__row"
      :data-facet="key"
    >
      <wx-text class="wx-catalog-landing-set__label" size="sm" weight="medium">
        {{ labelOf(String(key)) }}
      </wx-text>

      <div v-if="isRange(String(key), choice)" class="wx-catalog-landing-set__range">
        <wx-input-number
          :model-value="rangeOf(choice).min"
          :controls="false"
          :disabled="locked"
          size="sm"
          :placeholder="facetOf(String(key))?.min?.toString() ?? t('landing.set-from')"
          :aria-label="`${labelOf(String(key))}: ${t('landing.set-from')}`"
          @update:model-value="
            (picked: number | null | undefined) => bound(String(key), 'min', picked)
          "
        />
        <wx-input-number
          :model-value="rangeOf(choice).max"
          :controls="false"
          :disabled="locked"
          size="sm"
          :placeholder="facetOf(String(key))?.max?.toString() ?? t('landing.set-to')"
          :aria-label="`${labelOf(String(key))}: ${t('landing.set-to')}`"
          @update:model-value="
            (picked: number | null | undefined) => bound(String(key), 'max', picked)
          "
        />
      </div>

      <wx-select
        v-else
        class="wx-catalog-landing-set__values"
        :model-value="valuesOf(choice)"
        :options="optionsOf(String(key), choice)"
        multiple
        filterable
        clearable
        size="sm"
        :disabled="locked"
        :placeholder="t('landing.set-values')"
        :aria-label="labelOf(String(key))"
        @update:model-value="(picked: unknown) => pick(String(key), picked)"
      />

      <wx-button
        variant="text"
        size="sm"
        :disabled="locked"
        :aria-label="t('landing.set-remove')"
        :title="t('landing.set-remove')"
        @click="remove(String(key))"
      >
        <template #icon><wx-icon name="close" /></template>
      </wx-button>
    </div>

    <div class="wx-catalog-landing-set__foot">
      <wx-select
        v-if="adding.length > 0"
        class="wx-catalog-landing-set__add"
        :model-value="null"
        :options="adding"
        filterable
        size="sm"
        :disabled="locked"
        :placeholder="t('landing.set-add')"
        :aria-label="t('landing.set-add')"
        @update:model-value="add"
      />

      <wx-text v-if="counting" size="sm" tone="muted">{{ t('landing.set-counting') }}</wx-text>
      <wx-badge
        v-else-if="counted"
        class="wx-catalog-landing-set__count"
        :type="counted.count > 0 ? 'success' : 'warning'"
        size="sm"
        round
      >
        {{ t('landing.set-count', { count: counted.count }) }}
      </wx-badge>
    </div>

    <wx-alert v-if="countFailed" type="danger">{{ countFailed }}</wx-alert>

    <wx-alert v-if="counted?.taken" type="warning">
      {{ t('landing.set-taken', { name: counted.taken.name }) }}
      <wx-link v-if="takenLink" :as="RouterLink" :to="takenLink">
        {{ t('landing.set-open-taken') }}
      </wx-link>
    </wx-alert>
  </div>
</template>

<style scoped>
.wx-catalog-landing-set {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  container-type: inline-size;
  min-width: 0;
}

.wx-catalog-landing-set__row {
  display: grid;
  grid-template-columns: minmax(96px, 160px) minmax(0, 1fr) auto;
  align-items: center;
  gap: var(--wx-space-8);
}

.wx-catalog-landing-set__range {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--wx-space-8);
}

.wx-catalog-landing-set__foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-12);
}

.wx-catalog-landing-set__add {
  width: 240px;
  max-width: 100%;
}

@container (max-width: 480px) {
  .wx-catalog-landing-set__row {
    grid-template-columns: minmax(0, 1fr) auto;
  }

  .wx-catalog-landing-set__label {
    grid-column: 1 / -1;
  }
}
</style>
