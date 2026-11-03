<script setup lang="ts">
import { computed, ref } from 'vue'
import { WxAutocomplete, WxIcon, type AutocompleteOption } from '@webx-ui/core'
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
 * Nothing found — and `creatable` — the list itself says what to do (decision 42): its one line
 * is «+ Add «<typed>»», which opens the short form with that name, and the town made is picked
 * as if it had been found. In the list and not under the field: an open list covered whatever
 * stood under it, and a button that only showed once the list was closed was never seen.
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
/* The name the short form starts with: what was typed when «Add» was chosen. */
const naming = ref('')

const offered = computed(() => found.value.filter((one) => !props.exclude.includes(one.id)))

/* Only after an answer for what is typed now: an empty list while asking is not «nothing». */
const nothing = computed(
  () =>
    props.creatable &&
    !searching.value &&
    searched.value !== '' &&
    searched.value === term.value.trim() &&
    offered.value.length === 0,
)

const options = computed<AutocompleteOption[]>(() =>
  nothing.value
    ? [
        {
          // The typed text as the value: picking the line leaves the field as it was.
          value: term.value,
          label: t('locality.add-named', { name: searched.value }),
          action: true,
        },
      ]
    : offered.value.map((one) => ({ value: one.name, description: one.label, id: one.id })),
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
  if (option.action === true) {
    naming.value = searched.value
    creating.value = true

    return
  }

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
    >
      <template #option="{ option }">
        <span
          v-if="option.action"
          class="wx-delivery-locality-search__add"
          data-test="add-locality"
        >
          <wx-icon name="plus" />
          <span class="wx-delivery-locality-search__add-label">{{ option.label }}</span>
        </span>
        <template v-else>
          <span class="wx-delivery-locality-search__name">{{ option.value }}</span>
          <span v-if="option.description" class="wx-delivery-locality-search__label">{{
            option.description
          }}</span>
        </template>
      </template>
    </wx-autocomplete>

    <locality-dialog
      v-model:open="creating"
      :country="country"
      :name="naming"
      short
      @saved="made"
    />
  </div>
</template>

<style scoped>
.wx-delivery-locality-search {
  width: 100%;
  min-width: 0;
}

/* The action reads as one: the link colour of the panel, a plus before it. */
.wx-delivery-locality-search__add {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
  color: var(--wx-color-primary);
  font-weight: var(--wx-font-weight-medium);
}

.wx-delivery-locality-search__add-label {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-delivery-locality-search__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-delivery-locality-search__label {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}
</style>
