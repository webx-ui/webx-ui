---
'@webx-ui/core': minor
---

Dialogs from code: `openModal`, `createModal`, `useModal` and `confirm`

Some dialogs do not belong in a template. "Are you sure?" belongs in the middle of the function that
deletes something, and a picker belongs wherever a field needs filling — not declared once per
screen, wired to a boolean, and answered through an event three components away.

```ts
if (await confirm('Delete this product?')) await api.delete(product)

export const productBrowser = createModal<Product, { exclude?: number[] }>(ProductBrowser, {
  resolveOn: 'select',
})

const product = await productBrowser({ exclude: chosen.value.map((item) => item.id) })
```

- **The component stays a plain component.** It takes props and emits events; the opener turns one
  of those events into the answer, so the same file works in a template too. `useModal()` is there
  for a component that wants to close itself or for a button deep inside one — outside a modal it
  answers all the same, with calls that do nothing.
- **It renders in your app.** A component mounted outside the tree would lose the plugins, the
  provides, the router, the store and the translations; this one is given the app's own context, so
  injection works as it would in a template. `app.use(WebxUI)` arranges it; `connectModals(app)` for
  anyone importing components one at a time.
- **Dismissed is not an error.** The promise resolves with `undefined` when the panel is closed
  without an answer, and rejects only when the component itself throws — a cancel that throws turns
  every call site into a `try` block and one forgotten `catch` into an unhandled rejection.
- **It closes before it goes away.** The promise settles the moment the answer is known and the node
  is taken 250ms later, so the panel plays its closing animation rather than being cut off mid-fade.
- `confirm()` answers `true` or `false`, with a `danger` tone for anything that destroys something.
