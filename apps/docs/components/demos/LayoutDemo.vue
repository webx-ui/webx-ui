<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  WxAction,
  WxAside,
  WxBadge,
  WxButton,
  WxContainer,
  WxDrawer,
  WxDropdown,
  WxDropdownItem,
  WxFooter,
  WxHeader,
  WxIcon,
  WxMain,
  WxMenu,
  WxMenuItem,
  useResponsiveShell,
} from '@webx-ui/core'

const shellEl = ref<HTMLElement | null>(null)

/* The shell answers to its own width, so the box below can be dragged to see it. */
const { width, layout, collapsed, showAside, drawerOpen, toggle, close } = useResponsiveShell(
  shellEl,
  /* Closing it by hand is remembered; leaving it open goes back to following the width. */
  { persist: 'docs-shell' },
)

const section = ref('pages')

const entries = [
  { value: 'pages', icon: 'file', label: 'Pages' },
  { value: 'media', icon: 'image', label: 'Media' },
  { value: 'users', icon: 'users', label: 'Users' },
  { value: 'settings', icon: 'settings', label: 'Settings' },
] as const

function choose(value: string | number) {
  section.value = String(value)
  close()
}

/* A burger belongs to the drawer: while the sidebar is on the page, the button toggles it. */
const menuButton = computed(() =>
  layout.value === 'drawer'
    ? { icon: 'menu', title: 'Menu' }
    : { icon: 'sidebar', title: layout.value === 'rail' ? 'Expand the sidebar' : 'Collapse it' },
)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div class="layout-demo__scroller">
      <div ref="shellEl" class="layout-demo">
        <wx-container>
          <wx-header>
            <wx-action
              :icon="menuButton.icon"
              :label="menuButton.title"
              :title="menuButton.title"
              @click="toggle()"
            />
            <strong>Admin</strong>

            <template #end>
              <wx-badge size="sm">{{ layout }} · {{ width }}px</wx-badge>

              <wx-dropdown align="end">
                <template #trigger>
                  <wx-button variant="text" size="sm">
                    <template #icon><wx-icon name="user" /></template>
                    Alex <wx-icon name="chevron-down" size="0.85em" />
                  </wx-button>
                </template>

                <wx-dropdown-item icon="user">Profile</wx-dropdown-item>
                <wx-dropdown-item icon="settings">Preferences</wx-dropdown-item>
                <hr />
                <wx-dropdown-item icon="logout" tone="danger">Sign out</wx-dropdown-item>
              </wx-dropdown>
            </template>
          </wx-header>

          <wx-container direction="horizontal">
            <wx-aside v-if="showAside" :collapsed="collapsed" :width="200">
              <wx-menu
                :model-value="section"
                size="sm"
                :collapsed="collapsed"
                label="Sections"
                @select="choose"
              >
                <wx-menu-item
                  v-for="entry in entries"
                  :key="entry.value"
                  :value="entry.value"
                  :icon="entry.icon"
                  :label="entry.label"
                />
              </wx-menu>
            </wx-aside>

            <wx-main>
              <p>
                The <code>{{ section }}</code> screen goes here.
              </p>
            </wx-main>
          </wx-container>

          <wx-footer>WebX UI — MIT</wx-footer>
        </wx-container>

        <!-- The same menu, in a drawer, for the widths that have no room for a column. -->
        <wx-drawer v-model:open="drawerOpen" title="Menu" side="left" :size="240" closable>
          <wx-menu :model-value="section" size="sm" label="Sections" @select="choose">
            <wx-menu-item
              v-for="entry in entries"
              :key="entry.value"
              :value="entry.value"
              :icon="entry.icon"
              :label="entry.label"
            />
          </wx-menu>
        </wx-drawer>
      </div>
    </div>

    <span class="wx-demo__label">
      Drag the bottom-right corner: full sidebar, an icon rail under 1024px, a burger under 640px
    </span>
  </div>
</template>

<style scoped>
.layout-demo__scroller {
  width: 100%;
  overflow-x: auto;
}

/*
 * The shell is normally the page; here it is boxed, and resizable so the three
 * shapes can be seen without touching the window.
 */
.layout-demo {
  position: relative;
  width: 100%;
  min-width: 320px;
  height: 320px;
  overflow: hidden;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  resize: horizontal;
}

/* Nothing here arranges the header any more: the `end` slot takes the far side. */
</style>
