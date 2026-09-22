<script setup lang="ts">
import { computed } from 'vue'
import { useAdmin, useTheme } from '@webx-ui/module-admin'
import {
  WxAvatar,
  WxDivider,
  WxDropdown,
  WxDropdownItem,
  WxText,
  WxThemeSwitch,
} from '@webx-ui/core'

/**
 * The corner of the panel, as the playground needs it: who is signed in, and the two controls
 * that belong to nobody's module — the theme and the language the interface is drawn in.
 *
 * `@webx-ui/module-auth` has the real one, and it signs out against a server and writes the
 * theme down against the account; here there is nothing to sign out of and nobody to write to,
 * so the choice lives in this browser alone. The language is here rather than in the shell
 * because the panel a client runs puts it wherever their account lives — and it belongs
 * somewhere, or the ten dictionaries the packages ship can only be looked at on a site.
 */
defineProps<{ expanded?: boolean }>()

const admin = useAdmin()
const theme = useTheme()

const locales = computed(() => admin.i18n.state.panelLocales)
const locale = computed(() => admin.i18n.state.locale)
const panel = admin.i18n.scope('webx-admin')

const preference = computed({
  get: () => theme.state.preference,
  set: (value) => theme.set(value),
})
</script>

<template>
  <wx-dropdown placement="top-start">
    <template #trigger>
      <button class="account" type="button">
        <wx-avatar size="sm" name="Анна Ковальчук" />
        <span v-if="expanded" class="account__name">Анна Ковальчук</span>
      </button>
    </template>

    <wx-dropdown-item disabled>
      <wx-text size="sm" tone="muted">{{ panel('theme.label') }}</wx-text>
    </wx-dropdown-item>

    <!-- Stopped, or the click that throws the switch also shuts the menu it lives in. -->
    <div class="theme" @click.stop>
      <wx-theme-switch
        v-model="preference"
        size="sm"
        block
        :aria-label="panel('theme.label')"
        :light-label="panel('theme.light')"
        :dark-label="panel('theme.dark')"
        :system-label="panel('theme.system')"
      />
    </div>

    <wx-divider spacing="sm" />

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

.theme {
  padding: var(--wx-space-2) var(--wx-space-8) var(--wx-space-6);
}
</style>
