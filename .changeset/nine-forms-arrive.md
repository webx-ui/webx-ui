---
'@webx-ui/core': minor
---

Form controls: `WxForm`, `WxFormItem`, `WxTextarea`, `WxCheckbox`, `WxCheckboxGroup`, `WxRadio`,
`WxRadioGroup`, `WxSwitch` and `WxInputNumber`.

`WxForm` takes the `errors` object from a Laravel 422 response as-is; each `WxFormItem` looks up its
own `name` and gives the control inside it the error state, the message and the `aria-describedby`
wiring without the control needing any props. `disabled` and `size` cascade form → item → control,
most specific winning.

Every control is built on a native input, so keyboard behaviour and form semantics come from the
browser. `WxInput` now takes part in the same contract.
