---
'@webx-ui/core': minor
---

`useElementWidth` — the measurement `useResponsiveShell` was built on, now on its own and exported.

A layout that answers to its own box rather than to the window needs one number: how wide that box
is. The shell composable had it inside; a screen with columns of its own needs the same thing for a
different element — a reading pane deserves its width measured against what is left after the
sidebar, not against the viewport that still counts it.

```ts
const el = ref<HTMLElement | null>(null)
const width = useElementWidth(el)
```

`0` until it is measured, as in `useResponsiveShell`: treat it as "assume the roomy case", since the
real number arrives before paint. Pass nothing to measure the page itself.
