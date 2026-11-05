<script setup lang="ts">
import { computed, ref } from 'vue'
import { useModal, WxButton, WxDialog, WxRadioGroup, WxSpace, WxText } from '@webx-ui/core'
import { useTranslate } from '@webx-ui/module-admin'
import { NAMESPACE, useCatalogFeedsMessages } from './i18n'
import type { FeedPreset } from './types'

/**
 * «New feed» (§11 of the feeds spec): a preset to start from, or nothing at all. The answer is its
 * key, `''` for an empty feed; a feed holds a copy, so what is chosen here is only a beginning.
 */
defineOptions({ name: 'WxCatalogFeedNewDialog' })

const props = defineProps<{ presets: FeedPreset[] }>()

const { open, resolve, dismiss } = useModal<string>()

useCatalogFeedsMessages()

const t = useTranslate(NAMESPACE)

const chosen = ref(props.presets[0]?.key ?? '')

const options = computed(() => [
  ...props.presets.map((preset) => ({
    value: preset.key,
    label: preset.name,
    description: preset.description,
  })),
  { value: '', label: t('panel.new-empty'), description: t('panel.new-empty-help') },
])
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('panel.new-title')" :width="520">
    <wx-text size="sm" tone="muted">{{ t('panel.new-help') }}</wx-text>

    <wx-radio-group v-model="chosen" :options="options" direction="vertical" />

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.cancel') }}</wx-button>
        <wx-button type="primary" @click="resolve(chosen)">{{ t('panel.new-go') }}</wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
