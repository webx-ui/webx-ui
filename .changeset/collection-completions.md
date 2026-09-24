---
'@webx-ui/module-blocks': patch
---

The template editor of a block type completes `wx-collection` fields: `items`, `groups` and
`filter` after `$questions[`, and the keys every record and group has inside
`@foreach ($questions['items'] as $item)`.
