# Catalogue

`webx-ui/module-catalog` is the core of a shop: products, a tree of categories, a gallery per
product, the engine behind the lists and facets, and the storefront. Stock, brands, properties
and labels come as satellites (`module-catalog-*`) that plug into its registries. The design is
in `docs/architecture/WEBX_UI_CATALOG.md` and the core's spec in `WEBX_UI_MODULE_CATALOG.md`.
This page covers the gallery for now, and its videos in particular.

## The gallery

A product's pictures are files on a disk of the catalogue's own
(`webx-catalog.images.disk`, `public` by default), under `catalog/{id div 1000}/{id}/{sha1}.{ext}`,
not rows of the media library. The first picture by position is the main one. Each picture has
an `alt` and a `title` in every language. The editor adds pictures from a file or from an
address, puts them in order, captions them and takes them away. Each of these is a request of
its own, not part of the form's save, and each is in the product's journal as a change of
`images`.

## Video

A video is **attached to a picture**, which becomes its poster. A row of the gallery is always a
picture, because lists, feeds, `og:image` and `Product.image` all need one. A video comes from one
of two places:

- **YouTube.** A link in any form it is shared in (`watch?v=`, `youtu.be/`, `/shorts/`,
  `/embed/`, `/live/`, on `www.`, `m.` and `music.`). Added to the gallery, it becomes a new row:
  the video's cover (`maxresdefault`, or `hqdefault` when that is missing) is the picture, and an
  empty `alt` is filled with the video's title from oEmbed.
- **A file of your own**, MP4 or WebM, stored as it is: no re-encoding and no ffmpeg on the server.
  The panel sends it through [chunked uploads](./uploads) under the purpose `catalog.video`, so
  PHP's and nginx's limits never see the whole file. The limit that counts is
  `webx-catalog.videos.max_size_mb`. The type is checked by the file's content, so a picture
  renamed `.mp4` is refused.

A **direct link to a file** (`https://…/clip.mp4`, or an address that answers `video/mp4`) is
downloaded on the queue by `FetchVideo`, streamed to the disk under the same limit. The request
answers `202` at once, and the video appears when the job is done. Sites that add videos by link
need a queue worker.

Files go with what holds them. Replacing a video or taking it off deletes its file at once, and
deleting a picture deletes its video. A product moved into «Deleted» keeps its files, as it keeps
its pictures.

### On the storefront

`product.blade.php` shows the poster with a ▶. The player is put in only when the reader clicks:
`<video controls autoplay playsinline>` for a file, or an iframe from `youtube-nocookie.com` for
YouTube. Until that click the page loads nothing from YouTube and sets no cookie. Without
JavaScript the ▶ is a plain link to the file or to the video's page. The script is a few lines
inline, with no dependencies. A site that overrides the view keeps the classes
`webx-catalog-product__video` and `webx-catalog-product__play` and the `data-webx-embed` /
`data-webx-video` attributes to reuse it.

The markup gets a `VideoObject` in `Product.subjectOf` for each video. Its `name` is the
picture's `alt` or the product's name, its `description` is the summary or the name, and its
`thumbnailUrl` is the poster. A file has `contentUrl`, YouTube has `embedUrl`, and a file's
duration is given as ISO 8601 when the panel sent it.

### The API

```
POST   /api/cms/catalog/products/{id}/images                  multipart file | { url }
POST   /api/cms/catalog/products/{id}/images/{image}/video    { upload, duration? } | { url }
DELETE /api/cms/catalog/products/{id}/images/{image}/video
```

Every picture in the answers carries `video: { provider, url, embed, duration } | null`. `url` is
the file or the video's page. `embed` is the iframe's address, and `null` for a file.

### Agents

Six MCP tools cover the gallery:

- `catalog_products_gallery` reads it.
- `catalog_products_gallery_add` adds a picture, a YouTube link or a direct link to a file.
- `catalog_products_gallery_update` changes captions.
- `catalog_products_gallery_order` puts the gallery in order.
- `catalog_products_gallery_video` attaches a video, or takes it off with `url: null`.
- `catalog_products_gallery_remove` removes a picture.

`dry_run` on adding, attaching and removing reports what the address is without fetching it.
Files from the agent's own machine cannot be sent. `catalog://fields` reports whether videos are
on and the size limit.

### Configuration

```php
// config/webx-catalog.php
'fields' => [..., 'video' => (bool) env('WEBX_CATALOG_VIDEO', true)],
'videos' => ['max_size_mb' => 2048, 'types' => ['video/mp4', 'video/webm']],
```

With `WEBX_CATALOG_VIDEO=false` there is no player and no markup on the storefront, the panel
offers no video, and the API and the agent refuse to attach one. Videos already attached are
kept. The product screen passes the flag and the limits to the gallery field as props
(`video`, `videoTypes`, `videoMaxBytes`).

### Another provider

YouTube is the one provider in the core. A satellite or a site adds another in its provider:

```php
use WebxUi\Catalog\Gallery\Video\VideoProvider;
use WebxUi\Catalog\Gallery\Video\VideoProviders;

$this->app->make(VideoProviders::class)->register(new MyProvider);
```

A provider implements `VideoProvider`: its `key()` (stored in `video_provider`), a `label()` for
the journal, `idFrom($url)` (return `null` when the link is not one of its videos), `embedUrl()`,
`watchUrl()`, `posterUrls()` (best first) and `title()`. Its links are then accepted everywhere a
YouTube link is.
