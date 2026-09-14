<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAdmin } from './admin'
import { useTranslate } from './i18n'

/**
 * The menu, built from the manifest rather than written out. What the panel offers is what the
 * installation actually has — adding a module on the server and installing its front end is
 * the whole of "adding a section".
 *
 * Sections come first, then the groups the server declared — "System" for what keeps the
 * panel running — each as a branch that opens on its own when a section under it is current.
 */
defineProps<{ collapsed?: boolean }>()

const emit = defineEmits<{ select: [] }>()

const admin = useAdmin()
const t = useTranslate('webx-admin')
const router = useRouter()
const route = useRoute()

const current = computed<string>({
  get: () => {
    const match = admin.nav.value.find((entry) => route.path.startsWith(entry.path))

    return match?.id ?? ''
  },
  set: (id) => {
    const entry = admin.nav.value.find((candidate) => candidate.id === id)

    if (entry !== undefined) {
      void router.push(entry.path)
    }
  },
})
</script>

<template>
  <wx-menu
    v-model="current"
    :collapsed="collapsed"
    :label="t('nav.sections')"
    @select="emit('select')"
  >
    <wx-menu-item
      v-for="entry in admin.groups.value.top"
      :key="entry.id"
      :value="entry.id"
      :icon="entry.icon ?? undefined"
      :label="entry.title"
    />

    <wx-submenu
      v-for="group in admin.groups.value.groups"
      :key="group.id"
      :value="`group:${group.id}`"
      :title="group.title"
      icon="gear"
    >
      <wx-menu-item
        v-for="entry in group.entries"
        :key="entry.id"
        :value="entry.id"
        :icon="entry.icon ?? undefined"
        :label="entry.title"
      />
    </wx-submenu>
  </wx-menu>
</template>
