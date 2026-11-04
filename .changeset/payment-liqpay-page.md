---
'@webx-ui/php': minor
---

`webx-ui/payment-liqpay` takes payments: the signed form for the page of LiqPay with `paytypes` from the switches of the method and a term that is always sent, `fetchStatus()` with the whole table of statuses (`wait_accept` is paid with a reason, `try_again` stays pending, `payment_not_found` is a buyer who has not begun), and «Test the connection». The hold is still to come.
