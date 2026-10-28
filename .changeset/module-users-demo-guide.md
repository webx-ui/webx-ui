---
'@webx-ui/php': minor
---

`webx-ui/module-users`: demo content — the groups «Wholesale» and «Club» and five visitors at `@example.com` without passwords, one unconfirmed and one linked to Google (`webx:demo --module=users`, nothing on a site that already has visitors). A made-up social network `dev` walks the whole sign-in flow on a machine of development: registered only while `APP_ENV=local`, shown with `WEBX_USERS_DEV_ENABLED=true`. A visitor's groups now go with the row by any door, not only where the foreign key cascades. The guide «Site visitors» covers the sign-in, the account and a section of it from a module, the fields of the site and a new provider (Apple).
