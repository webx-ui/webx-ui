---
'@webx-ui/php': minor
---

`press()` and every `wx-collection` source stand on `RecordQuery`: `PressQuery` extends it (outlets or articles as a step, so `press()->models()` is new), the FAQ gets `FaqQuery`, and a source's `items()` is `(new XQuery)->selected($selection)->locale($locale)->get()`. **Breaking for a third-party source:** `Selection::apply()` is removed — use `RecordQuery::selected()`. `RecordQuery` now refuses categories on a model without them instead of ignoring them, and its `newQuery()`, `order()` and `narrow()` see `Builder<covariant TModel>`.
