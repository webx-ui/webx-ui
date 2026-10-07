---
'@webx-ui/php': patch
---

`module-inbox` captchas, from a test on a site with v2 Invisible keys:

- **Kinds of key.** `webx-inbox.captcha.recaptcha.type` (`WEBX_INBOX_RECAPTCHA_TYPE`) is `checkbox` (default, as before) | `invisible` | `v3`, with `min_score` (`WEBX_INBOX_RECAPTCHA_MIN_SCORE`, 0.5) and the action `webx_form_<slug>` checked for v3. `webx-inbox.captcha.turnstile.mode` (`WEBX_INBOX_TURNSTILE_MODE`) is `managed` (default) | `invisible`. Invisible widgets of either provider are drawn by `inbox.js` on the first submit, one per form, and run then. Turnstile runs with `interaction-only`, `execute` and no retry loop. A provider error is caught and told to the visitor instead of a post that would be refused.
- **What the visitor is told.** A missing or failed captcha answers 422 under `captcha` with its own sentence, shown beside the widget, in ten languages. An invisible or v3 captcha without a token says the form needs JavaScript. Every other layer keeps the one sentence it had.
- **The log says why.** The refusal line names the layer (`honeypot`, `too_fast`, `origin`, `captcha_missing`, `captcha_failed`, `captcha_unconfigured`) and what the provider answered (`error-codes`, v3 `score` and `action`). It never includes the token or the secret.
- **Panel.** Forms carry `captcha` (how each provider runs on the site, and whether its keys are there). The Antispam tab says which key type the site expects, that the keys live in `.env`, and warns when they are missing.
- **Audit.** `inbox.captcha_keys` (error): a form asks for a captcha whose key or secret is missing. `inbox.captcha_unused` (notice): the site has keys and a form uses none. `inbox.spam_without_captcha` (warning): a form without a captcha drew spam, meaning spam-status submissions plus antispam refusals (now counted per form and day in the cache), at least `inbox_spam_min` (3) in `inbox_spam_days` (30).
