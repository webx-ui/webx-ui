<script setup lang="ts">
import { WxImage } from '@webx-ui/core'
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">A picture, and one that is not there</span>
      <div class="row">
        <wx-image
          src="https://picsum.photos/seed/wx-a/320/200"
          alt="A road"
          :width="200"
          :height="130"
          radius="12px"
          preview
        />
        <wx-image src="/nothing-here.png" alt="Missing" :width="200" :height="130" radius="12px" />
      </div>
      <span class="wx-demo__note">Hover the first one: it opens full size.</span>
    </div>

    <div>
      <span class="wx-demo__label">Filling its box, three ways</span>
      <div class="row">
        <wx-image
          src="https://picsum.photos/seed/wx-b/400/200"
          alt=""
          :width="140"
          :height="140"
          fit="cover"
        />
        <wx-image
          src="https://picsum.photos/seed/wx-b/400/200"
          alt=""
          :width="140"
          :height="140"
          fit="contain"
        />
        <wx-image
          src="https://picsum.photos/seed/wx-b/400/200"
          alt=""
          :width="140"
          :height="140"
          fit="fill"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-12);
}

.wx-demo__note {
  display: block;
  margin-top: var(--wx-space-6);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
}
</style>
