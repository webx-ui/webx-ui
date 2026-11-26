---
'@webx-ui/php': patch
---

`module-catalog-manticore` logs in to a Manticore with `auth = 1`: `MANTICORE_USERNAME` and `MANTICORE_PASSWORD` send `Authorization: Basic` on every request; without them nothing changes. A refused login (401, 403) is not taken for an outage — the server is asked again on the next request — and `webx:doctor` and the log say what to fix without the password. A table the login has no right to, which Manticore hides, is reported and never made again.
