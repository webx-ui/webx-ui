<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import {
  createModal,
  toast,
  WxAlert,
  WxButton,
  WxEmpty,
  WxHeading,
  WxInput,
  WxSkeleton,
  WxSkeletonItem,
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

  if (needle === '') return blocks.value

  return blocks.value.filter(
    (block) =>
      block.title.toLowerCase().includes(needle) ||
      block.slug.includes(needle) ||
      (block.description ?? '').toLowerCase().includes(needle),
  )
})

/** The groups in the site's order, then any a type named that the config does not. */
const groups = computed(() => {
  const order = [...meta.value.groups]

  for (const block of shown.value) {
    if (!order.includes(block.group)) order.push(block.group)
  }

  const sections = order
    .map((id) => ({
      id,
      label: groupLabel(id, t),
      blocks: shown.value.filter((b) => b.group === id),
    }))
    .filter((section) => section.blocks.length > 0)

  // A handful of types needs no headings; a search wants a flat list.
  const flat = shown.value.length <= 5 || search.value.trim() !== '' || sections.length < 2

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
  <div class="wx-blocks-page">
    <div class="wx-blocks-page__head">
      <wx-heading :level="2">{{ title }}</wx-heading>
      <wx-button v-if="canManage" type="primary" icon="plus" @click="add">
        {{ t('page.new') }}
      </wx-button>
    </div>

    <wx-alert
      v-if="!meta.editing"
      type="info"
      variant="soft"
      :description="t('page.editing-off')"
    />

    <!-- The search stands over the grid it narrows, not in the corner beside the button. -->
    <wx-input
      v-if="loading || blocks.length > 0"
      v-model="search"
      class="wx-blocks-page__search"
      :placeholder="t('page.search')"
      clearable
    />

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
  </div>
</template>

<style scoped>
.wx-blocks-page {
  display: flex;
  flex-direction: column;
  gap: var(--wx-gap, var(--wx-space-16));
  container-type: inline-size;
}

.wx-blocks-page__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-12);
  flex-wrap: wrap;
}

.wx-blocks-page__search {
  max-width: 360px;
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
