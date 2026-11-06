---
title: Gateway exchange
guide: payments#the-exchange-journal
---

# about

This journal keeps every message between the site and the payment gateways whole, with what the site
decided about each: it is the evidence in any dispute about a payment. Keys, cookies and card numbers
are hidden wherever they stand, and signatures are kept. On a site without a gateway it holds only
the «Marked by hand» events of manual marks.

# howto

## How to find what happened to a payment

1. Type «Order number» or «Attempt id» in full: a part finds nothing.
2. Click a row to open the event: its time, code, signature and decision.
3. Under «Same action», click the neighbouring events to follow the whole story.

The tab «Payment» of an order leads here with «All events of the order». New events appear after
«Refresh», and older ones after «Show older». Nothing leaves the journal by itself.

## How to look for what went wrong

1. Tick «Errors only»: refused decisions, error codes, lost connections and bad signatures.
2. To see only forged or broken notifications, tick «Signature errors» instead.
3. Narrow it down by gateway, method or period.
4. Open an event and read «Decision»: it says what the site did.

A notification with a bad signature changes nothing in the order. The filters stay in the address of
the page, so you can send the link to a colleague.

## How to read a request and a response

1. Open the event.
2. If the panel says the bodies are hidden, you need the permission «Raw exchange bodies»: by default
   only a super administrator has it.
3. Switch between «Formatted» and «As stored»: the first only adds indents on the screen.
4. For the exact copy, press «Download».

A very long body is marked «Cut at the limit», and its full size and SHA-256 stay with it.
