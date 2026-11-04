---
'@webx-ui/php': minor
---

`webx-ui/payment-liqpay` holds money: `CapturesPayments` with `action=hold` on the page, a capture of the whole or a part by `hold_completion`, a release by `refund` of the whole sum, the end of a hold counted from the card (10 days for Visa, 7 for the rest), and the refusals of LiqPay in words for the manager. A part can be captured only from a hold in hryvnias: LiqPay refuses it for EUR and USD, and the manager is told to capture the whole sum or release the hold.
