<script setup lang="ts">
import { ref } from 'vue'
import {
  WxAction,
  WxBadge,
  WxContainer,
  WxDrawer,
  WxDropdown,
  WxDropdownItem,
  WxFooter,
  WxHeader,
  WxMain,
  WxMenu,
  WxMenuItem,
  WxSubmenu,
  useResponsiveShell,
} from '@webx-ui/core'

const shellEl = ref<HTMLElement | null>(null)

/*
 * The same composable as the sidebar shell. A bar has no rail, so only one thing is
 * read from it: whether there is room for the bar at all.
 */
const { width, layout, showAside: showBar, drawerOpen, toggle, close } = useResponsiveShell(shellEl)

const section = ref('pages')

function choose(value: string | number) {
  section.value = String(value)
  close()
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div class="topbar-demo__scroller">
      <div ref="shellEl" class="topbar-demo">
        <wx-container>
          <wx-header>
            <strong class="topbar-demo__brand">Admin</strong>

            <wx-menu
              v-if="showBar"
              :model-value="section"
              mode="horizontal"
              size="sm"
              label="Main navigation"
              class="topbar-demo__bar"
              @select="choose"
            >
              <wx-menu-item value="dashboard" icon="grid" label="Dashboard" />
              <wx-submenu value="content" icon="file" title="Content">
                <wx-menu-item value="pages" icon="file" label="Pages" />
                <wx-menu-item value="media" icon="image" label="Media" />
                <wx-submenu value="taxonomy" icon="tag" title="Taxonomy">
                  <wx-menu-item value="tags" icon="tag" label="Tags" />
                  <wx-menu-item value="categories" icon="folder" label="Categories" />
                </wx-submenu>
              </wx-submenu>
              <wx-menu-item value="reports" icon="list" label="Reports" />
            </wx-menu>

            <template #end>
              <wx-badge v-if="showBar" size="sm">{{ width }}px</wx-badge>
              <wx-action v-else icon="menu" label="Menu" title="Menu" @click="toggle()" />

              <wx-dropdown align="end">
                <template #trigger>
                  <wx-action icon="user" title="Account" />
                </template>

                <wx-dropdown-item icon="user">Profile</wx-dropdown-item>
                <wx-dropdown-item icon="settings">Preferences</wx-dropdown-item>
                <hr />
                <wx-dropdown-item icon="logout" tone="danger">Sign out</wx-dropdown-item>
              </wx-dropdown>
            </template>
          </wx-header>

          <wx-main>
            <p>
              The <code>{{ section }}</code> screen goes here, the full width of the page.
            </p>
          </wx-main>

          <wx-footer>WebX UI — MIT</wx-footer>
        </wx-container>

        <!-- Under 640px the bar has nowhere to go but behind the burger. -->
        <wx-drawer v-model:open="drawerOpen" title="Menu" side="left" :size="260" closable>
          <wx-menu :model-value="section" size="sm" label="Main navigation" @select="choose">
            <wx-menu-item value="dashboard" icon="grid" label="Dashboard" />
            <wx-submenu value="content" icon="file" title="Content">
              <wx-menu-item value="pages" icon="file" label="Pages" />
              <wx-menu-item value="media" icon="image" label="Media" />
              <wx-submenu value="taxonomy" icon="tag" title="Taxonomy">
                <wx-menu-item value="tags" icon="tag" label="Tags" />
                <wx-menu-item value="categories" icon="folder" label="Categories" />
              </wx-submenu>
            </wx-submenu>
            <wx-menu-item value="reports" icon="list" label="Reports" />
          </wx-menu>
        </wx-drawer>
      </div>
    </div>

    <span class="wx-demo__label">
      Drag the corner: the bar moves behind a burger under 640px ({{ layout }})
    </span>
  </div>
</template>

<style scoped>
.topbar-demo__scroller {
  width: 100%;
  overflow-x: auto;
}

.topbar-demo {
  position: relative;
  width: 100%;
  min-width: 320px;
  height: 260px;
  overflow: hidden;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  resize: horizontal;
}

.topbar-demo :deep(.wx-header) {
  gap: var(--wx-space-16);
}

.topbar-demo__brand {
  flex: 0 0 auto;
}

/* The bar takes the room between the brand and whatever sits at the end of it. */
.topbar-demo__bar {
  flex: 1 1 auto;
  min-width: 0;
}
</style>
