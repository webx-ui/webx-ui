<script setup lang="ts">
import { computed } from 'vue'
import { useLocales, WxText } from '@webx-ui/core'
import { useTranslate } from '@webx-ui/module-admin'
import { usePropertyEditor } from './editor'
import { exampleNumber, formatNumber, wordsIn } from './format'
import { NAMESPACE } from './i18n'
import type { Words } from './types'

/**
 * `wx-catalog-property-example`: a number as the site will print it, while its prefix, suffix and
 * precision are typed — `⌀12 мм`, `M8`, `1,5 кг`. In the language being edited, because the units
 * are per language and so is the decimal mark; a space belongs to the suffix, which is why it is
 * shown and not guessed.
 */
defineOptions({ name: 'WxCatalogPropertyExample' })

const editor = usePropertyEditor()
const locales = useLocales()
const t = useTranslate(NAMESPACE)

const example = computed(() => {
  const values = editor?.values.value ?? {}
  const locale = locales.active.value
  const precision = Number(values.precision ?? 0)

  return formatNumber(
    exampleNumber(precision),
    {
      precision,
      prefix: wordsIn(values.unit_prefix as Words, locale),
      suffix: wordsIn(values.unit_suffix as Words, locale),
    },
    locale,
  )
})
</script>

<template>
  <wx-text class="wx-catalog-property-example" size="sm" tone="muted">
    {{ t('panel.example') }}
    <span class="wx-catalog-property-example__value">{{ example }}</span>
  </wx-text>
</template>

<style scoped>
.wx-catalog-property-example__value {
  color: var(--wx-text-strong);
  font-weight: var(--wx-font-weight-medium);
  /* The spaces are the admin's — a suffix « мм» keeps its own — so they are shown as typed. */
  white-space: pre;
}
</style>
