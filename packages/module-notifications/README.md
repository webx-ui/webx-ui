# @webx-ui/module-notifications

The front end of the Notifications section of the [WebX UI](https://github.com/webx-ui/webx-ui)
admin panel: channels to Telegram and e-mail, their Mustache templates in every language of the
site, a live preview, tests, and the log of every delivery.

The other half is the Composer package `webx-ui/module-notifications`, which owns the channels, the
providers, the queue, the API and the agent tools. A section appears in the panel when both halves
are installed.

## Install

```bash
npm install @webx-ui/module-notifications
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { notifications } from '@webx-ui/module-notifications'
import '@webx-ui/module-notifications/style.css'

createAdmin({
  basePath: '/cms',
  modules: [notifications()],
}).mount()
```

`php artisan webx:module:add notifications` installs both halves and writes this into the panel's
entry. `notifications({ path: '/alerts' })` puts the section somewhere else inside the panel.

## What is in it

- The section: channels with their switch, copy and bin; the delivery log with filters and
  «Send again».
- A channel's page: the described screen `notifications.channel` with the provider's settings, the
  template in `WxCodeEditor` with Mustache highlighted and suggested after `{{`, and the preview
  beside it — rendered by `renderMessage()`, held to the server's render by shared test cases.
- Node types for screens: `wx-notifications-template`, `wx-notifications-chats`,
  `wx-notifications-emails`, and `wx-notifications-channels` — the picker a module chooses its
  channels for an event with, also exported as `WxNotificationChannelsField`.

The guide: https://webx-ui.github.io/webx-ui/guide/notifications
