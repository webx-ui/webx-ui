<script setup>
import AutocompleteDemo from '../components/demos/AutocompleteDemo.vue'
</script>

# Autocomplete

`WxAutocomplete` is a text field that suggests. Unlike [Select](/components/select), the model is
the text itself — the user may type something that is not in the list, which is what an address
line, a city or a tag field needs.

<AutocompleteDemo />

## Usage

```vue
<script setup lang="ts">
import { ref } from 'vue'

const city = ref('')
const cities = [{ value: 'Kyiv' }, { value: 'Kharkiv' }, { value: 'Lviv' }]
</script>

<template>
  <wx-autocomplete v-model="city" :options="cities" placeholder="Start typing a city" clearable />
</template>
```

An option's `value` is the text that lands in the input when it is picked. Everything else you hang
on the option — an id, a whole record — comes back untouched in `select` and in the `option` slot.

## Searching on the backend

`remote` turns the local filtering off, so the list is exactly what you put in `options`. The
`search` event is debounced, `min-length` holds it back until the term is worth a request, and
`loading` shows that one is in flight:

```vue
<script setup lang="ts">
import { ref } from 'vue'

const author = ref('')
const found = ref([])
const loading = ref(false)

async function search(term: string) {
  loading.value = true
  const response = await fetch(`/api/authors?q=${encodeURIComponent(term)}`)
  const authors = await response.json()
  found.value = authors.map((a) => ({ value: a.name, description: a.role, id: a.id }))
  loading.value = false
}
</script>

<template>
  <wx-autocomplete
    v-model="author"
    :options="found"
    :loading="loading"
    :min-length="2"
    remote
    @search="search"
    @select="onPick"
  />
</template>
```

Picking a suggestion does not fire another `search` — the field would otherwise ask the backend for
the text it has just been given.

## Custom rows

The `option` slot replaces the default two lines. It receives the whole option, extra fields
included:

```vue
<template>
  <wx-autocomplete v-model="author" :options="found" remote @search="search">
    <template #option="{ option }">
      <span class="row">
        <wx-icon name="user" />
        {{ option.value }}
        <small>#{{ option.id }} · {{ option.description }}</small>
      </span>
    </template>
  </wx-autocomplete>
</template>
```

## Props

| Prop          | Type                                             | Default           | Description                                  |
| ------------- | ------------------------------------------------ | ----------------- | -------------------------------------------- |
| `modelValue`  | `string`                                         | `''`              | The text in the field                        |
| `options`     | `AutocompleteOption[]`                           | `[]`              | `{ value, label?, description?, disabled? }` |
| `remote`      | `boolean`                                        | `false`           | Do not filter locally                        |
| `debounce`    | `number`                                         | `300`             | Quiet time before `search` fires             |
| `loading`     | `boolean`                                        | `false`           | Shows a spinner                              |
| `loadingText` | `string`                                         | `'Searching…'`    | Shown while the first response is awaited    |
| `emptyText`   | `string`                                         | `'Nothing found'` | Shown when nothing matches                   |
| `minLength`   | `number`                                         | `0`               | Characters needed before searching           |
| `clearable`   | `boolean`                                        | `false`           | Button that empties the field                |
| `openOnFocus` | `boolean`                                        | `true`            | Open the list on focus                       |
| `teleport`    | `boolean`                                        | `true`            | Render the list in a portal                  |
| `placeholder` | `string`                                         | —                 | Placeholder                                  |
| `size`        | `'sm' \| 'md' \| 'lg'`                           | `'md'`            | Control height                               |
| `status`      | `'default' \| 'success' \| 'warning' \| 'error'` | `'default'`       | Validation state                             |
| `disabled`    | `boolean`                                        | `false`           | Disables the control                         |
| `name`        | `string`                                         | —                 | Field name for a plain form post             |
| `ariaLabel`   | `string`                                         | —                 | Label when there is no visible one           |

**Events:** `update:modelValue`, `change` (`string`), `search` (`string`, debounced),
`select` (`AutocompleteOption`), `clear`, `open`, `close`.

**Slots:** `option` (`{ option }`), `prefix`, `suffix`.

Inside a [FormItem](/components/form) the field takes its size, its disabled state and its
validation status from the form, exactly like the other controls.
