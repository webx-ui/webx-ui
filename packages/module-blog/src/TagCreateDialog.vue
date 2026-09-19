<script setup lang="ts">
import { nextTick, onMounted, ref, useTemplateRef } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import { useModal, WxButton, WxDialog, WxFormItem, WxInput, WxSpace } from '@webx-ui/core'
import { createBlogApi } from './api'
import { useBlogMessages } from './i18n'
import type { TagRow } from './types'

/**
 * A new tag, spelled here rather than while writing.
 *
 * The address is a field of its own, unlike on the dialog that starts an article: a tag is one
 * word, the address is the same word transliterated, and the one time somebody wants a different
 * one is the time they are making the tag on purpose — which is this screen and not the article
 * form (§2.8).
 */
const { open, resolve, dismiss } = useModal<TagRow>()

const context = useAdmin()
const api = createBlogApi(context)
useBlogMessages()

const t = useTranslate('webx-blog')

const title = ref('')
const slug = ref('')
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const field = useTemplateRef<HTMLElement>('field')

onMounted(async () => {
  await nextTick()
  field.value?.querySelector('input')?.focus()
})

async function submit(): Promise<void> {
  if (title.value.trim() === '' || saving.value) return

  saving.value = true
  errors.value = {}

  try {
    resolve(
      await api.createTag({
        title: title.value.trim(),
        ...(slug.value.trim() === '' ? {} : { slug: slug.value.trim() }),
      }),
    )
  } catch (error) {
    errors.value = (error as { body?: { errors?: Record<string, string[]> } }).body?.errors ?? {}
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('tag.new-title')" :width="440">
    <wx-form-item :label="t('tag.field-title')" :error="errors.title?.[0]" required>
      <div ref="field">
        <wx-input v-model="title" :aria-label="t('tag.field-title')" @keyup.enter="submit" />
      </div>
    </wx-form-item>

    <wx-form-item
      :label="t('tag.field-slug')"
      :error="errors.slug?.[0]"
      :help="t('tag.field-slug-help')"
    >
      <wx-input v-model="slug" :aria-label="t('tag.field-slug')" @keyup.enter="submit" />
    </wx-form-item>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('tag.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" :disabled="title.trim() === ''" @click="submit">
          {{ t('tag.create') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
