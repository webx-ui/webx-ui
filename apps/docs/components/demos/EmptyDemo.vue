<script setup lang="ts">
import { ref } from 'vue'
import { WxButton, WxCard, WxEmpty, WxIcon } from '@webx-ui/core'

const term = ref('paid')
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Nothing here yet</span>
      <wx-card bordered shadow="never" padding="none">
        <wx-empty
          icon="cart"
          title="No orders yet"
          description="They will appear here as they come in."
        >
          <template #actions>
            <wx-button type="primary" size="sm">
              <template #icon><wx-icon name="plus" /></template>
              New order
            </wx-button>
          </template>
        </wx-empty>
      </wx-card>
    </div>

    <div>
      <span class="wx-demo__label">Nothing matched</span>
      <wx-card bordered shadow="never" padding="none">
        <wx-empty icon="search" size="sm" :title="`Nothing matches “${term}”`">
          Try a shorter term, or clear the filters.
          <template #actions>
            <wx-button size="sm" variant="outline" @click="term = ''">Clear</wx-button>
          </template>
        </wx-empty>
      </wx-card>
    </div>

    <div>
      <span class="wx-demo__label">A line on its own</span>
      <wx-card bordered shadow="never" padding="none">
        <wx-empty plain size="sm" description="Nothing to show" />
      </wx-card>
    </div>
  </div>
</template>
