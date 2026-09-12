<script setup>
import ModalDemo from '../components/demos/ModalDemo.vue'
</script>

# Dialogs from code

Some dialogs do not belong in a template. "Are you sure?" belongs in the middle of the function
that deletes something, and a picker belongs wherever a field needs filling — not declared once per
screen, wired to a boolean, and answered through an event three components away.

`openModal` mounts a component outside the app and gives back a promise for its answer.

```ts
const sure = await confirm('Delete this product?')
if (sure) await api.delete(product)
```

<ModalDemo />

## A browser of your own

Any component can be opened this way. It is a plain component: it takes props and emits events, and
the function that opens it turns one of those events into the answer.

```vue
<!-- ProductBrowser.vue -->
<script setup lang="ts">
import { WxDialog, useModal } from '@webx-ui/core'

defineProps<{ exclude?: number[] }>()
const emit = defineEmits<{ select: [product: Product] }>()

/* `open` is the modal's own, so the panel can play its closing animation. */
const { open } = useModal()
</script>

<template>
  <wx-dialog v-model:open="open" title="Find a product">
    <button v-for="product in found" :key="product.id" @click="emit('select', product)">
      {{ product.title }}
    </button>
  </wx-dialog>
</template>
```

```ts
// productBrowser.ts
import { createModal } from '@webx-ui/core'
import ProductBrowser from './ProductBrowser.vue'

export const productBrowser = createModal<Product, { exclude?: number[] }>(ProductBrowser, {
  resolveOn: 'select',
})
```

```ts
// anywhere
const product = await productBrowser({ exclude: chosen.value.map((item) => item.id) })
if (product) chosen.value.push(product)
```

`createModal` is the shape worth reaching for: one file names the component, its answer's type and
the event that carries it, and every call site is a single `await`.

[`openImageEditor`](/components/image-editor#from-code) is that file, shipped: `WxImageEditor` in a
dialog, resolving on its `save`. It is worth reading before writing one of your own — including the
half that is not the opener, which is a component that works both ways, with `:footer="false"` and
an exposed `apply()` for the panel to drive.

## Dismissed is not an error

The promise resolves with `undefined` when the panel is closed without an answer — by the ✕, by
escape, by a click outside. It does not reject.

A cancel that throws turns every call site into a `try` block, and one forgotten `catch` into an
unhandled rejection in somebody's console. `if (!product) return` reads better and is harder to get
wrong.

The promise does reject if the component itself throws, which is a real error and should reach your
error handler rather than leave the caller awaiting for ever.

## What the component may say

| The component…                           | …and the opener                        |
| ---------------------------------------- | -------------------------------------- |
| emits the event named by `resolveOn`     | settles the promise with its payload   |
| emits one named by `dismissOn`           | settles it with `undefined`            |
| calls `resolve(value)` from `useModal()` | the same, without an event             |
| calls `dismiss()`                        | the same as a dismissal                |
| sets `open` from `useModal()` to `false` | the same as a dismissal                |
| declares an `open` prop                  | it is passed, and `v-model:open` works |

The defaults are `resolveOn: 'resolve'` and `dismissOn: ['cancel', 'close']` — and `close` is what
`WxDialog` emits when it is dismissed, so a component whose root is a dialog needs nothing at all
for the ✕ and escape to work.

## Closing, and then going away

The promise settles the moment the answer is known; the node is taken away 250ms later, so the
panel plays its closing animation instead of being cut off mid-fade. Nothing waits on that — the
code after the `await` runs immediately.

`duration` changes the wait. `0` is right for a component with no animation, and for tests.

## It renders in your app

A component mounted outside the app tree would normally lose everything the app carries: plugins,
`provide`, the router, the store, the translations. It doesn't here — the modal is given the app's
own context, so injection works exactly as it would in a template.

`app.use(WebxUI)` arranges that. Importing components one at a time instead means saying which app
to use, once:

```ts
import { connectModals } from '@webx-ui/core'

const app = createApp(App)
connectModals(app)
app.mount('#app')
```

## Closing it from outside

The promise carries a `close`, for a modal that should not outlive something else — a route change,
a socket that dropped, a timeout:

```ts
const dialog = productBrowser()
router.afterEach(() => dialog.close())

const product = await dialog
```

## confirm

```ts
await confirm('Delete this product?')
await confirm({
  title: 'Delete this product?',
  message: 'It will be removed from every list it is in.',
  confirmText: 'Delete',
  tone: 'danger',
})
```

Answers `true` or `false`, never rejects.

| Option        | Type               | Default           | Description                                |
| ------------- | ------------------ | ----------------- | ------------------------------------------ |
| `title`       | `string`           | `'Are you sure?'` | The question, as the heading               |
| `message`     | `string`           | —                 | What is at stake                           |
| `confirmText` | `string`           | `'Confirm'`       | The button that means yes                  |
| `cancelText`  | `string`           | `'Cancel'`        | The button that means no                   |
| `tone`        | `ButtonType`       | `'primary'`       | Colour of the yes; `danger` if it destroys |
| `width`       | `number \| string` | `420`             | Width of the panel                         |

## API

```ts
openModal<T>(component, options?): ModalPromise<T>
createModal<T, P>(component, defaults?): (props?: P, options?) => ModalPromise<T>
useModal<T>(): { isModal, open, resolve, dismiss }
confirm(message?, options?): Promise<boolean>
connectModals(app): void
```

| Option       | Type                 | Default               | Description                             |
| ------------ | -------------------- | --------------------- | --------------------------------------- |
| `props`      | `object`             | —                     | Props for the component                 |
| `slots`      | `object`             | —                     | Slots, as render functions              |
| `resolveOn`  | `string \| string[]` | `'resolve'`           | Events that answer with their payload   |
| `dismissOn`  | `string \| string[]` | `['cancel', 'close']` | Events that answer with nothing         |
| `container`  | `HTMLElement`        | `document.body`       | Where to mount                          |
| `appContext` | `AppContext`         | the connected app     | Which app it renders in                 |
| `duration`   | `number`             | `250`                 | How long the closing animation is given |

## One thing it is not

This is not a way to build a screen. A modal opened from code is a question with an answer — a
confirmation, a picker, a small form. Anything the user can come back to, link to or reload belongs
in the page, with a route and a template.
