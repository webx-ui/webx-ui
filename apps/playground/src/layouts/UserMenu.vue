<script setup lang="ts">
/** The corner every admin has: who is signed in, and the way out. */
defineProps<{ compact?: boolean }>()
</script>

<template>
  <wx-indicator :value="3">
    <wx-action icon="bell" title="Сповіщення" />
  </wx-indicator>

  <wx-dropdown align="end">
    <template #trigger>
      <wx-action v-if="compact" icon="user" title="Олег М." />
      <wx-button v-else variant="text" size="sm">
        <template #icon><wx-icon name="user" /></template>
        Олег М.
      </wx-button>
    </template>

    <wx-dropdown-item icon="user">Профіль</wx-dropdown-item>
    <wx-dropdown-item icon="settings">Налаштування</wx-dropdown-item>
    <hr />
    <wx-dropdown-item icon="logout" tone="danger">Вийти</wx-dropdown-item>
  </wx-dropdown>
</template>
