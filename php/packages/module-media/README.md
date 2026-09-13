# webx-ui/module-media

The file manager of a [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: a tree of
folders, the files in them, uploads, search, and an image editor — on any disk Laravel can
address.

Its front end is [`@webx-ui/module-media`](https://www.npmjs.com/package/@webx-ui/module-media).

**Status: being built.** The section registers itself with the panel and carries its
configuration; folders, files and the endpoints around them are landing step by step, in the
order the [specification](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_MEDIA.md)
sets out.

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
