<script setup lang="ts">
import { useTranslate } from '@webx-ui/module-admin'
import { useModal, WxButton, WxDialog, WxEmpty, WxIcon, WxText } from '@webx-ui/core'
import { useBlocksMessages } from './i18n'
import type { Destination } from './move'

/**
 * "Move to": the lists a block may go into, each named by the containers down to it.
 *
 * A list rather than a drag across the tree. The places are few and each one is named, the
 * rules are applied before anything is offered rather than at the moment of a drop, and a
 * finger in a sheet can do it as well as a mouse can. The block goes to the end of the list
 * it is moved into; its place there is a drag within that list.
 */
withDefaults(
  defineProps<{
    title: string
    destinations: Destination[]
    /** What the top level is called: the page, or the block whose sample this is. */
    topLabel: string
  }>(),
  {},
)

const { resolve, dismiss, open } = useModal<Destination>()
useBlocksMessages()

const t = useTranslate('webx-blocks')
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('field.move-title', { title })" :width="520">
    <wx-empty
      v-if="destinations.length === 0"
      icon="grid"
      :title="t('field.move-none')"
      size="sm"
    />

    <div v-else class="wx-block-move">
      <button
        v-for="place in destinations"
        :key="`${place.parentKey}:${place.field}`"
        type="button"
        class="wx-block-move__place"
        :disabled="place.full"
        @click="resolve(place)"
      >
        <wx-icon :name="place.parentKey === null ? 'file' : 'folder'" />
        <span class="wx-block-move__trail">
          <template v-if="place.trail.length === 0">{{ topLabel }}</template>
          <template v-for="(step, index) in place.trail" v-else :key="index">
            <span v-if="index > 0" class="wx-block-move__sep">/</span>
            <span :class="{ 'is-last': index === place.trail.length - 1 }">{{ step }}</span>
          </template>
        </span>
        <wx-text v-if="place.full" size="sm" tone="muted">{{ t('field.move-full') }}</wx-text>
      </button>
    </div>

    <template #footer>
      <wx-button variant="outline" @click="dismiss()">{{ t('page.cancel') }}</wx-button>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-block-move {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}

.wx-block-move__place {
  display: flex;
  align-items: center;
  gap: var(--wx-space-10);
  width: 100%;
  padding: var(--wx-space-10) var(--wx-space-12);
  font: inherit;
  text-align: start;
  color: inherit;
  background: transparent;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-control);
  cursor: pointer;
}

.wx-block-move__place:hover:not(:disabled),
.wx-block-move__place:focus-visible {
  border-color: var(--wx-color-primary);
  background: var(--wx-color-primary-soft);
  outline: none;
}

.wx-block-move__place:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.wx-block-move__trail {
  display: flex;
  flex: 1;
  flex-wrap: wrap;
  gap: var(--wx-space-4);
  min-width: 0;
  color: var(--wx-text-muted);
}

.wx-block-move__trail .is-last,
.wx-block-move__trail:not(:has(span)) {
  color: var(--wx-text-default);
  font-weight: var(--wx-font-weight-medium);
}

.wx-block-move__sep {
  color: var(--wx-text-muted);
}
</style>
