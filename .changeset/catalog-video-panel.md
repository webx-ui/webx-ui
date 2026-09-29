---
'@webx-ui/module-catalog': minor
---

Videos in the product gallery, in the panel. A video is attached to a picture, which becomes its
poster.

- **Drop a video** (MP4 or WebM) into the gallery with the pictures. The browser takes a frame of
  it before anything is sent, and the frame goes up as the row's picture. The file then goes up in
  pieces through `useChunkedUpload` and is attached to that picture with its length. The row
  appears at once with its poster and a bar showing percentage, speed, time left, pause and cancel.
  If the browser cannot open the file, the panel says to add a picture and attach the video to it.
- **Row actions**: «Attach a video file…» and «Attach a YouTube link…», or «Remove the video» (after
  a question) on a row that has one. A row with a video has a ▶ on its preview, and the preview
  opens the video.
- **By address**: «Upload from an address» takes YouTube links and direct MP4/WebM links. A direct
  link is downloaded on the server, and the panel says so. A row left with the server's plain
  poster says how to get a real one.
- **Uploads belong to the editor**, not the gallery's tab: they carry on while other tabs are
  open. Leaving mid-upload asks first, and an interrupted upload is offered again after a reload,
  to be continued by choosing the same file.
- The screen's `video`, `videoTypes` and `videoMaxBytes` props on the `gallery` node set the
  limits, which are checked before a byte is sent. `video: false` (and a server that sends no
  prop) means no menu, no `video/*` in the zone and no upload.
- `CatalogApi` gains `fetchImage`, `attachVideo` and `detachVideo`. `ProductImage` gains `video`,
  and the new types `ProductVideo` and `QueuedVideo` come with it. `createGalleryVideo` lets an
  editor of your own keep the uploads the way `WxCatalogProductEditor` does.
