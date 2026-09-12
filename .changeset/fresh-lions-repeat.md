---
'@webx-ui/admin': minor
---

The panel has a language. `useTranslate('webx-admin')` and `useI18n()` draw the interface from
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
