---
'@webx-ui/php': minor
'@webx-ui/module-payments': minor
'@webx-ui/module-orders': minor
---

Payments, stage P9: the guides «Payments» (the shop) and «Payment gateways» (a satellite and its contract test), the package in the registry, `webx:demo --module=payments` switches gateway A's three methods on where the fake gateway is. After the check of P8 on lab: an attempt paid short of its amount reads «Captured 50,00 € of 92,90 €», not «Paid», on the order's tab, in the attempts and the attempt's window; the order's history writes such a capture as its own line `payment_part` («Captured … of …», by the method), not «Top-up»; an open attempt's head has square bottom corners. Money held after a failed try takes «Payment failed» down (`Orders::paymentHeld()`). The checkout's «Place the order» saves a step left unsaved first, as its «Save» would, and goes on after the redraw; a step the server refuses keeps its errors at its own fields and no order is sent.
