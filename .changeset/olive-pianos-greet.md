---
'@webx-ui/php': minor
---

Two packages the admin panel is built from. `webx-ui/admin` carries the module contract, the
registry, the manifest the front end reads before it draws anything, and the catch-all that
keeps a deep link from 404ing. `webx-ui/mcp` carries the contract by which a module offers
itself to an AI agent — a mutating tool is given `dry_run` and a write scope whether its author
remembered them or not.
