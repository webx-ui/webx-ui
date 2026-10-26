<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { WxAction, WxAutocomplete, WxBadge, type AutocompleteOption } from '@webx-ui/core'
import { createRelationsApi, useAdmin, useTranslate } from '@webx-ui/module-admin'
import { useConfiguratorsMessages } from './i18n'
import type { ConfiguratorCandidate } from './types'

/**
 * `wx-configurator`: one configurator, chosen by name and kept by its key — what
 * `<x-webx-configurator key="…" />` takes and the block `configurator` of the site keeps (§12.1).
 *
 * The configurators come from the panel's relation target `configurator`
 * (`GET /api/cms/relations/configurator`): no permission of the section is needed to put one on a
 * page, only to build one. The target searches the key as well as the name and puts the exact key
 * first, which is how a stored key is named when the field opens.
 *
 * A key nobody has stays in the field, marked: dropping it silently would change the block without
 * anybody touching it, and the server refuses such a key only when it is written anew.
 *
 * The same picker chooses a product of the catalogue for an option (§22.4): `target` names another
 * relation target and `by` the field of its row that is kept — `configurator-product` by `sku`.
 */
defineOptions({ name: 'WxConfiguratorField' })

const props = withDefaults(
  defineProps<{
    disabled?: boolean
    placeholder?: string
    emptyText?: string
    /** The relation target the rows come from. */
    target?: string
    /** The field of a row that is the value. */
    by?: 'key' | 'sku'
    missingText?: string
    removeText?: string
    nothingText?: string
  }>(),
  {
    disabled: false,
    placeholder: undefined,
    emptyText: undefined,
    target: 'configurator',
    by: 'key',
    missingText: undefined,
    removeText: undefined,
    nothingText: undefined,
  },
)

const value = defineModel<string | null>({ default: null })

useConfiguratorsMessages()

const admin = useAdmin()
const t = useTranslate('webx-configurator')
const api = createRelationsApi(admin, props.target)

/** What a row is kept by: its key, or a product's article number. */
const keyOf = (row: ConfiguratorCandidate): string | undefined => row[props.by]

const key = computed(() => (typeof value.value === 'string' ? value.value.trim() : ''))

/** Configurators the server described, by key, kept for the life of the field. */
const known = ref(new Map<string, ConfiguratorCandidate>())
/** Keys the server was asked about and has no configurator for. */
const missing = ref(new Set<string>())

const candidates = ref<ConfiguratorCandidate[]>([])
const searching = ref(false)
const term = ref('')

function remember(rows: ConfiguratorCandidate[]): void {
  const next = new Map(known.value)

  for (const row of rows) {
    const at = keyOf(row)
    if (at) next.set(at, row)
  }

  known.value = next
}

async function name(wanted: string): Promise<void> {
  if (wanted === '' || known.value.has(wanted) || missing.value.has(wanted)) return

  try {
    const rows = (await api.search(wanted)) as ConfiguratorCandidate[]
    remember(rows)

    if (!rows.some((row) => keyOf(row) === wanted))
      missing.value = new Set([...missing.value, wanted])
  } catch {
    // A request that did not come back is not an answer: the key shows as it is until the next try.
  }
}

watch(key, (wanted) => void name(wanted), { immediate: true })

const chosen = computed(() => (key.value === '' ? null : (known.value.get(key.value) ?? null)))

/** A mark for the chosen one: the server has none by that key, or the site does not show it. */
const mark = computed(() => {
  if (key.value === '') return null
  if (chosen.value === null)
    return missing.value.has(key.value)
      ? (props.missingText ?? t('configurator.picker-missing'))
      : null

  return chosen.value.visible ? null : t('configurator.picker-off')
})

const options = computed<AutocompleteOption[]>(() =>
  candidates.value
    .filter((one) => keyOf(one) && keyOf(one) !== key.value)
    .map((one) => ({
      value: one.title,
      description: [keyOf(one), one.visible ? null : t('configurator.picker-off')]
        .filter((part): part is string => typeof part === 'string' && part !== '')
        .join(' · '),
      id: keyOf(one),
    })),
)

async function search(query: string): Promise<void> {
  searching.value = true

  try {
    const found = (await api.search(query)) as ConfiguratorCandidate[]
    candidates.value = found
    remember(found)
  } catch {
    candidates.value = []
  } finally {
    searching.value = false
  }
}

/** A site has a handful: the list is there on the first click, before anything is typed. */
function opened(): void {
  if (candidates.value.length === 0 && !searching.value) void search(term.value)
}

function pick(option: AutocompleteOption): void {
  if (typeof option.id !== 'string' || option.id === '') return

  value.value = option.id

  // The box writes the picked name into itself after this handler; emptied on the next tick it
  // reads as a search box again rather than as a second copy of the choice above it.
  void nextTick(() => {
    term.value = ''
  })
}

function clear(): void {
  value.value = null
}

const placeholder = computed(() => props.placeholder ?? t('configurator.picker-search'))
</script>

<template>
  <div class="wx-configurator-picker">
    <div v-if="key !== ''" class="wx-configurator-picker__chosen">
      <span class="wx-configurator-picker__text">
        <span class="wx-configurator-picker__title">{{ chosen?.title ?? key }}</span>
        <span v-if="chosen" class="wx-configurator-picker__key">{{ key }}</span>
      </span>
      <wx-badge v-if="mark" type="warning">{{ mark }}</wx-badge>
      <wx-action
        v-if="!props.disabled"
        type="remove"
        size="sm"
        :title="props.removeText ?? t('configurator.picker-remove')"
        @click="clear"
      />
    </div>
    <p v-else class="wx-configurator-picker__empty">
      {{ props.emptyText ?? t('configurator.picker-empty') }}
    </p>

    <!-- A wrapper rather than a class on the box: its root is a fragment, and a scoped rule on it
         would reach nothing. -->
    <div class="wx-configurator-picker__search">
      <wx-autocomplete
        v-model="term"
        :options="options"
        remote
        teleport
        :loading="searching"
        :loading-text="t('configurator.picker-searching')"
        :empty-text="props.nothingText ?? t('configurator.picker-nothing')"
        :placeholder="placeholder"
        :aria-label="placeholder"
        :disabled="props.disabled"
        @search="search"
        @open="opened"
        @select="pick"
      />
    </div>
  </div>
</template>

<style scoped>
.wx-configurator-picker {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-configurator-picker__chosen {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
  padding: var(--wx-space-6) var(--wx-space-8) var(--wx-space-6) var(--wx-space-12);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  background: var(--wx-bg-surface);
}

.wx-configurator-picker__text {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  min-width: 0;
}

.wx-configurator-picker__title,
.wx-configurator-picker__key {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-configurator-picker__key,
.wx-configurator-picker__empty {
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-configurator-picker__empty {
  margin: 0;
}

.wx-configurator-picker :deep(.wx-badge) {
  flex: none;
}
</style>
