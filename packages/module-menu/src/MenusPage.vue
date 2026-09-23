<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  confirm,
  createModal,
  toast,
  WxAction,
  WxCard,
  WxEmpty,
  WxIndicator,
  WxListDetail,
  WxSkeleton,
  WxText,
} from '@webx-ui/core'
import {
  useAdmin,
  useErrorText,
  useTranslate,
  WxDate,
  WxListScreen,
  WxRowMenu,
  type RowAction,
  type ScreenAction,
} from '@webx-ui/module-admin'
import MenuCreateDialog from './MenuCreateDialog.vue'
import MenuTreePane from './MenuTreePane.vue'
import { createMenusApi } from './api'
import { useMenuMessages } from './i18n'
import type { MenuRow } from './types'

/**
 * The section: the menus of the site on the left, the items of one of them on the right (§9).
 *
 * The menus are the list and the tree is the record beside it, rather than the other way round.
 * Somebody who opens this section is arranging a menu, not choosing between four of them — so
 * the first one opens by itself where there is room for it, and on a phone it does not, because
 * there the same line would raise a panel over a list nobody has touched.
 */
const admin = useAdmin()
const api = createMenusApi(admin)
const route = useRoute()
const router = useRouter()
useMenuMessages()

const t = useTranslate('webx-menu')
/* Not the server's `message`: the panel says how a request failed in its own words. */
const message = useErrorText()

const menus = ref<MenuRow[]>([])
const loading = ref(true)
const open = ref(false)
const detailInline = ref(true)

const canManage = computed(() => admin.can('menu.manage'))

const edit = createModal<MenuRow, { menu?: MenuRow | null }>(MenuCreateDialog)

const title = computed(
  () =>
    admin.state.manifest?.modules.find((module) => module.id === 'menu')?.title ??
    t('module.title'),
)

/**
 * Which menu is open, kept in the address — so that coming back from anywhere lands on the
 * menu somebody was arranging, and so that "the footer" is a link they can send.
 */
const current = computed<string | null>(() => {
  const asked = route.query.menu

  return typeof asked === 'string' && asked !== '' ? asked : null
})

const chosen = computed(() => menus.value.find((menu) => menu.key === current.value) ?? null)

async function load(): Promise<void> {
  loading.value = true

  try {
    menus.value = await api.list()
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

void load()

/*
 * The first menu, where there is a column to draw it in. A screen whose whole right-hand half
 * says "choose one" has spent its width on an instruction; a phone, where the same thing means
 * opening a panel over the list, gets the list.
 */
watch([menus, chosen, detailInline], () => {
  if (!detailInline.value || menus.value.length === 0 || chosen.value !== null) return

  void router.replace({ query: { ...route.query, menu: menus.value[0].key } })
})

watch(chosen, (menu) => {
  if (menu !== null && detailInline.value) open.value = true
})

function choose(menu: MenuRow): void {
  void router.replace({ query: { ...route.query, menu: menu.key } })
  open.value = true
}

async function add(): Promise<void> {
  const made = await edit({ menu: null })

  if (!made) return

  await load()
  choose(made)
}

async function rename(menu: MenuRow): Promise<void> {
  const saved = await edit({ menu })

  if (!saved) return

  toast.success(t('menu.renamed'))

  // The key may have changed with it, and the address names the old one.
  if (current.value === menu.key && saved.key !== menu.key) {
    void router.replace({ query: { ...route.query, menu: saved.key } })
  }

  await load()
}

async function remove(menu: MenuRow): Promise<void> {
  const agreed = await confirm({
    title: t('menu.delete-menu-title', { menu: menu.title }),
    message: t('menu.delete-menu-text'),
    confirmText: t('menu.delete'),
    cancelText: t('menu.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.remove(menu.key)
    toast.success(t('menu.deleted'))

    if (current.value === menu.key) {
      const rest = { ...route.query }
      delete rest.menu
      void router.replace({ query: rest })
      open.value = false
    }

    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

/**
 * Forget what this menu was built into, in every language.
 *
 * The list is loaded again afterwards rather than trusted: the mark under the name is the whole
 * of what this button says out loud, and a button that leaves it reading "built today at 08:10"
 * is a button nobody believes the second time.
 */
async function flush(menu: MenuRow): Promise<void> {
  try {
    await api.flush(menu.key)
    toast.success(t('menu.flushed'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

async function flushAll(): Promise<void> {
  try {
    await api.flushAll()
    toast.success(t('menu.flushed'))
    await load()
  } catch (error) {
    toast.danger(message(error))
  }
}

function actionsFor(menu: MenuRow): RowAction[] {
  if (!canManage.value) return []

  return [
    { key: 'rename', icon: 'edit', label: t('menu.rename'), run: () => void rename(menu) },
    { key: 'flush', icon: 'refresh', label: t('menu.flush'), run: () => void flush(menu) },
    ...(menu.can.delete
      ? [
          {
            key: 'delete',
            icon: 'trash' as const,
            label: t('menu.delete'),
            danger: true,
            run: () => void remove(menu),
          },
        ]
      : []),
  ]
}

const actions = computed<ScreenAction[]>(() => {
  if (!canManage.value) return []

  return [
    {
      key: 'flush-all',
      label: t('menu.flush-all'),
      icon: 'refresh',
      menu: true,
      run: () => void flushAll(),
    },
    {
      key: 'new-menu',
      label: t('menu.new-menu'),
      icon: 'plus',
      primary: true,
      run: () => void add(),
    },
  ]
})
</script>

<template>
  <wx-list-screen :title="title" :actions="actions" :card="false">
    <wx-card class="wx-menus" padding="none">
      <wx-list-detail
        v-model:open="open"
        class="wx-menus__panes"
        :list-width="320"
        :detail-min="420"
        :detail-label="t('menu.menus')"
        @detail-inline="detailInline = $event"
      >
        <template #list>
          <wx-skeleton v-if="loading" class="wx-menus__loading" :rows="4" />

          <wx-empty
            v-else-if="menus.length === 0"
            :title="t('menu.no-menus')"
            :description="t('menu.no-menus-help')"
          />

          <ul v-else class="wx-menus__list">
            <li v-for="menu in menus" :key="menu.key" class="wx-menus__row">
              <button
                type="button"
                class="wx-menus__open"
                :class="{ 'is-current': menu.key === current }"
                @click="choose(menu)"
              >
                <span class="wx-menus__name">
                  <wx-text truncate weight="medium">{{ menu.title }}</wx-text>
                  <wx-indicator
                    class="wx-menus__count"
                    type="neutral"
                    :value="menu.items_count"
                    :label="t('menu.menus')"
                  />
                </span>

                <wx-text size="sm" tone="muted" mono truncate>{{ menu.key }}</wx-text>

                <!--
                  When the cache was built, in the panel's own words — and it is a sentence
                  rather than a date, because "built" is the part that answers the question
                  somebody pressed the button next to it to ask.
                -->
                <span class="wx-menus__cache">
                  <wx-text v-if="!menu.cache.enabled" size="sm" tone="muted">
                    {{ t('menu.cache-off') }}
                  </wx-text>
                  <template v-else-if="menu.cache.built_at !== null">
                    <wx-text size="sm" tone="muted">{{ t('menu.cache-built') }}</wx-text>
                    <wx-date :value="menu.cache.built_at" />
                  </template>
                  <wx-text v-else size="sm" tone="muted">{{ t('menu.cache-empty') }}</wx-text>
                </span>
              </button>

              <!--
                The reset stands in the row and not only in the `···`: it is what somebody
                presses to test the guess that they are looking at something stale, and a guess
                behind a menu is one they check by reloading the site instead (§9).
              -->
              <wx-action
                v-if="canManage && menu.cache.enabled"
                class="wx-menus__flush"
                icon="refresh"
                size="sm"
                :title="t('menu.flush')"
                @click="flush(menu)"
              />

              <wx-row-menu v-if="canManage" :actions="actionsFor(menu)" :label="menu.title" />
            </li>
          </ul>
        </template>

        <template #detail="{ inline, back }">
          <menu-tree-pane
            v-if="chosen"
            :key="chosen.key"
            :menu="chosen"
            :inline="inline"
            :back="back"
            @changed="load"
          />
        </template>

        <template #empty>
          <wx-empty :title="t('menu.choose-menu')" :description="t('menu.choose-menu-help')" />
        </template>
      </wx-list-detail>
    </wx-card>
  </wx-list-screen>
</template>

<style>
/*
 * What scrolls here is the page, the way it does on every other list of the panel.
 *
 * The card's corners are the screen's corners, and the two panes inside are square and paint
 * their own background to the edge — without the clip they cover the rounding. `clip` and not
 * `hidden`: `hidden` would make this a scroll container, and everything sticky inside would pin
 * itself to a box that never moves (CLAUDE.md §4).
 */
.wx-menus > .wx-card__body {
  display: flex;
  flex-direction: column;
  overflow: clip;
  border-radius: inherit;
}
</style>

<style scoped>
.wx-menus__loading {
  padding: var(--wx-space-16);
}

.wx-menus__list {
  margin: 0;
  padding: var(--wx-space-8);
  list-style: none;
}

.wx-menus__row {
  display: flex;
  align-items: center;
  gap: var(--wx-space-4);
  margin: 0;
  padding-inline: var(--wx-space-8);
  border-radius: var(--wx-radius-sm);
}

.wx-menus__row:has(.is-current) {
  background: var(--wx-bg-subtle);
}

.wx-menus__open {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--wx-space-2);
  /* The row is the target: a menu called `header` should not leave the line dead. */
  flex: 1 1 auto;
  min-width: 0;
  padding-block: var(--wx-space-8);
  padding-inline: 0;
  border: 0;
  background: none;
  font: inherit;
  color: inherit;
  text-align: start;
  cursor: pointer;
}

.wx-menus__open.is-current {
  color: var(--wx-color-primary);
}

.wx-menus__name {
  display: flex;
  align-items: center;
  gap: var(--wx-space-6);
  min-width: 0;
  max-width: 100%;
}

.wx-menus__count {
  flex: 0 0 auto;
}

.wx-menus__cache {
  display: flex;
  align-items: center;
  gap: var(--wx-space-4);
  min-width: 0;
}

/*
 * `:deep()`, because `WxAction` carries its tooltip's portal beside the button and so has a
 * fragment for a root — Vue puts the scope attribute only on a child with a single root, and
 * without this the rule matches nothing at all, silently (CLAUDE.md §4).
 */
.wx-menus__row > :deep(.wx-menus__flush) {
  flex: 0 0 auto;
}
</style>
