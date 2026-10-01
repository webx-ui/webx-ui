---
'@webx-ui/php': patch
---

`webx:setup --locales=…` without English moves the fallback language — `webx-localization.fallback` and `APP_FALLBACK_LOCALE` — to the site's default language, so `webx:doctor --strict` passes on a fresh install; a fallback the site already publishes in is left alone. The skeleton's `.dockerignore` keeps `bootstrap/cache/*.php` out of the image (the host's provider list named dev packages and the entrypoint's `package:discover` failed), along with `auth.json`, the Passport keys and Pail's output.
