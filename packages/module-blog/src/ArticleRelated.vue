<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  WxAction,
  WxSelect,
  WxSortableList,
  type SelectModelValue,
  type SelectOption,
} from '@webx-ui/core'
import { createBlogApi } from './api'
import { useArticleEditor } from './editor'
import { useBlogMessages } from './i18n'
import type { ArticleOption } from './types'

/**
 * The articles pinned under this one by hand, in the order they are shown.
 *
 * Only the pinned ones. What fills the rest of the list is worked out on the site — most tags
 * in common, then the main rubric, published only (§8) — and it is not shown here because it
 * would be a list that changes under the editor without anybody touching it. A fully manual
 * list is what nobody fills in after the hundredth article; a fully automatic one is what
 * nobody can override when it is wrong. This is the override.
 */
defineOptions({ name: 'WxArticleRelated' })

const props = withDefaults(defineProps<{ disabled?: boolean }>(), { disabled: false })

const value = defineModel<number[]>({ default: () => [] })

const context = useAdmin()
const api = createBlogApi(context)
const editor = useArticleEditor()
useBlogMessages()

const t = useTranslate('webx-blog')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()
const disabled = computed(() => props.disabled || editor?.disabled.value === true)

/** How long after the last keystroke the server is asked. */
const PAUSE = 250

/** As many matches as a dropdown is worth scrolling; past that the answer is to type more. */
const MATCHES = 10

const known = ref(new Map<number, string>())
const found = ref<ArticleOption[]>([])

let timer: ReturnType<typeof setTimeout> | undefined

watch(
  () => editor?.related.value,
  (titles) => {
    const all = new Map(known.value)

    for (const one of titles ?? []) all.set(one.id, one.title)

    known.value = all
  },
  { immediate: true, deep: true },
)

const rows = computed<ArticleOption[]>({
  get: () => value.value.map((id) => ({ id, title: known.value.get(id) ?? `#${id}` })),
  set: (next) => {
    value.value = next.map((row) => row.id)
  },
})

const options = computed<SelectOption[]>(() =>
  found.value
    .filter((one) => !value.value.includes(one.id))
    .map((one) => ({ label: one.title, value: one.id })),
)

async function look(term: string): Promise<void> {
  const mine = editor?.article.value?.id

  try {
    const page = await api.articles({ q: term, per_page: MATCHES })

    // Never itself: the list under an article would offer the reader the page they are on.
    found.value = page.data
      .filter((row) => row.id !== mine)
      .map((row) => ({ id: row.id, title: row.title }))

    const all = new Map(known.value)

    for (const one of found.value) all.set(one.id, one.title)

    known.value = all
  } catch (error) {
    toast.danger(message(error))
  }
}

function search(term: string): void {
  clearTimeout(timer)
  timer = setTimeout(() => void look(term), PAUSE)
}

function add(picked: SelectModelValue): void {
  if (typeof picked !== 'number' || value.value.includes(picked)) return

  value.value = [...value.value, picked]
}

function remove(id: number): void {
  value.value = value.value.filter((one) => one !== id)
}

watch(
  () => editor?.article.value?.id,
  () => void look(''),
  { immediate: true },
)

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <div class="wx-article-related">
    <wx-sortable-list
      v-model="rows"
      plain
      size="sm"
      item-key="id"
      item-label="title"
      :disabled="disabled"
      :empty-text="t('article.related-empty')"
      :aria-label="t('article.related')"
    >
      <template #default="{ item }">
        <span class="wx-article-related__name">{{ (item as ArticleOption).title }}</span>
      </template>

      <template #actions="{ item }">
        <wx-action
          type="remove"
          size="sm"
          :disabled="disabled"
          :title="t('article.related-remove')"
          @click="remove((item as ArticleOption).id)"
        />
      </template>
    </wx-sortable-list>

    <wx-select
      :model-value="null"
      :options="options"
      :placeholder="t('article.related-add')"
      :empty-text="t('article.related-nothing')"
      :disabled="disabled"
      filterable
      teleport
      :aria-label="t('article.related-add')"
      @search="search"
      @update:model-value="add"
    />
  </div>
</template>

<style scoped>
.wx-article-related {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-article-related__name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
