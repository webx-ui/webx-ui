# webx-ui/module-media

The file manager of a [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: a tree of
folders, the files in them, uploads, search, and an image editor — on any disk Laravel can
address.

Its front end is [`@webx-ui/module-media`](https://www.npmjs.com/package/@webx-ui/module-media).

**Status: the server half is complete.** Folders, files, uploads, previews and image editing
answer; the panel's front end is [`@webx-ui/module-media`](https://www.npmjs.com/package/@webx-ui/module-media).

## Requirements

- PHP 8.3+
- Laravel 13
- `webx-ui/admin`, `webx-ui/localization`, `webx-ui/mcp`, `webx-ui/nested-set`
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

## API

Everything lives under the panel's API path, behind the panel session and a permission.

|                                              |                                                                                 |
| -------------------------------------------- | ------------------------------------------------------------------------------- |
| `GET directories`                            | the whole tree with file counts                                                 |
| `POST/PATCH directories`, `PATCH …/move`     | create, rename, move                                                            |
| `DELETE directories/{id}`                    | refuses a folder that holds anything (409 with counts) until `?force=1`         |
| `GET files`                                  | paginated, `q`, `type`, `sort`, `per_page`                                      |
| `POST files`                                 | multi-file upload; the same bytes in the same folder answer `duplicate`         |
| `PATCH files/{id}`                           | rename — the key on the disk never changes                                      |
| `POST files/move`, `DELETE files`            | in batches                                                                      |
| `GET files/{id}/thumb?w=&h=&fit=`            | cuts the variant once, then redirects to it                                     |
| `POST files/{id}/edit`                       | crop, rotate, flip, resize — applied to the original, written over the same key |
| `POST files/{id}/copy`, `…/restore-original` | a second file; the picture as it arrived                                        |

The editor takes **operations rather than a finished picture**: the canvas in the browser works
on a preview, and what it could send back is smaller than the original.

## MCP

`list_directories`, `list_files`, `search_files`, `get_file`, `create_directory`,
`rename_file`, `move_files`, `upload_from_url`, `delete_files`. Every mutating tool takes
`dry_run`.

Deleting a folder is deliberately not among them: recursive deletion is the one operation here
that a mistaken call cannot take back, and an agent cannot ask the question the panel asks first.

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
