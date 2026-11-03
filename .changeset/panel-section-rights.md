---
'@webx-ui/php': patch
'@webx-ui/module-payments': patch
---

Panel rights (webx-ui#109): the entries of «Delivery» after the first (own points, carriers, geography, popular towns, binding queue) and of «Payments» (attempts, exchange) declared no permissions of their own and were shown to every administrator; they now open with `delivery.view`/`delivery.manage` and `payments.view`/`payments.manage`, the same the section's routes ask for. The roles' matrix and the consent screen call a module that heads its own menu group by the group's title (`WebxUi\Admin\ModuleNames`): «Delivery» and «Payments», not «Methods». The signature of an exchange event wraps instead of ending in «…».
