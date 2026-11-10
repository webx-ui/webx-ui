---
'@webx-ui/php': minor
---

Media: the MCP tool `media_upload_link` answers a one-time link an agent sends a file from its own disk to with `curl -F file=@<path> "<url>"` (or `curl -T` for the raw body), so the bytes never pass through the model. The link is signed, works once, lives `webx-media.upload_links.ttl` minutes (10 by default, `WEBX_MEDIA_UPLOAD_LINK_TTL`), is bound to a folder and to the administrator and connection that asked for it, and is rate-limited. The file goes through the panel's own rules (types, size, SVG sanitizing, picture optimizing, duplicates); answers and refusals are JSON. New table `media_upload_links` — run the migrations.
