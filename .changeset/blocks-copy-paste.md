---
'@webx-ui/module-blocks': minor
'@webx-ui/core': minor
'@webx-ui/php': patch
---

Blocks copy and paste between pages, and between every section that has the constructor —
pages, articles, services, layout regions. **Copy** in a block's `···` puts it aside with
everything inside it; **Copy all blocks** in the list's head puts aside the whole page. While
something is put aside, **Paste after**, **Paste inside** (on a container) and **Paste** (at the
end of the page) name what will go in. Keys are renewed all the way down; what the target may not
hold — by the same rules as adding a block — is left out and named in a toast. The clip lives in
the browser's storage of the site, survives a paste and is seen by every tab.

The core gains two icons for it: `clipboard` and `clipboard-paste`.
