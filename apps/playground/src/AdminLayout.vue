<script setup lang="ts">
import { ref } from 'vue'
import { useResponsiveShell } from '@webx-ui/core'

/**
 * The shell every screen of an admin panel sits in — header, navigation, and a hole
 * where the router puts the screen. In a real app the `<slot />` below is
 * `<router-view />`; here the playground passes the screen directly.
 */
const shellEl = ref<HTMLElement | null>(null)

const { layout, collapsed, showAside, drawerOpen, toggle, close } = useResponsiveShell(shellEl, {
  persist: 'playground-shell',
})

const section = ref('inbox')
</script>

<template>
  <div ref="shellEl" class="shell">
    <wx-container full-height>
      <wx-header>
        <wx-action
          :icon="layout === 'drawer' ? 'menu' : 'sidebar'"
          :title="layout === 'drawer' ? 'Меню' : 'Згорнути меню'"
          @click="toggle"
        />
        <strong class="shell__brand">Demo admin</strong>

        <template #end>
          <wx-indicator :value="3">
            <wx-action icon="bell" title="Сповіщення" />
          </wx-indicator>

          <wx-dropdown align="end">
            <template #trigger>
              <wx-button variant="text" size="sm">
                <template #icon><wx-icon name="user" /></template>
                Олег М.
              </wx-button>
            </template>
            <wx-dropdown-item icon="user">Профіль</wx-dropdown-item>
            <wx-dropdown-item icon="settings">Налаштування</wx-dropdown-item>
            <hr />
            <wx-dropdown-item icon="logout" tone="danger">Вийти</wx-dropdown-item>
          </wx-dropdown>
        </template>
      </wx-header>

      <wx-container direction="horizontal">
        <wx-aside v-if="showAside" :collapsed="collapsed" :width="220" scroll>
          <wx-menu v-model="section" label="Розділи" :collapsed="collapsed">
            <wx-menu-item value="dashboard" icon="home" label="Головна" />
            <wx-menu-item value="inbox" icon="mail" label="Вхідні" />
            <wx-menu-item value="orders" icon="cart" label="Замовлення" />
            <wx-menu-item value="catalog" icon="grid" label="Каталог" />
            <wx-menu-item value="content" icon="file" label="Контент" />
            <wx-menu-item value="settings" icon="settings" label="Система" />
          </wx-menu>
        </wx-aside>

        <!-- Where `<router-view />` goes: no padding, so a screen can fill it. -->
        <wx-main padding="none" class="shell__screen">
          <slot />
        </wx-main>
      </wx-container>
    </wx-container>

    <wx-drawer v-model:open="drawerOpen" title="Меню" side="left" :size="260" closable>
      <wx-menu v-model="section" label="Розділи" @select="close">
        <wx-menu-item value="dashboard" icon="home" label="Головна" />
        <wx-menu-item value="inbox" icon="mail" label="Вхідні" />
        <wx-menu-item value="orders" icon="cart" label="Замовлення" />
        <wx-menu-item value="catalog" icon="grid" label="Каталог" />
        <wx-menu-item value="content" icon="file" label="Контент" />
        <wx-menu-item value="settings" icon="settings" label="Система" />
      </wx-menu>
    </wx-drawer>
  </div>
</template>

<style scoped>
.shell {
  height: 100vh;
  overflow: hidden;
}

.shell__brand {
  font-size: var(--wx-font-size-md);
  font-weight: var(--wx-font-weight-semibold);
}

.shell__screen {
  min-width: 0;
  min-height: 0;
}
</style>
