<script setup lang="ts">
import { computed, ref } from 'vue'
import { shellLayoutFor, useResponsiveShell, WxToaster } from '@webx-ui/core'
import { useAdmin } from './admin'
import { useTranslate } from './i18n'

/**
 * The panel around the screen: navigation built from the manifest and the hole the router
 * fills.
 *
 * Three shapes, chosen by the width of the shell rather than the window: the full sidebar on a
 * desktop, an icon rail on a tablet, a drawer behind a burger on a phone.
 *
 * The sidebar is the whole of the chrome on the two wider ones — brand at the top, menu in the
 * middle with its own scrollbar, the account at the bottom — and there is no bar across the top
 * at all: 56px of the window's height went on a logo and an avatar, which are two things a
 * column 220px wide has room for. A phone has no such column, so there the bar comes back.
 *
 * What scrolls is the page. The sidebar stands still beside it, and screens that have to fill
 * the window measure themselves against the window.
 *
 * It draws nothing until the manifest has arrived, and nothing but the route while nobody is
 * signed in — the sign-in screen is a route like any other, and it has no business being
 * wrapped in a menu of sections the visitor cannot reach.
 */
const admin = useAdmin()
const t = useTranslate('webx-admin')
const shellEl = ref<HTMLElement | null>(null)

const { width, layout, collapsed, showAside, drawerOpen, toggle, close } = useResponsiveShell(
  shellEl,
  { persist: 'webx-admin-shell' },
)

const title = computed(() => admin.state.manifest?.title ?? '')

/*
 * The client's logo, when the settings hold one. The name never leaves: it is the alt of the
 * picture, what stands in the corner until the picture arrives, and what stands there for good
 * if the file behind it is gone.
 */
const logo = computed(() => admin.state.manifest?.branding?.logo ?? null)
const mark = computed(() => admin.state.manifest?.branding?.mark ?? null)

/*
 * The step the frame is spaced by — 8 on a phone, 12 on a tablet, 16 on a desktop — follows the
 * size of the screen and not the shape the menu is in. A sidebar somebody collapsed by hand on a
 * 1440px desktop is still a desktop, and air measured off the menu would shrink with it.
 */
const size = computed(() => shellLayoutFor(width.value, null))
</script>

<template>
  <wx-toaster />

  <div v-if="admin.state.status === 'unauthenticated'" class="wx-root wx-admin-plain">
    <router-view />
  </div>

  <div v-else-if="admin.state.status === 'loading'" class="wx-root wx-admin-plain">
    <wx-loading :label="t('shell.loading')" />
  </div>

  <div v-else-if="admin.state.status === 'error'" class="wx-root wx-admin-plain">
    <wx-result status="error" :title="t('shell.error-title')" :description="admin.state.error">
      <wx-button type="primary" @click="admin.reload()">{{ t('shell.retry') }}</wx-button>
    </wx-result>
  </div>

  <div v-else ref="shellEl" class="wx-root wx-admin" :class="`wx-admin--${size}`">
    <wx-container
      :direction="layout === 'drawer' ? 'vertical' : 'horizontal'"
      full-height
      class="wx-admin__frame"
    >
      <!--
        The phone's bar. It carries what the sidebar carries everywhere else, in the same
        order across instead of down: the way into the menu, the brand, the account.
      -->
      <wx-header
        v-if="layout === 'drawer'"
        class="wx-admin__header"
        floating
        sticky
        :bordered="false"
        padding="sm"
        height="var(--wx-admin-header-height)"
      >
        <wx-action icon="menu" :title="t('nav.menu')" @click="toggle" />

        <slot name="brand">
          <img
            v-if="logo"
            class="wx-admin__logo"
            :src="logo.url"
            :alt="title"
            :width="logo.width ?? undefined"
            :height="logo.height ?? undefined"
          />
          <wx-text v-else weight="semibold" truncate>{{ title }}</wx-text>
        </slot>

        <template #end>
          <slot name="user" />
        </template>
      </wx-header>

      <wx-aside
        v-if="showAside"
        class="wx-admin__aside"
        sticky
        floating
        scroll
        :bordered="false"
        :collapsed="collapsed"
        :width="220"
        :collapsed-width="56"
      >
        <template #top>
          <div class="wx-admin__brand" :class="{ 'wx-admin__brand--stacked': collapsed && mark }">
            <!--
              The rail has no room for a name, and the button is the one thing on it that
              has to stay: without it there is no way back to the full sidebar. A mark fits
              there, but not beside the button — 56px holds one of them at a time, so the two
              stand one above the other.
            -->
            <slot v-if="!collapsed" name="brand">
              <img
                v-if="logo"
                class="wx-admin__logo"
                :src="logo.url"
                :alt="title"
                :width="logo.width ?? undefined"
                :height="logo.height ?? undefined"
              />
              <wx-text v-else weight="semibold" truncate class="wx-admin__title">
                {{ title }}
              </wx-text>
            </slot>

            <img v-else-if="mark" class="wx-admin__mark" :src="mark.url" :alt="title" />

            <wx-action
              icon="sidebar"
              :title="collapsed ? t('nav.expand') : t('nav.collapse')"
              @click="toggle"
            />
          </div>
        </template>

        <slot name="nav" :collapsed="collapsed" />

        <template #bottom>
          <div class="wx-admin__user">
            <slot name="user" />
          </div>
        </template>
      </wx-aside>

      <wx-main padding="none" class="wx-admin__screen">
        <router-view />
      </wx-main>
    </wx-container>

    <!-- The same three zones the sidebar has, in the one place a phone can put them. -->
    <wx-drawer
      v-model:open="drawerOpen"
      :title="title === '' ? t('nav.menu') : title"
      side="left"
      :size="260"
      closable
    >
      <slot name="nav" :collapsed="false" :select="close" />

      <template #footer>
        <div class="wx-admin__user wx-admin__user--drawer">
          <slot name="user" />
        </div>
      </template>
    </wx-drawer>
  </div>
</template>

<style scoped>
/*
 * The page scrolls, so the shell is at least a window tall rather than exactly one. The body
 * colour is here as well as on the container: it is what shows around the floating cards, and
 * on a short screen the container ends where the content does.
 */
.wx-admin {
  /* The phone's bar, in one place: it is the bar's own height and part of what is left
     over for a screen that has to fill the window. */
  --wx-admin-header-height: 52px;

  min-height: 100dvh;
  background: var(--wx-bg-body);
}

/*
 * One step for the whole panel (§6 of the visual spec) — the air around the sidebar, around
 * the bar and between them and the screen, and, because a custom property inherits, the air
 * between the cards on a screen, down the columns of a grid, between the fields of a form and
 * inside a card. Everything that lays anything out reads it from here.
 *
 * The width decides, and it is the shell's size class that says which width — not a container
 * query: that would need `container-type` on the panel's root, and with it a containing block
 * for every `position: fixed` inside. A sidebar collapsed by hand at 1440 is still a desktop.
 */
.wx-admin--drawer {
  --wx-gap: var(--wx-space-8);
}

.wx-admin--rail {
  --wx-gap: var(--wx-space-12);
}

.wx-admin--sidebar {
  --wx-gap: var(--wx-space-16);
}

.wx-admin__frame {
  padding: var(--wx-gap);
  gap: var(--wx-gap);
}

.wx-admin__header {
  --wx-header-top: var(--wx-gap);
}

/*
 * The column stands in the window, inset by the same step on both sides, and what scrolls
 * inside it is the menu between the two zones.
 */
.wx-admin__aside {
  --wx-aside-top: var(--wx-gap);
  --wx-aside-height: calc(100dvh - var(--wx-gap) * 2);
}

.wx-admin__brand {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  box-sizing: border-box;
  min-height: 48px;
  padding: var(--wx-space-8);
  border-bottom: 1px solid var(--wx-border-muted);
}

/* On the rail the one button in this zone stands where the icons below it stand. */
.wx-aside--collapsed .wx-admin__brand {
  justify-content: center;
  padding-inline: var(--wx-space-4);
}

.wx-admin__brand--stacked {
  flex-direction: column;
  gap: var(--wx-space-4);
}

.wx-admin__title {
  flex: 1 1 auto;
  min-width: 0;
}

/*
 * The logo is given a height and takes whatever width that leaves it: a client's file can be
 * any shape, and the one measurement the corner can promise is how tall a brand is allowed to
 * be.
 *
 * `min-width: 0` is what actually holds a wide one in. A picture in a flex row refuses to go
 * below its natural width, and `max-width: 100%` is measured before the button beside it is
 * placed — so a 1600px wordmark took the whole sidebar and pushed the button for collapsing
 * it 15px outside the panel. Measured, not guessed.
 */
.wx-admin__logo {
  flex: 0 1 auto;
  min-width: 0;
  height: 28px;
  width: auto;
  max-width: 100%;
  object-fit: contain;
  object-position: left center;
}

.wx-admin__mark {
  height: 24px;
  width: 24px;
  object-fit: contain;
}

.wx-admin__user {
  display: flex;
  align-items: center;
  padding: var(--wx-space-8);
  border-top: 1px solid var(--wx-border-muted);
}

.wx-aside--collapsed .wx-admin__user {
  justify-content: center;
}

/*
 * In the drawer the footer already has its own rule and padding. It also puts what it holds
 * at the far end, where a row of buttons belongs — the account is not that, and it stands
 * where it stands in the sidebar.
 */
.wx-admin__user--drawer {
  flex: 1 1 auto;
  justify-content: flex-start;
  padding: 0;
  border-top: none;
}

/*
 * A screen that has to be exactly as tall as the window — the page editor, the kanban, the
 * media library — is told how much of the window the frame has left it. On the two wider
 * shapes that is the air above and below; on a phone the bar and its own step as well.
 */
.wx-admin__screen {
  --wx-fill-height: calc(100dvh - var(--wx-gap) * 2);

  /* And where a screen's own action bar stops, short of the edge, so that it floats with
     the same air as the sidebar beside it. */
  --wx-action-bar-bottom: var(--wx-gap);

  min-width: 0;
  min-height: 0;
}

.wx-admin--drawer .wx-admin__screen {
  --wx-fill-height: calc(100dvh - var(--wx-gap) * 3 - var(--wx-admin-header-height));
}

/* The states with no shell around them: sign-in, loading, and the one where the panel could
   not start. Each is a single thing in the middle of an empty page. */
.wx-admin-plain {
  display: grid;
  place-items: center;
  /* Without this the padding is added to the viewport height and the page scrolls by exactly
     the padding. */
  box-sizing: border-box;
  min-height: 100dvh;
  padding: var(--wx-space-16);
  background: var(--wx-bg-body);
}
</style>

<style>
/* Not scoped, and global on purpose: the panel is the whole page, so the browser default
   margin on <body> shows up as a gap around the shell — and an uneven one, since the shell
   already keeps its own air around the frame. */
html:has(> body > #webx-app),
body:has(> #webx-app) {
  margin: 0;
}
</style>
