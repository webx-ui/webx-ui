<script setup lang="ts">
import { computed, inject, ref, watch, type Component } from 'vue'
import {
  useAdmin,
  useI18n,
  useTheme,
  useTranslate,
  type ThemePreference,
} from '@webx-ui/module-admin'
import { createModal, WxThemeSwitch } from '@webx-ui/core'
import ProfileDialog from './ProfileDialog.vue'
import { avatarFieldKey, avatarResolverKey, useAuth, type AvatarResolver } from './session'

/**
 * The corner of the header: who this is, which language they read the panel in, and the way
 * out.
 *
 * The language picker lives here rather than in a settings screen because it is a property of
 * the person, not of the site — and because somebody who has landed in a language they cannot
 * read needs it within reach, not three clicks into a section they cannot navigate.
 */
const props = withDefaults(
  defineProps<{
    signOutLabel?: string
    /** Overrides the resolver `auth()` was given. Without either, initials. */
    resolveAvatar?: AvatarResolver
    /**
     * The field to pick a photograph with, handed down from the plugin. Without one the
     * profile still opens — a panel with no library edits everything else about a person.
     */
    avatarField?: Component
    /**
     * There is room beside the face for a name. The shell says so: the foot of an open
     * sidebar and the foot of the drawer have it, the icon rail and a phone's bar do not.
     */
    expanded?: boolean
  }>(),
  { signOutLabel: undefined, resolveAvatar: undefined, avatarField: undefined, expanded: false },
)

const admin = useAdmin()
const auth = useAuth()
const i18n = useI18n()
const theme = useTheme()
const t = useTranslate('webx-auth')
// The switch is the panel's own control, so its three words are the panel's own too.
const panel = i18n.scope('webx-admin')

const user = computed(() => admin.state.user)

// One language is not a choice, and a menu that offers it is noise.
const languages = computed(() =>
  i18n.state.panelLocales.length > 1 ? i18n.state.panelLocales : [],
)

const provided = inject(avatarResolverKey, null)
const providedField = inject(avatarFieldKey, null)
const avatarUrl = ref<string | undefined>(undefined)

/*
 * The server sends the key the photograph is stored under, not a picture: turning one into
 * the other is the library's job, and the panel says which library. Resolved again whenever
 * the key changes — somebody editing their own photograph is told about it by the row, and
 * the corner should agree with the row.
 */
watch(
  () => (typeof user.value?.avatar === 'string' ? user.value.avatar : null),
  async (key) => {
    const resolve = props.resolveAvatar ?? provided

    if (key === null || key === '' || resolve === null) {
      avatarUrl.value = undefined

      return
    }

    const url = await resolve(key)

    // Only the answer to the key still on screen: a slow answer to a previous one is stale.
    if (user.value?.avatar === key) {
      avatarUrl.value = url ?? undefined
    }
  },
  { immediate: true },
)

/*
 * Opened from a menu, which is itself a dismissable layer: Reka builds the dialog's own layer
 * in the middle of handling this very click, then sees the click end outside it and treats
 * that as a click away. So it opens on the next turn of the loop instead.
 */
const editProfile = createModal<true, { avatarField?: Component }>(ProfileDialog)

function openProfile(): void {
  setTimeout(
    () => void editProfile({ avatarField: props.avatarField ?? providedField ?? undefined }),
    0,
  )
}

/*
 * The screen changes first and the account catches up: repainting is local and instant, and
 * a control that waits for a round trip before it moves feels broken. If the write fails the
 * panel is still the colour that was asked for, and this browser remembers it — only the
 * other machines have not heard yet, and the next change tells them.
 */
const preference = computed<ThemePreference>({
  get: () => theme.state.preference,
  set: (value) => {
    theme.set(value)
    void auth.setTheme(value)
  },
})

async function choose(code: string): Promise<void> {
  if (code === i18n.state.locale) {
    return
  }

  await auth.setLocale(code)
}
</script>

<template>
  <wx-dropdown v-if="user !== null">
    <!--
      The avatar is the button. Wrapped in an action it was a small picture inside a box the
      same size as every icon beside it, which reads as one more tool rather than as who is
      signed in.
    -->
    <template #trigger>
      <button
        type="button"
        class="wx-user-menu__trigger"
        :class="{ 'wx-user-menu__trigger--named': expanded }"
        :title="user.name"
      >
        <wx-avatar :name="user.name" :src="avatarUrl" size="lg" />

        <!--
          Initials are a way of telling two people apart, not of saying who somebody is. Where
          the corner is wide enough, it says it.
        -->
        <span v-if="expanded" class="wx-user-menu__name">{{ user.name }}</span>
      </button>
    </template>

    <wx-dropdown-item disabled>
      <wx-text size="sm">{{ user.email }}</wx-text>
    </wx-dropdown-item>

    <wx-divider spacing="sm" />

    <wx-dropdown-item icon="user" @click="openProfile">
      {{ t('profile.menu') }}
    </wx-dropdown-item>

    <wx-divider spacing="sm" />

    <wx-dropdown-item disabled>
      <wx-text size="sm" tone="muted">{{ panel('theme.label') }}</wx-text>
    </wx-dropdown-item>

    <!--
      The click is stopped here: every click inside the panel closes the menu, and a switch
      that shuts the thing it lives in can only ever be thrown once at a time.
    -->
    <div class="wx-user-menu__theme" @click.stop>
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

    <template v-if="languages.length > 0">
      <wx-divider spacing="sm" />

      <wx-dropdown-item disabled>
        <wx-text size="sm" tone="muted">{{ t('menu.language') }}</wx-text>
      </wx-dropdown-item>

      <wx-dropdown-item
        v-for="language in languages"
        :key="language.code"
        :icon="language.code === i18n.state.locale ? 'check' : undefined"
        @click="choose(language.code)"
      >
        {{ language.nativeName }}
      </wx-dropdown-item>

      <wx-divider spacing="sm" />
    </template>

    <wx-dropdown-item icon="logout" @click="auth.logout()">
      {{ signOutLabel ?? t('menu.sign-out') }}
    </wx-dropdown-item>
  </wx-dropdown>
</template>

<style scoped>
/* The row the switch sits on, lined up with the text of the items above it. */
.wx-user-menu__theme {
  padding: var(--wx-space-2) var(--wx-space-8) var(--wx-space-6);
}

/*
 * The avatar is the whole button: no border, no background, nothing of the browser's own —
 * a bordered box around a round picture reads as one more tool in the row of icons, which is
 * what wrapping it in an action looked like in the first place.
 */
.wx-user-menu__trigger {
  display: inline-flex;
  padding: 0;
  border: none;
  border-radius: var(--wx-radius-full);
  background: transparent;
  cursor: pointer;
  transition: opacity var(--wx-duration-fast) var(--wx-easing-standard);
}

.wx-user-menu__trigger:hover {
  opacity: 0.85;
}

.wx-user-menu__trigger:focus-visible {
  outline: 2px solid var(--wx-border-focus);
  outline-offset: 2px;
}

/*
 * With a name beside it the button is a row rather than a circle, so it takes the width it is
 * given and rounds like the menu items above it instead of like the picture inside it.
 */
.wx-user-menu__trigger--named {
  align-items: center;
  gap: var(--wx-space-8);
  width: 100%;
  min-width: 0;
  padding: var(--wx-space-4);
  border-radius: var(--wx-radius-control);
  text-align: start;
}

.wx-user-menu__trigger--named:hover {
  opacity: 1;
  background: var(--wx-bg-subtle);
}

.wx-user-menu__name {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  color: var(--wx-text-default);
  font-family: var(--wx-font-family-sans);
  font-size: var(--wx-font-size-sm);
  font-weight: var(--wx-font-weight-medium);
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
