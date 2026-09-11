<script setup lang="ts">
import { ref } from 'vue'
import { applyTheme, type Theme } from '@webx-ui/tokens'
import SidebarLayout from './layouts/SidebarLayout.vue'
import TopbarLayout from './layouts/TopbarLayout.vue'
import DashboardScreen from './screens/DashboardScreen.vue'
import RecordsScreen from './screens/RecordsScreen.vue'
import InboxScreen from './inbox/InboxScreen.vue'
import KitchenSink from './KitchenSink.vue'

/**
 * Three shells and two screens, in the combinations an admin actually ships:
 * navigation across the top or down the side, and a screen that is either a padded
 * page or one that lays out its own columns.
 */
type Page = 'topbar' | 'sidebar' | 'records' | 'inbox' | 'components'

const page = ref<Page>('topbar')
const theme = ref<Theme>('light')

const pages: { value: Page; label: string }[] = [
  { value: 'topbar', label: 'Меню в шапці' },
  { value: 'sidebar', label: 'Меню збоку' },
  { value: 'records', label: 'Список + деталі' },
]

function toggleTheme() {
  theme.value = theme.value === 'light' ? 'dark' : 'light'
  applyTheme(theme.value)
}
</script>

<template>
  <div class="wx-root">
    <!-- The shell stays put; the screen inside it is what a router would swap. -->
    <topbar-layout v-if="page === 'topbar'">
      <dashboard-screen />
    </topbar-layout>

    <sidebar-layout v-else-if="page === 'sidebar'">
      <dashboard-screen />
    </sidebar-layout>

    <!-- A screen that lays out its own columns takes the room unpadded and unscrolled. -->
    <sidebar-layout v-else-if="page === 'records'" padding="none" :scroll="false">
      <records-screen />
    </sidebar-layout>

    <sidebar-layout v-else-if="page === 'inbox'" padding="none" :scroll="false">
      <inbox-screen />
    </sidebar-layout>

    <kitchen-sink v-else />

    <!-- The switcher belongs to the playground, not to any of the screens. -->
    <div class="switcher">
      <wx-button
        v-for="item in pages"
        :key="item.value"
        size="sm"
        :variant="page === item.value ? 'solid' : 'text'"
        :type="page === item.value ? 'primary' : 'default'"
        @click="page = item.value"
      >
        {{ item.label }}
      </wx-button>

      <wx-divider direction="vertical" spacing="sm" />

      <wx-button
        size="sm"
        :variant="page === 'inbox' ? 'solid' : 'text'"
        :type="page === 'inbox' ? 'primary' : 'default'"
        @click="page = 'inbox'"
      >
        Вхідні
      </wx-button>
      <wx-button
        size="sm"
        :variant="page === 'components' ? 'solid' : 'text'"
        :type="page === 'components' ? 'primary' : 'default'"
        @click="page = 'components'"
      >
        Компоненти
      </wx-button>

      <wx-divider direction="vertical" spacing="sm" />

      <wx-action
        :icon="theme === 'light' ? 'moon' : 'sun'"
        :title="theme === 'light' ? 'Темна тема' : 'Світла тема'"
        size="sm"
        @click="toggleTheme"
      />
    </div>
  </div>
</template>

<style>
body {
  margin: 0;
}
</style>

<style scoped>
.switcher {
  position: fixed;
  bottom: var(--wx-space-16);
  left: var(--wx-space-16);
  z-index: var(--wx-z-index-sticky);
  display: flex;
  align-items: center;
  gap: var(--wx-space-4);
  /* One row that scrolls, rather than three that eat a third of a phone screen. */
  max-width: calc(100vw - var(--wx-space-32));
  overflow-x: auto;
  scrollbar-width: none;
  padding: var(--wx-space-4);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-full);
  box-shadow: var(--wx-shadow-popover);
}
</style>
