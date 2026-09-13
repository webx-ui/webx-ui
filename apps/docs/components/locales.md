<script setup>
import LocalesDemo from '../components/demos/LocalesDemo.vue'
</script>

# Locales

A site published in three languages is edited in three languages, and the awkward part is never
the translation — it is the form. `WxLocales` keeps one language on screen at a time and every
other language in the DOM, so nothing is lost on save.

These are the site's **content** languages, not the ones the panel is drawn in. A panel in English
routinely edits a site published in Ukrainian and Russian.

<LocalesDemo />

## On a field

Most of the time you never reach for the component. Fields take `localized`:

```vue
<wx-input v-model="title" localized name="title" />
<wx-textarea v-model="description" localized name="description" />
```

The model then carries a record instead of a string:

```json
{ "uk": "Двигуни у зборі", "ru": "Двигатели в сборе" }
```

and each rendered input is named the way a classic form post is read back — `title[uk]`,
`title[ru]`. A field switched on over a column that used to hold one language shows those words
under the first locale rather than hiding them; the first edit turns the value into a record.

The selector sits in the corner of the field and folds down to the current language until it is
pointed at, so turning a field localized does not move the form around it.

## Around a section

Several fields that belong to one language are better switched together, above them:

```vue
<wx-locales variant="tabs">
  <template #default="{ locale }">
    <wx-input v-model="heading[locale.code]" />
  </template>
</wx-locales>
```

The slot is rendered once per language and only the current one is shown.

## One language at a time, everywhere

The language being edited is shared by the whole screen: switching it on one field switches it on
all of them. A page half in one language and half in another is how a record gets saved
untranslated. Pass `v-model:locale` to give one section a selector of its own.

## Where the list comes from

Inside a panel, `createAdmin` provides the site's content languages from the manifest, and there
is nothing to wire. Elsewhere:

```ts
import { provideLocales } from '@webx-ui/core'

provideLocales(computed(() => [{ code: 'uk' }, { code: 'ru' }]))
```

With no list — or with one language — a `localized` field is an ordinary field: no selector, and
the model stays a plain string.

## Reading a value back

```ts
import { localizedValue } from '@webx-ui/core'

localizedValue(product.title, 'ru') // 'Двигатели в сборе'
```

It falls back to any language that has words, because half-translated is the normal state of a
site being worked on, and a blank cell in a table says less than the language that does have them.

## API

### WxLocales props

| Prop      | Type                 | Default    | Description                                                  |
| --------- | -------------------- | ---------- | ------------------------------------------------------------ |
| `locales` | `LocaleOption[]`     | provided   | Languages to offer. Omitted, the panel's list is used.       |
| `variant` | `'inline' \| 'tabs'` | `'inline'` | Corner selector, or a row of tabs above.                     |
| `locale`  | `string`             | shared     | `v-model:locale` to give this section a language of its own. |

The default slot receives `{ locale, active }` and is rendered once per language.

### Field props

| Prop        | Type      | Default | Description                                    |
| ----------- | --------- | ------- | ---------------------------------------------- |
| `localized` | `boolean` | `false` | Edit the value in every language the site has. |
