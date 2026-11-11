<script setup lang="ts">
import { computed, ref, useAttrs } from 'vue'
import { WxInput, useLocales, type LocalizedValue } from '@webx-ui/core'
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
 * written — `localized` among them: a code kept per language (`{ uk: '…', ru: '…' }`) is made a
 * code language by language, each in its own, since «Гальмівні» is `halmivni` in Ukrainian and
 * would be `galmivni` read the Russian way.
 */
defineOptions({ name: 'WxCodeField', inheritAttrs: false })

const props = defineProps<{
  modelValue?: string | LocalizedValue | null
  /** Between words: `-` (the default), `_`, or `-_` for a key that takes either. */
  separator?: '-' | '_' | '-_' | '_-'
  /** For a code that must start with a letter: what one that starts with a digit goes after. */
  lead?: string
  /** How long the code may be. */
  maxlength?: number | string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string | LocalizedValue] }>()

const attrs = useAttrs()
const locale = useDefaultLocale()
const locales = useLocales()
const field = ref<{ input: HTMLInputElement | null } | null>(null)

/** The way `WxInput` decides it: `localized` asked for, and languages to edit. */
const perLanguage = computed(
  () => (attrs.localized === true || attrs.localized === '') && locales.list.value.length > 0,
)

/** The language a localized input shows — the one being typed in. */
function onScreen(): string {
  const chosen = locales.active.value

  return locales.list.value.some((one) => one.code === chosen)
    ? chosen
    : (locales.list.value[0]?.code ?? '')
}

function shape(typing: boolean, language = locale.value): CodeShape {
  const max = Number(props.maxlength)

  return {
    separator: props.separator ?? '-',
    lead: props.lead || undefined,
    max: Number.isFinite(max) && max > 0 ? max : undefined,
    locale: language,
    typing,
  }
}

/**
 * The code goes to the model and straight into the input as well: a sign that is dropped leaves
 * the model as it was, and the input would keep showing what was typed.
 */
function write(value: string | LocalizedValue, typing: boolean): void {
  let code: string
  let next: string | LocalizedValue

  if (typeof value === 'string') {
    code = codify(value, shape(typing))
    next = code
  } else {
    const shown = onScreen()

    // Only the language being typed in keeps an open separator: the rest are not being typed.
    next = Object.fromEntries(
      Object.entries(value).map(([language, text]) => [
        language,
        codify(String(text ?? ''), shape(typing && language === shown, language)),
      ]),
    )
    code = next[shown] ?? ''
  }

  if (JSON.stringify(next) !== JSON.stringify(props.modelValue ?? '')) {
    emit('update:modelValue', next)
  }

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
  if (typeof value === 'object' && value !== null) {
    write(value as LocalizedValue, true)
  } else {
    write(typeof value === 'string' ? value : '', true)
  }
}

function onLeave(): void {
  const typed = field.value?.input?.value

  if (!perLanguage.value) {
    write(typed ?? (typeof props.modelValue === 'string' ? props.modelValue : ''), false)

    return
  }

  // A plain string under `localized` belongs to the first language, as `WxInput` reads it.
  const held = props.modelValue
  const base: LocalizedValue =
    typeof held === 'object' && held !== null
      ? { ...held }
      : typeof held === 'string' && held !== ''
        ? { [locales.list.value[0]?.code ?? onScreen()]: held }
        : {}

  write(typed === undefined ? base : { ...base, [onScreen()]: typed }, false)
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
