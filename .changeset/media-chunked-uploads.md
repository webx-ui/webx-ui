---
'@webx-ui/module-media': minor
'@webx-ui/php': patch
---

Uploads into Files go a piece at a time through the panel's chunked protocol — the page, the
picker and every media field. Each file has its own bar; a dropped connection is retried with a
growing wait from the offset the server holds, an offline browser carries on by itself, the same
file chosen again after a reload continues, and cancelling throws the server's pieces away. Files
can be dropped onto the list. On the server, the purpose `media.library` and
`POST media/files/chunked` hand the finished file to the same rules and pipeline as a multipart
upload; an upload purpose in module-admin can now name extensions as well as MIME types.
