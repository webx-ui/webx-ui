---
'@webx-ui/php': patch
---

`module-admin`: a query budget for tests — `WebxUi\Admin\Testing\CountsQueries` (`assertQueriesWithin()`, `assertNoRepeatedQueries()`, `recordQueries()`) and `QueryTally`, which counts the statements a request or a render ran and, on a broken budget, lists them with the repeated ones called out — the shape an N+1 takes. No behaviour of the panel or the site changes.
