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
```

Because `data-theme` works on any element, a single page can mix themes — a dark sidebar inside a
light admin panel, for example.

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
