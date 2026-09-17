<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAdmin, useTranslate, WxListScreen } from '@webx-ui/module-admin'
import { WxButton, type TabItem, type TabValue } from '@webx-ui/core'
import { useSeoMessages } from './i18n'

/**
 * The head of the section: its name, the views of what it holds, and the one tool that belongs
 * to the whole of it.
 *
 * Rules, redirects and the trail of renames are three routes rather than three panels of one,
 * because they are three tables with their own paging and their own search — and a panel that
 * quietly resets both when you come back to it is worse than a second address. What they share
 * is the frame: one heading, one strip of tabs, one card under it (§19).
 */
const props = defineProps<{ base: string; current: 'rules' | 'redirects' | 'aliases' }>()

const emit = defineEmits<{ test: [] }>()

defineSlots<{
  /** The table. */
  default?: () => unknown
  /** The view's own main action — `New rule` — before the section's `Check an address`. */
  actions?: () => unknown
}>()

const context = useAdmin()
const router = useRouter()
const route = useRoute()

useSeoMessages()

const t = useTranslate('webx-seo')

/** The section's name as the server translated it; the built-in English until it arrives. */
const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'seo')?.title ??
    t('module.title'),
)

const views = computed<TabItem[]>(() => [
  { value: 'rules', label: t('page.rules') },
  { value: 'redirects', label: t('page.redirects') },
  { value: 'aliases', label: t('page.automatic') },
])

const where = computed<TabValue>({
  get: () => props.current,
  set: (next) => {
    const path = next === 'rules' ? props.base : `${props.base}/${String(next)}`

    if (path !== route.path) void router.push(path)
  },
})
</script>

<template>
  <wx-list-screen v-model:view="where" :title="title" :views="views">
    <template #actions>
      <slot name="actions" />

      <wx-button variant="outline" icon="search" @click="emit('test')">
        {{ t('page.test') }}
      </wx-button>
    </template>

    <slot />
  </wx-list-screen>
</template>
