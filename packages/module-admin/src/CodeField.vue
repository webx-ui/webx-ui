<script setup lang="ts">
import { ref } from 'vue'
import { WxInput } from '@webx-ui/core'
import { codify, useDefaultLocale, type CodeShape } from './slug'

/**
 * A machine name typed by hand — the code of a stock status, of a label: `wx-code`.
 *
 * What is typed becomes a code as it is typed, rather than a refusal on save: «тормозные
 * колодки» turns into `tormoznye-kolodki` under the fingers, in the site's main language, and
 * the separator a word needs next stays at the end until the field is left. The server brings
 * whatever it is handed to the same shape (`CodeType`, `Slug::code()`), so this is what the
 * editor sees, not what decides.
 *
 * `maxlength` cuts the code and is not handed to the input: the browser would cut a pasted name
 * before it was ever made a code. The rest of the node's props travel to `WxInput` as they were
 * written.
 */
defineOptions({ name: 'WxCodeField', inheritAttrs: false })

const props = defineProps<{
  modelValue?: string | null
  /** Between words: `-` (the default) or `_`. */
  separator?: string
  /** How long the code may be. */
  maxlength?: number | string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const locale = useDefaultLocale()
const field = ref<{ input: HTMLInputElement | null } | null>(null)

function shape(typing: boolean): CodeShape {
  const max = Number(props.maxlength)

  return {
    separator: props.separator === '_' ? '_' : '-',
    max: Number.isFinite(max) && max > 0 ? max : undefined,
    locale: locale.value,
    typing,
  }
}

/**
 * The code goes to the model and straight into the input as well: a sign that is dropped leaves
 * the model as it was, and the input would keep showing what was typed.
 */
function write(text: string, typing: boolean): void {
  const code = codify(text, shape(typing))

  if (code !== (props.modelValue ?? '')) emit('update:modelValue', code)

  const input = field.value?.input
  if (!input || input.value === code) return

  // Where the caret was, counted from the end — typing in the middle stays in the middle.
  const fromEnd = input.value.length - (input.selectionEnd ?? input.value.length)
  const focused = document.activeElement === input

  input.value = code

  if (focused) {
    const at = Math.max(0, code.length - fromEnd)
    input.setSelectionRange(at, at)
  }
}

function onInput(value: unknown): void {
  write(typeof value === 'string' ? value : '', true)
}

function onLeave(): void {
  write(field.value?.input?.value ?? props.modelValue ?? '', false)
}
</script>

<template>
  <wx-input
    ref="field"
    v-bind="$attrs"
    class="wx-code-field"
    :model-value="modelValue ?? ''"
    spellcheck="false"
    autocapitalize="off"
    @update:model-value="onInput"
    @blur="onLeave"
  />
</template>

<style scoped>
.wx-code-field :deep(.wx-input__inner) {
  font-family: var(--wx-font-family-mono);
}
</style>
