<script setup lang="ts">
import { useTranslate } from '@webx-ui/module-admin'
import { WxBadge, WxText } from '@webx-ui/core'
import BlockThumb from './BlockThumb.vue'
import type { BlockType } from './types'

/** One type in the list: its picture, its name, where it stands and which versions it has. */
defineProps<{ block: BlockType }>()

const emit = defineEmits<{ open: [block: BlockType] }>()

const t = useTranslate('webx-blocks')
</script>

<template>
  <button type="button" class="wx-block-card" @click="emit('open', block)">
    <block-thumb :thumbnail="block.thumbnail" :height="120" />
    <div class="wx-block-card__body">
      <div class="wx-block-card__title">{{ block.title }}</div>
      <wx-text size="sm" tone="muted">
        <code>{{ block.slug }}</code>
        ·
        {{
          block.usage_count > 0
            ? t('page.on-pages', { count: block.usage_count })
            : t('page.not-used')
        }}
      </wx-text>
      <div class="wx-block-card__chips">
        <wx-badge v-if="block.draft" type="warning" dot>
          {{ t('page.draft', { number: block.draft.number }) }}
        </wx-badge>
        <wx-badge v-if="block.published" type="success" dot>
          {{ t('page.live', { number: block.published.number }) }}
        </wx-badge>
        <wx-badge v-else type="default">{{ t('page.never-published') }}</wx-badge>
        <wx-badge v-if="!block.is_enabled" type="default" variant="outline">
          {{ t('page.hidden') }}
        </wx-badge>
      </div>
    </div>
  </button>
</template>

<style scoped>
.wx-block-card {
  display: flex;
  flex-direction: column;
  width: 100%;
  padding: 0;
  overflow: hidden;
  text-align: start;
  font: inherit;
  color: inherit;
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  cursor: pointer;
}

.wx-block-card:hover,
.wx-block-card:focus-visible {
  border-color: var(--wx-color-primary);
  outline: none;
}

.wx-block-card__body {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-6);
  padding: var(--wx-space-12) var(--wx-space-14);
}

.wx-block-card__title {
  font-weight: var(--wx-font-weight-semibold);
}

.wx-block-card__chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-6);
}

code {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
}
</style>
