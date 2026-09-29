---
'@webx-ui/php': minor
---

Videos in the product gallery of `webx-ui/module-catalog`, on the server. A video is attached to a
picture, which becomes its poster: three nullable columns on `catalog_product_images` and
`video: { provider, url, embed, duration } | null` on every picture the API returns.

- **YouTube** links in every form they are shared in. Added to the gallery, a link brings its
  cover (`maxresdefault`, `hqdefault` when that is missing), and the video's title from oEmbed
  fills an empty `alt`. `VideoProviders` is a registry, so a satellite or a site can add another
  provider.
- **Files of your own**, MP4 or WebM, come through the chunked uploads of `module-admin` under the
  purpose `catalog.video` (`catalog.manage`, limits from `webx-catalog.videos`). The type is checked
  by content, and the file is stored under its sha1 beside the pictures. A direct link to a file is
  downloaded on the queue by `FetchVideo`.
- New endpoints `POST` and `DELETE /api/cms/catalog/products/{id}/images/{image}/video`.
  `POST …/images` also takes a video link: `201` for YouTube, `202` when a download is queued.
  Replacing or taking off a video deletes its file, and deleting a picture deletes its video.
  Both attaching and detaching go into the journal under `images`.
- On the storefront, a poster with a ▶. A few lines of inline script put in a `<video>` or a
  `youtube-nocookie.com` iframe only on a click; without JS the ▶ is a link. `Product.subjectOf`
  gets a `VideoObject` for each video.
- Six MCP tools, `catalog_products_gallery*`, cover reading, adding, captions, order, video and
  removal, with `dry_run`. `catalog://fields` reports the flag and the size limit.
- `webx-catalog.fields.video` (`WEBX_CATALOG_VIDEO`, on by default) switches it all off: no player
  or markup, and attaching is refused in the API and MCP. Attached videos are kept. The product
  screen passes the flag and the limits to the gallery field.
- `webx:demo` gives one product a YouTube video over its demo picture.
