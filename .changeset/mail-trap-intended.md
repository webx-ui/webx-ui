---
'@webx-ui/php': patch
---

module-admin, module-notifications: a stand can keep its mail trap on production on purpose. `WEBX_MAIL_TRAP_INTENDED=true` (`webx-admin.mail.trap_intended`) turns the `webx:doctor` warning about a trap — the site's mail and a notification channel's alike — into a note, so a deploy's `--strict` check no longer fails on a closed prototype whose letters go to Mailpit; the panel and the agent still say that such letters reach nobody. The doctor's advice no longer says to add a delivering host to `webx-admin.mail.traps` (that list is what a trap is): it says how to point the mail at a real server and how to mark the trap as intended.
