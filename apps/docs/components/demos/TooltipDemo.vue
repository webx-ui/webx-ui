<script setup lang="ts">
import { WxAction, WxActions, WxButton, WxTooltip } from '@webx-ui/core'
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">A row of icon buttons, each with a name</span>
      <wx-actions>
        <wx-tooltip content="Open"><wx-action type="details" /></wx-tooltip>
        <wx-tooltip content="Edit"><wx-action type="edit" /></wx-tooltip>
        <wx-tooltip content="Send a copy by email"><wx-action type="send" /></wx-tooltip>
        <wx-tooltip content="Delete"><wx-action type="remove" /></wx-tooltip>
      </wx-actions>
      <span class="wx-demo__note">
        The first tip waits; the ones after it come at once, because by then you have said what you
        are doing.
      </span>
    </div>

    <div>
      <span class="wx-demo__label">Sides</span>
      <div class="wx-demo__row">
        <wx-tooltip content="Above" side="top"><wx-button size="sm">Top</wx-button></wx-tooltip>
        <wx-tooltip content="To the right" side="right"
          ><wx-button size="sm">Right</wx-button></wx-tooltip
        >
        <wx-tooltip content="Below" side="bottom"
          ><wx-button size="sm">Bottom</wx-button></wx-tooltip
        >
        <wx-tooltip content="To the left" side="left"
          ><wx-button size="sm">Left</wx-button></wx-tooltip
        >
      </div>
    </div>

    <div>
      <span class="wx-demo__label">More than a word</span>
      <wx-tooltip>
        <wx-button size="sm" variant="outline">Why is this disabled?</wx-button>
        <template #content>
          Only an administrator can change a published price. Ask one to unlock the record first.
        </template>
      </wx-tooltip>
    </div>
  </div>
</template>

<style scoped>
.wx-demo__row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-12);
}

.wx-demo__note {
  display: block;
  margin-top: var(--wx-space-8);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}
</style>
