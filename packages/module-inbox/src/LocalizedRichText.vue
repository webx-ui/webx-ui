<script setup lang="ts">
import { computed } from 'vue'
import { useLocales, WxLocales, WxRichText, type LocalizedValue } from '@webx-ui/core'

/**
 * Rich text in every language the site publishes in.
 *
 * `WxRichText` holds one string, the way `WxInput` does without `localized`; what a localized
 * field adds is the picker and the map underneath it. This is that, for the one place in this
 * module that needs it — the thank-you, which is printed raw on the site and is therefore
 * written where formatting is possible.
 *
 * With no content languages at all — a panel assembled by hand, a test — the value stays a
 * plain string, which is exactly what `useLocalized` does in the same situation and what the
 * server accepts for a form written before the site had a second language.
 */
const model = defineModel<LocalizedValue | string>({ default: '' })

const props = withDefaults(defineProps<{ placeholder?: string; minHeight?: string }>(), {
  placeholder: undefined,
  minHeight: '160px',
})

const locales = useLocales()

const active = computed(() => {
  const chosen = locales.active.value
  const known = locales.list.value.some((locale) => locale.code === chosen)

  return known ? chosen : (locales.list.value[0]?.code ?? '')
})

const text = computed({
  get: () => {
    const value = model.value

    if (typeof value === 'string') {
      // A string under a localized field belongs to the first language: that is where the
      // words were written, and hiding them would look like losing them.
      return active.value === '' || active.value === locales.list.value[0]?.code ? value : ''
    }

    return value[active.value] ?? ''
  },
  set: (next: string) => {
    if (active.value === '') {
      model.value = next

      return
    }

    const base = typeof model.value === 'string' ? { [active.value]: model.value } : model.value

    model.value = { ...base, [active.value]: next }
  },
})
</script>

<template>
  <wx-locales v-if="locales.list.value.length > 0">
    <wx-rich-text v-model="text" :placeholder="props.placeholder" :min-height="props.minHeight" />
  </wx-locales>

  <wx-rich-text
    v-else
    v-model="text"
    :placeholder="props.placeholder"
    :min-height="props.minHeight"
  />
</template>
