<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { useLocales, WxAlert, WxFormItem, WxText } from '@webx-ui/core'
import { useArticleEditor } from './editor'
import { useBlogMessages } from './i18n'

/**
 * The address the article answers at, printed whole.
 *
 * The field above edits the last segment; `signs-of-wear` on its own says nothing about whether
 * the blog lives at the root of the site or under `/blog/`, and that is exactly what somebody
 * checking an address wants to see. It follows the field as it is typed rather than the
 * registry, so what is on screen is what the save is about to ask for.
 *
 * The line under it appears only once there is something to lose: an article that is on the
 * site and whose address is being changed leaves a redirect behind, and that is the fact that
 * decides whether this is safe to do at all. Said before the save rather than in a toast
 * afterwards.
 */
defineOptions({ name: 'WxArticleAddress' })

const editor = useArticleEditor()
const locales = useLocales()
useBlogMessages()

const t = useTranslate('webx-blog')

/** The last segment as it stands in the field, not as the registry last heard it. */
const slug = computed(() => {
  const written = editor?.values.value.slug

  if (typeof written === 'string') return written

  // The language being edited and no other. Falling back on a language that does have words
  // would print an address the site does not answer at (§9).
  return (written as Record<string, string> | undefined)?.[locales.active.value] ?? ''
})

const address = computed(() => {
  if (slug.value === '') return null

  return `/${[editor?.prefix.value ?? '', slug.value].filter(Boolean).join('/')}`
})

/** On the site at an address that is about to become a different one. */
const moving = computed(() => {
  const current = editor?.article.value?.path

  return typeof current === 'string' && address.value !== null && address.value !== `/${current}`
})
</script>

<template>
  <div class="wx-article-address">
    <wx-form-item :label="t('article.address')">
      <wx-text v-if="address" mono class="wx-article-address__value">{{ address }}</wx-text>
      <wx-text v-else size="sm" tone="muted">{{ t('article.no-address') }}</wx-text>
    </wx-form-item>

    <wx-alert v-if="moving" type="info" variant="soft" :description="t('article.address-moving')" />
  </div>
</template>

<style scoped>
.wx-article-address {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-article-address__value {
  word-break: break-all;
}
</style>
