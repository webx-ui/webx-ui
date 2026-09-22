<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  useLocales,
  useModal,
  WxButton,
  WxCheckbox,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSelect,
  WxSpace,
  WxSwitch,
  type SelectOption,
} from '@webx-ui/core'
import { emptyLink, useTranslate, WxLinkPicker, type LinkValue } from '@webx-ui/module-admin'
import { useMenuMessages } from './i18n'
import type { LocalizedText, MenuItemInput, MenuItemRow, MenuRow } from './types'

/**
 * One menu item, in a dialog raised from code (§9).
 *
 * A dialog and not a tab or a screen of its own, because an item is small and is always edited
 * while looking at the tree it sits in: what somebody is deciding is where this one goes
 * relative to the others, and a screen that replaced the tree would take that away.
 *
 * Where it points is `WxLinkPicker` and nothing of this package's own — a menu item and a link
 * field of a block are the same choice, and the day they stop looking alike is the day one of
 * them grows a second way of saying `nofollow`.
 */
defineOptions({ name: 'WxMenuItemDialog' })

const props = withDefaults(
  defineProps<{
    menu: MenuRow
    /** The item being edited, or null for a new one. */
    item?: MenuItemRow | null
  }>(),
  { item: null },
)

const { open, resolve, dismiss } = useModal<MenuItemInput>()

const locales = useLocales()
useMenuMessages()

const t = useTranslate('webx-menu')

const link = ref<LinkValue>(props.item === null ? emptyLink() : toLink(props.item))
const title = ref<LocalizedText>({ ...(props.item?.title ?? {}) })
const variant = ref(props.item?.variant ?? props.menu.variants[0] ?? 'link')
const isHeading = ref(props.item?.is_heading ?? false)
const visible = ref(props.item?.visible ?? true)
const chosenLocales = ref<string[]>([...(props.item?.locales ?? [])])

/**
 * What the item would be called if nobody wrote a label: the name of the thing it points at.
 *
 * Shown as the placeholder rather than filled in, because filling it in would make it a label —
 * and then renaming the page would stop renaming the item, which is the whole property the
 * empty label has (§4).
 */
const fallback = computed(() => props.item?.resolved?.title ?? '')

/** Only where the site has more than one look to choose between. */
const variantOptions = computed<SelectOption[]>(() =>
  props.menu.variants.map((name) => ({ value: name, label: name })),
)

const localeOptions = computed<SelectOption[]>(() =>
  locales.list.value.map((locale) => ({ value: locale.code, label: locale.label ?? locale.code })),
)

function toLink(item: MenuItemRow): LinkValue {
  return {
    target: item.target,
    entity_type: item.entity_type,
    entity_id: item.entity_id,
    url: item.url,
    hash: item.hash,
    new_tab: item.new_tab,
    rel: item.rel,
  }
}

function submit(): void {
  resolve({
    title: title.value,
    link: link.value,
    variant: variant.value,
    is_heading: isHeading.value,
    locales: chosenLocales.value,
    visible: visible.value,
  })
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('menu.item-title')" :width="520">
    <wx-form-item :label="t('menu.field-where')">
      <wx-link-picker v-model="link" />
    </wx-form-item>

    <wx-form-item :label="t('menu.field-label')" :help="t('menu.label-help')">
      <wx-input
        v-model="title"
        localized
        :placeholder="fallback"
        :aria-label="t('menu.field-label')"
      />
    </wx-form-item>

    <wx-form-item v-if="variantOptions.length > 1" :label="t('menu.field-variant')">
      <wx-select
        v-model="variant"
        :options="variantOptions"
        :aria-label="t('menu.field-variant')"
      />
    </wx-form-item>

    <!--
      Empty means every language, so this is a `multiple` select with no "all" in it: a row
      called "All languages" is a row somebody picks alongside Ukrainian.
    -->
    <wx-form-item
      v-if="localeOptions.length > 1"
      :label="t('menu.field-locales')"
      :help="t('menu.locales-help')"
    >
      <wx-select
        v-model="chosenLocales"
        :options="localeOptions"
        multiple
        clearable
        :placeholder="t('menu.locales-all')"
        :aria-label="t('menu.field-locales')"
      />
    </wx-form-item>

    <wx-form-item :help="t('menu.heading-help')">
      <wx-checkbox v-model="isHeading">{{ t('menu.field-heading') }}</wx-checkbox>
    </wx-form-item>

    <wx-form-item>
      <wx-switch v-model="visible" :label="t('menu.field-visible')" />
    </wx-form-item>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('menu.cancel') }}</wx-button>
        <wx-button type="primary" @click="submit">{{ t('menu.save') }}</wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>
