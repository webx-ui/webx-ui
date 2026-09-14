# @webx-ui/schema

Screens as JSON for WebX UI admin panels: a screen is a tree of nodes, each with a stable `id`
and a `type` the registry resolves to a Vue component. A module ships the tree, a project lays a
**patch** over it — add, remove, replace, move, set — and `WxScreenRenderer` draws the result with
the core components.

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { WxScreenRenderer } from '@webx-ui/schema'

const values = ref({ 'general.project-name': 'Acme' })
const patch = [{ op: 'set', target: 'robots', props: { rows: 16 } }]
</script>

<template>
  <wx-screen-renderer :root="screen.root" :patch="patch" v-model="values" :errors="errors" />
</template>
```

The package also exports the pieces on their own — `applyPatch`, `validateScreen`,
`validatePatch`, `isVisible`, `coreTypes` — and ships `schemas/screen.schema.json` and
`schemas/patch.schema.json` for editors and for the server half.

The guide: https://webx-ui.github.io/webx-ui/guide/screens.html. The design and what comes next
(the screens endpoint in `module-admin`, `module-settings`) is `docs/architecture/WEBX_UI_SCREENS.md`
in the repository.
