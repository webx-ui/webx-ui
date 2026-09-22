<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import {
  WxAutocomplete,
  WxCheckbox,
  WxInput,
  WxSegmented,
  WxSelect,
  WxText,
  type AutocompleteOption,
  type InputModelValue,
  type SelectOption,
} from '@webx-ui/core'
import { useAdmin } from './admin'
import { useI18n, useTranslate } from './i18n'
import {
  createLinksApi,
  emptyLink,
  type LinkCandidate,
  type LinkRel,
  type LinkSourceInfo,
  type LinkTarget,
  type LinkValue,
} from './links'

/**
 * Where a link goes, chosen rather than typed.
 *
 * Three kinds of target and the kind is said out loud, because "there is no entity, so it must be a
 * URL" cannot tell a choice nobody has finished from one that deliberately goes nowhere. Which
 * sections are on offer is the server's answer: a source appears once its module is installed and
 * the reader may open it, so this component knows about pages and articles only in the sense that
 * it draws whatever came back.
 *
 * What is chosen is the entity, never its address. The title and the address beside it are only
 * there to show — a value saved last month has a morph pair and nothing to draw, which is what the
 * resolve on mount is for.
 */
defineOptions({ name: 'WxLinkPicker' })

const props = withDefaults(
  defineProps<{
    /** Off where a link that goes nowhere makes no sense — a button, a card. */
    allowNone?: boolean
    /** Off where the two attributes are somebody else's business, as in a menu item's own form. */
    attributes?: boolean
    disabled?: boolean
  }>(),
  { allowNone: true, attributes: true, disabled: false },
)

const value = defineModel<LinkValue | null>({ default: null })

const admin = useAdmin()
const i18n = useI18n()
const api = createLinksApi(admin)
const t = useTranslate('webx-admin')

const sources = ref<LinkSourceInfo[]>([])
const candidates = ref<LinkCandidate[]>([])
const routes = ref<{ name: string; path: string }[]>([])
const searching = ref(false)

/** The section being searched in, which is not part of the value: what is stored is the type. */
const section = ref<string | null>(null)

/** What the input shows: the chosen entity's title, or what somebody is typing. */
const term = ref('')

/** The entity behind the saved value, once the server has said what it is called. */
const chosen = ref<LinkCandidate | null>(null)

/**
 * The one search to ignore: the title this component just wrote into the field itself.
 *
 * Writing a value into `WxAutocomplete` is indistinguishable from typing it, so opening a form
 * with a saved link asks the server to search for the name of the page that is already chosen —
 * once per link field on the form. Held as the text rather than as a flag, because a flag would
 * have to be cleared on a timer and would swallow whatever the editor typed first.
 */
let echo: string | null = null

const link = computed<LinkValue>(() => value.value ?? emptyLink())

const targets = computed(() => {
  const options = [
    { value: 'entity', label: t('links.target-entity') },
    { value: 'url', label: t('links.target-url') },
  ]

  return props.allowNone ? [...options, { value: 'none', label: t('links.target-none') }] : options
})

const sectionOptions = computed<SelectOption[]>(() =>
  sources.value.map((source) => ({ value: source.type, label: source.title })),
)

/**
 * The results, with the two things that are not the title: where it lives, and whether the site
 * would show it. A draft stays in the list — it is drawn dimmed and says so — because a menu is
 * built before the pages in it are published.
 */
const options = computed<AutocompleteOption[]>(() =>
  candidates.value.map((candidate) => ({
    value: candidate.title,
    description: hint(candidate),
    id: candidate.id,
  })),
)

/** The addresses of the site, offered so that nobody has to remember `/account`. */
const routeOptions = computed<AutocompleteOption[]>(() =>
  routes.value.map((route) => ({ value: route.path, description: route.name })),
)

onMounted(async () => {
  sources.value = await api.sources()

  // The section the value already names, so opening a saved link lands in the right list.
  section.value = link.value.entity_type ?? sources.value[0]?.type ?? null

  await Promise.all([restore(), loadRoutes()])
})

/*
 * What a saved value cannot carry: the title of the thing it points at. Asked for once, on open,
 * and again whenever the pair changes from outside — a form that discarded a draft, an undo.
 */
watch(
  () => [link.value.entity_type, link.value.entity_id].join(':'),
  () => {
    void restore()
  },
)

async function restore(): Promise<void> {
  const { entity_type: type, entity_id: id } = link.value

  if (type === null || id === null) {
    chosen.value = null
    term.value = ''

    return
  }

  if (chosen.value?.id === id) {
    return
  }

  const [resolved] = await api.resolve([{ type, id }], i18n.state.locale)

  chosen.value = resolved ?? null
  term.value = resolved?.title ?? t('links.missing')
  echo = term.value
}

async function loadRoutes(): Promise<void> {
  routes.value = await api.routes()
}

async function search(query: string): Promise<void> {
  if (query === echo) {
    echo = null

    return
  }

  echo = null

  if (section.value === null) {
    return
  }

  searching.value = true

  try {
    candidates.value = await api.search(section.value, query, i18n.state.locale)
  } finally {
    searching.value = false
  }
}

function setTarget(target: string | number | undefined): void {
  if (target === undefined) {
    return
  }

  // Only the kind changes: an editor who switches to an address and back should find the page
  // they had chosen still chosen, so nothing else in the value is cleared here.
  value.value = { ...link.value, target: target as LinkTarget }
}

function setSection(type: unknown): void {
  section.value = type === null || type === undefined ? null : String(type)
  candidates.value = []
  void search('')
}

function pick(option: AutocompleteOption): void {
  const candidate = candidates.value.find((one) => one.id === option.id)

  if (candidate === undefined || section.value === null) {
    return
  }

  chosen.value = candidate
  term.value = candidate.title
  value.value = { ...link.value, entity_type: section.value, entity_id: candidate.id }
}

function clearEntity(): void {
  chosen.value = null
  term.value = ''
  candidates.value = []
  value.value = { ...link.value, entity_type: null, entity_id: null }
}

function setUrl(url: string): void {
  value.value = { ...link.value, url: url === '' ? null : url }
}

/**
 * The `#` is taken off as it is typed rather than on the way to the server: the field shows one
 * of its own, so leaving a second in the value would put two in front of the editor.
 */
function setHash(hash: InputModelValue): void {
  // A string, always: the field is not localized, so the map half of `InputModelValue` never
  // arrives here — and anything that did would be a value this field cannot mean.
  const anchor = (typeof hash === 'string' || typeof hash === 'number' ? String(hash) : '')
    .replace(/^#+/, '')
    .trim()

  value.value = { ...link.value, hash: anchor === '' ? null : anchor }
}

function setNewTab(on: boolean): void {
  value.value = { ...link.value, new_tab: on }
}

function toggleRel(rel: LinkRel, on: boolean): void {
  const kept = link.value.rel.filter((one) => one !== rel)

  value.value = { ...link.value, rel: on ? [...kept, rel] : kept }
}

function hint(candidate: LinkCandidate): string {
  const parts = [candidate.hint, candidate.url ?? undefined].filter(
    (part): part is string => typeof part === 'string' && part !== '',
  )

  if (!candidate.available) {
    parts.push(t('links.unavailable'))
  }

  return parts.join(' · ')
}

const REL: LinkRel[] = ['nofollow', 'sponsored', 'ugc']
</script>

<template>
  <div class="wx-link-picker">
    <wx-segmented
      :options="targets"
      :model-value="link.target"
      :disabled="props.disabled"
      :aria-label="t('links.target-entity')"
      @update:model-value="setTarget"
    />

    <div v-if="link.target === 'entity'" class="wx-link-picker__entity">
      <wx-select
        v-if="sectionOptions.length > 1"
        class="wx-link-picker__section"
        :options="sectionOptions"
        :model-value="section"
        :disabled="props.disabled"
        :aria-label="t('links.section')"
        @update:model-value="setSection"
      />

      <wx-autocomplete
        class="wx-link-picker__search"
        :model-value="term"
        :options="options"
        remote
        clearable
        :loading="searching"
        :loading-text="t('links.searching')"
        :empty-text="t('links.empty')"
        :placeholder="t('links.search')"
        :aria-label="t('links.search')"
        :disabled="props.disabled"
        @update:model-value="(text: string) => (term = text)"
        @search="search"
        @select="pick"
        @clear="clearEntity"
      />
    </div>

    <div v-else-if="link.target === 'url'" class="wx-link-picker__url">
      <wx-autocomplete
        :model-value="link.url ?? ''"
        :options="routeOptions"
        clearable
        open-on-focus
        :empty-text="t('links.url-routes')"
        :placeholder="t('links.url-placeholder')"
        :aria-label="t('links.url-label')"
        :disabled="props.disabled"
        @update:model-value="setUrl"
      />
    </div>

    <!--
      The anchor belongs to both kinds of target: an address can carry one inline, a chosen page
      has nowhere else to put one. The `#` is drawn rather than typed, so what is in the field is
      the name and nothing else.
    -->
    <label v-if="link.target !== 'none'" class="wx-link-picker__hash">
      <wx-text size="sm" tone="muted">{{ t('links.hash') }}</wx-text>
      <span class="wx-link-picker__sign" aria-hidden="true">#</span>
      <wx-input
        :model-value="link.hash ?? ''"
        :placeholder="t('links.hash-placeholder')"
        :aria-label="t('links.hash')"
        :disabled="props.disabled"
        clearable
        @update:model-value="setHash"
      />
    </label>

    <!--
      Nothing to ask about a link that goes nowhere, and the two attributes below would be a
      question about something that is not there.
    -->
    <div v-if="props.attributes && link.target !== 'none'" class="wx-link-picker__attributes">
      <wx-checkbox
        :model-value="link.new_tab"
        :disabled="props.disabled"
        @update:model-value="(on: boolean) => setNewTab(on)"
      >
        {{ t('links.new-tab') }}
      </wx-checkbox>

      <div class="wx-link-picker__rel">
        <wx-text size="sm" tone="muted">{{ t('links.rel') }}</wx-text>
        <wx-checkbox
          v-for="rel in REL"
          :key="rel"
          :model-value="link.rel.includes(rel)"
          :disabled="props.disabled"
          @update:model-value="(on: boolean) => toggleRel(rel, on)"
        >
          {{ t(`links.rel-${rel}`) }}
        </wx-checkbox>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wx-link-picker {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-link-picker__entity {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
}

/*
 * `:deep()`, because neither of these is one of our elements: `WxSelect` and `WxAutocomplete`
 * carry a portal beside the control, which makes their root a fragment — and Vue puts the scope
 * attribute only on a child that has a single root. Without it the rules match nothing at all,
 * silently, and both controls stand at their natural width.
 */
.wx-link-picker__entity > :deep(.wx-link-picker__section) {
  flex: 0 0 auto;
  min-width: 160px;
}

.wx-link-picker__entity > :deep(.wx-link-picker__search) {
  flex: 1 1 220px;
  min-width: 0;
}

/*
 * The `#` sits in the row above the field rather than inside it: an addon inside the control is
 * the design system's business and this field does not have one, and a sign drawn over the input
 * would be a second thing to keep in step with its padding.
 */
.wx-link-picker__hash {
  display: grid;
  grid-template-columns: auto 1fr;
  align-items: center;
  gap: var(--wx-space-4) var(--wx-space-6);
}

.wx-link-picker__hash > :first-child {
  grid-column: 1 / -1;
}

.wx-link-picker__sign {
  color: var(--wx-text-placeholder);
}

.wx-link-picker__attributes {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

/*
 * A column, not a wrapping row. Measured in the panel's editing column at 330px: the heading sat
 * on the first checkbox's line and the other two stacked under it, so "Relationship" read as the
 * name of the first box rather than of the three. Three labels of this length never share a row
 * at that width anyway.
 */
.wx-link-picker__rel {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
}
</style>
