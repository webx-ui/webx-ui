<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import {
  WxAction,
  WxAutocomplete,
  WxBadge,
  WxSortableList,
  type AutocompleteOption,
} from '@webx-ui/core'
import { useAdmin } from '../admin'
import { HttpError } from '../http'
import { useTranslate } from '../i18n'
import {
  createRelationsApi,
  normaliseRelations,
  useRelationOwner,
  type RelationCandidate,
} from './api'

/**
 * The records of another section this one points at, in the order they were put in
 * (`wx-relations`): the services a recipe is for, the recipes that go with it.
 *
 * The value is ids and nothing else. What they are called, whether the site still shows them, the
 * candidates for "Add" — all of that is the target's to say, over `GET /api/cms/relations/{target}`
 * (§3.7 of the recipes spec): with `q` it searches, with `ids[]` it names what is already chosen.
 * The target is the schema's (`props.target`), and a target the server does not know never reaches
 * the panel — the server takes the node off the screen, so recipes without the services module
 * have no "Services" field at all rather than a broken one.
 *
 * A chosen record the site does not show — unpublished, in the bin — stays in the list, marked:
 * dropping it silently would change the record without anybody having touched it, and the site
 * skips it anyway. One the server does not know at all is drawn by number, for the same reason.
 *
 * An administrator who may not see the target's records gets a 403 on both questions, and the
 * field becomes a list of what is chosen and nothing more: no names to reorder by, nothing to add
 * from — and removing a record you cannot see is not a choice anybody can make well.
 *
 * The order is kept because a template reads it ("the main service first"); `sortable: false` is
 * for a choice whose order means nothing, as in a block's filter.
 */
defineOptions({ name: 'WxRelationsField' })

const props = withDefaults(
  defineProps<{
    /** The target's key in the server's registry: `service`, `recipe`. */
    target: string
    /** How many may be chosen; `null` for any number. */
    max?: number | null
    sortable?: boolean
    disabled?: boolean
    addText?: string
    emptyText?: string
  }>(),
  {
    max: null,
    sortable: true,
    disabled: false,
    addText: undefined,
    emptyText: undefined,
  },
)

const value = defineModel<number[] | null>({ default: () => [] })

const admin = useAdmin()
const owner = useRelationOwner()
const t = useTranslate('webx-admin')

const api = computed(() => createRelationsApi(admin, props.target))

/** What the server said about each id it was asked about, kept for the life of the field. */
const known = ref(new Map<number, RelationCandidate>())
/** Ids the server was asked to name and did not: gone, or behind a module that is not installed. */
const missing = ref(new Set<number>())
const forbidden = ref(false)

const candidates = ref<RelationCandidate[]>([])
const searching = ref(false)
const term = ref('')

const chosen = computed(() => normaliseRelations(value.value))

function remember(rows: RelationCandidate[]): void {
  const next = new Map(known.value)

  for (const row of rows) next.set(row.id, row)

  known.value = next
}

/**
 * Only what has no name yet is asked about: picking from the search already brought the name of
 * what was picked, and a form that asks again on every edit asks about a list it just drew.
 */
async function name(ids: number[]): Promise<void> {
  const unknown = ids.filter((id) => !known.value.has(id) && !missing.value.has(id))

  if (unknown.length === 0 || forbidden.value) return

  try {
    const rows = await api.value.named(unknown)
    remember(rows)

    const answered = new Set(rows.map((row) => row.id))
    missing.value = new Set([...missing.value, ...unknown.filter((id) => !answered.has(id))])
  } catch (error) {
    // Anything else is a request that did not come back, and the rows keep their numbers until
    // the next change asks again. A refusal is an answer, and it is the same for every question.
    if (error instanceof HttpError && error.status === 403) forbidden.value = true
  }
}

watch(
  () => props.target,
  () => {
    known.value = new Map()
    missing.value = new Set()
    forbidden.value = false
    candidates.value = []
    void name(chosen.value)
  },
)

watch(chosen, (ids) => void name(ids), { immediate: true })

interface Row {
  id: number
  title: string
  candidate: RelationCandidate | null
}

/** Writable, because that is how `WxSortableList` hands a reorder back. */
const rows = computed<Row[]>({
  get: () =>
    chosen.value.map((id) => {
      const candidate = known.value.get(id) ?? null

      return { id, title: candidate?.title || `#${id}`, candidate }
    }),
  set: (next) => {
    value.value = next.map((row) => row.id)
  },
})

const full = computed(() => props.max !== null && chosen.value.length >= props.max)

/** The record being edited is never its own relation — "similar recipes" is about the others. */
const self = computed(() => (owner !== null && owner.type === props.target ? owner.id.value : null))

const options = computed<AutocompleteOption[]>(() =>
  candidates.value
    .filter((one) => one.id !== self.value && !chosen.value.includes(one.id))
    .map((one) => ({
      value: one.title,
      description: [one.subtitle, one.visible ? null : t('relations.field-hidden')]
        .filter((part): part is string => typeof part === 'string' && part !== '')
        .join(' · '),
      id: one.id,
    })),
)

async function search(query: string): Promise<void> {
  searching.value = true

  try {
    const found = await api.value.search(query)
    candidates.value = found
    remember(found)
  } catch (error) {
    candidates.value = []
    if (error instanceof HttpError && error.status === 403) forbidden.value = true
  } finally {
    searching.value = false
  }
}

function pick(option: AutocompleteOption): void {
  const id = Number(option.id)

  if (!Number.isInteger(id) || chosen.value.includes(id) || full.value) return

  value.value = [...chosen.value, id]

  // The box writes the picked title into itself after this handler; emptied on the next tick it
  // is ready for the next one instead of holding the name of something already in the list.
  void nextTick(() => {
    term.value = ''
  })
}

function remove(id: number): void {
  value.value = chosen.value.filter((one) => one !== id)
}

const locked = computed(() => props.disabled || forbidden.value)

const placeholder = computed(() =>
  full.value
    ? t('relations.field-full', { max: String(props.max) })
    : (props.addText ?? t('relations.field-add')),
)

/** A mark for a row whose record the site does not show, or the server does not know. */
function mark(row: Row): string | null {
  if (row.candidate === null) return missing.value.has(row.id) ? t('relations.field-missing') : null

  return row.candidate.visible ? null : t('relations.field-hidden')
}
</script>

<template>
  <div class="wx-relations-field" :class="{ 'is-unordered': !props.sortable }">
    <wx-sortable-list
      v-model="rows"
      plain
      size="sm"
      item-key="id"
      item-label="title"
      :disabled="locked || !props.sortable"
      :empty-text="props.emptyText ?? t('relations.field-empty')"
      :drag-label="t('relations.field-drag')"
    >
      <template #default="{ item }">
        <img
          v-if="(item as Row).candidate?.thumb"
          class="wx-relations-field__thumb"
          :src="(item as Row).candidate!.thumb!"
          alt=""
        />
        <span class="wx-relations-field__text">
          <span class="wx-relations-field__title">{{ (item as Row).title }}</span>
          <span v-if="(item as Row).candidate?.subtitle" class="wx-relations-field__subtitle">
            {{ (item as Row).candidate!.subtitle }}
          </span>
        </span>
        <wx-badge v-if="mark(item as Row)" type="warning">{{ mark(item as Row) }}</wx-badge>
      </template>

      <template v-if="!locked" #actions="{ item }">
        <wx-action
          type="remove"
          size="sm"
          :title="t('relations.field-remove')"
          @click="remove((item as Row).id)"
        />
      </template>
    </wx-sortable-list>

    <p v-if="forbidden" class="wx-relations-field__note">
      {{ t('relations.field-forbidden') }}
    </p>

    <!-- A wrapper rather than a class on the box: its root is a fragment, and a scoped rule on it
         would reach nothing. -->
    <div v-else class="wx-relations-field__add">
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
        :disabled="props.disabled || full"
        @search="search"
        @select="pick"
      />
    </div>
  </div>
</template>

<style scoped>
.wx-relations-field {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

/* The row's cell is `WxSortableList`'s; the picture, the name and the mark make one line of it. */
.wx-relations-field :deep(.wx-sortable-list__content) {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

/* Not shrunk by the name beside it: in a flex row an image keeps its natural width otherwise. */
.wx-relations-field__thumb {
  flex: none;
  width: 32px;
  height: 32px;
  min-width: 0;
  object-fit: cover;
  border-radius: var(--wx-radius-xs);
  background: var(--wx-bg-fill);
}

.wx-relations-field__text {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  min-width: 0;
}

.wx-relations-field__title,
.wx-relations-field__subtitle {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-relations-field__subtitle,
.wx-relations-field__note {
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-relations-field__note {
  margin: 0;
}

/* A choice whose order means nothing has nothing to hold: a greyed grip would read as "locked". */
.wx-relations-field.is-unordered :deep(.wx-sortable-list__grip) {
  display: none;
}

.wx-relations-field :deep(.wx-badge) {
  flex: none;
}
</style>
