<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxListScreen,
  type ScreenAction,
} from '@webx-ui/module-admin'
import {
  createModal,
  toast,
  WxAlert,
  WxEmpty,
  WxInput,
  WxSkeleton,
  WxSkeletonItem,
  type TabItem,
  type TabValue,
} from '@webx-ui/core'
import { createBlocksApi } from './api'
import BlockCard from './BlockCard.vue'
import BlockCreateDialog from './BlockCreateDialog.vue'
import BlockDeclaredCard from './BlockDeclaredCard.vue'
import { useBlocksMessages } from './i18n'
import { groupLabel, kindOf } from './schema'
import type { BlocksMeta, BlockType, DeclaredComponent } from './types'

/**
 * The section: every type as a card with a live thumbnail, grouped the way the picker groups
 * them. Cards rather than a table because a block is recognised by its picture — "Section"
 * says nothing about whether it is a full-width band or a container with columns.
 *
 * Two kinds, two groups (§3.9 of the components spec): the blocks editors put on pages, then
 * the components templates call. The places modules declared and the site has not customised
 * stand among the components — a place nobody sees is a possibility nobody learns about.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/blocks' })

const context = useAdmin()
const api = createBlocksApi(context)
const router = useRouter()
useBlocksMessages()

const t = useTranslate('webx-blocks')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const types = ref<BlockType[]>([])
const declared = ref<DeclaredComponent[]>([])
const loading = ref(true)
const search = ref('')
/** Which group is being looked at; `''` is all of them, grouped. */
const view = ref<TabValue>('')
/** The slug being customised, while the request is out. */
const customising = ref<string | null>(null)

/** The view of the components. Not a word a site could name a group of its own. */
const COMPONENTS = '@components'

const create = createModal<BlockType, Record<string, never>>(BlockCreateDialog)

const meta = computed<BlocksMeta>(() => {
  const found = context.state.manifest?.modules.find((module) => module.id === 'blocks')?.meta

  return {
    groups: (found?.groups as string[] | undefined) ?? [],
    editing: (found?.editing as boolean | undefined) ?? true,
    provides: (found?.provides as string[] | undefined) ?? [],
  }
})

const canManage = computed(() => context.can('blocks.manage') && meta.value.editing)

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'blocks')?.title ??
    t('module.title'),
)

const blocks = computed(() => types.value.filter((type) => kindOf(type) === 'block'))
const components = computed(() => types.value.filter((type) => kindOf(type) === 'component'))

/** The places still drawn by their module: a customised one is already a card of its own. */
const pending = computed(() => declared.value.filter((place) => !place.customised))

const hasComponents = computed(() => components.value.length > 0 || pending.value.length > 0)

/** A module's name as the navigation says it; its id, capitalised, for one the panel lacks. */
function moduleName(id: string): string {
  return (
    context.state.manifest?.modules.find((module) => module.id === id)?.title ??
    id.charAt(0).toUpperCase() + id.slice(1)
  )
}

/** Which module declared a slug, by name: the card of a customised place says whose it was. */
function declaredBy(slug: string): string | null {
  const place = declared.value.find((item) => item.slug === slug)

  return place ? moduleName(place.module) : null
}

function matches(fields: (string | null)[]): boolean {
  const needle = search.value.trim().toLowerCase()

  return needle === '' || fields.some((field) => (field ?? '').toLowerCase().includes(needle))
}

const shown = computed(() => {
  if (view.value === COMPONENTS) return []

  const inView =
    view.value === '' ? blocks.value : blocks.value.filter((block) => block.group === view.value)

  return inView.filter((block) => matches([block.title, block.slug, block.description]))
})

const shownComponents = computed(() =>
  view.value === '' || view.value === COMPONENTS
    ? components.value.filter((type) => matches([type.title, type.slug, type.description]))
    : [],
)

const shownPending = computed(() =>
  view.value === '' || view.value === COMPONENTS
    ? pending.value.filter((place) => matches([place.title, place.slug, place.description]))
    : [],
)

/** The groups in the site's order, then any a type named that the config does not. */
const order = computed(() => {
  const ids = [...meta.value.groups]

  for (const block of blocks.value) {
    if (!ids.includes(block.group)) ids.push(block.group)
  }

  return ids.filter((id) => blocks.value.some((block) => block.group === id))
})

/**
 * The groups as the views of the list (§10), with everything at once first — which is how a
 * section of ten types is read, and what a search wants — and the components last, as one view:
 * they have no groups, nobody picks them from a list. With a single group and no components
 * there is nothing to choose between, and the strip would be a control that says one thing.
 */
const views = computed<TabItem[]>(() => {
  const groupViews = order.value.map((id) => ({ value: id, label: groupLabel(id, t) }))
  const componentView = hasComponents.value
    ? [{ value: COMPONENTS, label: t('components.components') }]
    : []

  return groupViews.length + componentView.length < 2
    ? []
    : [{ value: '', label: t('page.all-groups') }, ...groupViews, ...componentView]
})

/** What the grid draws for the blocks: sections with headings, or one flat run of cards. */
const groups = computed(() => {
  const sections = order.value
    .map((id) => ({
      id,
      label: groupLabel(id, t),
      blocks: shown.value.filter((b) => b.group === id),
    }))
    .filter((section) => section.blocks.length > 0)

  // A handful of types needs no headings, a search wants a flat list, and a chosen group is
  // already named by the tab above it.
  const flat =
    view.value !== '' ||
    shown.value.length <= 5 ||
    search.value.trim() !== '' ||
    sections.length < 2

  if (!flat) return sections
  if (shown.value.length === 0) return []

  // Flat, but beside the components: the run of blocks still needs the one word that says
  // which of the two kinds it is.
  const both = view.value === '' && shownComponents.value.length + shownPending.value.length > 0

  return [{ id: '', label: both ? t('components.blocks') : '', blocks: shown.value }]
})

/** The components' heading, unless their own tab already names them or they are all there is. */
const componentsLabel = computed(() =>
  view.value === COMPONENTS || shown.value.length === 0 ? '' : t('components.components'),
)

const nothing = computed(() => types.value.length === 0 && pending.value.length === 0)

async function load(): Promise<void> {
  loading.value = true

  try {
    const list = await api.index()

    types.value = list.blocks
    declared.value = list.declared
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

function open(block: BlockType | { id: number }): void {
  void router.push(`${props.base}/${block.id}`)
}

async function add(): Promise<void> {
  const block = await create({})

  if (block) open(block)
}

/**
 * The declared place made the site's own: a draft of the module's view, not published — the site
 * keeps drawing the module's view until someone does (§4.2). So the editor opens on the draft
 * and the toast says the site has not changed yet, because that is the question on everyone's
 * mind after pressing a button called "Customise".
 */
async function customise(place: DeclaredComponent): Promise<void> {
  customising.value = place.slug

  try {
    const type = await api.customise(place.slug)

    toast.success(t('components.customised'))
    open(type)
  } catch (error) {
    // Already customised elsewhere (another tab, an agent): the answer names the type to open.
    const body = (error as { status?: number; body?: { id?: number } }).body

    if ((error as { status?: number }).status === 409 && typeof body?.id === 'number') {
      open({ id: body.id })

      return
    }

    toast.danger(message(error, t('components.customise-failed')))
  } finally {
    customising.value = null
  }
}

onMounted(load)

/* What the section offers. Declared, because on a phone the head folds it into the ···. */
const actions = computed<ScreenAction[]>(() =>
  canManage.value
    ? [{ key: 'new', label: t('page.new'), icon: 'plus', primary: true, run: () => void add() }]
    : [],
)
</script>

<template>
  <wx-list-screen
    v-model:view="view"
    class="wx-blocks-page"
    :title="title"
    :views="views"
    :actions="actions"
  >
    <wx-alert
      v-if="!meta.editing"
      type="info"
      variant="soft"
      :description="t('page.editing-off')"
    />

    <!-- Inside the card and along its top, where every other list of the panel keeps its
         search: on `Pages` it is the table's own row, and this grid has no table to put it in. -->
    <div v-if="loading || !nothing" class="wx-blocks-page__toolbar">
      <wx-input
        v-model="search"
        class="wx-blocks-page__search"
        :placeholder="t('page.search')"
        clearable
      />
    </div>

    <!-- The placeholder is shaped like what is coming: a row of cards, not a paragraph. -->
    <wx-skeleton v-if="loading">
      <template #template>
        <div class="wx-blocks-page__cards">
          <div v-for="n in 4" :key="n" class="wx-blocks-page__ghost">
            <wx-skeleton-item variant="image" :height="120" class="wx-blocks-page__ghost-thumb" />
            <div class="wx-blocks-page__ghost-body">
              <wx-skeleton-item variant="title" width="45%" />
              <wx-skeleton-item variant="text" width="70%" />
              <wx-skeleton-item variant="button" width="64px" height="22px" />
            </div>
          </div>
        </div>
      </template>
    </wx-skeleton>

    <wx-empty
      v-else-if="nothing"
      icon="grid"
      :title="t('page.empty')"
      :description="t('page.empty-help')"
    />

    <template v-else>
      <section v-for="group in groups" :key="group.id" class="wx-blocks-page__group">
        <div v-if="group.label" class="wx-blocks-page__group-title">{{ group.label }}</div>
        <div class="wx-blocks-page__cards">
          <block-card v-for="block in group.blocks" :key="block.id" :block="block" @open="open" />
        </div>
      </section>

      <section
        v-if="shownComponents.length > 0 || shownPending.length > 0"
        class="wx-blocks-page__group"
      >
        <div v-if="componentsLabel" class="wx-blocks-page__group-title">
          {{ componentsLabel }}
        </div>
        <div class="wx-blocks-page__cards">
          <block-card
            v-for="type in shownComponents"
            :key="type.id"
            :block="type"
            :module="declaredBy(type.slug)"
            @open="open"
          />
          <block-declared-card
            v-for="place in shownPending"
            :key="place.slug"
            :declared="place"
            :module="moduleName(place.module)"
            :can-manage="canManage"
            :busy="customising === place.slug"
            @customise="customise"
          />
        </div>
      </section>
    </template>
  </wx-list-screen>
</template>

<style scoped>
.wx-blocks-page__toolbar {
  display: flex;
  justify-content: flex-end;
}

.wx-blocks-page__search {
  width: 100%;
  max-width: 280px;
}

.wx-blocks-page__ghost {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
}

.wx-blocks-page__ghost-thumb {
  border-radius: 0;
}

.wx-blocks-page__ghost-body {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  padding: var(--wx-space-12) var(--wx-space-14);
}

.wx-blocks-page__group {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-blocks-page__group-title {
  font-size: var(--wx-font-size-xs);
  font-weight: var(--wx-font-weight-semibold);
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--wx-text-muted);
}

.wx-blocks-page__cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: var(--wx-gap, var(--wx-space-16));
}
</style>
