---
'@webx-ui/php': patch
---

`webx:doctor` checks the bundle by content, not by time: the skeleton's `vite.config.js` records every file of the site the build read, with its sha256, in `webx-sources.json` beside the manifest, and the `Bundle` check hashes them again. A build stage Docker reused from its layer cache no longer looks stale beside freshly copied sources, and an edit to a file the entry imports is caught too. A site without the record is compared by modification time, as before.
