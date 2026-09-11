---
'@webx-ui/core': patch
---

`WxTable` and the four pickers ship real types again.

Each of them builds slot names out of data — the table out of its column keys, the
pickers out of whatever slots they are handed — and a template that enumerates its own
slots makes the slot type depend on itself. TypeScript answers that circle by giving
up: the declarations carried `slots: any`, and in the table's case the props collapsed
to `any` as well, so nothing about `<wx-table>` was checked at all.

The slots are now declared instead of inferred. The table's are spelled out —
`cell-<key>` hands back the row, the value, the index and the column; `header-<key>`
the column; `summary-<key>` the summary line — and the pickers declare that they
forward whatever they are given. The type-check diagnostics that `vite-plugin-dts`
printed on every build are gone with them.
