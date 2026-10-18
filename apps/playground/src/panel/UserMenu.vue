<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAdmin, useTheme } from '@webx-ui/module-admin'
import { WxProfileDialog } from '@webx-ui/module-auth'
import {
  createModal,
  WxAvatar,
  WxDivider,
  WxDropdown,
  WxDropdownItem,
  WxIcon,
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
 *
 * And who to be: the three fixture administrators, so the panel can be looked at as somebody who
 * holds less than everything (WEBX_UI_PERMISSIONS.md §11). Switching reloads the page — the
 * manifest, the menu and every `can()` are worked out for the new person, as after a sign-in.
 */
defineProps<{ expanded?: boolean }>()

const admin = useAdmin()
const theme = useTheme()

const locales = computed(() => admin.i18n.state.panelLocales)
const locale = computed(() => admin.i18n.state.locale)
const panel = admin.i18n.scope('webx-admin')

const user = computed(() => admin.state.user)
const people = ref<{ id: number; name: string; email: string }[]>([])

void admin.http
  .get<{ data: { id: number; name: string; email: string }[] }>('/api/cms/inbox/recipients')
  .then((body) => (people.value = body.data))

/* Opened on the next turn, as the real menu does: the menu is a layer that would eat the click. */
const editProfile = createModal<true>(WxProfileDialog)

function openProfile(): void {
  setTimeout(() => void editProfile(), 0)
}

async function signInAs(id: number): Promise<void> {
  await admin.http.put('/api/cms/auth/as', { id })
  location.reload()
}

const preference = computed({
  get: () => theme.state.preference,
  set: (value) => theme.set(value),
})
</script>

<template>
  <wx-dropdown placement="top-start">
    <template #trigger>
      <button class="account" type="button">
        <wx-avatar size="sm" :name="user?.name ?? ''" />
        <template v-if="expanded">
          <span class="account__who">
            <span class="account__name">{{ user?.name }}</span>
            <span class="account__email">{{ user?.email }}</span>
          </span>
          <wx-icon name="chevron-up" size="sm" class="account__chevron" />
        </template>
      </button>
    </template>

    <wx-dropdown-item icon="user" @click="openProfile">
      {{ admin.i18n.scope('webx-auth')('profile.menu') }}
    </wx-dropdown-item>

    <wx-divider spacing="sm" />

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

    <wx-divider spacing="sm" />

    <wx-dropdown-item
      v-for="person in people"
      :key="person.id"
      :icon="person.id === user?.id ? 'check' : 'user'"
      @click="signInAs(person.id)"
    >
      {{ person.name }}
    </wx-dropdown-item>

    <wx-divider spacing="sm" />

    <wx-dropdown-item
      v-if="admin.siteUrl.value"
      icon="external-link"
      :href="admin.siteUrl.value"
      target="_blank"
      rel="noopener"
    >
      {{ panel('nav.open-site') }}
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

.account__who {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  min-width: 0;
  line-height: 1.3;
  text-align: start;
}

.account__name,
.account__email {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.account__name {
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-medium);
}

.account__email {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}

.account__chevron {
  flex: none;
  color: var(--wx-text-muted);
}

.theme {
  padding: var(--wx-space-2) var(--wx-space-8) var(--wx-space-6);
}
</style>
