# @webx-ui/admin

## 0.2.2

### Patch Changes

- 9e1b513: Three things found by putting the library on S3 behind a CDN.

  The image editor could not open a picture at all. It draws onto a canvas and writes that canvas
  out, which a browser refuses for bytes fetched from another origin without CORS headers — and a
  private bucket cannot be given those headers for the panel in any case. So `webx-ui/module-media`
  serves the picture itself at `files/{id}/source`, behind the same permission as the listing, and a
  `MediaFile` now says where that is. `url` stays what everything that only looks at a file uses.

  The editor also spoke English in a Russian panel: it is a component of the design system, so its
  words are props, and the manager was not passing any. It has its own ten-language group now, as
  does the question the card asks before deleting one file.

  `@webx-ui/admin`: changing the language renames the sections too. Titles are translated on the
  server and travel in the manifest, which was fetched in the previous language — so the panel used
  to switch everything except its own navigation until the page was reloaded.

## 0.2.1

### Patch Changes

- 47cc998: The file manager after an hour with it: icon actions instead of labelled buttons, filters behind
  popovers, folders in a drawer on a phone, case-insensitive search in any alphabet, previews that
  change when a picture is edited, and a copy-the-link that says whether it worked.

  `@webx-ui/admin` gains the toaster the panel never had — until now every `toast()` from every
  module reported into silence.

- Updated dependencies [47cc998]
  - @webx-ui/core@0.14.3

## 0.2.0

### Minor Changes

- 9c0a762: The panel has a language. `useTranslate('webx-admin')` and `useI18n()` draw the interface from
  a dictionary the server assembles out of every installed package's `lang` files, over the
  English this package carries in its own code — so a module is translated once, in the half that
  also writes the server's validation messages, and a panel with no server behind it still has
  labels.

  Which language belongs to the person reading, not to the site: the manifest carries their
  choice, and every request now says which language the panel is currently showing, so a 422
  arrives in the same language as the field it lands under. `i18n.state.contentLocales` is the
  separate list an editing screen builds its tabs from — the languages the site publishes in,
  which has nothing to do with the language of the chrome around them.

  `createHttp` takes a `headers` callback for standing headers read at the time of each request.

## 0.1.0

### Minor Changes

- 266f47d: The shell paints the page it owns. Every state now carries `wx-root` — the class holding the
  font family, the text colour and the page background — so the panel no longer renders in the
  browser's default serif on whatever background the page happened to have. Where the panel is
  the whole page rather than a widget on one, the body's own margin is reset, and the centred
  plain layout is `border-box`, so its padding no longer added a scrollbar to a column exactly
  one viewport tall.

### Patch Changes

- Updated dependencies [752adf0]
  - @webx-ui/core@0.14.2
