<script setup>
import TagsInputDemo from '../components/demos/TagsInputDemo.vue'
</script>

# TagsInput

`WxTagsInput` collects free-form tags, with suggestions that can come from a backend.

<TagsInputDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const tags = ref<string[]>([])
const suggestions = ref<string[]>([])

async function search(term: string) {
  suggestions.value = term ? await fetchTags(term) : []
}
</script>

<template>
  <wx-tags-input v-model="tags" :suggestions="suggestions" @search="search" />
</template>
```

The `search` event fires on every keystroke, so a server-side lookup is a matter of refreshing
`suggestions` in the handler. Whatever is already picked is filtered out of the list.

## Keyboard

| Key                | What happens                                           |
| ------------------ | ------------------------------------------------------ |
| `Enter`            | Adds the highlighted suggestion, or what was typed     |
| `Backspace`        | On an empty field: marks the last tag, then removes it |
| `ArrowDown` / `Up` | Moves through the suggestions                          |
| `Escape`           | Closes the suggestion list                             |

Backspace takes two presses on purpose. Deleting something the user cannot see is worse than asking
for one more key, so the first press marks the tag and the second removes it.

## Props

| Prop          | Type                                             | Default           | Description                                                       |
| ------------- | ------------------------------------------------ | ----------------- | ----------------------------------------------------------------- |
| `modelValue`  | `string[]`                                       | `[]`              | The tags                                                          |
| `suggestions` | `string[]`                                       | `[]`              | Offered while typing                                              |
| `allowCreate` | `boolean`                                        | `true`            | Enter adds a tag that is not in the list                          |
| `duplicates`  | `boolean`                                        | `false`           | Allow the same tag twice                                          |
| `max`         | `number`                                         | —                 | Largest number of tags                                            |
| `placeholder` | `string`                                         | —                 | Placeholder text                                                  |
| `emptyText`   | `string`                                         | `'Nothing found'` | Shown when the suggestions match nothing                          |
| `disabled`    | `boolean`                                        | `false`           | Disables the field                                                |
| `size`        | `'sm' \| 'md' \| 'lg'`                           | `'md'`            | Control height                                                    |
| `status`      | `'default' \| 'success' \| 'warning' \| 'error'` | `'default'`       | Validation state                                                  |
| `id`          | `string`                                         | generated         | Overrides the `id` the label points at; `WxFormItem` supplies one |
| `name`        | `string`                                         | —                 | `name` of the underlying input                                    |
| `ariaLabel`   | `string`                                         | —                 | Label when there is no visible one                                |

**Events:** `update:modelValue` (`string[]`), `change` (`string[]`), `search` (`string`).

Submitted with a form, the tags go out as repeated values under `name`.

## Why this one is not a wrapper

Every other control here leans on a library where the behaviour is hard. This one does not, and the
reason is worth recording: wrapping a tags field in a combobox to get suggestions makes the combobox
swallow the keys the tags field needs, and Backspace stops removing anything. Taking the input's
text under external control then breaks Enter, because the field adds from state it no longer owns.
The behaviour wanted here is a dozen lines, so it is written out rather than negotiated.
