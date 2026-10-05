---
'@webx-ui/php': patch
---

The site audit no longer reports the address beside a library key (`src` or `href` of a tag with
`data-wx-path`) as a link to a stand in the content: the site prints those pictures from the key,
so a site moved off its stand saw every picture it wrote there listed, with none of them on a page.
