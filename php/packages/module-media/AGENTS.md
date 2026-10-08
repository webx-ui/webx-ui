# webx-ui/module-media

The file library of the panel: a tree of folders, the files in them, uploads, search, thumbnails
and an image editor, on any disk Laravel's filesystem can address. The section «Files» of the
panel and the MCP tools `media_*` edit it; the fields `wx-media`, `wx-gallery`, `wx-file` and
`wx-files` let any screen or block point at a file. The folder tree is `webx-ui/nested-set`, the
section, screens and permissions are `webx-ui/module-admin`, the tools `webx-ui/mcp` — read their
guides when the question is about one of those. An entity's own attachments are not the library's.

## What it owns

- **Tables** `media_directories` (`WebxUi\Media\Models\MediaDirectory`, a nested set; the
  migration creates the root folder «Library») and `media_files` (`WebxUi\Media\Models\MediaFile`).
  A file row remembers its own `disk` and `path`, plus `original_path` once it has been edited.
- **Field types** `wx-media`, `wx-gallery`, `wx-file`, `wx-files` for described screens. They
  store only `{ path, alt, title }`; the address, `thumb`, size and dimensions are resolved on
  read. `alt` and `title` live in the value, not in the library.
- **Asset addresses**: binds `WebxUi\Admin\Contracts\AssetUrls` to `LibraryUrls`, which is how
  pictures inside a `wx-rich-text` value find their address.
- **API** under `{webx-admin.api_path}/media` (`directories`, `files`, `files/chunked`,
  `files/by-path`, `files/{file}/thumb`, `files/{file}/source`, `files/{file}/edit`, `…/copy`,
  `…/restore-original`, `files/move`, `files/delete`); route names `webx.media.*`.
- **Convert to WebP** (`convert: true` of `media_optimize_images`, `webx:media:webp`): a new key
  with the same uuid, every reference rewritten (history included, `usage.rewrite_ignore`),
  the old key kept in `media_aliases` — its public address 301s to the new one. Off by default.
- **Chunked uploads**: the upload purpose `media.library` with module-admin's `uploads`
  protocol — the panel sends every file a piece at a time and `files/chunked` hands the finished
  one to the library through the same rules and pipeline as `POST files`.
- **Permissions** `media.view`, `media.upload`, `media.manage` — uploading is separate from
  managing on purpose.
- **MCP** tools `media_list_directories`, `media_list_files`, `media_search_files`,
  `media_get_file`, `media_create_directory`, `media_delete_directory` (empty folders only),
  `media_rename_file`, `media_move_files`, `media_upload_from_url` (public addresses only),
  `media_optimize_images`, `media_delete_files`. Scopes `media:read`, `media:write`. A refusal is
  an MCP error, not an `ok: false` answer.
- Also registered: the panel section `media` (group `system`), demo files (`resources/demo`), and
  audit checks `media.missing_file` and `media.heavy` when `webx-ui/module-audit` is installed.

## Change it without forking

| You want                                  | Do this                                                                                           |
| ----------------------------------------- | ------------------------------------------------------------------------------------------------- |
| The library on another disk (S3 and such) | `WEBX_MEDIA_DISK=s3`; for S3 also `composer require league/flysystem-aws-s3-v3`                   |
| Share a bucket with something else        | `WEBX_MEDIA_PREFIX=…` — the directory every new key starts with                                   |
| Old JPEG and PNG pictures as WebP         | **Optimize** → «Convert to WebP», or `php artisan webx:media:webp --dry-run` first                |
| Bigger or smaller uploads                 | `WEBX_MEDIA_MAX_SIZE` (kilobytes); `upload.max_files` in the published config                     |
| Larger pieces of a chunked upload         | `webx-admin.uploads.chunk_mb`; PHP's own limits only have to fit one piece                        |
| Allow another file type                   | add the extension to `upload.extensions` in `config/webx-media.php`                               |
| Imagick instead of GD, other JPEG quality | `WEBX_MEDIA_IMAGE_DRIVER=imagick`; `image.quality`, `image.max_pixels` in the config              |
| Other thumbnail sizes                     | `thumbs.widths`, `thumbs.fits` in the config                                                      |
| Signed addresses that live longer         | `temporary_url_ttl` (seconds) — used only for a disk without a `url`                              |
| Previews of files deleted long ago        | audit `media.orphan_thumbs` and its fix, or `php artisan webx:media:prune-thumbs --dry-run` first |
| Edit the config at all                    | `php artisan vendor:publish --tag=webx-media-config`, keep only the keys you change               |
| Other words in the panel                  | `php artisan vendor:publish --tag=webx-media-lang`                                                |
| A picture or file field on a screen       | a field of type `wx-media`, `wx-gallery`, `wx-file` or `wx-files`; `props.accept` narrows         |

The disk and prefix apply to new uploads: every row keeps the disk and key it was written with,
so switching `WEBX_MEDIA_DISK` does not move or break what is already there — moving old files is
a migration of your own, bytes and rows together.

## Do not

- Do not edit anything in `vendor/webx-ui/module-media`, and do not copy the package into the
  site. Every row above is the supported way; if none fits, the package is missing a seam — say
  so instead of working around it.
- Do not store a file's URL in a field or a template: addresses depend on the disk, signed
  addresses expire, and a cache-busting version changes after an edit. Store the `path` the field
  gives you and let it resolve `url` and `thumb` on read.
- Do not write files into the disk by hand or insert `media_files` rows with SQL: the row carries
  `hash`, `mime`, `size`, dimensions and `name_lower` that search and duplicates rely on. Upload
  through the panel, the API or `media_upload_from_url`.
- Do not delete rows with SQL: `media_delete_files` (or the panel) removes the bytes, the
  edited original and every preview (`media/thumbs/<key>`) with the row. It refuses a file the site still uses and says where (a foreign
  key into `media_files`, or the file's key inside a text or JSON column); `force: true` deletes
  anyway — replace the file in those places first. A module that keeps files where the schema
  cannot show them tags a `WebxUi\Media\Usage\UsageSource` with `MediaUsage::TAG`. The panel's delete keeps the same rule: `DELETE files/{id}` and
  `POST files/delete` answer `409 files_in_use` with where unless `force`, and `POST files/usage`
  and `GET directories/{id}/contents` say it before anything is asked. A raw delete leaves
  orphaned bytes and previews; a deleted file on the disk leaves a row the audit reports as
  `media.missing_file`.
- Do not try to move or delete the root folder — it is refused (`root_immutable`). Deleting a
  non-empty folder answers `409` with counts until `?force=1`; ask the person first, there is no
  bin. `media_delete_directory` deletes only an empty folder, on purpose.
- Do not point `media_upload_from_url` at the site itself or the local network: loopback,
  private, link-local and reserved addresses are refused, after resolving the name. A host on
  the site's own network that must be fetched goes into `webx-media.remote.allow_hosts`.
- Do not ask for a thumbnail size outside `thumbs.widths` and `thumbs.fits`: it is refused, so
  one request cannot make the server resize anything to anything. Add the size to the config.
- Do not widen `upload.extensions` with executable or script types: the list is a white list on
  purpose, and what it lets in ends up on a public disk.

## Check your work

- Upload a file in the panel's «Files» section and open its address; on the `public` disk the
  site needs the `storage` link for that address to answer.
- With MCP: `media_list_directories`, then `media_list_files` or `media_get_file` for the `url`
  and `thumb`; every mutating tool takes `dry_run: true` first.
- With `webx-ui/module-audit`: `media.missing_file` lists rows whose bytes are gone, `media.heavy`
  the images too heavy for a page.

## Read more

- [README.md](README.md) in this directory — field values, the API, configuration, permissions.
- Guide: https://webx-ui.github.io/webx-ui/guide/media
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_MEDIA.md
- Described screens and their fields: https://webx-ui.github.io/webx-ui/guide/screens
