# Catalogue

`webx-ui/module-catalog` is the core of a shop: products, a tree of categories, a gallery per
product, the engine behind the lists and facets, and the storefront. Stock, brands, properties
and labels come as satellites (`module-catalog-*`) that plug into its registries. The design is
in `docs/architecture/WEBX_UI_CATALOG.md` and the core's spec in `WEBX_UI_MODULE_CATALOG.md`.
This page covers the gallery, its videos in particular — on the server, in the panel and on the
storefront — and the import and export of products as CSV and XLSX files.

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

### In the panel

The gallery on the product's «Pictures» tab takes videos in three ways:

- **Dropped into the upload zone** with the pictures (`video/mp4`, `video/webm`). The browser
  takes a frame of the video from the local file, a little way in, before anything is sent. That
  frame goes up as the row's picture, then the file goes up in pieces, then the two are put
  together along with the length the browser read. The row appears at once with its poster and a
  bar showing the percentage, speed and time left, with pause and cancel. Cancelling also removes
  the picture that was made of the frame. If the browser cannot open the file (usually a codec it
  does not play), the panel asks for a picture and for the video to be attached to that.
- **Attached to a picture** from the row's menu: «Attach a video file…» or «Attach a YouTube
  link…». The link dialog also takes a direct link to an MP4 or WebM file. A row with a video
  offers «Remove the video» instead, after a question, because a file is deleted at once.
- **By address**: the «Upload from an address» card takes pictures, YouTube links and direct
  links to files. A direct link answers «downloading on the server», and its row appears once the
  queue is done with it. That row's poster is the server's plain frame, and the row says how to
  get a real one: add a picture, attach the same link to it, delete the row.

A row with a video has a ▶ (with its length, when known) in the middle of its preview. Clicking
the preview opens the video in a new tab, not the poster.

Uploads belong to the editor, not to the gallery's tab. The rest of the form can be filled in
while a file goes up, and switching tabs does not stop it. The browser asks before the page is
left mid-upload, and so does the panel's own navigation. An upload interrupted by a reload or a
closed tab is offered again when the product is next opened. Choosing the same file (same name,
size and date) continues from where the server stopped, onto the picture it was meant for.

The limits come from the screen: the provider patches the `gallery` node with `video`,
`videoTypes` and `videoMaxBytes`. The type and size are checked before a byte is sent. With
`video: false` the gallery has no video menu, no `video/*` in its upload zone and no video words,
and it never starts an upload. A server older than the video work sends no `video` prop, and the
field then acts as if it were `false`.

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

## Import and export

Products go in and out as CSV or XLSX files; the spec is
`docs/architecture/WEBX_UI_MODULE_CATALOG_EXCHANGE.md`. A column of a file is a field of the
product form — `sku`, `name`, `name@de` for a translation, `price`, `category` as a path of
names, a label's code, a property by its code — and every row is saved by the same form, with its
checks and its journal. A bad row is an error beside its number; its neighbours are written anyway.

### In the panel

«Exchange» is in the `···` of the products, after «Deleted». It lists every run with what it did:
open one for its totals, the first hundred errors (all of them as a CSV file), the finished file of
an export and, for an import, what it changed in the journal. A run that is still going is asked
after every two seconds.

**Import** is a wizard of three steps: a file — uploaded in pieces or downloaded by the server from
an address — then its columns matched to the catalogue's with the first rows of the file under each,
then how to write: the key that finds a product, which rows to take, whether an empty cell clears a
field, what happens to products the file does not have, whether missing categories and values are
created, and what pictures by address do. The last step checks the file without writing anything,
or runs it, and can save it all as a profile.

**Export** writes what the list of products has picked — ticked rows or everything a filter finds —
from «Actions», or the whole catalogue from «Exchange»: the columns of a profile or chosen on the
spot, with the languages their translations go out in.

**Profiles** keep a name, a format, the settings and the columns, so that the same price list of a
supplier is one step the next time. The settings of an import and the head of a profile are the
described screens `catalog.exchange-import` and `catalog.exchange-profile`: a project takes a
setting away or fixes it with a patch, as on any form.

### Agents

Five MCP tools cover the exchange, behind the panel's permissions: importing needs
`catalog.manage`, the rest any of the three.

- `catalog_exchange_columns` lists the columns the agent may use and how each cell reads.
- `catalog_import` takes a file by address (`url`) and a profile, or a mapping with options. With
  neither, the headers are matched the way the panel suggests.
- `catalog_export` exports a filter or ids, with the columns of a profile or a list of codes.
- `catalog_exchange_run` returns a run: its counts, its first fifty errors and, once an export is
  done, `file_url`.
- `catalog_exchange_profiles` lists the profiles.

An agent has no upload, so a file it builds has to live at an address the server can download.
`dry_run` on an import is the import's own check: every row goes through the columns and the
form, every error is listed, and nothing is written. An export's `file_url` is a signed link that
works without signing in for `exchange.keep_hours`. `catalog://exchange` puts the rules of the
format on one page: the header, every column of the core and the satellites with how its cell
reads, the options, what an empty cell does. Building the file right is cheaper than reading its
errors.

A satellite's column describes its cell by implementing `DescribesCell` next to `ExchangeColumn`.
A column without it is listed as text taken as it is.
