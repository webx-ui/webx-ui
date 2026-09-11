<script setup lang="ts">
import { ref } from 'vue'
import {
  WxBadge,
  WxButton,
  WxMenu,
  WxMenuGroup,
  WxMenuItem,
  WxSubmenu,
  type MenuValue,
} from '@webx-ui/core'

const active = ref<MenuValue>('posts')
const collapsed = ref(false)

const topLevel = ref<MenuValue>('content')
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">A sidebar</span>
      <div class="menu-demo__row">
        <div class="menu-demo__sidebar" :class="{ 'is-collapsed': collapsed }">
          <wx-menu v-model="active" :collapsed="collapsed" accordion label="Main navigation">
            <wx-menu-item value="dashboard" icon="home" label="Dashboard" />

            <wx-menu-group title="Content">
              <wx-submenu value="pages" icon="file" title="Pages">
                <wx-menu-item value="posts" label="All pages" />
                <wx-menu-item value="drafts" label="Drafts">
                  <template #trailing><wx-badge size="sm">4</wx-badge></template>
                </wx-menu-item>
                <wx-submenu value="taxonomy" title="Taxonomy">
                  <wx-menu-item value="tags" label="Tags" />
                  <wx-menu-item value="categories" label="Categories" />
                </wx-submenu>
              </wx-submenu>

              <wx-menu-item value="media" icon="image" label="Media" />
            </wx-menu-group>

            <wx-menu-group title="System">
              <wx-menu-item value="users" icon="users" label="Users" />
              <wx-menu-item value="settings" icon="settings" label="Settings" />
              <wx-menu-item value="logs" icon="list" label="Logs" disabled />
            </wx-menu-group>
          </wx-menu>
        </div>

        <div class="menu-demo__aside">
          <wx-button size="sm" variant="outline" @click="collapsed = !collapsed">
            {{ collapsed ? 'Expand' : 'Collapse' }}
          </wx-button>
          <p class="menu-demo__value">
            Selected: <code>{{ active }}</code>
          </p>
        </div>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">A bar — submenus open as flyouts</span>
      <div class="menu-demo__bar">
        <wx-menu v-model="topLevel" mode="horizontal" label="Sections">
          <wx-menu-item value="overview" icon="grid" label="Overview" />
          <wx-submenu value="content" icon="file" title="Content">
            <wx-menu-item value="pages-top" icon="file" label="Pages" />
            <wx-menu-item value="media-top" icon="image" label="Media" />
            <wx-submenu value="more" icon="folder" title="More">
              <wx-menu-item value="redirects" icon="link" label="Redirects" />
              <wx-menu-item value="imports" icon="upload" label="Imports" />
            </wx-submenu>
          </wx-submenu>
          <wx-menu-item value="reports" icon="list" label="Reports" />
        </wx-menu>
      </div>
    </div>
  </div>
</template>

<style scoped>
.menu-demo__row {
  display: flex;
  align-items: flex-start;
  gap: var(--wx-space-16);
  width: 100%;
}

.menu-demo__sidebar {
  width: 240px;
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
  transition: width var(--wx-duration-normal) var(--wx-easing-standard);
}

.menu-demo__sidebar.is-collapsed {
  width: 56px;
}

.menu-demo__aside {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
}

.menu-demo__value {
  margin: 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}

.menu-demo__bar {
  width: 100%;
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
}
</style>
