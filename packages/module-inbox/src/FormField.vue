<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { WxAction, WxAutocomplete, WxBadge, type AutocompleteOption } from '@webx-ui/core'
import { createRelationsApi, useAdmin, useTranslate } from '@webx-ui/module-admin'
import { useInboxMessages } from './i18n'
import type { FormCandidate } from './types'

/**
 * `wx-inbox-form`: one form of the Inbox, chosen by name and kept by its slug — the value
 * `<x-webx-inbox::form slug="…" />` takes and the blocks that print a form have always held as
 * typed text. So a block written before this field opens with its form already chosen.
 *
 * The forms come from the panel's relation target `inbox-form` (`GET /api/cms/relations/inbox-form`),
 * the one a vacancy picks its application form from: no inbox permission is needed to choose a
 * form, only to read what it receives. The target searches the slug as well as the title and puts
 * the exact slug first, which is how a stored slug is named when the field opens.
 *
 * A slug no form has stays in the field, marked: dropping it silently would change the block
 * without anybody touching it, and the server refuses such a slug only when it is written anew.
 */
defineOptions({ name: 'WxInboxFormField' })

const props = withDefaults(
  defineProps<{
    disabled?: boolean
    placeholder?: string
    emptyText?: string
  }>(),
  { disabled: false, placeholder: undefined, emptyText: undefined },
)

const value = defineModel<string | null>({ default: null })

useInboxMessages()

const admin = useAdmin()
const t = useTranslate('webx-inbox')
const api = createRelationsApi(admin, 'inbox-form')

const slug = computed(() => (typeof value.value === 'string' ? value.value.trim() : ''))

/** Forms the server described, by slug, kept for the life of the field. */
const known = ref(new Map<string, FormCandidate>())
/** Slugs the server was asked about and has no form for. */
const missing = ref(new Set<string>())

const candidates = ref<FormCandidate[]>([])
const searching = ref(false)
const term = ref('')

function remember(rows: FormCandidate[]): void {
  const next = new Map(known.value)

  for (const row of rows) if (row.slug) next.set(row.slug, row)

  known.value = next
}

async function name(wanted: string): Promise<void> {
  if (wanted === '' || known.value.has(wanted) || missing.value.has(wanted)) return

  try {
    const rows = (await api.search(wanted)) as FormCandidate[]
    remember(rows)

    if (!rows.some((row) => row.slug === wanted))
      missing.value = new Set([...missing.value, wanted])
  } catch {
    // A request that did not come back is not an answer: the slug shows as it is until the next try.
  }
}

watch(slug, (wanted) => void name(wanted), { immediate: true })

const chosen = computed(() => (slug.value === '' ? null : (known.value.get(slug.value) ?? null)))

/** A mark for the chosen form: the server has none by that slug, or the site does not print it. */
const mark = computed(() => {
  if (slug.value === '') return null
  if (chosen.value === null)
    return missing.value.has(slug.value) ? t('relations.field-missing') : null

  return chosen.value.visible ? null : t('relations.field-off')
})

const options = computed<AutocompleteOption[]>(() =>
  candidates.value
    .filter((one) => one.slug && one.slug !== slug.value)
    .map((one) => ({
      value: one.title,
      description: [one.slug, one.visible ? null : t('relations.field-off')]
        .filter((part): part is string => typeof part === 'string' && part !== '')
        .join(' · '),
      id: one.slug,
    })),
)

async function search(query: string): Promise<void> {
  searching.value = true

  try {
    const found = (await api.search(query)) as FormCandidate[]
    candidates.value = found
    remember(found)
  } catch {
    candidates.value = []
  } finally {
    searching.value = false
  }
}

/** A site has a handful of forms: the list is there on the first click, before anything is typed. */
function opened(): void {
  if (candidates.value.length === 0 && !searching.value) void search(term.value)
}

function pick(option: AutocompleteOption): void {
  if (typeof option.id !== 'string' || option.id === '') return

  value.value = option.id

  // The box writes the picked title into itself after this handler; emptied on the next tick it
  // reads as a search box again rather than as a second copy of the choice above it.
  void nextTick(() => {
    term.value = ''
  })
}

function clear(): void {
  value.value = null
}

const placeholder = computed(() => props.placeholder ?? t('relations.field-search'))
</script>

<template>
  <div class="wx-inbox-form-field">
    <div v-if="slug !== ''" class="wx-inbox-form-field__chosen">
      <span class="wx-inbox-form-field__text">
        <span class="wx-inbox-form-field__title">{{ chosen?.title ?? slug }}</span>
        <span v-if="chosen" class="wx-inbox-form-field__slug">{{ slug }}</span>
      </span>
      <wx-badge v-if="mark" type="warning">{{ mark }}</wx-badge>
      <wx-action
        v-if="!props.disabled"
        type="remove"
        size="sm"
        :title="t('relations.field-remove')"
        @click="clear"
      />
    </div>
    <p v-else class="wx-inbox-form-field__empty">
      {{ props.emptyText ?? t('relations.field-empty') }}
    </p>

    <!-- A wrapper rather than a class on the box: its root is a fragment, and a scoped rule on it
         would reach nothing. -->
    <div class="wx-inbox-form-field__search">
      <wx-autocomplete
        v-model="term"
        :options="options"
        remote
        teleport
        :loading="searching"
        :loading-text="t('relations.field-searching')"
        :empty-text="t('relations.field-nothing')"
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
.wx-inbox-form-field {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-inbox-form-field__chosen {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
  padding: var(--wx-space-6) var(--wx-space-8) var(--wx-space-6) var(--wx-space-12);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  background: var(--wx-bg-surface);
}

.wx-inbox-form-field__text {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  min-width: 0;
}

.wx-inbox-form-field__title,
.wx-inbox-form-field__slug {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-inbox-form-field__slug,
.wx-inbox-form-field__empty {
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-inbox-form-field__empty {
  margin: 0;
}

.wx-inbox-form-field :deep(.wx-badge) {
  flex: none;
}
</style>
