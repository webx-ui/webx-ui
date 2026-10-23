---
'@webx-ui/php': minor
'@webx-ui/module-inbox': minor
---

A form of the Inbox has a description: General → Description in the panel, `options.description` per language in the API and in MCP (`inbox_form_get`, `inbox_form_save`). On the site it is a line over the fields (`webx-inbox-form__description`), plain text with the site's shortcodes; in a dialog it stands under the dialog's title (`aria-describedby`) and the form inside does not print it again. A form without one prints nothing.

A dialog can be a sheet from the bottom on a phone: `<x-webx-dialog sheet>` (`webx-dialog--sheet`) below 40rem of the window — the whole width, rounded on top, scrolling inside, sliding in unless reduced motion is asked for. The runtime measures the window (`is-sheet` on the open dialog, following a turned phone); without JavaScript it stays a card. A form in a dialog becomes one with `data-webx-sheet` on any of its openers, `Widgets::form('<slug>', sheet: true)` or `webx-widgets.form_dialog.sheet`. `<x-webx-dialog description>` prints a line under the title. Off by default: a dialog is the card in the middle it was.

The fields marked half width in the Inbox stand two to a row from 24rem of the form instead of 32rem: a dialog of 480px gets its pairs, a phone of 360px one field to a row.

The mobile menu's accordion can stop repeating a section's own page inside its branch: `<x-webx-mobile-menu.nav parent="split">` (or `<x-webx-header parent="split">`) makes the section's name the link and opens the branch by an arrow beside it, labelled «Submenu: …» like the header's. The default, `repeat`, is what it was.
