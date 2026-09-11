<script setup lang="ts">
import { ref } from 'vue'
import { applyTheme, type Theme } from '@webx-ui/tokens'
import AdminLayout from './AdminLayout.vue'
import InboxScreen from './inbox/InboxScreen.vue'
import KitchenSink from './KitchenSink.vue'

/** Two things to try out here: the components one by one, and a whole screen. */
const screen = ref<'inbox' | 'components'>('inbox')
const theme = ref<Theme>('light')

function toggleTheme() {
  theme.value = theme.value === 'light' ? 'dark' : 'light'
  applyTheme(theme.value)
}
</script>

<template>
  <div class="wx-root">
    <!-- The shell stays put; the screen inside it is what a router would swap. -->
    <admin-layout v-if="screen === 'inbox'">
      <inbox-screen />
    </admin-layout>
    <kitchen-sink v-else />

    <!-- The switcher belongs to the playground, not to either screen. -->
    <div class="switcher">
      <wx-button
        size="sm"
        :variant="screen === 'inbox' ? 'solid' : 'text'"
        :type="screen === 'inbox' ? 'primary' : 'default'"
        @click="screen = 'inbox'"
      >
        Вхідні
      </wx-button>
      <wx-button
        size="sm"
        :variant="screen === 'components' ? 'solid' : 'text'"
        :type="screen === 'components' ? 'primary' : 'default'"
        @click="screen = 'components'"
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
  padding: var(--wx-space-4);
  background: var(--wx-bg-surface);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-full);
  box-shadow: var(--wx-shadow-popover);
}
</style>
