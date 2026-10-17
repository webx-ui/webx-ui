---
'@webx-ui/core': minor
'@webx-ui/module-audit': minor
'@webx-ui/module-auth': minor
'@webx-ui/module-banners': minor
'@webx-ui/module-blocks': minor
'@webx-ui/module-blog': minor
'@webx-ui/module-catalog': minor
'@webx-ui/module-catalog-landings': minor
'@webx-ui/module-catalog-manticore': minor
'@webx-ui/module-catalog-properties': minor
'@webx-ui/module-events': minor
'@webx-ui/module-faq': minor
'@webx-ui/module-inbox': minor
'@webx-ui/module-menu': minor
'@webx-ui/module-notifications': minor
'@webx-ui/module-pages': minor
'@webx-ui/module-press': minor
'@webx-ui/module-recipes': minor
'@webx-ui/module-reviews': minor
'@webx-ui/module-seo': minor
'@webx-ui/module-services': minor
'@webx-ui/module-settings': minor
'@webx-ui/module-snippets': minor
'@webx-ui/module-tariffs': minor
'@webx-ui/module-tasks': minor
'@webx-ui/module-team': minor
'@webx-ui/module-vacancies': minor
---

The panel downloads half as much before it can draw anything (webx-ui#16, stage 4). `WxCodeEditor` (CodeMirror and its languages) and `WxRichText` (Tiptap) are async components of the core: same names, props, events and `v-model`, a template ref points at the editor once it has arrived (`null` until then), and a stub by name in a test still replaces them. The pages of every module's routes load when their route opens (`component: () => import(...)`), and the indexes export them as `defineAsyncComponent` under the same names — a page re-exported statically would be pulled back into the index. `createAdmin({ modules })` and a site's `admin.ts` are unchanged. A site with 11 modules: 839 → 477 KB gzipped on first load; the playground with every module: 1012 → 514 KB. A jsdom test that mounts a page or an editor from a package waits for it with `await vi.dynamicImportSettled()`. `@webx-ui/module-blocks`: the word of the `block-usage` cache group.
