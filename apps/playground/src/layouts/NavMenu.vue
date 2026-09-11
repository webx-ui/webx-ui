<script setup lang="ts">
import type { MenuMode } from '@webx-ui/core'

/**
 * The admin's sections, written once. All three shells show the same tree — the bar
 * across the top, the sidebar, the rail, the drawer — and the only thing that
 * differs is which way it is laid out, so it has no business being written out four
 * times in four templates.
 *
 * Three levels deep on purpose: that is where a menu stops being a list and starts
 * needing flyouts, and it is the part worth looking at in a playground.
 */
defineProps<{ mode?: MenuMode; collapsed?: boolean; label?: string }>()

const emit = defineEmits<{ select: [] }>()

const section = defineModel<string>({ default: 'dashboard' })
</script>

<template>
  <wx-menu
    v-model="section"
    :mode="mode"
    overflow-title="Ще"
    :collapsed="collapsed"
    :label="label ?? 'Розділи'"
    @select="emit('select')"
  >
    <wx-menu-item value="dashboard" icon="home" label="Огляд" />

    <wx-menu-item value="inbox" icon="mail" label="Вхідні">
      <template #trailing><wx-badge type="primary" size="sm" round>7</wx-badge></template>
    </wx-menu-item>

    <wx-menu-item value="orders" icon="cart" label="Замовлення" />

    <wx-submenu value="catalog" icon="grid" title="Каталог">
      <wx-menu-item value="products" icon="list" label="Товари" />
      <wx-menu-item value="media" icon="image" label="Медіа" />
      <wx-submenu value="taxonomy" icon="tag" title="Таксономія">
        <wx-menu-item value="tags" icon="tag" label="Мітки" />
        <wx-menu-item value="categories" icon="folder" label="Категорії" />
      </wx-submenu>
    </wx-submenu>

    <wx-menu-item value="pages" icon="file" label="Сторінки" />
    <wx-menu-item value="users" icon="users" label="Користувачі" />
    <wx-menu-item value="settings" icon="settings" label="Система" />
  </wx-menu>
</template>
