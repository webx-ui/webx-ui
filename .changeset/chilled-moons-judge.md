---
'@webx-ui/module-settings': patch
---

Republish so the dependency on `@webx-ui/module-admin` names the version that has `WxScreen`.

The first version of the package was published by hand, before the release bumped the frame, so
the tarball froze `^0.3.1` — a range that excludes the 0.4.0 which introduced `WxScreen`. Installs
picked a nested copy of the older frame and the app failed to build on the missing export.
