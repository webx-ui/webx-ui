<script setup lang="ts">
import { ref } from 'vue'
import { WxAlert, WxButton } from '@webx-ui/core'

const types = ['info', 'success', 'warning', 'danger'] as const

const shown = ref(true)
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Soft — the default</span>
      <div class="wx-demo__stack">
        <wx-alert
          v-for="type in types"
          :key="type"
          :type="type"
          :description="`A ${type} message`"
        />
      </div>
    </div>

    <div>
      <span class="wx-demo__label">With a title and a body</span>
      <wx-alert type="warning" title="The import finished with errors">
        12 of 340 rows were skipped. The report lists them with the reason for each.
      </wx-alert>
    </div>

    <div>
      <span class="wx-demo__label">Outline and solid</span>
      <div class="wx-demo__stack">
        <wx-alert type="success" variant="outline" description="Settings saved" />
        <wx-alert type="danger" variant="solid" description="The deploy failed" />
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Actions and dismissal</span>
      <div class="wx-demo__stack">
        <wx-alert type="info" title="Two editors have this page open" closable>
          <template #actions>
            <wx-button size="sm" variant="outline">Take over</wx-button>
          </template>
        </wx-alert>

        <wx-alert
          v-model:visible="shown"
          type="danger"
          description="Close me — the button below brings me back"
          closable
        />
        <wx-button v-if="!shown" size="sm" variant="outline" @click="shown = true">
          Bring it back
        </wx-button>
      </div>
    </div>
  </div>
</template>
