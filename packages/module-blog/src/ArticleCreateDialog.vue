<script setup lang="ts">
import { nextTick, onMounted, ref, useTemplateRef } from 'vue'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import { useModal, WxButton, WxDialog, WxFormItem, WxInput, WxSpace } from '@webx-ui/core'
import { createBlogApi } from './api'
import { useBlogMessages } from './i18n'
import type { ArticleRow } from './types'

/**
 * A new article: what it is called, and nothing else.
 *
 * No address field here, unlike the dialog that starts a page. A page is created inside a
 * branch and its address is the one thing about it that cannot be guessed; an article lives in
 * one flat space under the blog's prefix, and the server makes the slug out of the title — the
 * same transliteration it would apply to anything typed in the field. The address is on the
 * editor's own settings tab, where there is room to say what changing it costs.
 */
const { open, resolve, dismiss } = useModal<ArticleRow>()

const context = useAdmin()
const api = createBlogApi(context)
useBlogMessages()

const t = useTranslate('webx-blog')

const title = ref('')
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
    resolve(await api.create({ title: title.value.trim() }))
  } catch (error) {
    // The address is what can be refused here, and it is refused under the title that made
    // it: the registry sees pages, rubrics and tags too, so "taken" can mean taken by a page.
    errors.value = (error as { body?: { errors?: Record<string, string[]> } }).body?.errors ?? {}
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('panel.new-title')" :width="440">
    <wx-form-item
      :label="t('panel.field-title')"
      :error="errors.title?.[0] ?? errors.slug?.[0]"
      required
    >
      <div ref="field">
        <wx-input v-model="title" :aria-label="t('panel.field-title')" @keyup.enter="submit" />
      </div>
    </wx-form-item>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" :disabled="title.trim() === ''" @click="submit">
          {{ t('panel.create') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
