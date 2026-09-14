<script setup lang="ts">
import { computed, inject, ref, watch } from 'vue'
import { useAdmin, useI18n, useTranslate } from '@webx-ui/module-admin'
import { avatarResolverKey, useAuth, type AvatarResolver } from './session'

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
  }>(),
  { signOutLabel: undefined, resolveAvatar: undefined },
)

const admin = useAdmin()
const auth = useAuth()
const i18n = useI18n()
const t = useTranslate('webx-auth')

const user = computed(() => admin.state.user)

// One language is not a choice, and a menu that offers it is noise.
const languages = computed(() =>
  i18n.state.panelLocales.length > 1 ? i18n.state.panelLocales : [],
)

const provided = inject(avatarResolverKey, null)
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
      <button type="button" class="wx-user-menu__trigger" :title="user.name">
        <wx-avatar :name="user.name" :src="avatarUrl" size="lg" />
      </button>
    </template>

    <wx-dropdown-item disabled>
      <wx-text size="sm">{{ user.email }}</wx-text>
    </wx-dropdown-item>

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
</style>
