---
'@webx-ui/php': minor
---

Пресеты товарных фидов в `module-catalog-feeds`: Google Merchant (RSS 2.0, `g:`, только основной
`gtin`) и плоские JSON, XML, CSV. `POST catalog/feeds` с `preset` копирует пресет в новый фид, поля
формы сильнее него, неизвестный ключ — 422. `GET catalog/feeds/meta` отдаёт пресеты, форматы и
источники меток. Свой пресет — `FeedPresets::register()`.

Предупреждение про `availability_date` теперь есть и у `backorder`: Google требует дату для обоих.

В `module-catalog` запасная переменная `brand`: на сайте без спутника брендов она пустая, и шаблон с
`{{brand}}` проходит проверку имён.
