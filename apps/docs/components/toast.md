<script setup>
import ToastDemo from '../components/demos/ToastDemo.vue'
</script>

# Toast

A toast is what happened while you were doing something else: a save that went through, a request
that did not. `useToast()` raises them; one `WxToaster` on the page shows them.

<ToastDemo />

## Usage

Put the toaster once, at the root of the app:

```vue
<!-- App.vue -->
<template>
  <router-view />
  <wx-toaster placement="bottom-end" />
</template>
```

Then raise one from anywhere:

```ts
import { useToast } from '@webx-ui/core'

const toast = useToast()

toast.success('Order placed')
toast.danger({ title: 'Could not save', description: response.message })
```

## The queue is not in a component

That is the point. A toast almost always comes from somewhere with no view of its own — an HTTP
interceptor, a store, a `catch` block — and asking those places to find a component instance first
is how a notification system ends up being threaded through props.

So the queue is module state, and the same object is importable outside a component entirely:

```ts
// http.ts
import { toast } from '@webx-ui/core'

axios.interceptors.response.use(undefined, (error) => {
  if (error.response?.status >= 500) toast.danger('Something went wrong on our side.')
  return Promise.reject(error)
})
```

One queue per bundle, one toaster to show it. Two toasters means every toast twice.

## Message or notification

There is one component here, not two. The difference between what other libraries call a _message_
and a _notification_ is a title and a corner:

```ts
toast('Saved') //                                    one line, a message
toast.danger({ title: 'Upload failed', description: 'The file was larger than 10 MB.' })
```

Messages across the top, notifications in the corner — pick one placement for the app and stay
with it, so a reader learns where to look:

```vue
<wx-toaster placement="top-center" :width="320" />
```

## Errors stay

`toast.danger` sets `duration: 0`, which means it waits to be dismissed. An error that vanishes on
its own after four seconds is an error nobody read — and it is usually the one worth reading. Pass
a `duration` to override that if you really want it.

Everything else leaves after five seconds.

## One thing to do about it

```ts
toast('Order WX-4100 deleted', {
  action: { label: 'Undo', onClick: () => restore(order) },
})
```

One action, not a row of them. A toast the reader has to read and choose within four seconds is a
dialog wearing the wrong clothes.

## API

```ts
const toast = useToast()

toast(message | options, options?) // ToastHandle
toast.success(…)
toast.warning(…)
toast.danger(…)  // duration 0
toast.info(…)
toast.dismiss(id)
toast.clear()
```

Every call returns `{ id, dismiss }`, so a toast raised at the start of a long job can be taken
down when it ends.

### Options

| Option        | Type                                                        | Default     | Description                                 |
| ------------- | ----------------------------------------------------------- | ----------- | ------------------------------------------- |
| `title`       | `string`                                                    | —           | A headline; without one it is a single line |
| `description` | `string`                                                    | —           | The message                                 |
| `type`        | `'default' \| 'success' \| 'warning' \| 'danger' \| 'info'` | `'default'` | Colour and glyph                            |
| `duration`    | `number`                                                    | `5000`      | Milliseconds; `0` waits to be dismissed     |
| `closable`    | `boolean`                                                   | `true`      | Adds a ×                                    |
| `icon`        | `IconName \| false`                                         | —           | Instead of the one the type picks           |
| `action`      | `{ label, onClick }`                                        | —           | One thing to do about it                    |

## Toaster

| Prop        | Type               | Default           | Description                        |
| ----------- | ------------------ | ----------------- | ---------------------------------- |
| `placement` | `ToasterPlacement` | `'bottom-end'`    | Which corner or edge they stack in |
| `width`     | `number \| string` | `380`             | Width of a toast                   |
| `label`     | `string`           | `'Notifications'` | Name of the region                 |
| `max`       | `number`           | `5`               | How many at once; the rest wait    |
| `hotkey`    | `string[]`         | `['F8']`          | Key that jumps to the toasts       |

## Accessibility

The region is a live region with a name, and each toast is announced when it appears — `polite`
for most, `assertive` for errors, which interrupts. `F8` moves focus into the toasts so a keyboard
can reach an action without hunting. A toast carrying an action should not disappear on a timer;
give it `duration: 0`.
