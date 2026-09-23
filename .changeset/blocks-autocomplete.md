---
'@webx-ui/module-blocks': minor
'@webx-ui/core': patch
---

The block editor suggests as you type: fields after `{{` and `$` in the template, the keys of a picked file and of a repeater item, classes from the styles in `class="…"`, Blade directives after `@`; classes from the template after `.` in the styles; node keys and registered types in the fields. `WxCodeEditor` styles an autocompletion list passed through `extensions`, and its tooltips stand on `--wx-bg-surface` instead of the dialog backdrop colour.
