<script setup lang="ts">
import { computed } from 'vue'
import { useAdmin } from '@webx-ui/admin'
import { useAuth } from './session'

/**
 * The corner of the header: who this is, and the way out.
 */
withDefaults(defineProps<{ signOutLabel?: string }>(), { signOutLabel: 'Sign out' })

const admin = useAdmin()
const auth = useAuth()

const user = computed(() => admin.state.user)

const initials = computed(() => {
  const name = user.value?.name ?? ''

  return name
    .split(/\s+/)
    .filter((part) => part !== '')
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
})
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

    <wx-dropdown-item icon="logout" @click="auth.logout()">{{ signOutLabel }}</wx-dropdown-item>
  </wx-dropdown>
</template>
