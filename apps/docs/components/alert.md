<script setup>
import AlertDemo from '../components/demos/AlertDemo.vue'
</script>

# Alert

`WxAlert` is the message that stays on the page: a validation summary above a form, a warning that
a record is locked, the note that an import finished with errors. For something that appears,
says its piece and leaves, wait for `Message` / `Toast` — an alert is part of the layout.

<AlertDemo />

## Usage

```vue
<template>
  <wx-alert type="warning" title="The import finished with errors">
    12 of 340 rows were skipped.
  </wx-alert>
</template>
```

A one-line message needs no slot:

```vue
<template>
  <wx-alert type="success" description="Settings saved" />
</template>
```

## Types and variants

Four types — `info`, `success`, `warning`, `danger` — across three weights. `soft` is the tinted
panel an admin page usually wants; `outline` is quieter on a busy screen; `solid` is for the one
message that must not be missed.

```vue
<template>
  <wx-alert type="danger" variant="solid" description="The deploy failed" />
</template>
```

## Icons

The icon follows the type. Any [icon name](/components/icon) overrides it, and `:icon="false"`
drops it:

```vue
<template>
  <wx-alert type="info" icon="bell" description="Scheduled for tonight" />
  <wx-alert type="info" :icon="false" description="No icon at all" />
</template>
```

## Dismissing

`closable` adds a ×, and the alert hides itself — unlike [Badge](/components/badge), which leaves
the list to you, because an alert has nobody else to remove it. Bind `v-model:visible` when the
page needs it back:

```vue
<script setup lang="ts">
import { ref } from 'vue'

const showConflict = ref(true)
</script>

<template>
  <wx-alert v-model:visible="showConflict" type="warning" closable>
    Somebody else edited this page while you had it open.
  </wx-alert>
</template>
```

## Announcing it

An alert rendered with the page should not be announced — a screen reader would read it out of
nowhere, before the user has reached it. One that appears in response to something should be.
That is what `live` is for:

```vue
<template>
  <!-- Appears after a failed save, so it announces itself. -->
  <wx-alert v-if="errors.length" live type="danger" title="The form could not be saved" />
</template>
```

## Props

| Prop          | Type                                           | Default     | Description                               |
| ------------- | ---------------------------------------------- | ----------- | ----------------------------------------- |
| `type`        | `'info' \| 'success' \| 'warning' \| 'danger'` | `'info'`    | Semantic role of the message              |
| `variant`     | `'soft' \| 'solid' \| 'outline'`               | `'soft'`    | Visual weight                             |
| `title`       | `string`                                       | —           | Headline above the body                   |
| `description` | `string`                                       | —           | The message, instead of the default slot  |
| `icon`        | `IconName \| false`                            | by `type`   | Overrides the leading icon, or removes it |
| `closable`    | `boolean`                                      | `false`     | Adds a × that hides the alert             |
| `closeLabel`  | `string`                                       | `'Dismiss'` | Accessible name of the close button       |
| `live`        | `boolean`                                      | `false`     | Announces the alert when it appears       |

**Models:** `v-model:visible` (`boolean`, `true`) — whether the alert is rendered.

**Events:** `close` (`MouseEvent`).

**Slots:** `default` — the message; `title`; `icon`; `actions` — buttons at the end of the row.
