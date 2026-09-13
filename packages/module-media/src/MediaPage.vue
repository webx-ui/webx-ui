<script setup lang="ts">
import { WxCard } from '@webx-ui/core'
import MediaManager from './MediaManager.vue'

/**
 * The library as a page of the panel.
 *
 * On its own, the manager sits straight on the panel's background, which reads as something
 * that has not finished loading. In a dialog it already has walls; here it needs its own.
 */
</script>

<template>
  <wx-card class="wx-media-page" padding="md">
    <media-manager />
  </wx-card>
</template>

<style>
.wx-media-page {
  height: 100%;
  min-height: 0;
}

/* The card is the page: its body is what scrolls, not the page behind it. */
.wx-media-page > .wx-card__body {
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
}
</style>
