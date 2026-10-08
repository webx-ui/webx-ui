<script setup lang="ts">
import { useErrorText, useTranslate } from '@webx-ui/module-admin'
import { WxAction, WxActions, WxProgress } from '@webx-ui/core'
import type { UnfinishedUpload } from '@webx-ui/module-admin'
import { readable } from './format'
import type { MediaUploadJob } from './uploading'

/**
 * The files on their way in, each with how far it has got and what can be done about it.
 *
 * Only there while something is: a file that made it disappears into the grid, which is where
 * it is looked for. One that failed stays, with the reason and a second try.
 */
defineProps<{
  jobs: MediaUploadJob[]
  unfinished: UnfinishedUpload[]
}>()

const emit = defineEmits<{
  cancel: [key: number]
  retry: [key: number]
  forget: [entry: UnfinishedUpload]
}>()

const t = useTranslate('webx-media')
const message = useErrorText()

function status(job: MediaUploadJob): string {
  switch (job.stage) {
    case 'queued':
      return t('manager.upload-waiting')
    case 'paused':
      return t('manager.upload-paused')
    case 'offline':
      return t('manager.upload-offline')
    case 'storing':
      return t('manager.upload-storing')
    case 'failed':
      return message(job.error, t('errors.upload'))
    default:
      return `${Math.floor(job.progress * 100)}%`
  }
}

function percent(entry: UnfinishedUpload): number {
  return entry.size > 0 ? Math.floor((entry.offset / entry.size) * 100) : 0
}
</script>

<template>
  <div v-if="jobs.length > 0 || unfinished.length > 0" class="wx-media-uploads">
    <div
      v-for="job in jobs"
      :key="job.key"
      class="wx-media-uploads__row"
      :class="{ 'is-failed': job.stage === 'failed' }"
    >
      <span class="wx-media-uploads__name" :title="job.name">{{ job.name }}</span>
      <span class="wx-media-uploads__size">{{ readable(job.size) }}</span>

      <wx-progress
        class="wx-media-uploads__bar"
        size="sm"
        :value="Math.round(job.progress * 100)"
        :status="job.stage === 'failed' ? 'danger' : 'default'"
        :indeterminate="job.stage === 'storing'"
        :aria-label="job.name"
      />

      <span class="wx-media-uploads__status" role="status">{{ status(job) }}</span>

      <wx-actions size="sm" align="end" class="wx-media-uploads__actions">
        <wx-action
          v-if="job.stage === 'failed' && job.retryable"
          type="restore"
          icon="refresh"
          :title="t('manager.upload-retry')"
          @click="emit('retry', job.key)"
        />
        <wx-action
          v-if="job.stage !== 'storing'"
          type="remove"
          icon="close"
          :title="job.stage === 'failed' ? t('manager.upload-dismiss') : t('manager.upload-cancel')"
          @click="emit('cancel', job.key)"
        />
      </wx-actions>
    </div>

    <!-- From an earlier visit: the server still has the pieces, the browser no longer the file. -->
    <div
      v-for="entry in unfinished"
      :key="entry.fingerprint"
      class="wx-media-uploads__row is-unfinished"
    >
      <span class="wx-media-uploads__name" :title="entry.name">
        {{ t('manager.upload-unfinished', { name: entry.name, percent: percent(entry) }) }}
      </span>

      <wx-actions size="sm" align="end" class="wx-media-uploads__actions">
        <wx-action
          type="remove"
          icon="close"
          :title="t('manager.upload-forget')"
          @click="emit('forget', entry)"
        />
      </wx-actions>
    </div>
  </div>
</template>

<style>
.wx-media-uploads {
  /* A child of the files column, which would otherwise squeeze it to make room for the grid. */
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  container-type: inline-size;
  max-height: 200px;
  overflow: auto;
  padding: var(--wx-space-6) var(--wx-space-8);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-sm);
  font-size: var(--wx-font-size-sm);
}

.wx-media-uploads__row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(80px, 160px) minmax(0, 1fr) auto;
  grid-template-areas: 'name size bar status actions';
  align-items: center;
  gap: var(--wx-space-8);
  min-height: 28px;
}

.wx-media-uploads__row.is-unfinished {
  grid-template-columns: minmax(0, 1fr) auto;
  grid-template-areas: 'name actions';
  color: var(--wx-text-muted);
}

.wx-media-uploads__name {
  grid-area: name;
}

.wx-media-uploads__size {
  grid-area: size;
}

.wx-media-uploads__bar {
  grid-area: bar;
}

.wx-media-uploads__status {
  grid-area: status;
}

.wx-media-uploads__actions {
  grid-area: actions;
  justify-self: end;
  width: auto;
}

.wx-media-uploads__name,
.wx-media-uploads__status {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wx-media-uploads__size,
.wx-media-uploads__status {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}

/* Why it failed is the one thing on the row worth reading whole. */
.wx-media-uploads__row.is-failed .wx-media-uploads__status {
  color: var(--wx-color-danger);
  white-space: normal;
}

/* A phone: the name and the buttons on one line, the bar and what it says under them. */
@container (max-width: 480px) {
  .wx-media-uploads__row {
    grid-template-columns: minmax(0, 1fr) auto auto;
    grid-template-areas:
      'name size actions'
      'bar bar bar'
      'status status status';
    row-gap: var(--wx-space-2);
  }
}
</style>
