<script setup lang="ts">
import LinkPicker from './LinkPicker.vue'
import type { LinkValue } from './links'

/**
 * `wx-link` on a described screen.
 *
 * The label, the help line and the errors are the renderer's — a field of kind `field` arrives
 * already wrapped in a `WxFormItem` — so all this adds is the two props a node may set and the
 * model. Everything else the node wrote travels as an attribute rather than as a declared prop:
 * declaring the picker's would turn every boolean the node left alone into `false` on the way
 * through.
 */
defineOptions({ name: 'WxLinkField', inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    /** A link that goes nowhere is a choice on a menu item and noise on a button. */
    allowNone?: boolean
    /** Off where the two attributes belong to whatever holds the link rather than to the field. */
    attributes?: boolean
    disabled?: boolean
  }>(),
  { allowNone: true, attributes: true, disabled: false },
)

const value = defineModel<LinkValue | null>({ default: null })
</script>

<template>
  <link-picker
    v-model="value"
    v-bind="$attrs"
    :allow-none="props.allowNone"
    :attributes="props.attributes"
    :disabled="props.disabled"
  />
</template>
