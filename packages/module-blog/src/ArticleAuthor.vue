<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
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
const disabled = computed(() => props.disabled || editor?.disabled.value === true)

const options = computed<SelectOption[]>(() =>
  (editor?.options.value.authors ?? []).map((author) => ({
    label: author.title,
    value: author.id,
  })),
)

const chosen = computed<SelectModelValue>({
  get: () => value.value,
  set: (next) => {
    value.value = typeof next === 'number' ? next : null
  },
})
</script>

<template>
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
</template>
