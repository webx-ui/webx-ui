<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate, WxRowMenu, type RowAction } from '@webx-ui/module-admin'
import { usePagesMessages } from './i18n'
import type { PageRow } from './types'

/**
 * What a row of the page tree offers.
 *
 * A list of actions rather than markup, handed to the panel's one row menu: a table cell and
 * a card on a phone both get the same `···` in the same place, and so does every other
 * section (§20). What is written here is only which actions this row has — a page that is the
 * home page has no copy and nowhere else to be, and a page in the bin has one way out.
 *
 * Nothing here is offered and then refused: an action somebody has no right to is left out.
 */
const props = withDefaults(defineProps<{ page: PageRow; inBin?: boolean }>(), { inBin: false })

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

const actions = computed<RowAction[]>(() => {
  const page = props.page

  if (props.inBin) {
    return [
      {
        key: 'restore',
        icon: 'refresh',
        label: t('page.restore'),
        run: () => emit('restore', page),
      },
    ]
  }

  const list: RowAction[] = [
    { key: 'open', icon: 'edit', label: t('page.open'), run: () => emit('open', page) },
    { key: 'add', icon: 'plus', label: t('page.add-child'), run: () => emit('add', page) },
  ]

  // The home page has no copy and no other place to be: the two actions that would make one
  // are simply not offered rather than offered and refused.
  if (!page.is_home) {
    list.push({
      key: 'duplicate',
      icon: 'copy',
      label: t('page.duplicate'),
      run: () => emit('duplicate', page),
    })
  }

  if (page.can.move) {
    list.push({ key: 'move', icon: 'drag', label: t('page.move'), run: () => emit('move', page) })
  }

  list.push(
    {
      key: 'copy-address',
      icon: 'link',
      label: t('page.copy-address'),
      disabled: !page.url,
      run: () => emit('copy', page),
    },
    {
      key: 'on-site',
      icon: 'external-link',
      label: t('page.open-on-site'),
      disabled: !page.url,
      href: page.url ?? undefined,
    },
  )

  if (page.can.delete) {
    list.push({
      key: 'delete',
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
  <wx-row-menu :actions="actions" :label="props.page.title" />
</template>
