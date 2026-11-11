---
'@webx-ui/module-inbox': minor
'@webx-ui/php': minor
---

Inbox panel: the field dialog has a «Show» section — always, if or unless some answers of a choice field above were given (`options.when` / `options.unless`). Only select, radio, checkbox and consent fields above are offered; a consent counts on its tick; rows are joined by "and", answers inside a row by "or". An answer the field above no longer has stays in the row as a red mark until it is closed, a field that is no longer above is marked, and the server's `condition.*` refusals are shown under the section. The list of fields says under each conditional field when it is asked («if Purpose = Agency meeting») and marks a row whose condition the server would refuse; a refused drag puts the order back. Words in ten languages, help for the dialog in en/ru/uk.
