<script setup lang="ts">
import { computed, ref, useAttrs, watch } from 'vue'
import { WxRichText, type RichTextModelValue } from '@webx-ui/core'
import { useAdmin } from './admin'
import { richTextKeys, rewriteRichText } from './richTextAddresses'
import { useRichTextLabels } from './richTextLabels'

/**
 * `wx-rich-text` on a screen: the editor, in the panel's language, with the panel's library
 * behind its image button.
 *
 * The editor itself is a component of the design system and knows nothing about either — its
 * words are props and its pictures come from a function it is handed. Both are supplied here,
 * which is the one place that knows the panel: a module translated into ten languages with an
 * English toolbar in the middle of it is the failure this exists to avoid.
 *
 * No props of its own. Declaring the editor's would turn every boolean the node did not set
 * into `false` on the way through, so everything the node wrote travels as attributes and
 * arrives as it was written — `localized` included, which the editor handles itself.
 */
defineOptions({ name: 'WxRichTextField', inheritAttrs: false })

const admin = useAdmin()

/*
 * The library is a module's, not the panel's, so a panel without a file manager hands the
 * editor nothing — and the editor then does not draw an image button it cannot honour.
 */
const pickImage = computed(() => admin.pickImage ?? undefined)

/*
 * A document keeps each library picture's key beside an address, and the address is only true
 * on the day the paragraph was written — the site may have moved to another domain since, the
 * library to another disk, the picture been cropped. The site works the address out again on
 * every read; the editor is handed the value as stored, so it is worked out here, before the
 * editor sees it.
 *
 * What the editor sends back is passed on untouched and not looked at again: its pictures were
 * either refreshed here or picked from the library a moment ago. The editor takes the refreshed
 * document without announcing a change, so the form does not turn dirty by being opened.
 */
const attrs = useAttrs()
const shown = ref(attrs.modelValue as RichTextModelValue | undefined)
let sent: unknown

watch(
  () => attrs.modelValue,
  async (value) => {
    shown.value = value as RichTextModelValue | undefined

    const assetUrls = admin.assetUrls
    const keys = value === sent || !assetUrls ? [] : richTextKeys(value)

    if (!assetUrls || keys.length === 0) return

    const addresses = await assetUrls(keys).catch(() => null)

    // Somebody typed, or another record was opened, while the library was answering.
    if (addresses && attrs.modelValue === value) {
      shown.value = rewriteRichText(value, addresses) as RichTextModelValue
    }
  },
  { immediate: true },
)

function remember(value: unknown): void {
  sent = value
}

const labels = useRichTextLabels()
</script>

<template>
  <wx-rich-text
    v-bind="$attrs"
    :model-value="shown"
    :labels="labels"
    :pick-image="pickImage"
    @update:model-value="remember"
  />
</template>
