<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { WxButton, WxProgress, WxText, useLocales } from '@webx-ui/core'
import type { GalleryVideo, VideoJob } from './galleryVideo'
import { formatBytes, formatClock } from './video'

/**
 * One video on its way into the gallery: what it is doing, and the bar while the file goes up —
 * the share, the speed and the time left, with pause and cancel beside it.
 */
const props = defineProps<{ job: VideoJob; video: GalleryVideo }>()

const t = useTranslate('webx-catalog')
const locales = useLocales()

const upload = computed(() => props.video.upload)
const state = computed(() => upload.value.state.value)
const percent = computed(() => Math.floor(upload.value.progress.value * 100))

const figures = computed(() => {
  const locale = locales.active.value
  const parts = [
    t('panel.video-sent', {
      sent: formatBytes(upload.value.uploaded.value, locale),
      total: formatBytes(upload.value.total.value, locale),
    }),
  ]

  if (state.value === 'paused') {
    parts.push(t('panel.video-paused'))
  } else if (state.value === 'offline') {
    parts.push(t('panel.video-offline'))
  } else {
    if (upload.value.speed.value > 0) {
      parts.push(`${formatBytes(upload.value.speed.value, locale)}/s`)
    }

    const left = upload.value.remaining.value

    if (left !== null) parts.push(t('panel.video-left', { time: formatClock(left) }))
  }

  return parts
})

const stage = computed(() => {
  switch (props.job.stage) {
    case 'waiting':
      return t('panel.video-waiting')
    case 'poster':
      return t('panel.video-poster')
    case 'attaching':
      return t('panel.video-attaching')
    default:
      return ''
  }
})
</script>

<template>
  <div class="wx-catalog-video-progress" :class="{ 'is-failed': job.stage === 'failed' }">
    <template v-if="job.stage === 'uploading'">
      <wx-progress
        :value="percent"
        :max="100"
        size="sm"
        show-value
        :status="state === 'failed' ? 'danger' : 'default'"
        :aria-label="t('panel.video-uploading', { name: job.file.name })"
      />
      <div class="wx-catalog-video-progress__line">
        <wx-text size="sm" tone="muted" class="wx-catalog-video-progress__figures">
          <!-- The dot goes with the figure after it, so a line never ends on one. -->
          <template v-for="(part, index) in figures" :key="index">
            {{ ' '
            }}<span class="wx-catalog-video-progress__figure">{{
              index > 0 ? `· ${part}` : part
            }}</span>
          </template>
        </wx-text>
        <div class="wx-catalog-video-progress__buttons">
          <wx-button v-if="state === 'uploading'" size="sm" variant="text" @click="video.pause()">
            {{ t('panel.video-pause') }}
          </wx-button>
          <wx-button
            v-else-if="state === 'paused' || state === 'offline'"
            size="sm"
            variant="text"
            @click="video.resume()"
          >
            {{ t('panel.video-resume') }}
          </wx-button>
          <wx-button size="sm" variant="text" type="danger" @click="video.cancel(job)">
            {{ t('panel.cancel') }}
          </wx-button>
        </div>
      </div>
    </template>

    <template v-else-if="job.stage === 'failed'">
      <wx-text size="sm" tone="danger" role="alert">{{ job.error }}</wx-text>
      <div class="wx-catalog-video-progress__buttons">
        <wx-button v-if="!job.unreadable" size="sm" variant="text" @click="video.retry(job)">
          {{ t('panel.video-retry') }}
        </wx-button>
        <wx-button v-if="job.unreadable" size="sm" variant="text" @click="video.dismiss(job)">
          {{ t('panel.video-dismiss') }}
        </wx-button>
        <wx-button v-else size="sm" variant="text" type="danger" @click="video.cancel(job)">
          {{ t('panel.cancel') }}
        </wx-button>
      </div>
    </template>

    <template v-else>
      <wx-progress indeterminate size="sm" :aria-label="stage" />
      <div class="wx-catalog-video-progress__line">
        <wx-text size="sm" tone="muted">{{ stage }}</wx-text>
        <div class="wx-catalog-video-progress__buttons">
          <wx-button size="sm" variant="text" type="danger" @click="video.cancel(job)">
            {{ t('panel.cancel') }}
          </wx-button>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.wx-catalog-video-progress {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-catalog-video-progress__line {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-4) var(--wx-space-8);
  min-width: 0;
}

.wx-catalog-video-progress__figures {
  font-variant-numeric: tabular-nums;
  min-width: 0;
}

/* A figure never breaks in two; the line breaks between them. */
.wx-catalog-video-progress__figure {
  white-space: nowrap;
}

.wx-catalog-video-progress__buttons {
  display: flex;
  gap: var(--wx-space-4);
  margin-inline-start: auto;
}

.wx-catalog-video-progress.is-failed {
  flex-direction: row;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-4) var(--wx-space-8);
}
</style>
