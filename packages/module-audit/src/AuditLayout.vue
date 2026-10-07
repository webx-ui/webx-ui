<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAdmin, useTranslate, WxListScreen, type ScreenAction } from '@webx-ui/module-admin'
import type { TabItem, TabValue } from '@webx-ui/core'
import { useAuditMessages } from './i18n'

/**
 * The head of the section: its name, its views — the overview, the findings, the pages, the
 * hosts the site points at and the runs — and the way to the section's own settings.
 */
/*
 * `card` has its default here and not as `?? true` below: Vue turns a Boolean prop nobody passed
 * into `false`, so the coalescing never fired and every table stood without its card.
 */
const props = withDefaults(
  defineProps<{
    base: string
    current: 'overview' | 'issues' | 'pages' | 'hosts' | 'runs' | 'settings'
    /** A card around the slot — the tables want one, the overview draws its own. */
    card?: boolean
    /** What the view itself offers in the head, before the way to the settings. */
    actions?: ScreenAction[]
  }>(),
  { card: true, actions: () => [] },
)

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
  { value: 'pages', label: t('page.pages') },
  { value: 'hosts', label: t('page.hosts') },
  { value: 'runs', label: t('page.runs') },
])

/* The settings are a view of their own, out of the tabs: opened now and then, not every day. */
const actions = computed<ScreenAction[]>(() =>
  props.current === 'settings'
    ? props.actions
    : [
        ...props.actions,
        {
          key: 'settings',
          label: t('page.settings'),
          icon: 'settings',
          run: () => void router.push(`${props.base}/settings`),
        },
      ],
)

const where = computed<TabValue>({
  /*
   * The settings belong to no tab, but the tabs need a value of theirs — given one they do not
   * have, they correct it to the first and lead away at once. So «Overview» stays lit, and the
   * settings page has a «Back» of its own beside «Save».
   */
  get: () => (props.current === 'settings' ? 'overview' : props.current),
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
    :card="props.card"
  >
    <slot />
  </wx-list-screen>
</template>
