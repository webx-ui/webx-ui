<script setup>
import ThemeSwitchDemo from '../components/demos/ThemeSwitchDemo.vue'
</script>

# ThemeSwitch

`WxThemeSwitch` is light, dark, or whatever the machine says.

<ThemeSwitchDemo />

## Usage

```vue
<script setup lang="ts">
import { applyTheme, type ThemePreference } from '@webx-ui/tokens'

const theme = ref<ThemePreference>('system')

watch(theme, (value) => applyTheme(value))
</script>

<template>
  <wx-theme-switch v-model="theme" />
</template>
```

It only reports the choice. Applying it is [`applyTheme()`](/guide/theming#dark-mode), and
remembering it belongs to whoever knows where this person's settings live — the browser for a
site, the account for a panel. Keeping those two apart is what lets the same control sit in a
marketing header and in an admin menu.

## Three states, not two

A toggle can say light and dark. It cannot say _I have not decided_ — and that is the state most
people are in, because the machine has already decided for them. Follow it, and somebody's panel
goes dark at sunset along with everything else on their desk; pin it to a theme the first time
they touch the control, and it never does again.

So `system` is a real answer, and the one the switch starts on.

## Props

| Prop          | Type                   | Default               | Description                        |
| ------------- | ---------------------- | --------------------- | ---------------------------------- |
| `size`        | `'sm' \| 'md' \| 'lg'` | `'md'`                | Height of the track                |
| `block`       | `boolean`              | `false`               | Fills its width, three equal cells |
| `disabled`    | `boolean`              | `false`               | The whole group                    |
| `ariaLabel`   | `string`               | `'Theme'`             | Accessible name for the group      |
| `lightLabel`  | `string`               | `'Light'`             | Read out and shown on hover        |
| `darkLabel`   | `string`               | `'Dark'`              | —                                  |
| `systemLabel` | `string`               | `'Follow the system'` | —                                  |

**Models:** `v-model` (`'light' \| 'dark' \| 'system'`).

**Events:** `change` (`value`) — only when the choice actually changes.

## The words are props

Like every component in the core, it ships English and knows nothing about a dictionary: whoever
places it translates it. The three labels are the only thing that says what the middle picture
means, so they are worth passing.

```vue
<wx-theme-switch
  v-model="theme"
  :aria-label="t('theme.label')"
  :light-label="t('theme.light')"
  :dark-label="t('theme.dark')"
  :system-label="t('theme.system')"
/>
```

In an admin panel none of this is needed: `@webx-ui/module-admin` places the switch already
translated, and writes the choice down against the administrator so it follows them to the next
machine.

## Accessibility

`role="radiogroup"` with a `radio` per choice, `aria-checked` on each, and one tab stop: Tab
reaches the chosen cell and the arrow keys move between them. The pictures carry no words, so
each button's name comes from its label — which is why they are not optional.

The thumb slides and the chosen picture arrives with a small animation; both stop under
`prefers-reduced-motion`.
