<script setup lang="ts">
import { computed, ref } from 'vue'
import { applyTheme, type Theme } from '@webx-ui/tokens'
import { useAdmin } from '@webx-ui/module-admin'
import { WxAvatar, WxDropdown, WxDropdownItem } from '@webx-ui/core'

/**
 * The corner of the panel, as the playground needs it: who is signed in, and the two controls
 * that belong to nobody's module — the theme and the language the interface is drawn in.
 *
 * `@webx-ui/module-auth` has the real one, and it signs out against a server; here there is
 * nothing to sign out of, and a dead menu item is worse than none. The language is here rather
 * than in the shell because the panel a client runs puts it wherever their account lives — and
 * it belongs somewhere, or the ten dictionaries the packages ship can only be looked at on a
 * site.
 */
defineProps<{ expanded?: boolean }>()

const admin = useAdmin()
const theme = ref<Theme>('light')

const locales = computed(() => admin.i18n.state.panelLocales)
const locale = computed(() => admin.i18n.state.locale)

function toggleTheme(): void {
  theme.value = theme.value === 'light' ? 'dark' : 'light'
  applyTheme(theme.value)
}
</script>

<template>
  <wx-dropdown placement="top-start">
    <template #trigger>
      <button class="account" type="button">
        <wx-avatar size="sm" name="Анна Ковальчук" />
        <span v-if="expanded" class="account__name">Анна Ковальчук</span>
      </button>
    </template>

    <wx-dropdown-item :icon="theme === 'light' ? 'moon' : 'sun'" @click="toggleTheme">
      {{ theme === 'light' ? 'Dark theme' : 'Light theme' }}
    </wx-dropdown-item>

    <wx-dropdown-item
      v-for="item in locales"
      :key="item.code"
      :icon="item.code === locale ? 'check' : undefined"
      @click="admin.setLocale(item.code)"
    >
      {{ item.nativeName }}
    </wx-dropdown-item>

    <wx-dropdown-item icon="arrow-left" href="/">Back to the playground</wx-dropdown-item>
  </wx-dropdown>
</template>

<style scoped>
.account {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
  width: 100%;
  padding: var(--wx-space-4);
  border: 0;
  border-radius: var(--wx-radius-sm);
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
}

.account:hover {
  background: var(--wx-bg-subtle);
}

.account__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
