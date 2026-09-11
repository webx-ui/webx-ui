<script setup lang="ts">
import { ref } from 'vue'
import { WxButton, WxSegmented, WxToaster, useToast } from '@webx-ui/core'
import type { ToasterPlacement } from '@webx-ui/core'

const toast = useToast()
const placement = ref<ToasterPlacement>('bottom-end')

function undo() {
  toast('Order WX-4100 deleted', {
    action: { label: 'Undo', onClick: () => toast.success('Brought back') },
  })
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div class="wx-demo__row">
      <wx-button size="sm" @click="toast('Saved')">Plain</wx-button>
      <wx-button size="sm" @click="toast.success('Order placed')">Success</wx-button>
      <wx-button size="sm" @click="toast.warning('Stock is low')">Warning</wx-button>
      <wx-button
        size="sm"
        @click="toast.danger({ title: 'Could not save', description: 'The server said no.' })"
      >
        Error
      </wx-button>
      <wx-button size="sm" variant="outline" @click="undo">With an action</wx-button>
      <wx-button size="sm" variant="text" @click="toast.clear()">Clear</wx-button>
    </div>

    <div>
      <span class="wx-demo__label">Where they land</span>
      <wx-segmented
        v-model="placement"
        size="sm"
        aria-label="Placement"
        :options="[
          { label: 'Bottom right', value: 'bottom-end' },
          { label: 'Bottom left', value: 'bottom-start' },
          { label: 'Top centre', value: 'top-center' },
          { label: 'Top right', value: 'top-end' },
        ]"
      />
    </div>

    <wx-toaster :placement="placement" />
  </div>
</template>

<style scoped>
.wx-demo__row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8);
}
</style>
