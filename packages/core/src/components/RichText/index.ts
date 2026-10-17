import { defineAsyncComponent } from 'vue'

/*
 * Loaded when first drawn, not with the panel: Tiptap and ProseMirror are a fifth of what the
 * panel downloaded before anything appeared, and a list or a settings screen has no text field.
 * The component is the same one — props, events, `v-model` and the instance a template ref
 * points at — it just arrives a moment after the screen that holds it.
 */
export const WxRichText = Object.assign(
  defineAsyncComponent({ loader: () => import('./RichText.vue'), suspensible: false }),
  // Named like what it loads: a test — ours or a site's — that stubs `WxRichText` by name still does.
  { name: 'WxRichText' },
)
export type * from './types'
