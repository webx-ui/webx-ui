<script setup lang="ts">
import { computed } from 'vue'
import { useLocales, WxSelect } from '@webx-ui/core'

/**
 * `wx-catalog-feed-locale`: the language of a feed, out of the site's own. A feed has one; a second
 * language is a second feed (§3.1 of the feeds spec).
 */
defineOptions({ name: 'WxCatalogFeedLocale' })

defineProps<{ disabled?: boolean }>()

const value = defineModel<string | null>({ default: null })

const locales = useLocales()

const options = computed(() =>
  locales.list.value.map((locale) => ({ value: locale.code, label: locale.label ?? locale.code })),
)
</script>

<template>
  <wx-select v-model="value" :options="options" :disabled="disabled" />
</template>
