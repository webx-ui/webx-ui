<script setup>
import CodeEditorDemo from '../components/demos/CodeEditorDemo.vue'
import CodeEditorMustacheDemo from '../components/demos/CodeEditorMustacheDemo.vue'
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

`json`, `javascript`, `typescript`, `html`, `css`, `markdown`, `yaml`, `php`, `mustache`,
`mustache-html` and `plain` — the
languages an admin panel is likely to hold, and nothing else: each one is a parser that ships
with the component. `plain` is a monospace surface with the same chrome and no highlighting.

`php` understands the mixed HTML that a Blade template is, so it is the language to pick for
one.

## Mustache

::: v-pre

`mustache` paints [Mustache](https://mustache.github.io/mustache.5.html) tags over text and
`mustache-html` over HTML: a value in the primary colour, a section (`{{#…}}`, `{{^…}}`, `{{/…}}`)
bold, a comment muted, and what a template may not use here — a partial `{{>…}}`, a change of
delimiters, a tag that never ends — underlined in red. Neither CodeMirror nor a maintained package
has Mustache for CodeMirror 6, so the tags are found by one scan of the document; the same scan is
exported as `scanMustache()` for whatever else needs to know where a tag is.

`mustacheCompletions(variables)` is the matching source of suggestions — type `{{`, in the one-line
subject above the template too:
:::

<CodeEditorMustacheDemo />

::: v-pre

```vue
<script setup lang="ts">
import { mustacheCompletions, mustacheProblems, type MustacheVariable } from '@webx-ui/core'

const variables: MustacheVariable[] = [
  { name: 'form', label: 'The name of the form', example: 'Contact us' },
  { name: 'items', label: 'The fields', fields: [{ name: 'label' }, { name: 'value' }] },
]

const completions = mustacheCompletions(variables)
const problems = computed(() => mustacheProblems(template.value, variables))
</script>

<template>
  <wx-code-editor v-model="template" language="mustache-html" :completions="completions" />
</template>
```

- after `{{` — the variables, with the example value beside each and the description beside the
  list; a list (a variable with `fields`) is written as a whole section with the caret inside;
  `#name` and `^name` write "shown when it has a value" and "shown when it is empty";
- inside `{{#items}}` — the fields of its element first, the way Mustache looks a name up;
- after `{{/` — the section open nearest first;
- with no variables — only the syntax: a section, an inverted section, a comment.

Every suggestion ends the tag and takes over the `}}` that closing brackets already typed. The
words beside the suggestions are English; pass the second argument —
`mustacheCompletions(variables, { list: '…', field: '… :list' })` — to say them in another
language.

`mustacheProblems(text, variables?)` reads a template the same way and answers `unclosed`
(sections never closed), `stray` (a close of nothing), `refused` (partials, delimiters, pragmas),
`broken` (`{{` without `}}`) and — only when variables are given — `unknown`: names that are
neither a variable nor a field of the list they stand in.
:::

## Completions

`completions` turns on suggestions while typing and on `Ctrl+Space`. It is either a list —
completed from the word before the caret — or a function of the document and the caret that says
where its suggestions start:

```ts
const completions: CodeEditorCompleter = ({ text, pos }) =>
  text.slice(0, pos).endsWith('@')
    ? { from: pos, options: [{ label: 'mention', detail: 'a person' }] }
    : null
```

A suggestion has a `label`, and may have `apply` (what is written instead; `${}` marks where the
caret lands, as in a CodeMirror snippet), `detail`, `info`, `type` and `boost`.

### What the language knows

`language-completions` switches on the suggestions each language ships with, no list of your own:

- **HTML** — tags after `<`, attributes inside a tag (`<script ` → `src`, `async`, `defer`,
  `type`…) and their values; `>` writes the closing tag.
- **JavaScript**, alone or inside `<script>` — keywords, snippets (`if`, `for`, `function`,
  `try`…), the names the code itself declares, and the globals of the page after a dot
  (`document.`, `console.`, `localStorage.`, `navigator.`). No type analysis.
- **CSS**, alone or inside `<style>` — properties and values.

`Esc` closes the list, and Enter without one is a line break. `completions`, when given, replaces
these.

```vue
<wx-code-editor v-model="code" language="html" language-completions />
```

## In every language

`localized` edits the document once per language the site publishes in, with the same chip in the
corner as `WxInput` and `WxTextarea`; the model is then `{ en: '…', uk: '…' }`, and switching
the language swaps the document without counting as an edit of either.

## One line

`single-line` makes the editor a field of one line, as tall as `WxInput` of its size — for a
template that is one line, the subject of a letter, and still wants its highlighting and
`completions`. Enter takes the suggestion in focus and otherwise does nothing, a line break typed
or pasted in becomes a space, and `Tab` moves on to the next field instead of indenting.

```vue
<wx-code-editor v-model="subject" language="mustache" single-line :completions="completions" />
```

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

## Loaded on demand

`WxCodeEditor` is an async component: CodeMirror and its seven languages — up to 300 KB gzipped,
a third of what the panel used to download before anything appeared — are a chunk the browser
fetches when a screen first draws an editor. Props, events and `v-model` are the same, and a
template ref points at the editor itself once it has arrived; until then it is `null`, so reach
for `view` from a handler rather than straight after mounting. In a jsdom test, wait for it with
`await vi.dynamicImportSettled()`; a stub by name (`stubs: { WxCodeEditor: … }`) still replaces it.

## Beyond the props

`extensions` accepts any CodeMirror extensions and appends them to the editor's own: a custom
linter, a keybinding. `view` — the `EditorView` — is
exposed for anything else. Both are escape hatches; if a need keeps coming up, it belongs in a
prop.

## Props

| Prop                  | Type                                             | Default     | Description                                                       |
| --------------------- | ------------------------------------------------ | ----------- | ----------------------------------------------------------------- |
| `modelValue`          | `string \| Record<string, string>`               | `''`        | The document; one per language under `localized`                  |
| `language`            | `CodeEditorLanguage`                             | `'plain'`   | What to highlight as                                              |
| `placeholder`         | `string`                                         | —           | Shown while the document is empty                                 |
| `lineNumbers`         | `boolean`                                        | `true`      | Line numbers and the fold gutter                                  |
| `lineWrapping`        | `boolean`                                        | `false`     | Wrap long lines instead of scrolling sideways                     |
| `tabSize`             | `number`                                         | `2`         | Indentation width; `Tab` inserts this many spaces                 |
| `lint`                | `boolean`                                        | `true`      | Run the language's linter, where there is one                     |
| `completions`         | `CodeEditorCompletions`                          | —           | Suggestions: a list of words, or a function of the place          |
| `languageCompletions` | `boolean`                                        | `false`     | Suggestions the language knows: HTML tags and attributes, JS, CSS |
| `localized`           | `boolean`                                        | `false`     | One document per language of the site                             |
| `singleLine`          | `boolean`                                        | `false`     | One line, as tall as an input; Enter breaks no line               |
| `extensions`          | `Extension[]`                                    | `[]`        | Extra CodeMirror extensions                                       |
| `minHeight`           | `string`                                         | `'160px'`   | Height before the editor starts growing                           |
| `maxHeight`           | `string`                                         | —           | Height at which it scrolls instead                                |
| `size`                | `'sm' \| 'md' \| 'lg'`                           | `'md'`      | Control size                                                      |
| `status`              | `'default' \| 'success' \| 'warning' \| 'error'` | `'default'` | Validation state                                                  |
| `disabled`            | `boolean`                                        | `false`     | Not editable and skipped by `Tab`                                 |
| `readonly`            | `boolean`                                        | `false`     | Not editable, but focusable and scrollable                        |
| `id`                  | `string`                                         | generated   | Overrides the `id` the label points at; `WxFormItem` supplies one |
| `ariaLabel`           | `string`                                         | —           | Label when there is no visible one                                |

**Events:** `update:modelValue` (`string`), `change` (`string`), `focus`, `blur`,
`lint` (`CodeEditorDiagnostic[]`).

**Exposed:** `view` — the CodeMirror `EditorView` — plus `focus()` and `format()`. Type a template
ref with `ref<CodeEditorExposed | null>(null)`: the component is generic over its model, and
`InstanceType<typeof WxCodeEditor>` does not take a generic component.

## Bundle size

CodeMirror and its language packages are dependencies of `@webx-ui/core` and are installed with
it. They are not bundled into our output, so an app that never imports `WxCodeEditor` will not
ship them. Watch for a second copy of `@codemirror/state`: if the app installs CodeMirror itself at
another version, the editor throws `Unrecognized extension value` — dedupe it in the bundler.
