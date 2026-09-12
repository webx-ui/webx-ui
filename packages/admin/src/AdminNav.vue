<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAdmin } from './admin'

/**
 * The menu, built from the manifest rather than written out. What the panel offers is what the
 * installation actually has — adding a module on the server and installing its front end is
 * the whole of "adding a section".
 */
defineProps<{ collapsed?: boolean }>()

const emit = defineEmits<{ select: [] }>()

const admin = useAdmin()
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
  <wx-menu v-model="current" :collapsed="collapsed" label="Sections" @select="emit('select')">
    <wx-menu-item
      v-for="entry in admin.nav.value"
      :key="entry.id"
      :value="entry.id"
      :icon="entry.icon ?? undefined"
      :label="entry.title"
    />
  </wx-menu>
</template>
