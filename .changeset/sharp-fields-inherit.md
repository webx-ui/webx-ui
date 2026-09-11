---
'@webx-ui/core': patch
---

`class` and `style` on a form control now land on the control

Every control sets `inheritAttrs: false` and hands `$attrs` to the element inside
it, so that `placeholder`, `autocomplete` and the ARIA attributes reach the real
input. Taken literally that sent `class` and `style` there too, and both were then
in the wrong place:

- `class="w-60"` on a `<wx-select>` is asking for a narrower select, and the select
  is the wrapper, not its input.
- A parent's scoped CSS could not reach it. Scoped styles carry an attribute
  stamped on a child component's root, so a class landing three elements deep
  matched nothing — silently.
- Where `$attrs` went to an element that is only sometimes rendered — the search
  field of a filterable `WxSelect` — the class disappeared altogether.

`class` and `style` now go on the root; everything else still goes on the control.
The split is exported as `useControlAttrs` for anyone building a control of their
own.
