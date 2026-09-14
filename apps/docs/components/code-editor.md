<script setup>
import CodeEditorDemo from '../components/demos/CodeEditorDemo.vue'
</script>

# CodeEditor

`WxCodeEditor` is the field for code: JSON patches, a snippet of CSS, a Blade template, a YAML
config. It is built on [CodeMirror 6](https://codemirror.net/) — MIT, the same lineage as the
ProseMirror under [RichText](/components/rich-text) — and the model is a plain string. Syntax
highlighting, line numbers, folding, bracket matching, undo history and search (`Ctrl+F`) come
with it; the colours are the design tokens, so both themes are covered.

<CodeEditorDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const patch = ref('{}')
</script>

<template>
  <wx-form-item label="Patch" name="patch">
    <wx-code-editor v-model="patch" language="json" />
  </wx-form-item>
</template>
```

Like every form control it takes `size`, `status` and `disabled` from the enclosing
`WxFormItem` and `WxForm`, and a 422 from the server lands under the field as usual.

## Languages

`json`, `javascript`, `typescript`, `html`, `css`, `markdown`, `yaml`, `php` and `plain` — the
languages an admin panel is likely to hold, and nothing else: each one is a parser that ships
with the component. `plain` is a monospace surface with the same chrome and no highlighting.

`php` understands the mixed HTML that a Blade template is, so it is the language to pick for
one.

## JSON is checked as you type

With `language="json"` the document is parsed after each pause in typing. A syntax error is
underlined, the message shows on hover, and the `lint` event reports the same list — empty when
the document is valid:

```vue
<script setup lang="ts">
import { ref } from 'vue'
import type { CodeEditorDiagnostic } from '@webx-ui/core'

const problems = ref<CodeEditorDiagnostic[]>([])
</script>

<template>
  <wx-code-editor v-model="patch" language="json" @lint="problems = $event" />
  <wx-button :disabled="problems.length > 0">Apply</wx-button>
</template>
```

Each diagnostic carries `from`, `to` (character offsets), `severity` and `message`. Set
`:lint="false"` to turn the check off. Other languages have no linter yet; the flag does nothing
there.

`format()` re-indents a JSON document with `tabSize` spaces and returns `true`; when the text does
not parse it returns `false` and leaves it alone, so a "Format" button never destroys what the
user was in the middle of typing:

```vue
<template>
  <wx-code-editor ref="editor" v-model="patch" language="json" />
  <wx-button @click="editor?.format()">Format</wx-button>
</template>
```

## Keyboard

`Tab` indents and `Shift+Tab` dedents, as in any editor — which means `Tab` does not leave the
field. Press `Escape` first and the next `Tab` moves focus on; screen readers announce this.
`Ctrl+F` opens search inside the editor, `Ctrl+Z` / `Ctrl+Shift+Z` undo and redo, and
`Ctrl+/`-style comment toggling is not wired because the languages disagree about what a comment
is.

## Height

The editor starts at `minHeight` and grows with the document. Give it `maxHeight` and it stops
growing there and scrolls inside instead — the right choice inside a dialog, where the page must
not become the thing that scrolls.

## Beyond the props

`extensions` accepts any CodeMirror extensions and appends them to the editor's own: a custom
linter, autocompletion for a known vocabulary, a keybinding. `view` — the `EditorView` — is
exposed for anything else. Both are escape hatches; if a need keeps coming up, it belongs in a
prop.

## Props

| Prop           | Type                                             | Default     | Description                                                       |
| -------------- | ------------------------------------------------ | ----------- | ----------------------------------------------------------------- |
| `modelValue`   | `string`                                         | `''`        | The document                                                      |
| `language`     | `CodeEditorLanguage`                             | `'plain'`   | What to highlight as                                              |
| `placeholder`  | `string`                                         | —           | Shown while the document is empty                                 |
| `lineNumbers`  | `boolean`                                        | `true`      | Line numbers and the fold gutter                                  |
| `lineWrapping` | `boolean`                                        | `false`     | Wrap long lines instead of scrolling sideways                     |
| `tabSize`      | `number`                                         | `2`         | Indentation width; `Tab` inserts this many spaces                 |
| `lint`         | `boolean`                                        | `true`      | Run the language's linter, where there is one                     |
| `extensions`   | `Extension[]`                                    | `[]`        | Extra CodeMirror extensions                                       |
| `minHeight`    | `string`                                         | `'160px'`   | Height before the editor starts growing                           |
| `maxHeight`    | `string`                                         | —           | Height at which it scrolls instead                                |
| `size`         | `'sm' \| 'md' \| 'lg'`                           | `'md'`      | Control size                                                      |
| `status`       | `'default' \| 'success' \| 'warning' \| 'error'` | `'default'` | Validation state                                                  |
| `disabled`     | `boolean`                                        | `false`     | Not editable and skipped by `Tab`                                 |
| `readonly`     | `boolean`                                        | `false`     | Not editable, but focusable and scrollable                        |
| `id`           | `string`                                         | generated   | Overrides the `id` the label points at; `WxFormItem` supplies one |
| `ariaLabel`    | `string`                                         | —           | Label when there is no visible one                                |

**Events:** `update:modelValue` (`string`), `change` (`string`), `focus`, `blur`,
`lint` (`CodeEditorDiagnostic[]`).

**Exposed:** `view` — the CodeMirror `EditorView` — plus `focus()` and `format()`.

## Bundle size

CodeMirror and its language packages are dependencies of `@webx-ui/core` and are installed with
it. They are not bundled into our output, so an app that never imports `WxCodeEditor` will not
ship them. Watch for a second copy of `@codemirror/state`: if the app installs CodeMirror itself at
another version, the editor throws `Unrecognized extension value` — dedupe it in the bundler.
