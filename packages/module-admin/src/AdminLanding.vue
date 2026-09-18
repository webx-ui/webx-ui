<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { WxResult } from '@webx-ui/core'
import { useAdmin } from './admin'
import { useTranslate } from './i18n'

/**
 * What the panel opens on.
 *
 * Until now nothing answered at `/`: the routes are the modules', and no module claimed the
 * root — so signing in landed on a blank page and the first thing anybody did was click a
 * section. A module says which one that should be with `landing: true`, and here is where it
 * is honoured.
 *
 * It waits for the manifest rather than redirecting on the spot, because what a panel has is
 * something only the server knows: a landing module whose Composer half is not installed is
 * not in the navigation, and sending somebody to a section that is not there would be worse
 * than sending them nowhere. The fallback is the first entry of the menu, which is the section
 * this panel would call its front page anyway.
 */
const props = withDefaults(defineProps<{ landing?: string | null }>(), { landing: null })

const admin = useAdmin()
const router = useRouter()
const t = useTranslate('webx-admin')

const destination = computed<string | null>(() => {
  const entries = admin.nav.value

  if (entries.length === 0) {
    return null
  }

  const claimed = props.landing === null ? undefined : entries.find((e) => e.path === props.landing)

  return (claimed ?? entries[0])?.path ?? null
})

watch(
  destination,
  (path) => {
    if (path !== null) void router.replace(path)
  },
  { immediate: true },
)
</script>

<template>
  <!--
    Only for a panel that has nothing to show. While the manifest is on its way the shell is
    already drawing its own spinner, and a second one inside it would be two.
  -->
  <wx-result
    v-if="destination === null && admin.state.status === 'ready'"
    status="info"
    :title="t('shell.empty-title')"
    :description="t('shell.empty-description')"
  />
</template>
