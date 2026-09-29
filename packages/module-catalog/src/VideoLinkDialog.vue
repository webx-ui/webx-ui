<script setup lang="ts">
import { computed, nextTick, onMounted, ref, useTemplateRef } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { useModal, WxButton, WxDialog, WxFormItem, WxInput, WxSpace } from '@webx-ui/core'
import { createCatalogApi } from './api'
import { firstError } from './errors'
import { useCatalogMessages } from './i18n'
import type { ProductImage, QueuedVideo } from './types'
import { isVideoLink } from './video'

/**
 * A video by its address, onto a picture of the gallery: a YouTube link, or a direct link to an
 * MP4 or WebM file the server then downloads in its queue (§5 of the video spec).
 */
const props = defineProps<{ product: number; image: number }>()

const { open, resolve, dismiss } = useModal<ProductImage | QueuedVideo>()

const api = createCatalogApi(useAdmin())
useCatalogMessages()

const t = useTranslate('webx-catalog')
const message = useErrorText()

const address = ref('')
const saving = ref(false)
const error = ref<string | null>(null)
const field = useTemplateRef<HTMLElement>('field')

const ready = computed(() => isVideoLink(address.value))

onMounted(async () => {
  await nextTick()
  field.value?.querySelector('input')?.focus()
})

async function submit(): Promise<void> {
  if (saving.value) return

  if (!ready.value) {
    error.value = t('panel.video-link-wrong')

    return
  }

  saving.value = true
  error.value = null

  try {
    resolve(await api.attachVideo(props.product, props.image, { url: address.value.trim() }))
  } catch (failure) {
    const errors = (failure as { body?: { errors?: Record<string, string[]> } }).body?.errors ?? {}

    error.value = firstError(errors, 'url') ?? message(failure)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('panel.video-link-title')" :width="480">
    <wx-form-item
      :label="t('panel.video-link')"
      :help="t('panel.video-link-help')"
      :error="error ?? undefined"
    >
      <div ref="field">
        <wx-input
          v-model="address"
          type="url"
          placeholder="https://www.youtube.com/watch?v="
          :aria-label="t('panel.video-link')"
          @keydown.enter.prevent="submit"
        />
      </div>
    </wx-form-item>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.cancel') }}</wx-button>
        <wx-button
          type="primary"
          :loading="saving"
          :disabled="address.trim() === ''"
          @click="submit"
        >
          {{ t('panel.video-attach') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
