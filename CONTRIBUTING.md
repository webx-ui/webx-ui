# Contributing

Thanks for helping build WebX UI. This document covers the local setup, the conventions every
component follows, and how releases happen.

## Requirements

- Node.js 20.19+ (CI runs 22)
- pnpm — `corepack enable pnpm`

## Setup

```bash
pnpm install
pnpm build
```

| Command           | What it does                                            |
| ----------------- | ------------------------------------------------------- |
| `pnpm dev`        | Vite playground on http://localhost:5174                |
| `pnpm docs:dev`   | VitePress docs, live-linked to the package sources      |
| `pnpm test`       | Vitest (jsdom + Vue Test Utils)                         |
| `pnpm test:watch` | Vitest in watch mode                                    |
| `pnpm lint`       | ESLint over the whole workspace                         |
| `pnpm format`     | Prettier write                                          |
| `pnpm typecheck`  | `vue-tsc` / `tsc` in every package                      |
| `pnpm build`      | Builds `tokens`, `core` and `schema` into their `dist/` |

The docs and playground alias `@webx-ui/*` to the package **sources**, so changes to a component
show up immediately without rebuilding.

## Conventions

- Components are declared as `WxButton` (PascalCase, `Wx` prefix) and always shown in templates as
  `<wx-button>` — including in the docs.
- CSS classes are BEM with a `wx-` prefix: `.wx-button`, `.wx-button--primary`, `.wx-button__label`.
  State classes are `is-*` (`is-disabled`, `is-focused`).
- `<script setup lang="ts">` with typed `defineProps` / `defineEmits`, and `defineModel` for
  `v-model`.
- Styles are `scoped` and use **only** token variables (`var(--wx-*)`). A literal colour in a
  component is a bug.
- Components never fetch data. Props in, events out.
- Layout responds to the **container**, not the window: `container-type: inline-size` and
  `@container` queries, so a component in a 380px drawer behaves like a narrow one. Where the
  decision is behaviour rather than layout, measure with `useElementWidth`.
- jsdom does not lay anything out, so a test cannot catch a visual bug. Anything about size,
  position, overflow or a shadow is checked in a browser against `pnpm docs:dev` — measured with
  `getBoundingClientRect` and `getComputedStyle`, not judged by eye.
- Each component lives in `packages/core/src/components/<Name>/` and ships four files:

  ```
  Button.vue        component
  types.ts          props, emits, public types
  Button.test.ts    Vitest + Vue Test Utils
  index.ts          named exports
  ```

  plus a page in `apps/docs/components/` with a live demo from
  `apps/docs/components/demos/`.

- Export the component from `packages/core/src/components/index.ts` — the plugin picks up
  everything named `Wx*` automatically.

## Tokens

`packages/tokens/src/tokens.json` is the source of truth. Values written as
`{primitive.color.blue.600}` become `var(--wx-color-blue-600)` in the generated CSS.

```bash
pnpm --filter @webx-ui/tokens generate
```

regenerates `dist/tokens.css` and the typed `src/generated/tokens.ts`. Commit the generated TS file
— CI fails if it is stale.

## PHP packages

The Laravel side lives in `php/packages/*` and is published to Packagist as `webx-ui/*`. It has
its own toolchain and its own CI job:

```bash
cd php
composer install
composer lint && composer analyse && composer test
```

All PHP packages share one version, carried by the private `@webx-ui/php` package in
`php/package.json` — so a change under `php/packages/` takes a changeset on **`@webx-ui/php`**.
There is nothing to publish: the release workflow tags the monorepo, mirrors every package into
its own repository, and Packagist picks the tag up. See
[`php/README.md`](php/README.md) and
[`docs/architecture/WEBX_UI_PHP_RELEASE.md`](docs/architecture/WEBX_UI_PHP_RELEASE.md).

## Pull requests

`main` is closed by a ruleset with a **merge queue**: changes land through PRs, no approving review
is required, and the required check is `Lint, typecheck, test, build` — an aggregate of the
parallel jobs in `ci.yml` (when it fails, look at those jobs). Once the PR's own CI is green, put it
in the queue; GitHub builds `main` plus the PR, runs CI once more and merges. There is no need to
bring the branch up to date by hand.

`gh pr merge` does not enqueue (it tries to enable auto-merge, which the repository does not
allow). Use the button on the PR, or:

```bash
gh api graphql -f query='mutation{enqueuePullRequest(input:{pullRequestId:"<id>"}){mergeQueueEntry{state position}}}'
```

where `<id>` is `gh pr view <number> --json id -q .id`.

Add a changeset to any PR that changes a published package:

```bash
pnpm changeset
```

Pick the packages, pick the bump (`patch` / `minor` / `major` — pre-1.0 we stay on `patch` and
`minor`), describe the change in one line. Docs-only or tooling-only PRs do not need one.

## Releases

1. Merging a PR with changesets makes the release workflow open a **"chore: version packages"** PR
   with the version bumps and changelogs.
2. Merging that PR publishes the packages to npm, tags the PHP packages `php-v<version>` and runs
   `php-split.yml`, which mirrors each composer package into its own repository for Packagist. If
   the npm job fails after the tag, re-run only the mirror:
   `gh workflow run php-split.yml --ref main -f tag=v<version>`.

Publishing uses **npm Trusted Publishing (OIDC)** from GitHub Actions — there is no `NPM_TOKEN` in
the repository secrets. Trusted Publishing cannot create a name, so the first version of each new
package is published manually by the owner — from a worktree of the `changeset-release/main`
branch, where every `workspace:^` range already resolves to the versions it will ship with (check
the ranges in the packed tarball before publishing). After that, Trusted Publishing is configured
on npmjs.com against this repo's `release.yml`.

A branch can be shown on a live site without a release: `gh workflow run release.yml --ref <branch>`
publishes a snapshot under the `next` dist-tag. See
[`docs/architecture/WEBX_UI_RELEASE_SPEED.md`](docs/architecture/WEBX_UI_RELEASE_SPEED.md).

## Documentation

The site is VitePress in `apps/docs`, deployed to GitHub Pages by `docs.yml` on every push to
`main`. The site is served from a subpath —
https://webx-ui.github.io/webx-ui/ with `base: '/webx-ui/'`.

A component page documents props, events, slots, accessibility notes, and starts with a live demo.
