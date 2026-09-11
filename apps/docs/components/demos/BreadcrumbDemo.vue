<script setup lang="ts">
import { WxBreadcrumb, WxBreadcrumbItem } from '@webx-ui/core'
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">The default separator</span>
      <wx-breadcrumb>
        <wx-breadcrumb-item href="#" icon="home">Dashboard</wx-breadcrumb-item>
        <wx-breadcrumb-item href="#">Pages</wx-breadcrumb-item>
        <wx-breadcrumb-item>About us</wx-breadcrumb-item>
      </wx-breadcrumb>
    </div>

    <div>
      <span class="wx-demo__label">An icon separator</span>
      <wx-breadcrumb separator-icon="chevron-right">
        <wx-breadcrumb-item href="#">Dashboard</wx-breadcrumb-item>
        <wx-breadcrumb-item href="#">Shop</wx-breadcrumb-item>
        <wx-breadcrumb-item href="#">Orders</wx-breadcrumb-item>
        <wx-breadcrumb-item>#1041</wx-breadcrumb-item>
      </wx-breadcrumb>
    </div>

    <div>
      <span class="wx-demo__label">Small, with another character</span>
      <wx-breadcrumb size="sm" separator="›">
        <wx-breadcrumb-item href="#">Media</wx-breadcrumb-item>
        <wx-breadcrumb-item href="#">2026</wx-breadcrumb-item>
        <wx-breadcrumb-item>March</wx-breadcrumb-item>
      </wx-breadcrumb>
    </div>
  </div>
</template>
