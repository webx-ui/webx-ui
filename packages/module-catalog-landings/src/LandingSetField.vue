<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { WxAlert, WxLink } from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { WxCatalogFacetSet } from '@webx-ui/module-catalog'
import { cleanSet, createLandingsApi } from './api'
import { useLandingEditor } from './editor'
import { NAMESPACE, useCatalogLandingsMessages } from './i18n'
import type { BaseFacet, LandingFilters, SetCount } from './types'

/**
 * `wx-catalog-landing-set`: the set of a landing (§8.2 of the landings spec). The field itself is
 * the catalogue's `WxCatalogFacetSet`; what is a landing's lives here — the facets its base offers,
 * the live number of products, the slugs the set suggests, and the warning when another landing of
 * the base already holds the set.
 *
 * The facets are asked again when the base changes.
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

/** The saved landing's chip says what a value is called when the base no longer counts it. */
function chipWord(key: string): string | undefined {
  return editor?.landing.value?.chips.find((chip) => chip.key === key)?.text
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

    <wx-catalog-facet-set
      v-model="value"
      :facets="offered"
      :disabled="locked"
      :count="counted?.count ?? null"
      :counting="counting"
      :value-label="chipWord"
    />

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
  min-width: 0;
}
</style>
