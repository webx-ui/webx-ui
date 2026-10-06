---
'@webx-ui/module-audit': patch
'@webx-ui/php': patch
---

The audit's address lists read at a glance. A finding is a card: its page, the «New» badge and the
count as a counter on one line, the table under it. Columns take their width from their type, so
the tables of one check line up. A redirect row reads from → to, the target emphasised and a
trailing slash, `www.` or `http → https` named; 302 is coloured apart from 301. Long addresses
keep to one line, losing their middle, with the whole in the panel's tooltip. A redirect check
can be read by link — one card per link with the pages it is on. On «Outgoing» a host's links are
grouped by where they lead, every page counted (`targets` in the hosts answer), and a card's
«Show all» folds back with «Collapse».
