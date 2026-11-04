---
'@webx-ui/php': minor
---

`webx-ui/module-payments`: optional gateway contract `DeclaresNotCardFields` — the journal's card rule leaves the values of the declared JSON and form fields alone, so a date in milliseconds or a payment id that passes Luhn stays readable and is not counted in `masked.pan`. Everywhere else numbers are looked for as before, and secrets are wiped from the declared fields too. `Testing\GatewayANotCards` is gateway A with the contract on; `Testing\FakeGateway` is no longer final.
