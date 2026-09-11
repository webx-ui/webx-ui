<script setup lang="ts">
import { ref } from 'vue'
import { useResponsiveShell } from '@webx-ui/core'
import type { MainProps } from '@webx-ui/core'
import BrandMark from './BrandMark.vue'
import NavMenu from './NavMenu.vue'
import UserMenu from './UserMenu.vue'

/**
 * The same admin, navigated from the top: brand on the left, sections across the
 * bar, the account on the right. No sidebar at all, so the screen gets the full
 * width of the page — which is what a table-heavy admin usually wants.
 *
 * A bar has no rail to collapse into, so only one thing is read from the shell: at
 * the point where the bar no longer fits, the whole menu moves behind a burger.
 */
withDefaults(
  defineProps<{
    padding?: MainProps['padding']
    scroll?: boolean
  }>(),
  { padding: 'md', scroll: true },
)

const shellEl = ref<HTMLElement | null>(null)

const { showAside: showBar, drawerOpen, toggle, close } = useResponsiveShell(shellEl)

const section = ref('dashboard')
</script>

<template>
  <div ref="shellEl" class="shell">
    <wx-container viewport>
      <wx-header class="shell__header">
        <brand-mark />

        <nav-menu v-if="showBar" v-model="section" mode="horizontal" class="shell__bar" />

        <template #end>
          <!-- The burger appears only once the bar is gone; until then it is noise. -->
          <wx-action v-if="!showBar" icon="menu" title="Меню" @click="toggle" />
          <user-menu :compact="!showBar" />
        </template>
      </wx-header>

      <!-- Where `<router-view />` goes. -->
      <wx-main :padding="padding" :scroll="scroll" class="shell__screen">
        <slot />
      </wx-main>
    </wx-container>

    <wx-drawer v-model:open="drawerOpen" title="Меню" side="left" :size="260" closable>
      <nav-menu v-model="section" @select="close" />
    </wx-drawer>
  </div>
</template>

<style scoped>
.shell {
  /* The shell is the window; the main column is what scrolls inside it. */
  height: 100dvh;
}

.shell__header {
  gap: var(--wx-space-16);
}

/* The bar takes the room between the brand and the account. */
.shell__bar {
  flex: 1 1 auto;
  min-width: 0;
}

.shell__screen {
  min-width: 0;
  min-height: 0;
}
</style>
