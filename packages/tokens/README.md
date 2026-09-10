# @webx-ui/tokens

Design tokens for [WebX UI](https://github.com/webx-ui/webx-ui): colors, spacing, typography, radii,
shadows and z-index — as CSS custom properties (`--wx-*`) and as a typed TS object.

## Install

```bash
pnpm add @webx-ui/tokens
```

## Usage

```ts
import '@webx-ui/tokens/tokens.css'
```

```css
.my-panel {
  background: var(--wx-bg-surface);
  color: var(--wx-text-default);
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-lg);
  padding: var(--wx-space-16);
}
```

Dark theme is applied via `data-theme="dark"` on any ancestor (usually `<html>`), and is also picked
up automatically from `prefers-color-scheme` unless `data-theme="light"` is set explicitly.

```ts
import { applyTheme, cssVar, tokens } from '@webx-ui/tokens'

applyTheme('dark')
cssVar('color-primary') // "var(--wx-color-primary)"
tokens.primitive.color.blue.base // "#427edd"
```

## Editing tokens

`src/tokens.json` is the single source of truth. `pnpm --filter @webx-ui/tokens generate` rebuilds
`dist/tokens.css` and `src/generated/tokens.ts`; values written as `{primitive.color.blue.base}`
become `var(--wx-color-blue-base)` references.
