# @webx-ui/module-notifications

## 0.5.0

### Minor Changes

- aabc44b: A message about an order is sent again by hand (decision 99): «the buyer says the letter never came». A line of «Notifications» on the tab «History» — sent or failed — has **Send again**: the same message to the same address, asked first with the letter's name and the addressee, as a new row of the log marked «Sent by hand» with who sent it. **Send the buyer a letter** in the `···` of the card picks an event the order has a reason for (placed, the public status it stands in, paid, payment failed, received, cancelled, refunded), shows each channel's letter from a dry run and sends it to the order's e-mail as it is now — only through the channels of the event that write to the buyer; with no such channel, or no e-mail, the action is greyed with why. Each send is a history line `notification_sent` with its author; the same send again within a minute is refused, so a second press sends nothing. It is `orders.manage`, and there is nothing of it without `webx-ui/module-notifications`. `Orders::notify()` and MCP `orders_notify` (with `dry_run`) do the same.

  `webx-ui/module-notifications`: `Notifications::resend()` sends a row of the log again — one that went too, unlike `retry()` — as a new row; `sendByHand()` sends an event again to its addressee alone; `addressed()`, `writesTo()` and `render()` tell a consumer which channels would send and what. A new migration adds `manual`, `manual_by` and `resent_from` to `notifications_deliveries`; the log of the section marks such rows «Sent by hand» with who sent them.

### Patch Changes

- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
- Updated dependencies [aabc44b]
  - @webx-ui/core@0.46.0
  - @webx-ui/module-admin@0.34.0
  - @webx-ui/schema@0.10.0

## 0.4.0

### Minor Changes

- b4296aa: Fixes from checking a client's site before launch.

  - **Site password and the panel.** An administrator signed in to the panel passes the site's password
    (`WEBX_SITE_GATE`) on every address: the page preview and what it loads, a preview reloaded after
    its token ran out, «Open on the site». A stranger still gets the dialog; a switched-off account does
    not pass.
  - **Service pages.** The OAuth consent and every route marked `SiteRoutes::SERVICE` get neither the
    panel on the site nor the debug bar, and an SEO rule cannot be made for a service address (panel
    and `seo_urls_set`).
  - **Alt of a file.** A library file has a translated alt (card menu → Alt text, `media_set_alt`, read
    by `media_get_file`/`list`/`search`). Templates print it wherever an image field has no alt of its
    own; `hero`, `cards`, `reviews`, `services` and `press-outlets` print alt instead of an empty one.
  - **Where the mail goes.** `Mail\MailRoutes` describes the site's mail and a channel's server, knows
    traps (`log`, `array`, a test sandbox, Mailpit/MailHog — `webx-admin.mail.traps`) and a config
    cached before `.env` changed. `webx:doctor` (check `Mail`), the form's Notifications tab, a mail
    channel, the submission card and MCP (`inbox_get`, `notifications_channel_get`, `notifications_test`,
    `notifications_deliveries`) say it; a queued letter says when it leaves and asks about the queue
    after five minutes; a letter a trap took is «test transport», not «sent».

## 0.3.1

### Patch Changes

- e42643b: Removing one row of a list asks first, in a small popover by the button («Delete?» — «Yes» / «Cancel»), instead of removing at once. `WxAction` gains `confirm`, `confirmText` and `cancelText`: with a question set, `click` comes only on a yes. `WxPopconfirm` puts the focus on Cancel, goes ahead on Enter and reads the question out as the panel's description. The panel's words are `useRemoveConfirm()` of `@webx-ui/module-admin` (`webx-admin::confirm.*`, ten languages); labels and categories of a record, related records, banner layers, landing products and sets, category properties, intervals, inbox choices, notification chats and emails, SEO links, FAQ rows and the mapping and column rows of an exchange profile ask with it. Deleting a whole record keeps its dialog.
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
- Updated dependencies [e42643b]
  - @webx-ui/module-admin@0.33.0
  - @webx-ui/core@0.45.0
  - @webx-ui/schema@0.9.5

## 0.3.0

### Minor Changes

- 9dcb323: The channel's form: the subject of a letter suggests the event's variables after `{{` as the
  template does (one line), and a variable clicked under the template goes into whichever of the two
  the caret was in last. Under the template, **Insert the default template** puts the event's own
  message in — the subject and the template in every language it has — asking first when something is
  written; a channel whose message is still empty gets it when its event is chosen. A letter whose
  template starts with a tag is previewed as the HTML letter it is, in a frame that runs nothing.

### Patch Changes

- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
- Updated dependencies [9dcb323]
  - @webx-ui/module-admin@0.32.0
  - @webx-ui/core@0.44.0
  - @webx-ui/schema@0.9.4

## 0.2.1

### Patch Changes

- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
- Updated dependencies [a33dc79]
  - @webx-ui/module-admin@0.31.0

## 0.2.0

### Minor Changes

- 08eabf2: The panel downloads half as much before it can draw anything (webx-ui#16, stage 4). `WxCodeEditor` (CodeMirror and its languages) and `WxRichText` (Tiptap) are async components of the core: same names, props, events and `v-model`, a template ref points at the editor once it has arrived (`null` until then), and a stub by name in a test still replaces them. The pages of every module's routes load when their route opens (`component: () => import(...)`), and the indexes export them as `defineAsyncComponent` under the same names — a page re-exported statically would be pulled back into the index. `createAdmin({ modules })` and a site's `admin.ts` are unchanged. A site with 11 modules: 839 → 477 KB gzipped on first load; the playground with every module: 1012 → 514 KB. A jsdom test that mounts a page or an editor from a package waits for it with `await vi.dynamicImportSettled()`. `@webx-ui/module-blocks`: the word of the `block-usage` cache group.

### Patch Changes

- Updated dependencies [08eabf2]
- Updated dependencies [08eabf2]
  - @webx-ui/module-admin@0.30.0
  - @webx-ui/core@0.43.0
  - @webx-ui/schema@0.9.3

## 0.1.0

### Minor Changes

- c700893: The «Notifications» section of the panel, a new package: channels with their switch, copy and bin; a channel's page with the provider's settings (secrets shown as set, never their value, and forgotten from the head's menu), recipients — Telegram chats with «Find chats», administrators and addresses for a letter, the address the event brings — and the Mustache template in every language of the site in a code editor that suggests the event's variables, lists and their fields after `{{` and closes the open section after `{{/`; a live preview on the event's examples, rendered by the same rules as the server; «Check the connection» and «Send a test»; the delivery log with filters and «Send again». `WxNotificationChannelsField` (`wx-notifications-channels`) is the picker a module chooses its channels for an event with.

### Patch Changes

- Updated dependencies [c700893]
- Updated dependencies [c700893]
  - @webx-ui/core@0.42.0
  - @webx-ui/module-admin@0.29.0
  - @webx-ui/schema@0.9.2
