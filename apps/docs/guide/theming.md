# Theming

WebX UI has two layers of variables:

- **Primitives** — the raw scale: `--wx-color-blue-base`, `--wx-space-16`, `--wx-radius-lg`.
- **Semantic** — what a primitive means in context: `--wx-color-primary`, `--wx-bg-surface`,
  `--wx-text-muted`, `--wx-border-default`.

Components only ever read semantic variables. Overriding a semantic variable restyles every
component that uses it.

## Dark mode

The dark theme is applied with `data-theme="dark"` on any ancestor — usually `<html>`:

```html
<html data-theme="dark"></html>
```

It also follows the OS setting automatically, unless `data-theme="light"` is set explicitly:

```css
@media (prefers-color-scheme: dark) {
  :root:not([data-theme='light']) {
    /* dark values */
  }
}
```

From TypeScript:

```ts
import { applyTheme } from '@webx-ui/tokens'

applyTheme('dark') // sets data-theme on <html>
applyTheme('light', panelElement) // or on any element
applyTheme('system') // removes it again: follow the machine
```

Because `data-theme` works on any element, a single page can mix themes, in either direction: a
dark sidebar inside a light panel, or a light preview inside a dark one. Both values are written
out, so an island declares its own theme rather than inheriting the page's.

## Three states

What somebody chooses is not the same thing as what is on screen. `system` is a standing
instruction to follow the machine, and it resolves anew every time the machine changes its mind —
which is why `applyTheme('system')` **removes** the attribute rather than writing a third value:
the stylesheet already follows `prefers-color-scheme` for everything that is not pinned to light.

The stylesheet needs nothing else. Anything that has to _know_ — a canvas painting its own
background, an editor handed a colour scheme — can ask:

```ts
import { systemTheme, watchSystemTheme } from '@webx-ui/tokens'

systemTheme() // 'light' | 'dark', right now
const stop = watchSystemTheme((theme) => redraw(theme))
```

[ThemeSwitch](/components/theme-switch) is the control for all three. In an admin panel it is
already placed, translated and stored against the administrator — see
[the panel's own theme](#the-panel-s-own-theme) below.

## The panel's own theme

`createAdmin()` builds a theme controller before it mounts anything, so the sign-in screen is
already the colour this browser was left in, and hands it to the panel:

```ts
import { useTheme } from '@webx-ui/module-admin'

const theme = useTheme()

theme.state.preference // 'light' | 'dark' | 'system'
theme.state.resolved // 'light' | 'dark' — what is actually on screen
theme.set('dark')
```

Two copies of the choice, deliberately. The one in `localStorage` is what paints the first frame,
before anybody is known; the one stored against the administrator is the one that lasts, and it
wins the moment the session says who they are — so a theme chosen on a laptop at night is waiting
at the desk in the morning. `@webx-ui/module-auth` puts the switch in the account menu and writes
it down; `null` there means _follow the machine_, which is a choice too.

A panel served by `webx-ui/module-admin` also paints before its bundle runs: the Blade shell reads
the browser's copy in a three-line script, so a dark panel never starts white.

## Colour states

Every accent colour comes as a set of five, so components never compute a shade themselves:

| Variable                      | Used for                                   |
| ----------------------------- | ------------------------------------------ |
| `--wx-color-primary`          | Resting fill or accent                     |
| `--wx-color-primary-hover`    | Pointer hover                              |
| `--wx-color-primary-active`   | Pressed                                    |
| `--wx-color-primary-disabled` | Disabled fill — a real colour, not opacity |
| `--wx-color-primary-soft`     | Tinted background: soft buttons, alerts    |

The same five exist for `success`, `warning`, `danger` and `info`. Rebranding means overriding a
set, not a single value.

## Density

Controls are comfortable by default (`--wx-size-control-md: 42px`). Wrap any subtree in
`wx-density-compact` to shrink them — useful for tables, toolbars and dialogs:

```html
<div class="wx-density-compact">
  <wx-input placeholder="34px tall here" />
</div>
```

It is a plain variable override, so it nests and can be scoped as finely as you like. The full list
of what it changes is on the [Tokens](/tokens/#density) page.

## Rebranding

Override the semantic layer once, globally:

```css
:root {
  --wx-color-primary: #7c3aed;
  --wx-color-primary-hover: #6d28d9;
  --wx-color-primary-active: #5b21b6;
  --wx-color-primary-disabled: #c4b5fd;
  --wx-color-primary-soft: #f5f3ff;
  --wx-radius-control: 6px;
}
```

Scope it to restyle one section only:

```css
.marketing-panel {
  --wx-color-primary: #059669;
}
```

## Editing the tokens themselves

`packages/tokens/src/tokens.json` is the single source of truth. References written as
`{primitive.color.blue.base}` are emitted as `var(--wx-color-blue-base)`, so the generated CSS stays
readable and themes can be diffed. Running

```bash
pnpm --filter @webx-ui/tokens generate
```

rewrites `dist/tokens.css` and the typed `src/generated/tokens.ts`.
