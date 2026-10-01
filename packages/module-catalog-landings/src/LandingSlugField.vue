<script setup lang="ts">
import { computed } from 'vue'
import { useLocales, WxButton, WxInput, WxText, type LocalizedValue } from '@webx-ui/core'
import { useTranslate } from '@webx-ui/module-admin'
import { useLandingEditor } from './editor'
import { NAMESPACE, useCatalogLandingsMessages } from './i18n'

/**
 * `wx-catalog-landing-slug`: the address of a landing, per language, with the suggestion its set
 * makes under it — `{category}-{value}` of the slugs, as «Create in bulk» fills it (§8.2). The
 * set builder asks the server and puts the suggestion into the editor; «Use» writes it into the
 * language being edited and into the languages still empty.
 */
defineOptions({ name: 'WxCatalogLandingSlug' })

const props = withDefaults(defineProps<{ localized?: boolean }>(), { localized: false })
const value = defineModel<LocalizedValue | string | null>({ default: null })

const editor = useLandingEditor()
const locales = useLocales()
useCatalogLandingsMessages()

const t = useTranslate(NAMESPACE)

const locked = computed(() => editor?.locked.value ?? false)
const record = computed<LocalizedValue>(() =>
  value.value && typeof value.value === 'object' ? value.value : {},
)

const suggestion = computed(() => {
  const suggested = editor?.suggested.value ?? {}
  const locale = locales.active.value || Object.keys(suggested)[0] || ''
  const slug = suggested[locale]

  if (!slug) return null

  const current = props.localized ? record.value[locale] : value.value

  return current === slug ? null : slug
})

function use(): void {
  const suggested = editor?.suggested.value ?? {}

  if (!props.localized) {
    value.value = suggestion.value

    return
  }

  const next: LocalizedValue = { ...record.value }
  const active = locales.active.value

  for (const [locale, slug] of Object.entries(suggested)) {
    if (locale === active || !next[locale]) next[locale] = slug
  }

  value.value = next
}
</script>

<template>
  <div class="wx-catalog-landing-slug">
    <wx-input
      :model-value="value ?? undefined"
      :localized="props.localized"
      :disabled="locked"
      :maxlength="255"
      @update:model-value="(next: unknown) => (value = next as LocalizedValue | string)"
    />
    <div v-if="suggestion && !locked" class="wx-catalog-landing-slug__hint">
      <wx-text size="sm" tone="muted">{{
        t('landing.slug-suggested', { slug: suggestion })
      }}</wx-text>
      <wx-button size="sm" variant="text" @click="use">{{ t('landing.slug-use') }}</wx-button>
    </div>
  </div>
</template>

<style scoped>
.wx-catalog-landing-slug {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
  min-width: 0;
}

.wx-catalog-landing-slug__hint {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--wx-space-8);
}
</style>
