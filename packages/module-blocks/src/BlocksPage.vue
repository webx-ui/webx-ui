<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAdmin, useTranslate, WxListScreen } from '@webx-ui/module-admin'
import {
  createModal,
  toast,
  WxAlert,
  WxButton,
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
import { useBlocksMessages } from './i18n'
import { groupLabel } from './schema'
import type { BlocksMeta, BlockType } from './types'

/**
 * The section: every type as a card with a live thumbnail, grouped the way the picker groups
 * them. Cards rather than a table because a block is recognised by its picture — "Section"
 * says nothing about whether it is a full-width band or a container with columns.
 */
const props = withDefaults(defineProps<{ base?: string }>(), { base: '/blocks' })

const context = useAdmin()
const api = createBlocksApi(context)
const router = useRouter()
useBlocksMessages()

const t = useTranslate('webx-blocks')

const blocks = ref<BlockType[]>([])
const loading = ref(true)
const search = ref('')
/** Which group is being looked at; `''` is all of them, grouped. */
const view = ref<TabValue>('')

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

const shown = computed(() => {
  const needle = search.value.trim().toLowerCase()
  const inView =
    view.value === '' ? blocks.value : blocks.value.filter((block) => block.group === view.value)

  if (needle === '') return inView

  return inView.filter(
    (block) =>
      block.title.toLowerCase().includes(needle) ||
      block.slug.includes(needle) ||
      (block.description ?? '').toLowerCase().includes(needle),
  )
})

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
 * section of ten types is read, and what a search wants. Below two groups there is nothing to
 * choose between, and the strip would be a control that says one thing.
 */
const views = computed<TabItem[]>(() =>
  order.value.length < 2
    ? []
    : [
        { value: '', label: t('page.all-groups') },
        ...order.value.map((id) => ({ value: id, label: groupLabel(id, t) })),
      ],
)

/** What the grid draws: sections with headings, or one flat run of cards. */
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

  return flat ? [{ id: '', label: '', blocks: shown.value }] : sections
})

async function load(): Promise<void> {
  loading.value = true

  try {
    blocks.value = await api.list()
  } catch (error) {
    toast.danger((error as { body?: { message?: string } }).body?.message ?? String(error))
  } finally {
    loading.value = false
  }
}

function open(block: BlockType): void {
  void router.push(`${props.base}/${block.id}`)
}

async function add(): Promise<void> {
  const block = await create({})

  if (block) open(block)
}

onMounted(load)
</script>

<template>
  <wx-list-screen v-model:view="view" class="wx-blocks-page" :title="title" :views="views">
    <template v-if="canManage" #actions>
      <wx-button type="primary" icon="plus" @click="add">{{ t('page.new') }}</wx-button>
    </template>

    <wx-alert
      v-if="!meta.editing"
      type="info"
      variant="soft"
      :description="t('page.editing-off')"
    />

    <!-- Inside the card and along its top, where every other list of the panel keeps its
         search: on `Pages` it is the table's own row, and this grid has no table to put it in. -->
    <div v-if="loading || blocks.length > 0" class="wx-blocks-page__toolbar">
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
      v-else-if="blocks.length === 0"
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
