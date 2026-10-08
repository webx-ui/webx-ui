# webx-ui/module-media

The file manager of a [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: a tree of
folders, the files in them, uploads, search, and an image editor — on any disk Laravel can
address.

Its front end is [`@webx-ui/module-media`](https://www.npmjs.com/package/@webx-ui/module-media).

**Status: the server half is complete.** Folders, files, uploads, previews and image editing
answer; the panel's front end is [`@webx-ui/module-media`](https://www.npmjs.com/package/@webx-ui/module-media).

## Requirements

- PHP 8.4+
- Laravel 13
- `webx-ui/module-admin`, `webx-ui/localization`, `webx-ui/mcp`, `webx-ui/nested-set`
- `intervention/image` 3 (GD or Imagick)

## Install

```bash
composer require webx-ui/module-media
```

The section appears in the panel's manifest on its own. Publish the configuration to change
anything in it:

```bash
php artisan vendor:publish --tag=webx-media-config
```

## What it owns, and what it does not

The **library** — folders, files, and what can be done to a file — is this module's.

An **entity's attachments** are not. Attaching a file to a product or an article copies it into
that entity's own media, so ten thousand product photographs never enter a tree an editor has to
browse, and deleting a file in the library cannot break a product card. The price is duplicated
bytes and no "where used", and it is paid knowingly.

`alt` and `title` are not stored here either: one image used by two articles needs two captions,
so they belong to the entity that uses it, beside the reference.

## Fields on a screen

Four field types are registered for [described screens](https://webx-ui.github.io/webx-ui/guide/screens),
matching the four the front end draws:

| Type         | Stored                    | Read back as                   |
| ------------ | ------------------------- | ------------------------------ |
| `wx-media`   | `{ path, alt, title }`    | the same, plus the file's keys |
| `wx-gallery` | a list of those, in order | a list, each resolved          |
| `wx-file`    | `{ path, alt, title }`    | the same, plus the file's keys |
| `wx-files`   | a list of those, in order | a list, each resolved          |

**Only the key and the captions are stored.** The address never is — a library that moves to
another disk would otherwise mean rewriting everything already saved. On read each value is
resolved with `url` and `thumb`, `name`, `extension`, `mime`, `size`, and `width` and `height` for
a picture: the whole set, because a Blade template has nothing to ask the library with, and
`width` and `height` are what keep a page from jumping. Whole lists are looked up in one query.

`props.accept` is checked against the row that is fetched for the resolve anyway, so a `.zip`
cannot be posted into a gallery by hand. A key whose file has since been deleted is **kept**: it
resolves to `url: null` and the field draws a broken card, because one missing picture out of
twenty must not be the reason a page cannot be saved.

## API

Everything lives under the panel's API path, behind the panel session and a permission.

|                                              |                                                                                 |
| -------------------------------------------- | ------------------------------------------------------------------------------- |
| `GET directories`                            | the whole tree with file counts                                                 |
| `POST/PATCH directories`, `PATCH …/move`     | create, rename, move                                                            |
| `DELETE directories/{id}`                    | refuses a folder that holds anything (409 with counts) until `?force=1`         |
| `GET files`                                  | paginated, `q`, `type`, `sort`, `per_page`                                      |
| `POST files`                                 | multi-file upload; the same bytes in the same folder answer `duplicate`         |
| `POST files/chunked`                         | `{ directory_id, upload }` — a finished chunked upload into the library         |
| `PATCH files/{id}`                           | rename — the key on the disk never changes                                      |
| `POST files/move`, `DELETE files`            | in batches                                                                      |
| `GET files/{id}/thumb?w=&h=&fit=`            | cuts the variant once, then redirects to it                                     |
| `POST files/{id}/edit`                       | crop, rotate, flip, resize — applied to the original, written over the same key |
| `POST files/{id}/copy`, `…/restore-original` | a second file; the picture as it arrived                                        |

The editor takes **operations rather than a finished picture**: the canvas in the browser works
on a preview, and what it could send back is smaller than the original.

## Optimizing

A JPEG, PNG or still WebP is turned the right way up, stripped of its metadata, scaled down to
`max_side` and saved as a WebP on its way in; **Optimize** in the panel (and `optimize_images`)
runs the pictures already there through the same steps, over the same key and in the same format.
The steps are `webx-media.optimize.steps` — add a class implementing
`WebxUi\Media\Images\Optimizing\OptimizeStep` to add your own.

### Convert to WebP

Off unless asked for — the checkbox in the **Optimize** dialog, `convert: true` of
`media_optimize_images` (honours `dry_run`), or `php artisan webx:media:webp [--dry-run]
[--directory=] [--id=]`. A still JPEG or PNG (and a HEIC, where Imagick is built with libheif)
becomes a WebP when that is smaller; transparency is kept, GIF, SVG and animated pictures are left
alone. Per file, so that a failure leaves the site as it was:

1. the WebP is written beside the old file under the same uuid — `media/9f/2a/<uuid>.webp`;
2. in one transaction: the row takes the new key (path, extension, MIME, file name, hash, size,
   dimensions), the old key is kept in `media_aliases`, and every reference to the old basename
   is rewritten — every text and JSON column of every table, **without** the caps the delete
   check uses, history and versions included (`usage.rewrite_ignore` names what is skipped);
3. then the old bytes and old previews go, and the rendered caches (settings, block regions,
   menus) are let go of through the `MediaKeysRewritten` event.

Why history is rewritten: restoring an old version must not bring back a key whose bytes are
gone. The alias covers what no rewrite reaches — search engines, CDNs, e-mails, links on other
sites: the old public address (`/storage/media/…/<uuid>.jpg`, any `?v=`) answers **301** to the
new one for a disk the site serves itself, and `files/by-path` and the field lookups find the file
by either key. The old bytes are not kept for a grace period: a 301 serves every old link, and the
space is the point.

The editor's kept original (`original_path`) stays as it arrived, in its own format — it is the
picture before anything was done to it. **Restore original** re-encodes it into the file's
current format, so the key never lies about what it holds.

Each file is reported as `converted`, `unchanged` (WebP would not be smaller), `skipped` or
`missing`, with bytes before and after and the number of references rewritten.

A module that keeps keys where the schema cannot show them implements
`WebxUi\Media\Usage\UsageRewriter` beside `UsageSource` on its tagged source; it is called inside
the same transaction.

## MCP

`list_directories`, `list_files`, `search_files`, `get_file`, `create_directory`, `delete_directory`,
`rename_file`, `move_files`, `upload_from_url`, `optimize_images`, `delete_files`. Every mutating tool takes
`dry_run`; a refusal is an MCP error (`ToolFailure`), not an `ok: false` answer.

- `delete_directory` deletes an **empty** folder only: recursive deletion is the one operation
  here that a mistaken call cannot take back, and an agent cannot ask the question the panel asks
  first.
- `delete_files` refuses a file the site still uses and says where, unless `force: true`.
  `WebxUi\Media\Usage\MediaUsage` asks every source tagged `MediaUsage::TAG`; the one that ships,
  `DatabaseUsage`, reads the schema — foreign keys into `media_files`, and the last segment of
  the file's key (a uuid) inside text and JSON columns. History, logs and queues are skipped
  (`webx-media.usage.ignore`).
- `upload_from_url` fetches only from the public internet: the host is resolved, every address
  is checked (loopback, private, link-local, reserved, in both families and inside IPv6), the
  connection is pinned to the checked address, and redirects are followed by hand and checked
  the same way. The type is sniffed from the bytes, the name gets an extension from the upload
  white list, and the panel's upload rules apply. `webx-media.remote.allow_hosts` names hosts on
  the site's own network that may be fetched anyway.

## Configuration

`config/webx-media.php`. The disk is the module's own setting rather than the application's
default — a site whose storage is `local` may still want its library on S3, and a client
installation often has nothing but `public`:

```dotenv
WEBX_MEDIA_DISK=s3
WEBX_MEDIA_PREFIX=media
```

Everything goes through Laravel's filesystem, so a remote disk behaves like a local one. A disk
with a configured `url` serves files directly; a private bucket gets temporary signed addresses.

An S3 disk needs its adapter, which Laravel does not ship and this module deliberately does not
require — most installations never point at a bucket, and nobody should carry the AWS SDK to
store files in `public`:

```
composer require league/flysystem-aws-s3-v3
```

Without it the disk resolves and then fails on first use with
`Class "League\Flysystem\AwsS3V3\PortableVisibilityConverter" not found`, which names Flysystem
rather than the missing package.

Uploads are limited by size and by a white list of types, and images are refused above
`image.max_pixels` before they are decoded — a small file can still be a very large picture.

## Chunked uploads

The panel uploads into Files a piece at a time through module-admin's protocol (`POST uploads`,
`PATCH uploads/{id}`, …), under the purpose `media.library` (`FileStore::UPLOAD_PURPOSE`):
`media.upload` or `media.manage` may start one, the extensions are `upload.extensions` (checked
against the file name, so a HEIC a browser declares no type for still goes) and the size is
`upload.max_size` — both refused when the session is created. `POST files/chunked` then claims the
finished file and runs it through exactly what `POST files` does: the same rules on the content,
now read from the bytes, the same pipeline, the same deduplication, the same answer. PHP's
`upload_max_filesize` and `post_max_size` only bound a piece (`webx-admin.uploads.chunk_mb`), not
the file; pieces of abandoned uploads are swept by `webx:prune-uploads`.

## Permissions

`media.view`, `media.upload`, `media.manage`. Uploading is separate from managing on purpose: an
editor who may add a picture to an article is not necessarily someone who may delete a folder
full of them.

## Languages

Ten shipped: en, ru, uk, de, pl, fr, es, it, pt, tr. English is the fallback, and it is laid
_under_ the chosen language line by line, so a half-translated group shows what it has and
English for the rest.

Only English, Russian and Ukrainian have been read by a speaker; the other seven are machine
translations. A correction from someone who speaks the language is welcome — they are plain PHP
arrays in `lang/`.

## Licence

MIT.
