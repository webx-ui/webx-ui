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
      <div class="wx-blocks-page__tools">
        <wx-input v-model="search" :placeholder="t('page.search')" clearable />
        <wx-button v-if="canManage" type="primary" icon="plus" @click="add">
          {{ t('page.new') }}
        </wx-button>
      </div>
    </div>

    <wx-alert
      v-if="!meta.editing"
      type="info"
      variant="soft"
      :description="t('page.editing-off')"
    />

    <wx-skeleton v-if="loading" :rows="4" />

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
  gap: var(--wx-space-16);
  container-type: inline-size;
}

.wx-blocks-page__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-12);
  flex-wrap: wrap;
}

.wx-blocks-page__tools {
  display: flex;
  gap: var(--wx-space-8);
  align-items: center;
  flex-wrap: wrap;
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
  color: var(--wx-color-text-muted);
}

.wx-blocks-page__cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: var(--wx-space-16);
}
</style>
