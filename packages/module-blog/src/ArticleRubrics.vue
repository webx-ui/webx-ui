<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import {
  WxAction,
  WxBadge,
  WxSelect,
  WxSortableList,
  type SelectModelValue,
  type SelectOption,
} from '@webx-ui/core'
import { useArticleEditor } from './editor'
import { useBlogMessages } from './i18n'
import type { ArticleOption } from './types'

/**
 * The rubrics an article is in, in the order somebody dragged them into.
 *
 * The order is the whole control. The first rubric is the main one (§2.6) — it goes into the
 * breadcrumbs, into "more in this rubric" and into the `<category>` of the RSS — so a separate
 * switch for "which is the main one" would be a second control saying what this one already
 * says, and two controls that can disagree.
 *
 * A list and not a multi-select for the same reason: a bag of checkboxes has no first.
 */
defineOptions({ name: 'WxArticleRubrics' })

const props = withDefaults(defineProps<{ disabled?: boolean }>(), { disabled: false })

const value = defineModel<number[]>({ default: () => [] })

const editor = useArticleEditor()
useBlogMessages()

const t = useTranslate('webx-blog')
const disabled = computed(() => props.disabled || editor?.disabled.value === true)

const catalogue = computed(() => {
  const all = new Map<number, string>()

  for (const rubric of editor?.options.value.rubrics ?? []) all.set(rubric.id, rubric.title)

  return all
})

/**
 * The chosen rubrics as rows. Writable, because that is how `WxSortableList` hands a reorder
 * back — the ids are the value, and the rows are only what they are drawn as.
 */
const rows = computed<ArticleOption[]>({
  get: () => value.value.map((id) => ({ id, title: catalogue.value.get(id) ?? `#${id}` })),
  set: (next) => {
    value.value = next.map((row) => row.id)
  },
})

const available = computed<SelectOption[]>(() =>
  (editor?.options.value.rubrics ?? [])
    .filter((rubric) => !value.value.includes(rubric.id))
    .map((rubric) => ({ label: rubric.title, value: rubric.id })),
)

function add(picked: SelectModelValue): void {
  if (typeof picked !== 'number' || value.value.includes(picked)) return

  value.value = [...value.value, picked]
}

function remove(id: number): void {
  value.value = value.value.filter((one) => one !== id)
}
</script>

<template>
  <div class="wx-article-rubrics">
    <wx-sortable-list
      v-model="rows"
      plain
      size="sm"
      item-key="id"
      item-label="title"
      :disabled="disabled"
      :empty-text="t('article.rubrics-empty')"
      :aria-label="t('article.rubrics')"
    >
      <template #default="{ item, index }">
        <span class="wx-article-rubrics__name">{{ (item as ArticleOption).title }}</span>
        <!-- Said on the row rather than in a legend under the list: the rule is "the first
             one", and the place to say so is the first one. -->
        <wx-badge v-if="index === 0" type="primary">{{ t('article.rubric-main') }}</wx-badge>
      </template>

      <template #actions="{ item }">
        <wx-action
          type="remove"
          size="sm"
          :disabled="disabled"
          :title="t('article.rubric-remove')"
          @click="remove((item as ArticleOption).id)"
        />
      </template>
    </wx-sortable-list>

    <wx-select
      :model-value="null"
      :options="available"
      :placeholder="t('article.rubric-add')"
      :disabled="disabled || available.length === 0"
      :empty-text="t('article.rubric-none-left')"
      filterable
      teleport
      :aria-label="t('article.rubric-add')"
      @update:model-value="add"
    />
  </div>
</template>

<style scoped>
.wx-article-rubrics {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

/*
 * The cell a row's content goes into is a block, so the name and the "main" badge stood against
 * each other with nothing between them — and `flex: 1` on the name did nothing at all, along
 * with the clipping it was there to enable. The slot makes a line of its own contents; through
 * `:deep()`, because the cell belongs to `WxSortableList`.
 */
.wx-article-rubrics :deep(.wx-sortable-list__content) {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
}

/* Shrinks rather than grows: a name that pushed the badge to the far end of the row would say
   "main" about the distance instead of about the rubric. */
.wx-article-rubrics__name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
