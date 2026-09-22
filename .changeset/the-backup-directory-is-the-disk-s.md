---
'@webx-ui/php': patch
---

The backup command no longer names a path that Laravel has moved. `storage/app/backups` has
not been where the dumps go since Laravel 11 rooted the `local` disk at `storage/app/private`,
and the command's description, the `Backups` docblock and the published config all still said
it. The wording now points at `path` under the root of `disk`, which is what the code has
always read and what stays true the next time the root moves.
