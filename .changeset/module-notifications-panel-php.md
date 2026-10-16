---
'@webx-ui/php': minor
---

module-notifications in the panel: the template is the npm half's editor (`wx-notifications-template`), the package names its npm half (`extra.webx.npm`, `extra.webx.panel`) and is in the catalogue of `webx:module:add`. Every event brings the relation target its consumers choose channels from, `notify-<event>` — the channels of that event and the ones for any event, without the bin, recipients or secrets, and with no permission of the module needed. `GET notifications/administrators` lists the administrators an e-mail channel can write to; the providers' icons are the panel's (`send`, `mail`). `lang/*/panel.php` in ten languages, and `PreviewParityTest` holds the panel's preview to the server's render on shared cases.
