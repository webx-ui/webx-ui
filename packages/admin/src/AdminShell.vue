<script setup lang="ts">
import { ref } from 'vue'
import { useResponsiveShell } from '@webx-ui/core'
import { useAdmin } from './admin'

/**
 * The panel around the screen: navigation built from the manifest, a header, and the hole the
 * router fills.
 *
 * Three shapes, chosen by the width of the shell rather than the window: the full sidebar on a
 * desktop, an icon rail on a tablet, a drawer behind a burger on a phone.
 *
 * It draws nothing until the manifest has arrived, and nothing but the route while nobody is
 * signed in — the sign-in screen is a route like any other, and it has no business being
 * wrapped in a menu of sections the visitor cannot reach.
 */
const admin = useAdmin()
const shellEl = ref<HTMLElement | null>(null)

const { layout, collapsed, showAside, drawerOpen, toggle, close } = useResponsiveShell(shellEl, {
  persist: 'webx-admin-shell',
})
</script>

<template>
  <div v-if="admin.state.status === 'unauthenticated'" class="wx-root wx-admin-plain">
    <router-view />
  </div>

  <div v-else-if="admin.state.status === 'loading'" class="wx-root wx-admin-plain">
    <wx-loading label="Loading the panel…" />
  </div>

  <div v-else-if="admin.state.status === 'error'" class="wx-root wx-admin-plain">
    <wx-result status="error" title="The panel could not start" :description="admin.state.error">
      <wx-button type="primary" @click="admin.reload()">Try again</wx-button>
    </wx-result>
  </div>

  <div v-else ref="shellEl" class="wx-root wx-admin">
    <wx-container viewport>
      <wx-header>
        <wx-action
          :icon="layout === 'drawer' ? 'menu' : 'sidebar'"
          :title="layout === 'drawer' ? 'Menu' : 'Collapse the menu'"
          @click="toggle"
        />

        <slot name="brand">
          <wx-text weight="semibold">{{ admin.state.manifest?.title }}</wx-text>
        </slot>

        <template #end>
          <slot name="user" />
        </template>
      </wx-header>

      <wx-container direction="horizontal">
        <wx-aside v-if="showAside" :collapsed="collapsed" :width="220" scroll>
          <slot name="nav" :collapsed="collapsed" />
        </wx-aside>

        <wx-main padding="md" scroll class="wx-admin__screen">
          <router-view />
        </wx-main>
      </wx-container>
    </wx-container>

    <wx-drawer v-model:open="drawerOpen" title="Menu" side="left" :size="260" closable>
      <slot name="nav" :collapsed="false" @select="close" />
    </wx-drawer>
  </div>
</template>

<style scoped>
.wx-admin {
  height: 100dvh;
}

.wx-admin__screen {
  min-width: 0;
  min-height: 0;
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
   margin on <body> shows up as a gap around the shell and puts a scrollbar under a column
   that is exactly one viewport tall. */
html:has(> body > #webx-app),
body:has(> #webx-app) {
  margin: 0;
}
</style>
