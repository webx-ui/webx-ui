<script setup lang="ts">
import { computed } from 'vue'
import { useAdmin, useI18n, useTranslate } from '@webx-ui/admin'
import { useAuth } from './session'

/**
 * The corner of the header: who this is, which language they read the panel in, and the way
 * out.
 *
 * The language picker lives here rather than in a settings screen because it is a property of
 * the person, not of the site — and because somebody who has landed in a language they cannot
 * read needs it within reach, not three clicks into a section they cannot navigate.
 */
withDefaults(defineProps<{ signOutLabel?: string }>(), { signOutLabel: undefined })

const admin = useAdmin()
const auth = useAuth()
const i18n = useI18n()
const t = useTranslate('webx-auth')

const user = computed(() => admin.state.user)

// One language is not a choice, and a menu that offers it is noise.
const languages = computed(() =>
  i18n.state.panelLocales.length > 1 ? i18n.state.panelLocales : [],
)

const initials = computed(() => {
  const name = user.value?.name ?? ''

  return name
    .split(/\s+/)
    .filter((part) => part !== '')
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
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
    <template #trigger>
      <wx-action :title="user.name">
        <wx-avatar :label="initials" size="sm" />
      </wx-action>
    </template>

    <wx-dropdown-item disabled>
      <wx-text size="sm">{{ user.email }}</wx-text>
    </wx-dropdown-item>

    <template v-if="languages.length > 0">
      <wx-divider :spacing="4" />

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

      <wx-divider :spacing="4" />
    </template>

    <wx-dropdown-item icon="logout" @click="auth.logout()">
      {{ signOutLabel ?? t('menu.sign-out') }}
    </wx-dropdown-item>
  </wx-dropdown>
</template>
