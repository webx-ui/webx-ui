---
'@webx-ui/php': patch
---

Setup: the database server is a question now, but only when it needs to be

`webx:setup` asked for the database _name_ and took the address it would be created at without
ever asking: the `--db-*` options, then `.env`, then `127.0.0.1:3306` as `root`. On a machine
where the local MariaDB listens somewhere else — OSPanel gives each of its database modules a
loopback address of its own — that meant six answered questions and then a stop at the step
that had already rewritten `.env`, on a host nobody had been offered the chance to name.

The address is now reached for before anything about the database is asked. A machine that
answers is never asked about it and the run reads exactly as it did. A machine that does not is
told what refused it, and asked for the host, the port, the user and the password with what was
just tried as the defaults — an empty answer to the hidden password field keeps the one in
`.env` — and then it tries again. Three answers that still reach nothing end the run with the
message that names `--db-host`, `--db-port` and `--db-connection=sqlite`; so does the very first
failure on a run with nobody in front of it, so `--no-interaction` behaves exactly as before.
