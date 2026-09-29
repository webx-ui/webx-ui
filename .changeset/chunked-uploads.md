---
'@webx-ui/module-admin': minor
'@webx-ui/php': minor
---

Chunked uploads in the panel's frame, for files larger than PHP and the web server let through in
one request. `webx-ui/module-admin` adds the `admin_uploads` table and four endpoints under
`/api/cms/uploads` (create or resume, `HEAD` for the offset, `PATCH` a piece at the server's
offset, `DELETE`), the `UploadPurposes` register a module names what an upload is for in, with
its permission, types and size, and `Uploads::claim()` to take a finished file. Abandoned uploads
go hourly with `webx:prune-uploads` after `webx-admin.uploads.ttl_hours`, and `webx:doctor`
checks the room on the disk. `@webx-ui/module-admin` adds `useChunkedUpload()` — pause, resume,
cancel, progress, speed and time left; halves the piece on a 413, follows the server's offset on a
409, waits out going offline and remembers unfinished uploads so a reload can continue them — and
an optional `send()` on the HTTP client for requests that are not JSON.
