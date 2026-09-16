<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@webx-ui/module-admin'
import { WxAction, WxActions, WxDropdown, WxDropdownItem, type IconName } from '@webx-ui/core'
import { usePagesMessages } from './i18n'
import type { PageRow } from './types'

/**
 * What a row offers, in a table cell or on a card.
 *
 * Written once as a list and drawn twice: a row is read by shape, a menu is read by name, and
 * seven unlabelled icons in a dropdown would be worse than the row they replaced. On a card
 * there is no row at all — at that width the card is the screen, and a menu of words is easier
 * to hit than seven targets the size of a fingernail.
 */
const props = withDefaults(
  defineProps<{
    page: PageRow
    inBin?: boolean
    /** Only the menu, for a card. */
    menuOnly?: boolean
  }>(),
  { inBin: false, menuOnly: false },
)

const emit = defineEmits<{
  open: [page: PageRow]
  add: [page: PageRow]
  duplicate: [page: PageRow]
  move: [page: PageRow]
  copy: [page: PageRow]
  remove: [page: PageRow]
  restore: [page: PageRow]
}>()

usePagesMessages()

const t = useTranslate('webx-pages')

interface RowAction {
  key: string
  /** The `WxAction` preset, which picks the icon and the colour of the row. */
  type: 'edit' | 'add' | 'copy' | 'sort' | 'link' | 'goto' | 'remove' | 'restore'
  /** The same icon by name, for the menu, where there is no preset to read it from. */
  icon: IconName
  label: string
  danger?: boolean
  disabled?: boolean
  href?: string
  run?: () => void
}

const actions = computed<RowAction[]>(() => {
  const page = props.page

  if (props.inBin) {
    return [
      {
        key: 'restore',
        type: 'restore',
        icon: 'refresh',
        label: t('page.restore'),
        run: () => emit('restore', page),
      },
    ]
  }

  const list: RowAction[] = [
    {
      key: 'open',
      type: 'edit',
      icon: 'edit',
      label: t('page.open'),
      run: () => emit('open', page),
    },
    {
      key: 'add',
      type: 'add',
      icon: 'plus',
      label: t('page.add-child'),
      run: () => emit('add', page),
    },
  ]

  // The home page has no copy and no other place to be: the two actions that would make one
  // are simply not offered rather than offered and refused.
  if (!page.is_home) {
    list.push({
      key: 'duplicate',
      type: 'copy',
      icon: 'copy',
      label: t('page.duplicate'),
      run: () => emit('duplicate', page),
    })
  }

  if (page.can.move) {
    list.push({
      key: 'move',
      type: 'sort',
      icon: 'drag',
      label: t('page.move'),
      run: () => emit('move', page),
    })
  }

  list.push(
    {
      key: 'copy-address',
      type: 'link',
      icon: 'link',
      label: t('page.copy-address'),
      disabled: !page.url,
      run: () => emit('copy', page),
    },
    {
      key: 'on-site',
      type: 'goto',
      icon: 'external-link',
      label: t('page.open-on-site'),
      disabled: !page.url,
      href: page.url ?? undefined,
    },
  )

  if (page.can.delete) {
    list.push({
      key: 'delete',
      type: 'remove',
      icon: 'trash',
      label: t('page.delete'),
      danger: true,
      run: () => emit('remove', page),
    })
  }

  return list
})
</script>

<template>
  <wx-dropdown v-if="props.menuOnly" align="end" @click.stop>
    <template #trigger>
      <wx-action type="more" size="sm" :title="props.page.title" />
    </template>

    <wx-dropdown-item
      v-for="action in actions"
      :key="action.key"
      :icon="action.icon"
      :tone="action.danger ? 'danger' : 'default'"
      :href="action.href"
      :target="action.href ? '_blank' : undefined"
      :disabled="action.disabled"
      @click="action.run?.()"
    >
      {{ action.label }}
    </wx-dropdown-item>
  </wx-dropdown>

  <wx-actions v-else size="sm" align="end" collapse :aria-label="props.page.title" @click.stop>
    <wx-action
      v-for="action in actions"
      :key="action.key"
      :type="action.type"
      :title="action.label"
      :href="action.href"
      :target="action.href ? '_blank' : undefined"
      :disabled="action.disabled"
      @click="action.run?.()"
    />

    <template #collapsed>
      <wx-dropdown-item
        v-for="action in actions"
        :key="action.key"
        :icon="action.icon"
        :tone="action.danger ? 'danger' : 'default'"
        :href="action.href"
        :target="action.href ? '_blank' : undefined"
        :disabled="action.disabled"
        @click="action.run?.()"
      >
        {{ action.label }}
      </wx-dropdown-item>
    </template>
  </wx-actions>
</template>
