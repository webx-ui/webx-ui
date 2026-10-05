---
'@webx-ui/php': minor
---

The MCP server is named after its site — `example.com`, the host of `app.url`, or
`WEBX_MCP_NAME` — instead of "WebX UI", and the first line of its instructions gives the address
and the environment. A new tool, `site_info`, answers which site the connection is to and as
whom, so that an agent with several WebX UI sites connected at once checks before it writes.
