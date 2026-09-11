<script setup lang="ts">
import { WxDivider, WxLink } from '@webx-ui/core'
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">A rule between blocks</span>
      <p>Everything above the line.</p>
      <wx-divider />
      <p>Everything below it.</p>
    </div>

    <div>
      <span class="wx-demo__label">With a label</span>
      <wx-divider label="Advanced" />
      <wx-divider label="Left" align="start" variant="dashed" />
      <wx-divider label="Right" align="end" variant="dotted" />
    </div>

    <div>
      <span class="wx-demo__label">Vertical, between things on one line</span>
      <div class="wx-demo__row">
        <wx-link href="#">Edit</wx-link>
        <wx-divider direction="vertical" />
        <wx-link href="#">Duplicate</wx-link>
        <wx-divider direction="vertical" />
        <wx-link href="#" type="danger">Delete</wx-link>
      </div>
    </div>
  </div>
</template>
