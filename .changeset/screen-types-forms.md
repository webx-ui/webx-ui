---
'@webx-ui/schema': minor
'@webx-ui/php': minor
---

Screens and block schemas can use every form control of the core. The registry now knows
`wx-checkbox-group`, `wx-segmented`, `wx-slider`, `wx-rate`, `wx-time-picker`,
`wx-date-time-picker`, `wx-date-range-picker`, `wx-tags-input`, `wx-autocomplete`,
`wx-icon-picker`, `wx-code-editor`, `wx-cascader`, `wx-tree-select` and `wx-transfer`, plus
`wx-heading` for display. `module-admin` registers a field type for each on the server — rules
that check options, bounds, date formats and lists, and `store()` that casts and keeps an emptied
list or date as `null`. `wx-date-time-picker` always writes the offset and is kept as ISO 8601 in
the application's timezone. The agent catalogue and the block help list them too.
