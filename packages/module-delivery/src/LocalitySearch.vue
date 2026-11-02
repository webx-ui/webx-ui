<script setup lang="ts">
import { computed, ref } from 'vue'
import { WxAutocomplete, WxButton, type AutocompleteOption } from '@webx-ui/core'
import { useAdmin } from '@webx-ui/module-admin'
import { createDeliveryApi } from './api'
import { useDeliveryWords } from './i18n'
import LocalityDialog from './LocalityDialog.vue'
import type { LocalityBrief } from './types'

/**
 * The search of a locality (§4.5) the section's pickers share: the field of a point, the
 * courier's list, the popular towns, «Bind to…» of the queue. Prefix of any name, the heaviest
 * first, active localities only.
 *
 * Nothing found — and `creatable` — offers «Add a locality» right under the field (decision 42):
 * the short form opens with the typed name, and the town made is picked as if it had been found.
 */
const props = withDefaults(
  defineProps<{
    /** Only this country; none — every country of the site. */
    country?: string | null
    /** Offer «Add a locality» when nothing is found. */
    creatable?: boolean
    /** Ids not to offer — the ones already chosen. */
    exclude?: number[]
    placeholder?: string
    disabled?: boolean
  }>(),
  { country: null, creatable: false, exclude: () => [], placeholder: undefined, disabled: false },
)

const emit = defineEmits<{ pick: [locality: LocalityBrief] }>()

const api = createDeliveryApi(useAdmin())
const t = useDeliveryWords()

const term = ref('')
const found = ref<LocalityBrief[]>([])
const searched = ref('')
const searching = ref(false)
const creating = ref(false)

const options = computed<AutocompleteOption[]>(() =>
  found.value
    .filter((one) => !props.exclude.includes(one.id))
    .map((one) => ({ value: one.name, description: one.label, id: one.id })),
)

/* Only after an answer for what is typed now: an empty list while asking is not «nothing». */
const nothing = computed(
  () =>
    props.creatable &&
    !searching.value &&
    searched.value !== '' &&
    searched.value === term.value.trim() &&
    options.value.length === 0,
)

let asked = 0

async function search(query: string): Promise<void> {
  const mine = ++asked
  const q = query.trim()

  searching.value = true

  try {
    const rows = await api.suggest(q, props.country)

    if (mine !== asked) return

    found.value = rows
    searched.value = q
  } catch {
    if (mine === asked) found.value = []
  } finally {
    if (mine === asked) searching.value = false
  }
}

function select(option: AutocompleteOption): void {
  const one = found.value.find((row) => row.id === (option as { id?: number }).id)

  if (one) {
    emit('pick', one)
    term.value = ''
  }
}

function made(locality: LocalityBrief): void {
  emit('pick', locality)
  term.value = ''
  searched.value = ''
}
</script>

<template>
  <div class="wx-delivery-locality-search">
    <wx-autocomplete
      v-model="term"
      :options="options"
      remote
      teleport
      open-on-focus
      :loading="searching"
      :empty-text="t('locality.nothing-found')"
      :placeholder="placeholder ?? t('locality.search')"
      :aria-label="placeholder ?? t('locality.search')"
      :disabled="disabled"
      @search="search"
      @select="select"
    />

    <wx-button
      v-if="nothing"
      class="wx-delivery-locality-search__add"
      size="sm"
      variant="outline"
      icon="plus"
      data-test="add-locality"
      @click="creating = true"
    >
      {{ t('locality.add-here') }}
    </wx-button>

    <locality-dialog v-model:open="creating" :country="country" :name="term" short @saved="made" />
  </div>
</template>

<style scoped>
.wx-delivery-locality-search {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wx-space-6);
  width: 100%;
  min-width: 0;
}

.wx-delivery-locality-search > :deep(.wx-autocomplete) {
  width: 100%;
}
</style>
