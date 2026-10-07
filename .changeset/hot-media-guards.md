---
'@webx-ui/php': patch
---

`module-media`: `media_upload_from_url` fetches only from the public internet — loopback, private, link-local and reserved addresses (IPv6 and IPv4 inside IPv6 too) are refused after resolving the name, the connection is pinned to the checked address, redirects are checked hop by hop, and a transport error is said in plain words; the type is sniffed from the bytes and the panel's upload rules apply (`webx-media.remote.allow_hosts` for trusted internal hosts). A stored MIME type never keeps header parameters, and a migration cleans the ones already stored. `media_delete_files` refuses a file the site still uses and says where, unless `force: true` (`MediaUsage`, with `DatabaseUsage` reading foreign keys and text/JSON columns; modules can tag their own `UsageSource`). New `media_delete_directory` deletes an empty folder. Media tools refuse with an MCP error instead of `{ ok: false }`, and name the missing folder or file ids.
