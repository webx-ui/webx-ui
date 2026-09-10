# Theming

WebX UI has two layers of variables:

- **Primitives** — the raw scale: `--wx-color-blue-600`, `--wx-space-5`, `--wx-radius-lg`.
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

## Rebranding

Override the semantic layer once, globally:

```css
:root {
  --wx-color-primary: #7c3aed;
  --wx-color-primary-hover: #6d28d9;
  --wx-color-primary-soft: #f5f3ff;
  --wx-radius-md: 10px;
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
`{primitive.color.blue.600}` are emitted as `var(--wx-color-blue-600)`, so the generated CSS stays
readable and themes can be diffed. Running

```bash
pnpm --filter @webx-ui/tokens generate
```

rewrites `dist/tokens.css` and the typed `src/generated/tokens.ts`.
