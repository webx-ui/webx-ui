<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { useLocales, WxAlert, WxInput } from '@webx-ui/core'
import { useArticleEditor } from './editor'
import { useBlogMessages } from './i18n'

/**
 * The address of an article: the part that is edited, with the part that is not in front of it.
 *
 * The field edits the last segment, and `signs-of-wear` on its own says nothing about whether
 * the blog lives at the root of the site or under `/blog/` — which is exactly what somebody
 * checking an address wants to see. That used to be a row of its own under the field, labelled
 * "Address" directly below a field labelled "Address": a whole line of the form spent on one
 * constant segment, and two labels that taught the reader to skim both. The prefix belongs
 * inside the control, where the address is read and written in one place.
 *
 * The line under it appears only once there is something to lose: an article that is on the
 * site and whose address is being changed leaves a redirect behind, and that is the fact that
 * decides whether this is safe to do at all. Said before the save rather than in a toast
 * afterwards.
 *
 * Nothing is declared as a prop on purpose. The screen hands a field its `name`, its value,
 * `localized` and whatever the description put in `props`, and a wrapper that declared any of
 * them would hand `WxInput` a `false` where the caller asked for nothing (CLAUDE.md §4).
 */
defineOptions({ name: 'WxArticleSlug', inheritAttrs: false })

const editor = useArticleEditor()
const locales = useLocales()
useBlogMessages()

const t = useTranslate('webx-blog')

/** `/blog/`, or `/` for a blog that lives at the root of the site. */
const prefix = computed(() => {
  const head = editor?.prefix.value ?? ''

  return head === '' ? '/' : `/${head}/`
})

/** The last segment as it stands in the field, not as the registry last heard it. */
const slug = computed(() => {
  const written = editor?.values.value.slug

  if (typeof written === 'string') return written

  // The language being edited and no other. Falling back on a language that does have words
  // would print an address the site does not answer at (§9).
  return (written as Record<string, string> | undefined)?.[locales.active.value] ?? ''
})

/** On the site at an address that is about to become a different one. */
const moving = computed(() => {
  const current = editor?.article.value?.path

  return (
    typeof current === 'string' &&
    slug.value !== '' &&
    `${prefix.value}${slug.value}` !== `/${current}`
  )
})
</script>

<template>
  <div class="wx-article-slug">
    <wx-input v-bind="$attrs">
      <template #prefix>
        <span class="wx-article-slug__prefix">{{ prefix }}</span>
      </template>
    </wx-input>

    <wx-alert v-if="moving" type="info" variant="soft" :description="t('article.address-moving')" />
  </div>
</template>

<style scoped>
.wx-article-slug {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

/*
 * One address in one face.
 *
 * The prefix is part of the address and not a decoration on the field, so it is set the way the
 * panel prints every other address — and so is what is typed after it. Two faces on one line
 * do not sit on one line: the metrics differ, each box is centred on itself, and `/blog/` rode
 * two pixels above the word it belongs to. Only the muting tells the two apart, which is the
 * one difference worth drawing: the prefix is the part that cannot be typed over.
 */
.wx-article-slug__prefix,
.wx-article-slug :deep(.wx-input__inner) {
  font-family: var(--wx-font-family-mono);
}

.wx-article-slug__prefix {
  color: var(--wx-text-muted);
}
</style>
