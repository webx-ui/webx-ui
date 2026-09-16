<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { WxAction, WxActions, WxDropdown, WxDropdownItem, WxInput } from '@webx-ui/core'
import type { MediaKind } from './types'

/**
 * One row of icons over the files.
 *
 * Actions rather than labelled buttons: the row has to survive a phone and a picker dialog half
 * the width of a window, and a row of words does not. What each icon means is a tooltip away,
 * and what is chosen is said in the icon's own label.
 */
const props = defineProps<{
  canUpload: boolean
  canManage: boolean
  selected: number
  /** The caller asked for one kind of file, so there is nothing to filter by. */
  fixedType?: boolean
  /** Narrow screens get the folders behind a button instead of beside the files. */
  compact: boolean
}>()

const search = defineModel<string>('search', { default: '' })
const type = defineModel<MediaKind | 'all'>('type', { default: 'all' })
const sort = defineModel<string>('sort', { default: '-created_at' })

const emit = defineEmits<{
  upload: []
  move: []
  remove: []
  folders: []
}>()

const t = useTranslate('webx-media')

const types = computed<{ value: MediaKind | 'all'; label: string }[]>(() => [
  { value: 'all', label: t('manager.all-types') },
  ...(['image', 'video', 'audio', 'document', 'other'] as MediaKind[]).map((kind) => ({
    value: kind,
    label: t(`manager.${kind}`),
  })),
])

const sorts = computed(() => [
  { value: '-created_at', label: t('manager.sort-newest') },
  { value: 'created_at', label: t('manager.sort-oldest') },
  { value: 'name', label: t('manager.sort-name') },
  { value: '-size', label: t('manager.sort-size') },
])

const typeLabel = computed(() => types.value.find((one) => one.value === type.value)?.label ?? '')
const sortLabel = computed(() => sorts.value.find((one) => one.value === sort.value)?.label ?? '')
</script>

<template>
  <header class="wx-media-toolbar">
    <wx-action
      v-if="compact"
      icon="folder"
      :title="t('manager.folders')"
      size="sm"
      @click="emit('folders')"
    />

    <wx-input
      v-model="search"
      class="wx-media-toolbar__search"
      :placeholder="t('manager.search')"
      clearable
      size="sm"
    />

    <wx-dropdown v-if="!fixedType">
      <template #trigger>
        <wx-action icon="filter" :title="`${t('manager.all-types')}: ${typeLabel}`" size="sm" />
      </template>

      <wx-dropdown-item
        v-for="option in types"
        :key="String(option.value)"
        :active="option.value === type"
        @click="type = option.value"
      >
        {{ option.label }}
      </wx-dropdown-item>
    </wx-dropdown>

    <wx-dropdown>
      <template #trigger>
        <!-- Not `type="sort"`: in this set that means reordering by hand, and its icon is a drag handle. -->
        <wx-action icon="arrow-down" :title="sortLabel" size="sm" />
      </template>

      <wx-dropdown-item
        v-for="option in sorts"
        :key="option.value"
        :active="option.value === sort"
        @click="sort = option.value"
      >
        {{ option.label }}
      </wx-dropdown-item>
    </wx-dropdown>

    <wx-action
      v-if="canUpload"
      type="upload"
      :title="t('manager.upload')"
      size="sm"
      @click="emit('upload')"
    />

    <!-- Kept in the row rather than added to it: the icons are always here and simply cannot
         be used while nothing is selected, so the grid never moves down a line. -->
    <wx-actions v-if="canManage" size="sm">
      <wx-action
        icon="folder"
        :title="t('manager.move')"
        :disabled="props.selected === 0"
        @click="emit('move')"
      />
      <wx-action
        type="remove"
        :title="t('manager.delete')"
        :disabled="props.selected === 0"
        @click="emit('remove')"
      />
    </wx-actions>
  </header>
</template>

<style>
.wx-media-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-6);
}

.wx-media-toolbar__search {
  flex: 1 1 180px;
  min-width: 120px;
}

.wx-media-toolbar__count {
  font-size: var(--wx-font-size-sm);
  color: var(--wx-text-muted);
  margin-inline-start: auto;
}
</style>
