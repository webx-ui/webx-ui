<script setup lang="ts">
import { computed } from 'vue'
import { useLocales, WxAlert, WxInput } from '@webx-ui/core'
import { useCategoryEditor } from './editor'

/**
 * The address of a category: the part that is edited, with the module's prefix in front of it.
 *
 * `repairs` on its own says nothing about whether the section lives under `/blog/` or at the
 * root of the site, which is what somebody checking an address wants to see — so the prefix
 * stands inside the control, the way `wx-article-slug` prints it for an article.
 *
 * The line under it appears only once there is something to lose: a category on the site whose
 * address is being changed leaves a redirect behind, and that is the fact that decides whether
 * this is safe to do at all.
 *
 * Nothing is declared as a prop on purpose: a wrapper that declared what `WxInput` takes would
 * hand it a `false` where the screen asked for nothing (CLAUDE.md §4).
 */
defineOptions({ name: 'WxCategorySlug', inheritAttrs: false })

const editor = useCategoryEditor()
const locales = useLocales()

/** `/blog/`, or `/` for a module that lives at the root of the site. */
const prefix = computed(() => {
  const head = editor?.prefix.value ?? ''

  return head === '' ? '/' : `/${head}/`
})

/** The segment as it stands in the field, in the language being edited and no other. */
const slug = computed(() => {
  const written = editor?.values.value.slug

  if (typeof written === 'string') return written

  return (written as Record<string, string> | null | undefined)?.[locales.active.value] ?? ''
})

const moving = computed(() => {
  const current = editor?.category.value?.path

  return (
    typeof current === 'string' &&
    slug.value !== '' &&
    `${prefix.value}${slug.value}` !== `/${current}`
  )
})
</script>

<template>
  <div class="wx-category-slug">
    <wx-input v-bind="$attrs">
      <template #prefix>
        <span class="wx-category-slug__prefix">{{ prefix }}</span>
      </template>
    </wx-input>

    <wx-alert v-if="moving && editor" type="info" variant="soft" :description="editor.moving()" />
  </div>
</template>

<style scoped>
.wx-category-slug {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

/* One address in one face: the prefix and what is typed after it are one string, and only the
   muting says which part cannot be typed over. */
.wx-category-slug__prefix,
.wx-category-slug :deep(.wx-input__inner) {
  font-family: var(--wx-font-family-mono);
}

.wx-category-slug__prefix {
  color: var(--wx-text-muted);
}
</style>
