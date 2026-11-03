---
'@webx-ui/php': minor
---

module-payments: the gateway's notification `POST /payments/notify/{method}` — journaled before it is read (503 when the journal cannot write), a repeat `duplicate`, a bad signature `rejected`, a payment nobody has `unmatched`, an unsigned one only a reason to ask the API, a failure `error` and 500 — and the buyer's return `GET|POST /pay/return/{uuid}` settled by the gateway's API, never by the address. `Payments::settle()` and `check()`, the page of payment «we are checking» with its JSON, and gateway A's buttons sending notifications and the buyer back.
