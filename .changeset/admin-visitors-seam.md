---
'@webx-ui/php': minor
---

Admin: who the site's visitor is, for every module and without a visitors module — the contract `WebxUi\Admin\Visitors\Visitors` (`current()`, `find()`, `findByEmail()`, `signInUrl()`) with the value `Visitor`, bound to `NullVisitors` until a visitors module binds its own; the events `VisitorSignedIn`, `VisitorSignedOut`, `VisitorRegistered`, `VisitorDeleting`; the middleware `webx.visitor` and `webx.visitor:verified` (a guest is sent to sign in, JSON 401 `visitor.guest`; an unconfirmed e-mail — 403 `visitor.unverified`; 404 on a site without visitors); the Blade directives `@visitor` and `@visitorVerified`.
