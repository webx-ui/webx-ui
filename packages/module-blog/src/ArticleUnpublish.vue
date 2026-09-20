<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import { confirm, toast, WxButton, WxFormItem } from '@webx-ui/core'
import { createBlogApi } from './api'
import { useArticleEditor } from './editor'
import { useBlogMessages } from './i18n'

/**
 * Taking the article off the site, from the tab where the rest of its publication is decided.
 *
 * It lives here and not in the action bar because the bar holds what is done to the draft —
 * save, publish — and this is done to what visitors are reading. Under the day it goes out for
 * the same reason: whether an article is on the site and when it got there is one question, and
 * an editor who has just looked at the date is the one deciding to pull it.
 *
 * Offered only while there is something to take off. A draft was never there, and one already
 * taken off has nowhere further to go — the way back is "publish", which is in the bar.
 *
 * It is not "delete": the article keeps its address in the registry, its history and its
 * rubrics, and publishing puts it back exactly where it was.
 */
defineOptions({ name: 'WxArticleUnpublish' })

const context = useAdmin()
const api = createBlogApi(context)
const editor = useArticleEditor()
useBlogMessages()

const t = useTranslate('webx-blog')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const working = ref(false)

/** On the site, with or without edits waiting — or waiting for a day that has not come. */
const live = computed(() => {
  const status = editor?.article.value?.status

  return status === 'published' || status === 'modified' || status === 'scheduled'
})

const disabled = computed(() => editor?.disabled.value === true || working.value)

/**
 * Asked about, because this is the one thing on the settings tab that visitors see happen.
 * Everything else here writes a draft nobody outside the panel can read.
 */
async function unpublish(): Promise<void> {
  const row = editor?.article.value

  if (!row) return

  const agreed = await confirm({
    title: t('article.unpublish-title', { title: row.title }),
    message:
      row.status === 'scheduled' ? t('article.unpublish-waiting') : t('article.unpublish-text'),
    confirmText: t('panel.unpublish'),
    cancelText: t('panel.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  working.value = true

  try {
    await api.unpublish(row.id)
    await editor?.reload()
    toast.success(t('panel.unpublished-done'))
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}
</script>

<template>
  <wx-form-item
    v-if="live && editor?.canManage"
    class="wx-article-unpublish"
    :help="t('article.unpublish-help')"
  >
    <wx-button
      variant="outline"
      type="danger"
      icon="eye-off"
      :loading="working"
      :disabled="disabled"
      @click="unpublish"
    >
      {{ t('panel.unpublish') }}
    </wx-button>
  </wx-form-item>
</template>

<style scoped>
/*
 * A form item is a column that stretches what it holds, which is right for a field and wrong
 * for a button: the one thing on this card that is not a field would otherwise be the widest
 * thing on it, six hundred pixels of red outline under a date picker.
 *
 * Through `:deep()`, because the column belongs to `WxFormItem` and a scoped rule reaches only
 * as far as this component's own elements.
 */
.wx-article-unpublish :deep(.wx-button) {
  align-self: flex-start;
}
</style>
