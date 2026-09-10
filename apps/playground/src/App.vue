<script setup lang="ts">
import { ref } from 'vue'
import { applyTheme, type Theme } from '@webx-ui/tokens'

const theme = ref<Theme>('light')
const title = ref('Landing page')
const saving = ref(false)

function toggleTheme() {
  theme.value = theme.value === 'light' ? 'dark' : 'light'
  applyTheme(theme.value)
}

function save() {
  saving.value = true
  setTimeout(() => (saving.value = false), 1200)
}
</script>

<template>
  <main class="wx-root page">
    <header class="page__head">
      <h1 class="page__title">WebX UI playground</h1>
      <wx-button variant="outline" size="sm" @click="toggleTheme">
        {{ theme === 'light' ? 'Dark' : 'Light' }} theme
      </wx-button>
    </header>

    <wx-card title="Page settings" shadow="always">
      <template #extra>Draft</template>

      <div class="stack">
        <wx-input v-model="title" placeholder="Title" clearable show-count :maxlength="60" />
        <wx-input model-value="" placeholder="Slug">
          <template #prefix>/</template>
        </wx-input>
        <wx-input model-value="broken@" status="error" placeholder="Email" />
      </div>

      <template #footer>
        <div class="actions">
          <wx-button variant="text">Cancel</wx-button>
          <wx-button type="primary" :loading="saving" @click="save">Save</wx-button>
        </div>
      </template>
    </wx-card>

    <wx-card title="Buttons" padding="md">
      <div class="row">
        <wx-button>Default</wx-button>
        <wx-button type="primary">Primary</wx-button>
        <wx-button type="success">Success</wx-button>
        <wx-button type="warning">Warning</wx-button>
        <wx-button type="danger">Danger</wx-button>
      </div>
      <div class="row">
        <wx-button type="primary" variant="outline">Outline</wx-button>
        <wx-button type="primary" variant="text">Text</wx-button>
        <wx-button type="primary" round>Round</wx-button>
        <wx-button type="primary" disabled>Disabled</wx-button>
      </div>
    </wx-card>
  </main>
</template>

<style>
body {
  margin: 0;
}

.page {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-18);
  min-height: 100vh;
  padding: var(--wx-space-32);
  box-sizing: border-box;
}

.page__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-16);
}

.page__title {
  margin: 0;
  font-size: var(--wx-font-size-2xl);
  color: var(--wx-text-strong);
}

.stack {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-12);
  margin-bottom: var(--wx-space-12);
}

.actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--wx-space-8);
}
</style>
