<script setup lang="ts">
import { computed, ref } from 'vue'
import { WxCheckbox, WxCheckboxGroup } from '@webx-ui/core'

const agreed = ref(true)
const permissions = ref<string[]>(['read'])

const options = [
  { label: 'Read', value: 'read' },
  { label: 'Write', value: 'write' },
  { label: 'Delete', value: 'delete' },
  { label: 'Publish', value: 'publish', disabled: true },
]

const all = options.filter((o) => !o.disabled).map((o) => o.value)
const allChecked = computed(() => all.every((v) => permissions.value.includes(v)))
const someChecked = computed(() => permissions.value.length > 0 && !allChecked.value)

function toggleAll(checked: boolean) {
  permissions.value = checked ? [...all] : []
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Single</span>
      <wx-checkbox v-model="agreed" label="I have read the guidelines" />
    </div>

    <div>
      <span class="wx-demo__label">Sizes</span>
      <div class="wx-demo__row">
        <wx-checkbox :model-value="true" size="sm" label="Small" />
        <wx-checkbox :model-value="true" size="md" label="Medium" />
        <wx-checkbox :model-value="true" size="lg" label="Large" />
        <wx-checkbox :model-value="true" disabled label="Disabled" />
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Group with a select-all box</span>
      <div class="wx-demo__stack">
        <wx-checkbox
          :model-value="allChecked"
          :indeterminate="someChecked"
          label="All permissions"
          @change="toggleAll"
        />
        <wx-checkbox-group v-model="permissions" :options="options" />
      </div>
      <p style="margin: 8px 0 0; color: var(--wx-text-muted); font-size: 13px">
        Value: {{ permissions.join(', ') || '—' }}
      </p>
    </div>
  </div>
</template>
