<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAdmin, useTranslate, WxListScreen, type ScreenAction } from '@webx-ui/module-admin'
import type { TabItem, TabValue } from '@webx-ui/core'
import { useAuditMessages } from './i18n'

/**
 * The head of the section: its name, the overview and the findings as two views, and the way
 * to the settings, which live on the «Audit» tab of the site's settings.
 */
const props = defineProps<{
  base: string
  settingsPath: string
  current: 'overview' | 'issues'
  /** A card around the slot — the findings' table wants one, the overview draws its own. */
  card?: boolean
}>()

defineSlots<{ default?: () => unknown }>()

const context = useAdmin()
const router = useRouter()
const route = useRoute()

useAuditMessages()

const t = useTranslate('webx-audit')

const title = computed(
  () =>
    context.state.manifest?.modules.find((module) => module.id === 'audit')?.title ??
    t('module.title'),
)

const views = computed<TabItem[]>(() => [
  { value: 'overview', label: t('page.overview') },
  { value: 'issues', label: t('page.issues') },
])

const actions = computed<ScreenAction[]>(() =>
  /* Only where the settings section is installed and opens for this administrator. */
  context.state.manifest?.modules.some((module) => module.id === 'settings')
    ? [
        {
          key: 'settings',
          label: t('page.settings'),
          icon: 'settings',
          run: () => void router.push(props.settingsPath),
        },
      ]
    : [],
)

const where = computed<TabValue>({
  get: () => props.current,
  set: (next) => {
    const path = next === 'overview' ? props.base : `${props.base}/${String(next)}`

    if (path !== route.path) void router.push(path)
  },
})
</script>

<template>
  <wx-list-screen
    v-model:view="where"
    :title="title"
    :views="views"
    :actions="actions"
    :card="props.card ?? true"
  >
    <slot />
  </wx-list-screen>
</template>
