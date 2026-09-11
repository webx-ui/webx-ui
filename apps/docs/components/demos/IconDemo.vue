<script setup lang="ts">
import { computed, ref } from 'vue'
import { WxIcon, WxInput, iconNames } from '@webx-ui/core'

const query = ref('')
const all = iconNames()

const found = computed(() => {
  const term = query.value.trim().toLowerCase()
  return term ? all.filter((name) => name.includes(term)) : all
})

const copied = ref('')

async function copy(name: string) {
  copied.value = name
  try {
    await navigator.clipboard?.writeText(`<wx-icon name="${name}" />`)
  } catch {
    /* Clipboard access is not granted in every browser — the name is on screen anyway. */
  }
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div class="wx-demo__row">
      <wx-icon name="check" />
      <wx-icon name="check" size="md" />
      <wx-icon name="check" size="lg" />
      <wx-icon name="check" :size="32" />
      <wx-icon name="loader" :size="24" spin />
      <span style="color: var(--wx-text-muted); font-size: 14px">
        Icons take the colour and, by default, the size of the text around them.
      </span>
    </div>

    <wx-input v-model="query" placeholder="Search the set" clearable size="sm">
      <template #prefix><wx-icon name="search" /></template>
    </wx-input>

    <div class="icon-grid">
      <button
        v-for="name in found"
        :key="name"
        class="icon-cell"
        type="button"
        :title="`Copy <wx-icon name=&quot;${name}&quot; />`"
        @click="copy(name)"
      >
        <wx-icon :name="name" :size="22" />
        <span class="icon-cell__name">{{ name }}</span>
      </button>
    </div>

    <p v-if="copied" style="margin: 0; color: var(--wx-text-muted); font-size: 14px">
      Copied <code>&lt;wx-icon name="{{ copied }}" /&gt;</code>
    </p>
  </div>
</template>

<style scoped>
.icon-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(124px, 1fr));
  gap: 8px;
  width: 100%;
}

.icon-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 12px 6px;
  background: transparent;
  border: 1px solid var(--wx-border-muted);
  border-radius: var(--wx-radius-sm);
  color: var(--wx-text-default);
  font-family: inherit;
  cursor: pointer;
}

.icon-cell:hover {
  background: var(--wx-bg-fill);
  border-color: var(--wx-border-default);
}

.icon-cell__name {
  color: var(--wx-text-muted);
  font-size: 11px;
  word-break: break-all;
}
</style>
