<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxAlert,
  WxAutocomplete,
  WxBadge,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSortableList,
  WxSpace,
  WxSwitch,
  WxText,
  type AutocompleteOption,
} from '@webx-ui/core'
import { createSeoApi } from './api'
import { useSeoMessages } from './i18n'
import type { SeoLinkBlock, SeoLinkProblem, SeoLinkSaved } from './types'

/**
 * One donor and its block (§18.4): the page, an optional heading, and the links in the order
 * they are printed.
 *
 * A link whose acceptor leads nowhere is kept and marked rather than refused: a link that broke
 * after it was written would otherwise stop the block from being saved for a new heading (§18.8).
 */
const props = withDefaults(defineProps<{ block?: SeoLinkBlock | null }>(), { block: null })

const { resolve, dismiss, open } = useModal<SeoLinkBlock>()

const context = useAdmin()
const api = createSeoApi(context)
useSeoMessages()

const t = useTranslate('webx-seo')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

interface Row {
  /* Rows are keyed by something that survives a drag; the position does not. */
  key: number
  acceptor: string
  anchor: string
  /** What the server said about the acceptor when the block was read; gone once it is edited. */
  broken: boolean
  saved: string | null
}

let next = 0

const loading = ref(false)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
/** What a save replaced on the way — said after it, with the dialog still open to read it. */
const warnings = ref<SeoLinkProblem[]>([])
const saved = ref<SeoLinkSaved | null>(null)

const donor = ref('')
const heading = ref('')
const active = ref(true)
const rows = ref<Row[]>([])
const locale = ref<string | null>(null)

const suggestions = ref<AutocompleteOption[]>([])
const searching = ref(false)

const editing = computed(() => props.block !== null || saved.value !== null)

function fill(block: SeoLinkBlock | null): void {
  donor.value = block?.donor.url ?? ''
  heading.value = block?.heading ?? ''
  active.value = block?.is_active ?? true
  locale.value = block?.donor.locale ?? null
  rows.value = (block?.items ?? []).map((item) => ({
    key: next++,
    acceptor: item.acceptor.url,
    anchor: item.anchor,
    broken: item.acceptor.broken,
    saved: item.acceptor.saved_url !== item.acceptor.url ? item.acceptor.saved_url : null,
  }))
}

/* The list leaves the links out, so an existing block is read whole before it is shown. */
watch(
  () => props.block,
  async (block) => {
    errors.value = {}
    warnings.value = []
    saved.value = null
    fill(block)

    if (block === null) {
      add()

      return
    }

    loading.value = true

    try {
      fill(await api.link(block.id))
    } catch (error) {
      toast.danger(message(error))
    } finally {
      loading.value = false
    }
  },
  { immediate: true },
)

function add(): void {
  rows.value.push({ key: next++, acceptor: '', anchor: '', broken: false, saved: null })
}

function drop(index: number): void {
  rows.value.splice(index, 1)
}

/* The server's errors name a link by its place in what was sent; a drag changes that place, so
   they belong to the order that was saved and are cleared by the next save. */
function errorOf(field: string): string | undefined {
  return errors.value[field]?.[0]
}

function edited(row: Row): void {
  row.broken = false
  row.saved = null
}

async function search(term: string): Promise<void> {
  searching.value = true

  try {
    suggestions.value = (await api.linkAddresses(term, locale.value)).map((address) => ({
      value: address.url,
      description: address.entity_type ?? undefined,
    }))
  } catch {
    // A suggestion is a courtesy; the field still takes whatever is typed.
    suggestions.value = []
  } finally {
    searching.value = false
  }
}

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}
  warnings.value = []

  const input = {
    donor: donor.value.trim(),
    heading: heading.value.trim() === '' ? null : heading.value.trim(),
    is_active: active.value,
    items: rows.value
      .filter((row) => row.acceptor.trim() !== '' || row.anchor.trim() !== '')
      .map((row) => ({ acceptor: row.acceptor.trim(), anchor: row.anchor.trim() })),
  }

  const id = saved.value?.id ?? props.block?.id ?? null

  try {
    const result = id === null ? await api.createLink(input) : await api.updateLink(id, input)

    toast.success(t('page.saved'))

    if (result.warnings.length === 0) {
      resolve(result)

      return
    }

    // Stays open: what was replaced is worth reading, and the form now shows what was kept.
    saved.value = result
    warnings.value = result.warnings
    fill(result)
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) {
      errors.value = body.errors
      toast.danger(t('page.failed'))
    } else {
      toast.danger(message(error, t('page.failed')))
    }
  } finally {
    saving.value = false
  }
}

/** Closing after a save that had something to say still reports the save to the list. */
function close(): void {
  if (saved.value !== null) resolve(saved.value)
  else dismiss()
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('links.block')" :width="760" @close="close">
    <div class="wx-seo-link">
      <wx-alert
        v-if="warnings.length > 0"
        type="warning"
        variant="soft"
        :title="t('links.saved-warnings')"
      >
        <ul class="wx-seo-link__notes">
          <li v-for="(warning, index) in warnings" :key="index">{{ warning.message }}</li>
        </ul>
      </wx-alert>

      <wx-form-item
        :label="t('links.donor')"
        :help="t('links.donor-help')"
        :error="errorOf('donor')"
      >
        <wx-input v-model="donor" placeholder="/catalog/phones" :disabled="loading" />
      </wx-form-item>

      <div class="wx-seo-link__row">
        <wx-form-item
          :label="t('links.heading')"
          :help="t('links.heading-help')"
          :error="errorOf('heading')"
        >
          <wx-input v-model="heading" :disabled="loading" />
        </wx-form-item>

        <wx-form-item>
          <wx-switch v-model="active" :label="t('page.active')" />
        </wx-form-item>
      </div>

      <wx-sortable-list
        v-model="rows"
        item-key="key"
        :item-label="(row: Row) => row.anchor || row.acceptor"
        :title="t('links.links')"
        :empty-text="t('links.no-links')"
        :drag-label="t('links.drag')"
      >
        <template #default="{ item, index }">
          <div class="wx-seo-link__item" :class="{ 'is-broken': item.broken }">
            <wx-form-item
              :label="t('links.acceptor')"
              :error="errorOf(`items.${index}.acceptor`)"
              class="wx-seo-link__field"
            >
              <wx-autocomplete
                v-model="item.acceptor"
                :options="suggestions"
                remote
                :loading="searching"
                :min-length="1"
                teleport
                placeholder="/catalog/phones/apple"
                :status="item.broken ? 'error' : undefined"
                @search="search"
                @change="edited(item)"
              />
              <wx-text v-if="item.broken" size="sm" tone="danger">
                {{ t('links.broken-help') }}
              </wx-text>
              <wx-text v-else-if="item.saved" size="sm" tone="muted">
                {{ t('links.was', { url: item.saved }) }}
              </wx-text>
            </wx-form-item>

            <wx-form-item
              :label="t('links.anchor')"
              :error="errorOf(`items.${index}.anchor`)"
              class="wx-seo-link__field"
            >
              <wx-input v-model="item.anchor" />
            </wx-form-item>

            <wx-badge v-if="item.broken" type="danger" class="wx-seo-link__badge">
              {{ t('links.gone') }}
            </wx-badge>
          </div>
        </template>

        <template #actions="{ index }">
          <wx-button
            variant="text"
            size="sm"
            icon="trash"
            :aria-label="t('links.remove-link')"
            @click="drop(index)"
          />
        </template>
      </wx-sortable-list>

      <div>
        <wx-button variant="outline" size="sm" icon="plus" @click="add">
          {{ t('links.add-link') }}
        </wx-button>
      </div>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="close">
          {{ saved ? t('links.done') : t('page.cancel') }}
        </wx-button>
        <wx-button type="primary" :loading="saving" :disabled="loading" @click="save">
          {{ editing ? t('page.save') : t('links.new') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-seo-link {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  container-type: inline-size;
}

.wx-seo-link__row {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: var(--wx-space-12);
  align-items: start;
}

.wx-seo-link__notes {
  margin: 0;
  padding-inline-start: var(--wx-space-16);
}

.wx-seo-link__item {
  display: grid;
  grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
  gap: var(--wx-space-8);
  align-items: start;
  min-width: 0;
  padding-block: var(--wx-space-4);
}

.wx-seo-link__field {
  min-width: 0;
}

.wx-seo-link__badge {
  grid-column: 1 / -1;
  justify-self: start;
}

/* The badge says it once; on a wide row the red field already does. */
@container (min-width: 520px) {
  .wx-seo-link__badge {
    display: none;
  }
}

@container (max-width: 519px) {
  .wx-seo-link__item {
    grid-template-columns: minmax(0, 1fr);
  }

  .wx-seo-link__row {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
