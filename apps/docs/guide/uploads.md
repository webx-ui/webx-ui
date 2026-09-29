# Large uploads

A form uploads a picture in one request, and that is fine up to a few megabytes. A video is
not a picture: a 3 GB file runs into PHP's `upload_max_filesize`, the web server's body limit
and `max_execution_time` long before it runs into anything sensible, and a dropped connection
at 90 % starts it again from nothing.

`webx-ui/module-admin` sends such files **in pieces**, appended on the server to a file that is
whole the moment its last piece lands. A dropped connection, a laptop that went to sleep or a
closed tab resume from where the file stopped — the server knows how much it has, and the same
file chosen again finds its upload. Any module can use it; the catalogue's product videos are
the first to.

## The protocol

Four requests under the panel's API, behind the same sign-in as the rest of it:

```
POST   /api/cms/uploads        { name, size, type, fingerprint, purpose } → 201 { data: { id, offset, size, chunk_size } }
HEAD   /api/cms/uploads/{id}   → Upload-Offset: n
PATCH  /api/cms/uploads/{id}   the bytes, Upload-Offset: n → 204, Upload-Offset: n + length
DELETE /api/cms/uploads/{id}   → 204
```

- **The server holds the offset.** A piece has to start where the file ends; one that does not
  gets `409` with the real `Upload-Offset`, and the client carries on from there.
- **`chunk_size`** is the smaller of `webx-admin.uploads.chunk_mb` (8 MB) and 90 % of what
  `upload_max_filesize` and `post_max_size` allow. What nginx allows the server cannot know;
  the client halves its piece on a `413`, down to 256 KB, and keeps the smaller piece.
- **The fingerprint** is the file's name, size and date of change. A `POST` with the same
  fingerprint, purpose and administrator, before the session expires, returns the existing
  session and its offset — `200` rather than `201`. Another administrator never gets it.
- **Room on the disk** is checked when the session is created: a file larger than the free space
  is refused at once, not at 90 %.

The pieces go to `storage/app/uploads/{id}.part` on the local disk whatever the site's default
disk is: they are appended to, and a cloud disk has no append.

## A purpose, on the server

The browser names what a file is for, so the server needs a white list of what may be named. A
module registers its purpose from its provider, with the permission an upload is started
behind, the types it takes and the largest file:

```php
use WebxUi\Admin\Uploads\UploadPurposes;

$this->app->make(UploadPurposes::class)->register(
    'catalog.video',
    permission: 'catalog.manage',
    types: ['video/mp4', 'video/webm'],
    maxBytes: fn (): int => (int) config('webx-catalog.videos.max_size_mb') * 1024 * 1024,
);
```

The declared type is checked on creation, so that the wrong file is refused before a gigabyte of
it has crossed the wire. It is what the browser said, not what the file is: the module checks
the content once it has the file.

When the upload is done, the panel hands the id to the module's own endpoint, and the module
**claims** it:

```php
use WebxUi\Admin\Uploads\Uploads;

$file = app(Uploads::class)->claim($request->input('upload'), 'catalog.video', $request->user());

$file->moveTo(Storage::disk('public'), "catalog/{$product->id}/{$hash}.mp4");
```

`claim()` refuses an unfinished file, one uploaded for another purpose and — when the
administrator is passed — somebody else's. The session is gone once it returns; the file is the
module's to move (`moveTo`, streamed, because it may be gigabytes) or to throw away
(`discard`). A refusal is an `UploadRefused`, which renders itself as the same 404 or 422 the
upload endpoints answer with.

## Clearing up

An upload nobody has sent a piece to for `webx-admin.uploads.ttl_hours` (24) goes, with its
file, on the hourly `webx:prune-uploads` the package puts on the schedule. The same sweep takes
piece files that outlived their session — a claim whose consumer never moved the file.

`webx:doctor` checks, once a module has registered a purpose, that the directory takes a file
and that the disk holds at least the largest file anybody may send.

```php
// config/webx-admin.php
'uploads' => [
    'ttl_hours' => env('WEBX_UPLOADS_TTL_HOURS', 24),
    'chunk_mb' => env('WEBX_UPLOADS_CHUNK_MB', 8),
],
```

## `useChunkedUpload()`, in the panel

```ts
import { useChunkedUpload } from '@webx-ui/module-admin'

const upload = useChunkedUpload()

const id = await upload.start(file, 'catalog.video')
if (id !== null) await api.attachVideo(image, id)
```

| Member                              | What                                                                        |
| ----------------------------------- | --------------------------------------------------------------------------- |
| `start(file, purpose)`              | resolves with the upload's id when the file is whole, `null` when cancelled |
| `pause()` / `resume()` / `cancel()` | `resume()` asks the server where the file ends first; `cancel()` deletes it |
| `state`                             | `idle`, `uploading`, `paused`, `offline`, `done` or `failed`                |
| `progress`, `uploaded`, `total`     | 0 to 1, and the bytes behind it                                             |
| `speed`, `remaining`                | bytes a second over the last few seconds; seconds left, `null` until known  |
| `id`, `error`                       | the session; what failed, in the server's words when it said any            |

A pause keeps the promise of `start()` pending — it resolves once the upload is resumed and
finished. A failure rejects it, and `resume()` then tries again with a promise of its own.

**Offline and back.** The browser's `offline` event pauses the upload as `offline`, and `online`
resumes it. A piece lost to the network while the browser still claims to be online is sent
again three times, with a wait that doubles, before the upload fails.

**After a reload.** Unfinished uploads are remembered in `localStorage` by their fingerprint,
so that a page can say what it could continue:

```ts
import { unfinishedUploads, forgetUnfinishedUpload } from '@webx-ui/module-admin'

for (const left of unfinishedUploads('catalog.video')) {
  // "Continue uploading clip.mp4 (1.2 of 3.4 GB) — choose the same file"
  console.log(left.name, left.offset, left.size)
}
```

Choosing the same file again and calling `start()` finds the session on the server and sends
only what is missing. Nothing is remembered where the browser refuses site data, and the upload
works the same without it.
