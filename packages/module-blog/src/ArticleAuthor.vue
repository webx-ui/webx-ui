<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useAdmin, useTranslate } from '@webx-ui/module-admin'
import { WxSelect, type SelectModelValue, type SelectOption } from '@webx-ui/core'
import { useArticleEditor } from './editor'
import { useBlogMessages } from './i18n'

/**
 * Who signed the article: one administrator, or nobody (§2.14).
 *
 * A list rather than a text field, because the byline is a row in the table of administrators:
 * renaming somebody renames it everywhere, and an account that is gone leaves the article
 * unsigned instead of signed by a name nobody can look up.
 *
 * "Nobody" is the empty state of the field and not a line in the list. A row meaning "none" is
 * a row somebody picks by accident, and `SelectValue` has no room for `null` anyway — so the
 * answer is the clear button and a placeholder that says what an empty field means.
 */
defineOptions({ name: 'WxArticleAuthor' })

const props = withDefaults(defineProps<{ disabled?: boolean }>(), { disabled: false })

const value = defineModel<number | null>({ default: null })

const editor = useArticleEditor()
useBlogMessages()

const t = useTranslate('webx-blog')
const context = useAdmin()
const disabled = computed(() => props.disabled || editor?.disabled.value === true)

const options = computed<SelectOption[]>(() =>
  (editor?.options.value.authors ?? []).map((author) => ({
    label: author.title,
    value: author.id,
  })),
)

/**
 * The way to the author's profile under the field (§19.5 of the spec): to it where there is one, to
 * a new one for this account where there is not — only on a site with author pages, which is a site
 * whose manifest has the section, and only for somebody who may edit profiles.
 */
const profileLink = computed(() => {
  const hasSection = context.state.manifest?.modules.some((module) => module.id === 'authors')

  if (!hasSection || !context.can('blog.authors.manage') || value.value === null) return null

  const base = editor?.base ?? '/blog'
  const profile = editor?.options.value.authors.find((author) => author.id === value.value)?.profile

  return profile
    ? {
        to: { path: `${base}/authors`, query: { author: String(profile) } },
        label: t('author.profile'),
      }
    : {
        to: { path: `${base}/authors`, query: { author: 'new', account: String(value.value) } },
        label: t('author.make-profile'),
      }
})

const chosen = computed<SelectModelValue>({
  get: () => value.value,
  set: (next) => {
    value.value = typeof next === 'number' ? next : null
  },
})
</script>

<template>
  <div class="wx-article-author">
    <wx-select
      v-model="chosen"
      :options="options"
      :placeholder="t('article.no-author')"
      :disabled="disabled"
      clearable
      filterable
      teleport
      :aria-label="t('article.author')"
    />

    <router-link v-if="profileLink" class="wx-article-author__profile" :to="profileLink.to">
      {{ profileLink.label }}
    </router-link>
  </div>
</template>

<style scoped>
.wx-article-author {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wx-space-6);
  min-width: 0;
}

.wx-article-author > :first-child {
  align-self: stretch;
}

.wx-article-author__profile {
  color: var(--wx-color-primary);
  font-size: var(--wx-font-size-sm);
}
</style>
