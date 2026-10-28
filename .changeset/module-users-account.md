---
'@webx-ui/php': minor
---

`webx-ui/module-users`: the visitor's account. `/account/profile` keeps the names, the phone and the site's fields of the profile and changes the e-mail; `/account/security` changes the password with the current one (or sends «Set a password» to an account without one), signs out every other device and deletes the account. A module adds its section with `AccountMenu::register()` and `AccountRoutes::group()`, which give its routes the middleware and the language copies of the account; the menu stands in theme-default's `layout.account` by a composition patch and in any other layout by the frame `<x-webx-users::account>`. Deleting runs `VisitorDeleting` for the modules in one transaction, shows their `DeletionWarnings` first and sends «Your account was deleted». Signing out leaves the other devices signed in; the remember token, set when the account is made, is what «Sign out everywhere» and a new password change.
