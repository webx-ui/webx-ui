# Introduction

WebX UI is an open-source design system built on Vue 3. It exists to power admin panels (CMS) for
Laravel projects: the library is public and generic, the admin panels that consume it stay private.

## Packages

| Package                                                            | Status   | What it does                                                              |
| ------------------------------------------------------------------ | -------- | ------------------------------------------------------------------------- |
| [`@webx-ui/tokens`](https://www.npmjs.com/package/@webx-ui/tokens) | released | Colors, spacing, typography, radii, shadows as `--wx-*` CSS variables     |
| [`@webx-ui/core`](https://www.npmjs.com/package/@webx-ui/core)     | released | Vue 3 components — see the [roadmap](/guide/roadmap) for what is in it    |
| [`@webx-ui/schema`](https://www.npmjs.com/package/@webx-ui/schema) | skeleton | Contracts for rendering admin screens from JSON                           |
| `@webx-ui/adapter-laravel`                                         | planned  | Laravel data adapter: paginator, 422 errors, sort/filter query parameters |

## Principles

- **Tokens only.** Components never hard-code a colour, spacing or radius — they read `--wx-*`
  variables, so a project can restyle the whole system by overriding a handful of values.
- **No API calls inside components.** Data comes in through props (or a data adapter), changes go out
  as events. That keeps components testable and reusable across backends.
- **Laravel-friendly, not Laravel-bound.** `WxTable` consumes the payload of `->paginate()`
  as-is (`data`, `current_page`, `last_page`, `per_page`, `total`, `from`, `to`), but nothing in
  `core` knows about Laravel.
- **Our own implementations.** Element Plus is a checklist of what an admin panel needs, not a
  dependency. Headless behaviour (dialog, dropdown, combobox, toast) leans on
  [Reka UI](https://reka-ui.com/); trees, drag-and-drop, date pickers and rich text come from
  focused third-party libraries.
- **Nothing client-specific in public.** Anything tied to a particular site stays out of this repo.

## Naming

| Thing          | Convention            | Example                      |
| -------------- | --------------------- | ---------------------------- |
| npm scope      | `@webx-ui`            | `@webx-ui/core`              |
| Component name | `Wx` + PascalCase     | `WxButton`                   |
| In templates   | kebab-case            | `<wx-button type="primary">` |
| CSS classes    | BEM with `wx-` prefix | `.wx-button--primary`        |
| CSS variables  | `--wx-` prefix        | `--wx-color-primary`         |

Next: [Installation](/guide/installation).
