<script setup lang="ts">
import { ref } from 'vue'
import { useResponsiveShell } from '@webx-ui/core'
import type { MainProps } from '@webx-ui/core'
import BrandMark from './BrandMark.vue'
import NavMenu from './NavMenu.vue'
import UserMenu from './UserMenu.vue'

/**
 * The classic admin shell: navigation down the left, a header across the top, and a
 * hole where the router puts the screen. In a real app the `<slot />` below is
 * `<router-view />`; here the playground passes the screen directly.
 *
 * The sidebar has three shapes, and the width of the shell picks between them: full
 * on a desktop, an icon rail on a tablet, and gone behind a burger on a phone.
 */
withDefaults(
  defineProps<{
    /** Handed to `WxMain`: `none` for a screen that lays out its own columns. */
    padding?: MainProps['padding']
    /** Whether the screen scrolls inside the column or fills it and scrolls itself. */
    scroll?: boolean
  }>(),
  { padding: 'md', scroll: true },
)

const shellEl = ref<HTMLElement | null>(null)

const { layout, collapsed, showAside, drawerOpen, toggle, close } = useResponsiveShell(shellEl, {
  persist: 'playground-shell',
})

const section = ref('dashboard')
</script>

<template>
  <div ref="shellEl" class="shell">
    <wx-container viewport>
      <wx-header>
        <wx-action
          :icon="layout === 'drawer' ? 'menu' : 'sidebar'"
          :title="layout === 'drawer' ? 'Меню' : 'Згорнути меню'"
          @click="toggle"
        />
        <brand-mark />

        <template #end>
          <user-menu />
        </template>
      </wx-header>

      <wx-container direction="horizontal">
        <wx-aside v-if="showAside" :collapsed="collapsed" :width="220" scroll>
          <nav-menu v-model="section" :collapsed="collapsed" />
        </wx-aside>

        <!-- Where `<router-view />` goes. -->
        <wx-main :padding="padding" :scroll="scroll" class="shell__screen">
          <slot />
        </wx-main>
      </wx-container>
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

.shell__screen {
  min-width: 0;
  min-height: 0;
}
</style>
