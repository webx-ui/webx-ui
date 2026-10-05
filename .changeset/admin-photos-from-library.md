---
'@webx-ui/module-auth': minor
---

Administrators' photographs come from the media library on their own: the form, the list and the
corner menu take the library's `wx-media` field and its `assetUrls`, so a panel with `media()`
needs no `avatarField` / `resolveAvatar` in `admin.ts` — which `webx:panel --sync` never wrote,
leaving every site without photographs. Handing either in still wins.
