import { defineAsyncComponent } from 'vue'

/*
 * Loaded when first drawn, not with the panel: CodeMirror with its seven languages is a third of
 * what the panel downloaded before anything appeared, and most screens have no code on them.
 * The component is the same one — props, events, `v-model` and the instance a template ref
 * points at — it just arrives a moment after the screen that holds it.
 */
export const WxCodeEditor = Object.assign(
  defineAsyncComponent({ loader: () => import('./CodeEditor.vue'), suspensible: false }),
  // Named like what it loads: a test — ours or a site's — that stubs `WxCodeEditor` by name still does.
  { name: 'WxCodeEditor' },
)
export type * from './types'
export {
  mustacheCompletions,
  mustacheHighlighting,
  mustacheProblems,
  scanMustache,
} from './mustache'
export type { MustacheProblems, MustacheTag, MustacheTagKind } from './mustache'
