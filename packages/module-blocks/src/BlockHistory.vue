<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate, WxDate } from '@webx-ui/module-admin'
import { confirm, toast, WxBadge, WxButton, WxSkeleton, WxText } from '@webx-ui/core'
import { createBlocksApi } from './api'
import type { BlockType, BlockVersionMeta } from './types'

/**
 * A version per save, newest first, each with who and why. Restoring makes the old version a
 * new draft — publishing it is the same separate step it always is.
 */
const props = defineProps<{ block: BlockType; canManage: boolean }>()

const emit = defineEmits<{ restored: [block: BlockType] }>()

const context = useAdmin()
const api = createBlocksApi(context)
const t = useTranslate('webx-blocks')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const versions = ref<BlockVersionMeta[] | null>(null)

async function load(): Promise<void> {
  versions.value = await api.versions(props.block.id)
}

function state(version: BlockVersionMeta): 'draft' | 'live' | 'replaced' {
  if (props.block.draft?.number === version.number) return 'draft'
  if (props.block.published?.number === version.number) return 'live'

  return 'replaced'
}

async function restore(version: BlockVersionMeta): Promise<void> {
  const agreed = await confirm({
    title: t('page.restore-title', { number: version.number }),
    message: t('page.restore-text'),
    confirmText: t('page.restore'),
    cancelText: t('page.cancel'),
  })

  if (!agreed) return

  try {
    const block = await api.restore(props.block.id, version.number)
    toast.success(t('page.restored', { number: block.draft?.number ?? version.number }))
    emit('restored', block)
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

onMounted(load)
watch(
  () => [props.block.draft?.number, props.block.published?.number],
  () => void load(),
)
</script>

<template>
  <div class="wx-block-history">
    <!-- Where a row would stand: see the same note on the pages history. -->
    <wx-skeleton v-if="versions === null" class="wx-block-history__ghost" :rows="3" />
    <div v-for="version in versions" v-else :key="version.number" class="wx-block-history__row">
      <code class="wx-block-history__number">{{
        t('page.version', { number: version.number })
      }}</code>
      <div class="wx-block-history__who">
        <wx-text>{{ t(`page.version-${state(version)}`) }}</wx-text>
        <wx-text size="sm" tone="muted">
          <wx-date v-if="version.created_at" :value="version.created_at" />
          · {{ version.author ?? t(`page.source-${version.source}`) }}
          <template v-if="version.comment"> · {{ version.comment }}</template>
        </wx-text>
      </div>
      <wx-badge v-if="state(version) === 'draft'" type="warning" dot>{{
        t('page.version-draft')
      }}</wx-badge>
      <wx-badge v-else-if="state(version) === 'live'" type="success" dot>{{
        t('page.version-live')
      }}</wx-badge>
      <wx-button
        v-if="canManage && state(version) === 'replaced'"
        size="sm"
        variant="outline"
        @click="restore(version)"
      >
        {{ t('page.restore') }}
      </wx-button>
    </div>
  </div>
</template>

<style scoped>
.wx-block-history {
  display: flex;
  flex-direction: column;
}

.wx-block-history__ghost {
  padding: var(--wx-space-10) var(--wx-space-12);
}

.wx-block-history__row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  padding: var(--wx-space-10) var(--wx-space-12);
  border-block-end: 1px solid var(--wx-border-muted);
  flex-wrap: wrap;
}

.wx-block-history__row:last-child {
  border-block-end: 0;
}

.wx-block-history__number {
  width: 40px;
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
}

.wx-block-history__who {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 160px;
}
</style>
