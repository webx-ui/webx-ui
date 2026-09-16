---
'@webx-ui/php': patch
---

A compiled block template is named by its content as well as its version. The slug and the
version number alone are not unique across databases: a test suite on an in-memory database
and the developer's own site compile into one directory, each with a `hero` at version 1 and
a different template — and whichever compiled first served both, so a page printed the other
site's block, or nothing. The file name now carries a hash of the template; a new version is
still a new file, and nothing is ever invalidated.
