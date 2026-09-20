<script setup lang="ts">
import { computed, ref, useTemplateRef } from 'vue'
import { useAdmin, useTranslate, WxListScreen, type ScreenAction } from '@webx-ui/module-admin'
import { WxCard, type TabItem, type TabValue } from '@webx-ui/core'
import MediaManager from './MediaManager.vue'
import { useMediaMessages } from './i18n'
import type { MediaKind } from './types'

/**
 * The library as a page of the panel.
 *
 * It looks like the other lists: the section's name on its own line, the one action the
 * section exists for beside it, the kinds of file as the views of the list, and the manager
 * under them in a card.
 *
 * Uploading is that action, so it is a filled primary button with a word on it rather than the
 * third grey icon in a row of six (§18). Blue and not green: in this system green means
 * "it worked", and a green button says "uploaded" where it means "upload". The library brings
 * its own card because the card is what scrolls here.
 */
const context = useAdmin()
useMediaMessages()

const t = useTranslate('webx-media')

const manager = useTemplateRef<{ upload: () => void }>('manager')
const kind = ref<TabValue>('all')
/* The tabs speak in tab values; the manager speaks in kinds of file. */
const fileKind = computed(() => kind.value as MediaKind | 'all')

const canUpload = computed(() => context.can('media.upload') || context.can('media.manage'))

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'media')?.title ??
    t('module.title'),
)

/* The same kinds the toolbar used to hide in a dropdown; here they are what the list is. */
const views = computed<TabItem[]>(() => [
  { value: 'all', label: t('manager.all-types') },
  ...(['image', 'video', 'audio', 'document', 'other'] as MediaKind[]).map((one) => ({
    value: one,
    label: t(`manager.${one}`),
  })),
])

/* What the section offers. Declared, because on a phone the head folds it into the ···. */
const actions = computed<ScreenAction[]>(() =>
  canUpload.value
    ? [
        {
          key: 'upload',
          label: t('manager.upload'),
          icon: 'upload',
          primary: true,
          run: () => manager.value?.upload(),
        },
      ]
    : [],
)
</script>

<template>
  <wx-list-screen
    v-model:view="kind"
    :title="title"
    :views="views"
    :actions="actions"
    :card="false"
    fill
  >
    <wx-card class="wx-media-page" padding="md">
      <media-manager ref="manager" :type="fileKind" in-page />
    </wx-card>
  </wx-list-screen>
</template>

<style>
.wx-media-page {
  height: 100%;
  min-height: 0;
}

/* The card is the page: its body is what scrolls, not the page behind it. */
.wx-media-page > .wx-card__body {
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
}
</style>
