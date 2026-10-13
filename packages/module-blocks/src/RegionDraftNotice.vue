<script setup lang="ts">
import { useTranslate } from '@webx-ui/module-admin'
import type { SiteBarRegion } from './types'

/**
 * A region's unpublished draft in the panel on the site, with the buttons that publish it or
 * throw it away.
 *
 * Drawn over the region's map and over the form of one of its blocks alike: the head of the bar
 * speaks for the record only, so the form is where an editor who has just saved a block of the
 * footer is looking — and a notice that only the map carried was found after a reload.
 */
defineProps<{
  region: SiteBarRegion
  /** This region's publication or discard is under way. */
  busy?: boolean
  /** Another region's is: one at a time, since each reloads the page. */
  blocked?: boolean
  /** Why the publication was refused — the blocks of the draft that fail. */
  refusals?: string[]
  /** The region's draft drawn on this page: after a reload the page shows the site again. */
  preview?: string | null
}>()

const emit = defineEmits<{ publish: []; discard: [] }>()

const t = useTranslate('webx-blocks')
</script>

<template>
  <wx-alert
    type="warning"
    :title="t('site-bar.changes', { count: region.draft.changes })"
    :description="
      !region.draft.mine && region.draft.by
        ? t('site-bar.draft-other', { name: region.draft.by })
        : undefined
    "
  >
    <template #actions>
      <wx-button
        size="sm"
        type="primary"
        :loading="busy"
        :disabled="blocked && !busy"
        @click="emit('publish')"
      >
        {{ t('site-bar.publish') }}
      </wx-button>
      <wx-button size="sm" variant="outline" :disabled="busy || blocked" @click="emit('discard')">
        {{ t('site-bar.discard') }}
      </wx-button>
      <wx-button
        v-if="preview"
        size="sm"
        variant="text"
        :href="preview"
        target="_blank"
        rel="noopener"
      >
        {{ t('site-bar.preview') }}
      </wx-button>
    </template>
  </wx-alert>

  <div v-if="refusals?.length" class="wx-region-draft__refusals" role="alert">
    <wx-text v-for="(line, index) in refusals" :key="index" size="sm" tone="danger">{{
      line
    }}</wx-text>
  </div>
</template>

<style scoped>
.wx-region-draft__refusals {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}
</style>
