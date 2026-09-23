<script setup lang="ts">
import { computed, onUnmounted, ref, watchEffect } from 'vue'
import { shellLayoutFor, useResponsiveShell } from '@webx-ui/core'
import NavMenu from './NavMenu.vue'

/**
 * The shell the panel actually wears, so that a screen tried out here is tried out in the frame
 * it will live in.
 *
 * It is `AdminShell` from `module-admin` with the manifest and the router taken out: the
 * sidebar is a card floating in the page rather than a wall beside it, it carries the whole of
 * the chrome — brand at the top, sections in the middle, the account at the bottom — and on a
 * desktop there is no bar across the top at all. The page is what scrolls; the sidebar stands
 * still next to it.
 *
 * The step everything is spaced by (`--wx-gap`) follows the width of the shell and is written
 * on the document as well, because drawers, dialogs and toasts are teleported out of the tree
 * and would otherwise inherit nothing.
 */
const shellEl = ref<HTMLElement | null>(null)

const { width, layout, collapsed, showAside, drawerOpen, toggle, close } = useResponsiveShell(
  shellEl,
  { persist: 'playground-admin-shell' },
)

const title = 'Demo admin'

const size = computed(() => shellLayoutFor(width.value, null))

watchEffect(() => {
  if (typeof document === 'undefined') return

  document.documentElement.dataset.wxShell = size.value
})

onUnmounted(() => {
  if (typeof document !== 'undefined') delete document.documentElement.dataset.wxShell
})

const section = ref('dashboard')
</script>

<template>
  <div ref="shellEl" class="admin" :class="`admin--${size}`">
    <wx-container
      :direction="layout === 'drawer' ? 'vertical' : 'horizontal'"
      full-height
      class="admin__frame"
    >
      <!-- The phone's bar: the way into the menu, the brand, the account — the sidebar's
           three zones in the one place a phone can put them. -->
      <wx-header
        v-if="layout === 'drawer'"
        class="admin__header"
        floating
        sticky
        :bordered="false"
        padding="sm"
        height="var(--admin-header-height)"
      >
        <wx-action icon="menu" title="Меню" @click="toggle" />
        <wx-text weight="semibold" truncate>{{ title }}</wx-text>

        <template #end>
          <wx-avatar size="sm" name="Олег Мороз" />
        </template>
      </wx-header>

      <wx-aside
        v-if="showAside"
        class="admin__aside"
        sticky
        floating
        scroll
        :bordered="false"
        :collapsed="collapsed"
        :width="220"
        :collapsed-width="56"
      >
        <template #top>
          <div class="admin__brand">
            <!-- The rail holds one of the two at a time, and the button is the one that has
                 to stay: without it there is no way back to the full sidebar. -->
            <span v-if="!collapsed" class="admin__mark" aria-hidden="true">W</span>
            <strong v-if="!collapsed" class="admin__title">{{ title }}</strong>

            <wx-action
              icon="sidebar"
              :title="collapsed ? 'Розгорнути меню' : 'Згорнути меню'"
              @click="toggle"
            />
          </div>
        </template>

        <nav-menu v-model="section" :collapsed="collapsed" />

        <template #bottom>
          <div class="admin__user">
            <wx-dropdown align="start" side="top">
              <template #trigger>
                <button class="admin__account" type="button">
                  <wx-avatar size="sm" name="Олег Мороз" />
                  <span v-if="!collapsed" class="admin__account-name">Олег М.</span>
                </button>
              </template>

              <wx-dropdown-item icon="user">Профіль</wx-dropdown-item>
              <wx-dropdown-item icon="settings">Налаштування</wx-dropdown-item>
              <hr />
              <wx-dropdown-item icon="logout" tone="danger">Вийти</wx-dropdown-item>
            </wx-dropdown>
          </div>
        </template>
      </wx-aside>

      <wx-main padding="none" class="admin__screen">
        <slot />
      </wx-main>
    </wx-container>

    <wx-drawer
      v-model:open="drawerOpen"
      class="admin__drawer"
      :title="title"
      side="left"
      :size="260"
      :full-screen="false"
      closable
    >
      <nav-menu v-model="section" @select="close" />

      <template #footer>
        <div class="admin__user admin__user--drawer">
          <wx-avatar size="sm" name="Олег Мороз" />
          <span class="admin__account-name">Олег М.</span>
        </div>
      </template>
    </wx-drawer>
  </div>
</template>

<style scoped>
/* The page scrolls, so the shell is at least a window tall rather than exactly one. The body
   colour is what shows around the floating cards. */
.admin {
  --admin-header-height: 52px;

  min-height: 100dvh;
  background: var(--wx-bg-body);
}

/* One step for the whole panel: the air around the sidebar, between it and the screen, and —
   because a custom property inherits — inside every card on the screen. */
.admin--drawer {
  --wx-gap: var(--wx-space-8);
}

.admin--rail {
  --wx-gap: var(--wx-space-12);
}

.admin--sidebar {
  --wx-gap: var(--wx-space-16);
}

.admin__frame {
  padding: var(--wx-gap);
  gap: var(--wx-gap);
}

.admin__header {
  --wx-header-top: var(--wx-gap);
}

.admin__aside {
  --wx-aside-top: var(--wx-gap);
  --wx-aside-height: calc(100dvh - var(--wx-gap) * 2);
}

.admin__brand {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  box-sizing: border-box;
  min-height: 48px;
  padding: var(--wx-space-8);
  border-bottom: 1px solid var(--wx-border-muted);
}

/* On the rail the one button in this zone stands where the icons below it stand. */
.wx-aside--collapsed .admin__brand {
  justify-content: center;
  padding-inline: var(--wx-space-4);
}

.admin__mark {
  display: grid;
  place-items: center;
  flex: none;
  width: 26px;
  height: 26px;
  border-radius: var(--wx-radius-sm);
  background: var(--wx-color-primary);
  color: var(--wx-text-inverse);
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-bold);
}

.admin__title {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  font-size: var(--wx-font-size-md);
  font-weight: var(--wx-font-weight-semibold);
  white-space: nowrap;
  text-overflow: ellipsis;
}

.admin__user {
  display: flex;
  align-items: center;
  padding: var(--wx-space-8);
  border-top: 1px solid var(--wx-border-muted);
}

.wx-aside--collapsed .admin__user {
  justify-content: center;
}

.admin__account {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  min-width: 0;
  padding: var(--wx-space-4);
  border: none;
  border-radius: var(--wx-radius-control);
  background: none;
  color: inherit;
  font: inherit;
  cursor: pointer;
}

.admin__account:hover {
  background: var(--wx-bg-subtle);
}

.admin__account-name {
  overflow: hidden;
  font-size: var(--wx-font-size-sm);
  white-space: nowrap;
  text-overflow: ellipsis;
}

.admin__user--drawer {
  gap: var(--wx-space-8);
  flex: 1 1 auto;
  padding: 0;
  border-top: none;
}

/* A screen that has to be exactly as tall as the window is told how much of it the frame has
   left over. */
.admin__screen {
  --wx-fill-height: calc(100dvh - var(--wx-gap) * 2);
  --wx-action-bar-bottom: var(--wx-gap);

  min-width: 0;
  min-height: 0;
}

.admin--drawer .admin__screen {
  --wx-fill-height: calc(100dvh - var(--wx-gap) * 3 - var(--admin-header-height));
}
</style>

<style>
/*
 * The panel's step where the tree cannot reach: on the document root, for everything that is
 * teleported out of the shell — drawers, dialogs, the toaster. Unscoped, because `:root` is
 * nobody's element.
 */
/*
 * One left edge down the drawer. The drawer already insets what it holds, and `WxMenu` adds a
 * second one of its own — so the highlighted row starts further in than the account block under
 * it. Unscoped and named on the drawer itself: the drawer is teleported to the end of the
 * document, so nothing that begins at `.admin` reaches it.
 */
.admin__drawer .wx-drawer__content > .wx-menu {
  padding-inline: 0;
}

:root[data-wx-shell='drawer'] {
  --wx-gap: var(--wx-space-8);
}

:root[data-wx-shell='rail'] {
  --wx-gap: var(--wx-space-12);
}

:root[data-wx-shell='sidebar'] {
  --wx-gap: var(--wx-space-16);
}
</style>
