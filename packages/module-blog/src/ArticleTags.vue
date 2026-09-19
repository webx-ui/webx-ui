<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  WxAction,
  WxBadge,
  WxButton,
  WxSelect,
  WxText,
  type SelectModelValue,
  type SelectOption,
} from '@webx-ui/core'
import { createBlogApi } from './api'
import { useArticleEditor } from './editor'
import { useBlogMessages } from './i18n'
import type { BlogTag } from './types'

/**
 * The tags on an article: find one by typing, or make it here.
 *
 * Making one here is the point (§2.8). Tags are entered by the hundred from the article being
 * written, and a field that could only pick from what already exists would mean leaving the
 * article, opening another section, making a word and coming back — which nobody does, so the
 * article goes out untagged instead.
 *
 * What holds the other half of that bargain is the number beside every suggestion: seeing that
 * "belts" already has forty articles is what stops "belt" from being the forty-first. The
 * screen that merges the ones that got away is a separate one, and it exists.
 */
defineOptions({ name: 'WxArticleTags' })

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

/**
 * Every tag this session has seen a title for: the article's own, whatever the last search
 * answered, and whatever was made here. A chip needs a word, and the value is only ids.
 */
const known = ref(new Map<number, string>())
const found = ref<BlogTag[]>([])
const term = ref('')
const working = ref(false)

let timer: ReturnType<typeof setTimeout> | undefined

watch(
  () => editor?.article.value?.tags,
  (tags) => {
    const all = new Map(known.value)

    for (const tag of tags ?? []) all.set(tag.id, tag.title)

    known.value = all
  },
  { immediate: true, deep: true },
)

const chips = computed(() =>
  value.value.map((id) => ({ id, title: known.value.get(id) ?? `#${id}` })),
)

const options = computed<SelectOption[]>(() =>
  found.value
    .filter((tag) => !value.value.includes(tag.id))
    .map((tag) => ({
      // The count is part of the label rather than a column: a select draws a line of text, and
      // the number is what the reader is choosing between two near-identical words on.
      label: t('article.tag-option', { title: tag.title, count: tag.articles_count }),
      value: tag.id,
    })),
)

/** Nothing on the server is spelled exactly this way — so this is a word, not a typo of one. */
const missing = computed(() => {
  const typed = term.value.trim()

  if (typed === '') return null

  const same = (title: string): boolean => title.toLowerCase() === typed.toLowerCase()

  return found.value.some((tag) => same(tag.title)) ? null : typed
})

async function look(): Promise<void> {
  try {
    found.value = await api.tags(term.value)

    const all = new Map(known.value)

    for (const tag of found.value) all.set(tag.id, tag.title)

    known.value = all
  } catch (error) {
    toast.danger(message(error))
  }
}

function search(typed: string): void {
  term.value = typed

  clearTimeout(timer)
  timer = setTimeout(() => void look(), PAUSE)
}

function add(picked: SelectModelValue): void {
  if (typeof picked !== 'number' || value.value.includes(picked)) return

  value.value = [...value.value, picked]
  term.value = ''
}

function remove(id: number): void {
  value.value = value.value.filter((one) => one !== id)
}

async function create(): Promise<void> {
  const title = missing.value

  if (title === null || working.value) return

  working.value = true

  try {
    const tag = await api.createTag({ title })

    known.value = new Map(known.value).set(tag.id, tag.title)
    found.value = [tag, ...found.value]
    add(tag.id)
  } catch (error) {
    toast.danger(message(error))
  } finally {
    working.value = false
  }
}

// The first opening of the list asks with an empty term, which answers with the most used.
watch(
  () => editor?.article.value?.id,
  () => void look(),
  { immediate: true },
)

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <div class="wx-article-tags">
    <div v-if="chips.length > 0" class="wx-article-tags__chips">
      <wx-badge v-for="chip in chips" :key="chip.id" type="default" class="wx-article-tags__chip">
        {{ chip.title }}
        <wx-action
          icon="close"
          tone="neutral"
          size="sm"
          :disabled="disabled"
          :title="t('article.tag-remove')"
          @click="remove(chip.id)"
        />
      </wx-badge>
    </div>
    <wx-text v-else size="sm" tone="muted">{{ t('article.tags-empty') }}</wx-text>

    <div class="wx-article-tags__pick">
      <wx-select
        :model-value="null"
        :options="options"
        :placeholder="t('article.tag-add')"
        :empty-text="t('article.tag-nothing')"
        :disabled="disabled"
        filterable
        teleport
        :aria-label="t('article.tag-add')"
        @search="search"
        @update:model-value="add"
      />

      <!-- Beside the field rather than inside the list: a line in a dropdown that creates
           something is a line somebody picks by mistake while scrolling. -->
      <wx-button
        v-if="missing"
        variant="outline"
        icon="plus"
        :loading="working"
        :disabled="disabled"
        @click="create"
      >
        {{ t('article.tag-create', { title: missing }) }}
      </wx-button>
    </div>
  </div>
</template>

<style scoped>
.wx-article-tags {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.wx-article-tags__chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-6);
}

.wx-article-tags__chip {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-4);
}

.wx-article-tags__pick {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  flex-wrap: wrap;
}

.wx-article-tags__pick > :deep(.wx-select) {
  flex: 1 1 220px;
  min-width: 0;
}
</style>
