<script setup lang="ts">
import { computed, ref } from 'vue'
import { provideLocales, WxRichText } from '@webx-ui/core'
import { WxScreenRenderer, type ScreenModel, type ScreenNode } from '@webx-ui/schema'

/**
 * `wx-rich-text` as a screen node.
 *
 * In a panel the component behind the type is `module-admin`'s own wrapper, which hands the
 * editor the panel's words and the media library's picker. Neither exists on this page, so the
 * type is registered here against `WxRichText` itself with a picker that stands in for a
 * library — everything else is the same tree a module would ship.
 */
const root: ScreenNode[] = [
  {
    id: 'card',
    type: 'wx-card',
    label: 'The article',
    children: [
      { id: 'title', type: 'wx-input', name: 'title', label: 'Title', localized: true },
      {
        id: 'body',
        type: 'wx-rich-text',
        name: 'body',
        label: 'Body',
        localized: true,
        help: 'The chip in the corner swaps the language; the picture button opens the library.',
        props: { minHeight: '200px', placeholder: 'Write the article…' },
      },
    ],
  },
]

const model = ref<ScreenModel>({
  title: { en: 'Release notes', de: 'Neuerungen' },
  body: {
    en: '<h2>What changed</h2><p>Two languages, one editor.</p>',
    de: '<p>Zwei Sprachen.</p>',
  },
})

provideLocales(computed(() => [{ code: 'en' }, { code: 'de' }]))

/**
 * Stands in for the media library a panel would open here — and answers the way one does: the
 * address to draw with, and the key the document keeps so the address can be worked out again.
 */
async function pickImage() {
  return { url: 'https://picsum.photos/seed/webx-field/640/360', path: '2026/09/seed.jpg' }
}

const types = {
  'wx-rich-text': {
    component: WxRichText,
    kind: 'field' as const,
    wide: true,
    bind: () => ({ pickImage }),
  },
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <wx-screen-renderer v-model="model" :root="root" :types="types" />

    <details>
      <summary style="cursor: pointer; color: var(--wx-text-muted); font-size: 13px">
        The model
      </summary>
      <pre
        style="
          margin: 8px 0 0;
          padding: 12px;
          overflow-x: auto;
          background: var(--wx-bg-fill);
          border-radius: 8px;
          font-size: 12px;
        "
        >{{ model }}</pre>
    </details>
  </div>
</template>
