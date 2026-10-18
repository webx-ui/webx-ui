<script setup lang="ts">
import { computed, ref, useTemplateRef } from 'vue'
import {
  rowMenuWidth,
  useAdmin,
  useTranslate,
  WxRowMenu,
  type RowAction,
} from '@webx-ui/module-admin'
import {
  WxBadge,
  WxEntityCard,
  WxTable,
  WxText,
  useElementWidth,
  type TableColumn,
} from '@webx-ui/core'
import { createAdminsApi } from './admins'
import { useAuthMessages } from './i18n'
import type { Role } from './types'

/**
 * The roles: what each is called, how much it opens and how many people hold it.
 *
 * Not paged and not searched. A site has a handful of roles, and the whole list is the thing a
 * person compares when deciding which one to give somebody.
 */
const props = withDefaults(
  defineProps<{
    /** Adds the menu that deletes a role — for whoever may edit roles. */
    removable?: boolean
  }>(),
  { removable: false },
)

const emit = defineEmits<{
  open: [role: Role]
  remove: [role: Role]
}>()

const admin = useAdmin()
const api = createAdminsApi(admin)
useAuthMessages()

const t = useTranslate('webx-auth')

/** Where a row stops being a row. The table and the columns read the same number. */
const CARDS = 480

const root = useTemplateRef<HTMLElement>('root')
const width = useElementWidth(root)
const asCards = computed(() => width.value > 0 && width.value < CARDS)

const roles = ref<Role[]>([])
const loading = ref(false)

const columns = computed<TableColumn<Role>[]>(() => {
  if (asCards.value) return [{ key: 'card', label: '' }]

  return [
    { key: 'name', label: t('roles.name') },
    { key: 'slug', label: t('roles.slug'), hideBelow: 620 },
    { key: 'permissions', label: t('roles.permissions'), align: 'right', hideBelow: 520 },
    { key: 'users', label: t('roles.users'), align: 'right' },
    {
      key: 'actions',
      label: '',
      width: rowMenuWidth,
      align: 'right',
      hidden: !props.removable,
      hideOnCards: true,
    },
  ]
})

async function load(): Promise<void> {
  loading.value = true

  try {
    roles.value = await api.roles()
  } finally {
    loading.value = false
  }
}

/* A role the reader holds has no menu: deleting it is rule 5's to refuse, so it is not offered. */
function actionsFor(row: Role): RowAction[] {
  if (row.held) return []

  return [
    {
      key: 'delete',
      icon: 'trash',
      label: t('roles.delete'),
      danger: true,
      run: () => emit('remove', row),
    },
  ]
}

void load()

defineExpose({ reload: load })
</script>

<template>
  <div ref="root" class="wx-role-list">
    <wx-table
      :data="roles"
      :columns="columns"
      row-key="id"
      clickable
      hover
      flush
      :loading="loading"
      :empty-text="t('roles.empty')"
      :cards-below="CARDS"
      @row-click="(row: Role) => emit('open', row)"
    >
      <template #cell-name="{ row }">
        <span class="wx-role-list__name">
          <span>{{ row.name }}</span>
          <wx-badge v-if="row.held" type="info" size="sm">{{ t('roles.yours') }}</wx-badge>
        </span>
      </template>

      <template #cell-slug="{ row }">
        <wx-text size="sm" tone="muted" class="wx-role-list__slug">{{ row.slug }}</wx-text>
      </template>

      <template #cell-permissions="{ row }">{{ row.permissions.length }}</template>

      <template #cell-users="{ row }">{{ row.users }}</template>

      <template #cell-actions="{ row }">
        <wx-row-menu v-if="!row.held" :actions="actionsFor(row)" :label="row.name" />
      </template>

      <template #card-actions="{ row }">
        <wx-row-menu v-if="removable && !row.held" :actions="actionsFor(row)" :label="row.name" />
      </template>

      <template #cell-card="{ row }">
        <wx-entity-card variant="plain" :title="row.name" :subtitle="row.slug">
          <template #meta>
            <wx-badge size="sm">{{
              t('roles.permission-count', { count: row.permissions.length })
            }}</wx-badge>
            <wx-badge size="sm">{{ t('roles.users-count', { count: row.users }) }}</wx-badge>
          </template>
        </wx-entity-card>
      </template>
    </wx-table>
  </div>
</template>

<style>
.wx-role-list {
  min-width: 0;
}

.wx-role-list__name {
  display: inline-flex;
  align-items: center;
  gap: var(--wx-space-8);
  flex-wrap: wrap;
}

/* A code is read letter by letter, not as words. */
.wx-role-list__slug {
  font-family: var(--wx-font-family-mono);
}
</style>
