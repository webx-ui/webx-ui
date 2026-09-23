<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  confirm,
  createModal,
  toast,
  WxAction,
  WxButton,
  WxEmpty,
  WxIcon,
  WxSkeleton,
  WxText,
} from '@webx-ui/core'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import MenuBranch from './MenuBranch.vue'
import MenuItemDialog from './MenuItemDialog.vue'
import { createMenusApi } from './api'
import { useMenuMessages } from './i18n'
import { countBranch, findItem, movedId } from './tree'
import type { MenuItemInput, MenuItemRow, MenuRow } from './types'

/**
 * The items of one menu: the pane beside the list, and the whole screen on a phone.
 *
 * The way back out is drawn here and not by the pane around it, because the drawer a narrow
 * screen puts this in has no close of its own on purpose (§9): what closes it is `Back` in the
 * head of what is inside, the way every other screen of the panel goes back.
 *
 * Mounted afresh for each menu — the tree, the drag state and the scroll all belong to the menu
 * being looked at, and keeping them across a switch is how a drop lands in the wrong one.
 */
defineOptions({ name: 'WxMenuTreePane' })

const props = withDefaults(
  defineProps<{
    menu: MenuRow
    /** False in the drawer a narrow screen opens this in. */
    inline?: boolean
    back?: () => void
  }>(),
  { inline: true, back: undefined },
)

const emit = defineEmits<{
  /** Something about the menu changed: its item count, and the age of its cache. */
  changed: []
}>()

const admin = useAdmin()
const api = createMenusApi(admin)
useMenuMessages()

const t = useTranslate('webx-menu')
const message = useErrorText()

const items = ref<MenuItemRow[]>([])
const loading = ref(true)

const canManage = computed(() => admin.can('menu.manage'))

const edit = createModal<MenuItemInput, { menu: MenuRow; item: MenuItemRow | null }>(MenuItemDialog)

async function load(): Promise<void> {
  loading.value = true

  try {
    items.value = await api.items(props.menu.key)
  } catch (error) {
    toast.danger(message(error))
  } finally {
    loading.value = false
  }
}

void load()

/** The level under a parent, as it stands right now. */
function levelOf(parentId: number | null): MenuItemRow[] {
  if (parentId === null) return items.value

  return findItem(items.value, parentId)?.children ?? []
}

function setLevel(parentId: number | null, next: MenuItemRow[]): void {
  if (parentId === null) {
    items.value = next

    return
  }

  const parent = findItem(items.value, parentId)

  if (parent !== null) parent.children = next
}

/**
 * A level came back in a new order. One drag reports twice when it crosses levels — the one it
 * left and the one it landed in — and only the second of those is a move to send.
 *
 * The screen is already showing the new order: the list reorders its model before it says
 * anything. So a refusal has to put the tree back rather than leave the screen disagreeing with
 * the database.
 */
async function reorder(parentId: number | null, next: MenuItemRow[]): Promise<void> {
  const before = levelOf(parentId).map((item) => item.id)

  setLevel(parentId, next)

  const after = next.map((item) => item.id)
  const moved = movedId(before, after)

  if (moved === null) return

  try {
    await api.moveItem(props.menu.key, moved, parentId, after.indexOf(moved))
    emit('changed')
  } catch (error) {
    toast.danger(message(error, t('menu.move-failed')))
    await load()
  }
}

async function add(): Promise<void> {
  const input = await edit({ menu: props.menu, item: null })

  if (!input) return

  try {
    await api.addItem(props.menu.key, input)
    toast.success(t('menu.item-saved'))
    await load()
    emit('changed')
  } catch (error) {
    toast.danger(message(error))
  }
}

async function save(item: MenuItemRow): Promise<void> {
  const input = await edit({ menu: props.menu, item })

  if (!input) return

  try {
    await api.saveItem(props.menu.key, item.id, input)
    toast.success(t('menu.item-saved'))
    await load()
    emit('changed')
  } catch (error) {
    toast.danger(message(error))
  }
}

/**
 * A copy of an item, put directly under the one it was copied from.
 *
 * At the end of the level first and then moved, because where a new item goes is the tree's
 * business and there is one way of saying it. A copy that landed at the bottom of a menu of
 * twenty would be a copy somebody has to go and find.
 */
async function duplicate(item: MenuItemRow): Promise<void> {
  try {
    const copy = await api.addItem(props.menu.key, {
      title: item.title,
      link: {
        target: item.target,
        entity_type: item.entity_type,
        entity_id: item.entity_id,
        url: item.url,
        hash: item.hash,
        new_tab: item.new_tab,
        rel: item.rel,
      },
      variant: item.variant,
      is_heading: item.is_heading,
      locales: item.locales,
      visible: item.visible,
      parent_id: item.parent_id,
    })

    const siblings = levelOf(item.parent_id)
    await api.moveItem(
      props.menu.key,
      copy.id,
      item.parent_id,
      siblings.findIndex((one) => one.id === item.id) + 1,
    )

    toast.success(t('menu.duplicated'))
    await load()
    emit('changed')
  } catch (error) {
    toast.danger(message(error))
  }
}

/**
 * An item, and whatever is under it.
 *
 * The number is counted off the tree that is on the screen, which is the same tree the delete
 * walks — the promise and the act have to be the same question, and bounds read a while ago are
 * bounds that have since moved (§10).
 */
async function remove(item: MenuItemRow): Promise<void> {
  const under = countBranch(item)

  const agreed = await confirm({
    title: t('menu.delete-item-title', { item: item.label || t('menu.untitled-item') }),
    message:
      under === 0 ? t('menu.delete-item-text') : t('menu.delete-item-branch', { count: under }),
    confirmText: t('menu.delete'),
    cancelText: t('menu.cancel'),
    tone: 'danger',
  })

  if (!agreed) return

  try {
    await api.removeItem(props.menu.key, item.id)
    toast.success(t('menu.item-deleted'))
    await load()
    emit('changed')
  } catch (error) {
    toast.danger(message(error))
  }
}

defineExpose({ reload: load })
</script>

<template>
  <div class="wx-menu-pane">
    <header class="wx-menu-pane__head">
      <!--
        The drawer this opens in on a phone has no cross: closing it is `Back`, in the head of
        what is inside it, where every other screen of the panel keeps it.
      -->
      <wx-action
        v-if="!props.inline"
        icon="arrow-left"
        size="sm"
        :title="t('menu.back')"
        @click="props.back?.()"
      />

      <div class="wx-menu-pane__name">
        <wx-text weight="semibold" truncate>{{ props.menu.title }}</wx-text>
        <wx-text size="sm" tone="muted" mono truncate>{{ props.menu.key }}</wx-text>
      </div>

      <wx-button v-if="canManage" variant="outline" size="sm" @click="add">
        <template #icon><wx-icon name="plus" /></template>
        {{ t('menu.new-item') }}
      </wx-button>
    </header>

    <div class="wx-menu-pane__body">
      <wx-skeleton v-if="loading" :rows="5" />

      <wx-empty
        v-else-if="items.length === 0"
        :title="t('menu.empty-tree')"
        :description="t('menu.empty-tree-help')"
      />

      <menu-branch
        v-else
        :items="items"
        :disabled="!canManage"
        @reorder="reorder"
        @edit="save"
        @duplicate="duplicate"
        @remove="remove"
      />
    </div>
  </div>
</template>

<style scoped>
.wx-menu-pane {
  display: flex;
  flex-direction: column;
  min-height: 0;
}

.wx-menu-pane__head {
  display: flex;
  align-items: center;
  gap: var(--wx-space-12);
  padding: var(--wx-space-12) var(--wx-space-16);
  border-bottom: 1px solid var(--wx-border-muted);
}

.wx-menu-pane__name {
  display: flex;
  flex-direction: column;
  /* A long name shortens; it does not push the button that makes items off the line. */
  flex: 1 1 auto;
  min-width: 0;
}

.wx-menu-pane__body {
  padding: var(--wx-space-12) var(--wx-space-16);
}
</style>
