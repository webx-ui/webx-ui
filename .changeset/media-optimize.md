---
'@webx-ui/php': minor
'@webx-ui/module-media': minor
---

Pictures are optimized on the way into the library: a JPEG, PNG or still WebP is turned the right
way up, stripped of its metadata, scaled down to 2560px on its long side and saved as a WebP at
quality 82, unless that comes out no smaller. The steps are `webx-media.optimize` and a project
adds its own. **Optimize**, beside Upload, runs the pictures already there through the same steps
over the same key and in the same format, ten per request with a count and a stop; MCP
`optimize_images` does the same for an agent. Needs the new migration (`media_files.optimized`).
