<script setup lang="ts">
import { ref } from 'vue'
import { WxAction, WxEntityCard } from '@webx-ui/core'

const labels = ref([
  { id: 1, title: 'New' },
  { id: 2, title: 'Bestseller' },
  { id: 3, title: 'On sale' },
])

function remove(id: number) {
  labels.value = labels.value.filter((label) => label.id !== id)
}

function reset() {
  labels.value = [
    { id: 1, title: 'New' },
    { id: 2, title: 'Bestseller' },
    { id: 3, title: 'On sale' },
  ]
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">A row asks by its button before it goes</span>
      <div class="wx-demo__stack">
        <wx-entity-card
          v-for="label in labels"
          :key="label.id"
          :title="label.title"
          bordered
          variant="plain"
        >
          <template #actions>
            <wx-action
              type="remove"
              size="sm"
              title="Take the label off"
              confirm="Delete?"
              @click="remove(label.id)"
            />
          </template>
        </wx-entity-card>
        <p v-if="labels.length === 0" class="wx-demo__empty">
          Nothing left. <a href="#" @click.prevent="reset">Bring them back</a>
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wx-demo__stack {
  display: grid;
  gap: var(--wx-space-8);
  max-width: 420px;
}

.wx-demo__empty {
  margin: 0;
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}
</style>
