# Платёжные шлюзы — обзор API и требования к контракту базы оплаты

Статус: обзор (webx-ui#105, этап 1), ждёт приёмки. Спека базы —
[`WEBX_UI_MODULE_PAYMENTS.md`](WEBX_UI_MODULE_PAYMENTS.md); её правка по итогам обзора — этап 2 той же
задачи (расхождения — §6). Шаблон спутника по образцу доставки —
[`WEBX_UI_MODULE_DELIVERY.md`](WEBX_UI_MODULE_DELIVERY.md) §13.

**Зачем.** Решение человека 03.11.2026: прежде чем фиксировать контракт базы, изучить API популярных
шлюзов, чтобы **любой из них подключался новым спутником `payment-<шлюз>` без правки кода базы**. Первый
спутник — LiqPay; дальше — по запросу клиентов (платная опция).

**Как собрано.** Только публичная официальная документация шлюзов, прочитанная 03.11.2026; у каждого
факта — ссылка. Регистраций, ключей и запросов к API не было. **«Не подтверждено»** — в публичных доках
этого нет или доки противоречат себе; такое не додумано, а вынесено в вопросы (§7). Названия шлюзов
живут только в этом обзоре и в будущих спутниках: в коде и тестах базы — по-прежнему «шлюз A» и
`FakeGateway` (решение 10 спеки оплаты).

Содержание: §1 — сводная таблица; §2 — карточки шлюзов; §3 — требования к контракту; §4 — «ложится
ли без правки базы» по каждому шлюзу; §5 — порядок спутников; §6 — расхождения со спекой оплаты; §7 —
открытые вопросы.

## 1. Сводная таблица

Обозначения: ✓ — есть по докам; — — нет; ? — не подтверждено; «десят.» — сумма десятичной строкой или
числом (`12.50`), «мин.» — целым в минимальных единицах (`1250`).

| Шлюз               | Страница шлюза | Виджет / iframe | Создание               | Сумма  | Подпись уведомления                            | Ответ на уведомление | Опрос статуса | Холд                   | Возврат через API              | Части               | Комиссия в ответе | PHP SDK        |
| ------------------ | -------------- | --------------- | ---------------------- | ------ | ---------------------------------------------- | -------------------- | ------------- | ---------------------- | ------------------------------ | ------------------- | ----------------- | -------------- |
| LiqPay             | ✓ форма POST   | ✓               | подписанная форма      | десят. | хеш `key+data+key` (SHA3-256 или SHA1 — ?)     | ?                    | ✓             | ✓ 7/10 дн.             | ✓ частичный                    | ✓ через `paytypes`  | ✓                 | есть, устарел  |
| mono (plata)       | ✓ редирект     | ✓ iframe        | серверный JSON         | мин.   | ECDSA по сырому телу, ключ из API              | любой 200            | ✓             | ✓ 9 дн., только UAH    | ✓ несколько частичных, асинхр. | отдельный API       | ✓                 | нет            |
| WayForPay          | ✓ форма POST   | ✓               | подписанная форма      | десят. | HMAC-MD5 по полям                              | **подписанный JSON** | ✓             | ✓ до 20 дн.            | ✓ частичный                    | ✓ `payParts*`       | ✓                 | есть, заброшен |
| Portmone           | ✓ форма POST   | ✓ popup/iframe  | подписанная форма      | десят. | **нет** (?)                                    | XML / JSON `RESULT`  | ✓             | ✓ 7 дн.                | ✓ несколько частичных          | ✓ `installment`     | ✓                 | нет            |
| NovaPay            | ✓ редирект     | ✓ iframe        | серверный JSON, 2 шага | десят. | RSA по сырому телу (по SDK; в справочнике — ?) | 2xx (по SDK)         | ✓             | ✓ 29 дн.               | ✓ ограничено                   | ?                   | ?                 | есть           |
| iPay.ua            | ✓ редирект     | ?               | серверный XML          | мин.   | HMAC от соли, **не покрывает данные**          | HTTP 200             | ✓             | режим мерчанта, 14 дн. | ✓ по согласованию              | ?                   | ✓                 | ?              |
| Tranzzo            | ✓ редирект 303 | ✓               | серверный JSON         | десят. | хеш `secret+data+secret` (SHA1)                | ?                    | ✓             | ✓                      | ✓ частичный                    | ✓ `privat_payparts` | ✓                 | ?              |
| Приват «Частинами» | ✓ редирект     | ✓ iframe        | серверный JSON         | десят. | SHA1 `pass+…+pass`                             | ?                    | ✓             | ✓ подтверждает магазин | ✓ частичный                    | — (сам рассрочка)   | ?                 | нет            |
| Stripe             | ✓ редирект     | ✓               | серверный, form-enc.   | мин.   | HMAC-SHA256 по `t.raw_body`                    | 2xx                  | ✓             | ✓ 7 дн.                | ✓ несколько частичных, асинхр. | ✓ BNPL              | ✓ позже           | есть           |
| PayPal             | ✓ редирект     | ✓ кнопки        | серверный JSON         | десят. | сертификат + CRC32 тела или API проверки       | 2xx                  | ✓             | ✓ 3/29 дн.             | ✓ несколько частичных, асинхр. | ✓ Pay Later         | ✓ позже           | есть           |

**Fondy** в Украине не работает (§2.11) и в обзор не входит.

## 2. Карточки шлюзов

В каждой карточке — то, что важно для контракта; полный разбор по десяти пунктам брифа — в источниках
по ссылкам. Пункты: 1 — сценарии; 2 — создание платежа; 3 — холд; 4 — уведомления, возврат покупателя,
опрос; 5 — статусы; 6 — возвраты; 7 — споры; 8 — деньги; 9 — токены; 10 — настройки, тест, SDK, лимиты.

### 2.1. LiqPay

Источники: checkout — <https://www.liqpay.ua/en/doc/api/internet_acquiring/checkout>; callback —
<https://www.liqpay.ua/uk/doc/api/callback>; двухстадийная — <https://www.liqpay.ua/uk/doc/api/internet_acquiring/two_step?tab=0>;
возврат — <https://www.liqpay.ua/uk/doc/api/internet_acquiring/refund?tab=0>; виджет —
<https://www.liqpay.ua/uk/doc/api/internet_acquiring/widget?tab=0>; инвойс —
<https://www.liqpay.ua/uk/doc/api/internet_acquiring/invoice?tab=0>; статус —
<https://www.liqpay.ua/uk/doc/api/information/status_payment?tab=0>; тест — <https://www.liqpay.ua/uk/doc/api/testing>;
ошибки — <https://www.liqpay.ua/uk/doc/api/errors>; SDK — <https://github.com/liqpay/sdk-php>.

1. Страница шлюза: форма POST `data` + `signature` на `/api/3/checkout`; виджет `LiqPayCheckout.init`
   (embed / popup); счёт со ссылкой `invoice_send` (ссылка `href`, срок `expired_date`); Apple Pay и
   Google Pay — `paytypes` `apay`, `gpay`; рассрочка и «оплата частинами» — `paytypes` `moment_part`,
   `paypart` на той же странице, магазин получает всю сумму (checkout).
2. `data = base64(json)`, `signature = base64(hash(private_key + data + private_key))`. Обязательные:
   `version`, `public_key`, `action`, `amount`, `currency`, `description`, `order_id` (≤ 255).
   Необязательные: `result_url`, `server_url`, `expired_date`, `language`, `paytypes`, `rro_info`
   (фискализация; товары — по `id` из кабинета LiqPay, заводятся заранее). **Алгоритм хеша — не
   подтверждено:** текст доков и пример с таблицей — SHA3-256 (пример пересчитан), а примеры кода на
   тех же страницах и официальный SDK — SHA1; дата перехода и приём обоих — не описаны. Версия API: в
   таблице «7», в примерах и SDK — «3». Повтор `order_id` — ошибка `2915` «order_id вже існує»
   (ошибки); ключа идемпотентности нет. Срок по умолчанию без `expired_date` — не подтверждено.
3. `action=hold` → `hold_completion` с `amount` (частичное списание — остаток разблокируется сам);
   гарантия блокировки 7 дней (Mastercard) / 10 дней (Visa), завершить можно до 30 дней; отмена холда —
   через `refund` (двухстадийная).
4. POST на `server_url` с `data` и `signature`; подписана **строка base64**, а не разобранный JSON;
   шлётся по конечному статусу (callback). Повторы, порядок и ожидаемый ответ — не подтверждено.
   `result_url` — переадресация покупателя, что в ней приходит — не подтверждено. Опрос —
   `action=status` по `order_id` (статус).
5. Конечные: `success`, `failure`, `error`, `reversed`, `subscribed`, `unsubscribed`; ждут действия
   покупателя: `3ds_verify`, `otp_verify`, `cvv_verify` и ещё 8 `*_verify`/`wait_*`; прочие:
   `processing`, `prepared`, `hold_wait`, `invoice_wait`, `cash_wait`, `wait_accept` (деньги списаны,
   магазин не прошёл проверку), `wait_secure`, `wait_compensation`, `wait_reserve` и др. (callback).
6. `action=refund` с `amount` — частичный; несколько частичных — не подтверждено (косвенно: поля
   `refund_amount`, `refund_date_last` в callback). Возврат может ждать «за рахунок майбутніх
   платежів» (`wait_amount: true`); о возврате приходит callback (возврат).
7. Споры событиями — не подтверждено.
8. Валюты UAH, USD, EUR, другие — по запросу; сумма десятичная; комиссии — `sender_commission`,
   `receiver_commission`, `agent_commission` в callback.
9. Токены (`recurringbytoken`), подписки (`action=subscribe`) — есть.
10. В панели: `public_key`, `private_key`. Песочница — ключи с префиксом `sandbox_`, тестовые карты
    успеха, 3DS, OTP, отказа (тест). SDK — один класс на SHA1, последний коммит 2025-04-23: свой тонкий
    клиент проще. Лимиты — не подтверждено (есть только ошибка `109` «ліміт спроб з IP»).

### 2.2. monobank — plata by mono (эквайринг)

Источники: OpenAPI эквайринга — <https://api.monobank.ua/docs/acquiring.html>; методы —
<https://monobank.ua/api-docs/acquiring/methods/ia>; вебхуки —
<https://monobank.ua/api-docs/acquiring/dev/webhooks>; тест — <https://monobank.ua/api-docs/acquiring/dev/test>;
iframe — <https://monobank.ua/api-docs/acquiring/methods/ia/docs--widget-frame>; ПРРО —
<https://monobank.ua/api-docs/acquiring/extras/prro>; «Покупка Частинами» — <https://monobank.ua/api-docs/chast>.

1. `POST /api/merchant/invoice/create` → `pageUrl` (редирект); iframe — `displayType: "iframe"`;
   ссылка менеджеру — тот же `pageUrl` (`validity` по умолчанию 24 ч, максимум 30 дней); Apple Pay /
   Google Pay — на странице шлюза; кнопка monopay и диплинк `appUrl`. Рассрочка — **отдельный API**
   («Покупка Частинами», ниже).
2. JSON с заголовком `X-Token`; обязателен только `amount`; `ccy` — числовой ISO 4217 (980 по
   умолчанию); номер заказа — `merchantPaymInfo.reference`; состав `basketOrder[]` обязателен **только
   при подключённом ПРРО** (ПРРО). Заголовки `X-Cms`, `X-Cms-Version` — для модулей CMS. Ключа
   идемпотентности нет, каждый вызов — новый `invoiceId`; неоплаченный счёт гасится
   `invoice/remove`.
3. `paymentType: hold` — 9 дней, **только UAH**; `invoice/finalize` с суммой (частичное — с `items`);
   ответ — «заявка принята»; явная отмена холда через API — не подтверждено (методы).
4. Вебхук — POST JSON, тело как ответ «статус счёта»; подпись `X-Sign` — **ECDSA P-256 / SHA-256 по
   сырым байтам тела**, публичный ключ — `GET /api/merchant/pubkey` (кешировать, перезапрашивать при
   несовпадении); до 3 повторов, пока нет 200, тело ответа не важно; **порядок не гарантирован**
   (`success` раньше `processing`) — сводить по `modifiedDate`; **на `expired` вебхука нет** (OpenAPI,
   вебхуки). Опрос — `GET /api/merchant/invoice/status`. Что приходит на `redirectUrl` — не
   подтверждено.
5. Счёт: `created`, `processing`, `hold`, `success`, `failure` (с `failureReason`, `errCode`),
   `reversed`, `expired`; заявки возврата в `cancelList[]`: `processing`, `success`, `failure`.
6. `invoice/cancel` с `amount` — частичный, несколько (каждый с `extRef`), **асинхронно**; итог — в
   `cancelList` вебхука о смене счёта (OpenAPI).
7. Споры — не подтверждено.
8. Сумма — целым в минимальных единицах; комиссия `paymentInfo.fee`; маска карты, `rrn`.
9. Токены (`saveCardData`, включает поддержка), подписки — есть.
10. В панели — один `X-Token`. Тестовый токен — любому клиенту банка, любая карта по Луну (тест).
    Официального PHP SDK нет. Лимиты: есть ответ `429`, цифр нет.

**«Покупка Частинами» mono** — свой хост (`u2.monobank.com.ua`, песочница `u2-demo-ext.mono.st4g3.com`),
свои ключи (`store-id`, `signature = base64(HMAC-SHA256(тело, secret))`, колбэк подписан так же),
десятичные суммы; поток: `order/create` → push в приложение → `WAITING_FOR_STORE_CONFIRM` → магазин
**обязан** вызвать `order/confirm` после выдачи товара или `order/reject`; колбэк только на ожидание
подтверждения и на отказы, остальное — опросом `order/state`; повтор `store_order_id` возвращает
существующую заявку; 3–25 платежей.

### 2.3. WayForPay

Источники: Purchase — <https://wiki.wayforpay.com/en/view/852102>; виджет —
<https://wiki.wayforpay.com/en/view/852091>; инвойс — <https://wiki.wayforpay.com/en/view/608996852>;
Settle — <https://wiki.wayforpay.com/en/view/852113>; Refund — <https://wiki.wayforpay.com/en/view/852115>;
Check status — <https://wiki.wayforpay.com/en/view/852117>; коды — <https://wiki.wayforpay.com/uk/view/852131>;
тест — <https://wiki.wayforpay.com/en/view/852472>; ПРРО — <https://help.wayforpay.com/view/446189895>;
SDK — <https://github.com/wayforpay/php-sdk>.

1. Подписанная форма POST на `secure.wayforpay.com/pay`; тот же запрос с `behavior=offline` отдаёт
   JSON со ссылкой; виджет `pay-widget.js`; инвойс `CREATE_INVOICE` → `invoiceUrl`; Apple Pay, Google
   Pay, «оплата частинами» шести банков (`payParts*`) — в `paymentSystems` (Purchase).
2. Обязательные: `merchantAccount`, `merchantDomainName`, `orderReference`, `orderDate`, `amount`,
   `currency`, **`productName[]`, `productPrice[]`, `productCount[]`**, `merchantSignature`. Подпись
   — HMAC-MD5 строки через `;`: `merchantAccount;merchantDomainName;orderReference;orderDate;amount;currency`,
   затем все имена, все количества, все цены (Purchase). Повтор `orderReference` — код 1112
   «Duplicate Order ID» (коды). Срок — `orderLifetime`, `orderTimeout` (сек), статус `Expired` ставится
   сам. Фискализация — услуга пРРО в кабинете, полей в API нет (ПРРО).
3. `merchantTransactionType=AUTH` → `SETTLE` (частичное — сумма меньше), холд `holdTimeout` до 20
   дней; снятие холда — `REFUND` по `WaitingAuthComplete` → `Voided`, частичное снятие — нельзя (1141).
4. `serviceUrl` — POST JSON при каждой смене статуса; подпись HMAC-MD5
   `merchantAccount;orderReference;amount;currency;authCode;cardPan;transactionStatus;reasonCode`.
   **Ответ обязателен и подписан:** `{orderReference, status: "accept", time, signature}`, подпись
   `orderReference;status;time`; без него повторы до 4 суток (Purchase). `returnUrl` — набор полей не
   описан. Опрос — `CHECK_STATUS`, ответ подписан как уведомление.
5. `InProcessing`, `WaitingAuthComplete`, `Approved`, `Pending` (антифрод), `Expired`, `Declined`,
   `Refunded`, `Voided`, `RefundInProcessing`; 3DS — не статус, а код 5100 (коды).
6. `REFUND` с `amount` и `comment`, ответ синхронный; без денег на балансе магазина —
   `RefundInProcessing` (1138); несколько частичных — не подтверждено (есть 1128 «превышено
   количество попыток возврата»).
7. Споры — не подтверждено.
8. Сумма десятичная, валюта — UAH (альтернативная — USD, EUR для показа); комиссия `fee`.
9. Токены `recToken`, регулярные платежи — есть.
10. В панели: `merchantAccount`, секретный ключ, домен магазина (входит в подпись). Публичный тестовый
    мерчант `test_merch_n1` с ключом на боевом адресе (тест). SDK — v1.0.11 от 2020, `SETTLE` не умеет.
    Лимиты — не подтверждено.

### 2.4. Portmone

Источник (одна страница, разделы по номерам): <https://docs.portmone.com.ua/docs/en/PaymentGatewayEng/>;
Apple Pay — <https://docs.portmone.com.ua/docs/en/APayEng/>; Google Pay —
<https://docs.portmone.com.ua/docs/en/GPayEng/>.

1. Форма POST или JSON на `/gateway/` (§3.1, §3.2); popup и iframe (§3.4); ссылки на оплату по e-mail,
   SMS и `getLinkInvoice` (§12); Apple Pay / Google Pay — `paymentTypes`; части — `installment=Y` с
   планом банков (§3.7).
2. Подпись только у создания: HMAC-SHA256 строки
   `upper(payeeId . dt . bin2hex(shopOrderNumber) . billAmount) . upper(bin2hex(login))` (§3.5).
   **Остальные методы — `login` и `password` открыто в теле** (§6.2, §8.2, §9.1). Товары `goods[]` с
   УКТЗЕД и ставками — в ссылках и возвратах, фискализация — §14. Один `shopOrderNumber` — несколько
   платёжных документов `shopBillId` (попытки, инвойсы, возвраты); если по заказу уже есть
   `PAYED`/`PREAUTH` — повторная оплата отклоняется («Transaction statuses and lifecycle»). Срок —
   `exp_time`.
3. `preauth_flag=Y` → `set_paid` с `postauth_amount` (частичное) или `reject`; автосписание и
   автоотмена по датам; держать не дольше 7 дней; через Privat24 холд не работает (§6).
4. Возврат покупателя на `success_url` — **POST** с данными платежа; доки требуют перепроверить статус
   и сумму методом `result` (§1, §3.1). Серверное уведомление — XML в поле `data` (`BILLS`) только об
   **успешной** оплате, либо JSON; ответ — XML `RESULT` / JSON `{errorCode, reason, responseId}`;
   дедуп по `BILL_ID` (§8.3, §8.5). **Подпись уведомления не описана** (не подтверждено), адрес
   уведомлений задаётся при подключении, не в запросе. Опрос — `method=result` (§8.2).
5. Статус — у документа `shopBillId`: `CREATED`, `PREAUTH`, `PAYED`, `REJECTED` (**бывает
   промежуточным во время 3DS**), `RETURN` (отдельный документ с отрицательной суммой).
6. `method=return` — полный и частичный, каждый — новый документ `RETURN`; рекомендуют не раньше 24 ч;
   `reject` — только в день оплаты (§9). Уведомления о возврате — не подтверждено.
7. Событий нет, только флаг `chargeback` в ответе `result` (§8.2.2).
8. UAH, USD, EUR, GBP, PLN, KZT; сумма десятичная; комиссия `commission`.
9. Токены, подписки — есть (§4).
10. В панели: `payeeId`, `login`, `password`, ключ подписи. Тестовый режим включает менеджер на боевом
    адресе, тестовые карты (§2). Официального PHP SDK нет. Лимиты — не подтверждено.

### 2.5. NovaPay (финтех группы «Нова пошта»)

Источники: оглавление — <https://novapay.readme.io/llms.txt>; создание сессии —
<https://novapay.readme.io/reference/create-session>; платёж — <https://novapay.readme.io/reference/add-payment>;
холд — <https://novapay.readme.io/reference/complete-hold>; возврат/отмена —
<https://novapay.readme.io/reference/void-session>; статус — <https://novapay.readme.io/reference/get-status>;
статусы — <https://novapay.readme.io/reference/session-statuses.md>; постбек —
<https://novapay.readme.io/reference/v2-acquiring-postback-current-version.md>; подпись запросов —
<https://novapay.readme.io/reference/authentication.md>; ограничения возврата —
<https://nova-pay.atlassian.net/wiki/spaces/EXT/pages/1107853313/Refund>; Чекаут —
<https://novapay.readme.io/reference/checkout.md>; SDK — <https://github.com/NovaPay/novapay-php>.

1. Страница NovaPay (карта, Apple Pay, Google Pay, кошелёк NovaPay), iframe; отдельный продукт
   «Чекаут NovaPay» — оплата и доставка «Новой почтой» в одном потоке, включая оплату при получении.
   Рассрочка, отдельные платёжные ссылки — не подтверждено.
2. Два серверных вызова: `POST /v1/session` (обязательны `merchant_id`, **`client_phone`**; адреса
   `callback_url`, `success_url`, `fail_url`) → `POST /v1/payment` (`amount`, `external_id`,
   `use_hold`, `products[]`) → `url`. **Валюты в запросе нет** (только гривна). Запросы подписаны
   заголовком `x-sign` = base64(RSA-SHA256(тело)) приватным ключом мерчанта. Сессия живёт 30 дней.
   SDK прямо пишет: `addPayment` не идемпотентен — при таймауте сначала `get-status`.
3. `use_hold: true` → `/v1/complete-hold` (частичное — меньшей суммой), подтвердить в 29 дней; **с
   блоком доставки холд обязателен**; отмена — `/v1/void`.
4. Постбек POST JSON при каждой смене статуса сессии, схема v2 с 01.10.2025. **Подпись постбека в
   справочнике не описана;** по официальному SDK — заголовок `x-sign-v2`, RSA по сырому телу,
   проверка публичным ключом NovaPay, без метки времени (повтор старого постбека проходит — дедуп на
   магазине); не-2xx — повтор (SDK). Опрос — `/v1/get-status`. Возможна привязка запросов к IP
   магазина (ошибка `IpAddressNotRegisteredError`).
5. `created`, `expired`, `processing`, `holded`, `hold_confirmed`, `processing_hold_completion`,
   `paid`, `failed`, `processing_void`, `voided`; в Чекауте — ещё `precreated`.
6. `/v1/void`: полный или частичный; частичный — **только клиентам со счётом NovaPay**, одна успешная
   операция возврата на операцию, на карту другого банка — только в день оплаты; асинхронно
   (`processing_refund`), ответ пустой (ограничения возврата). Постбек о возврате — не подтверждено.
7. Споры — не подтверждено.
8. Сумма — **десятичные гривны числом** (`100.25`), в `get-status` — строками; комиссия в ответе — не
   подтверждено.
9. Токены и рекуррент — не подтверждено.
10. В панели: `merchant_id`, **приватный ключ RSA (PEM, многострочный)**; публичный ключ NovaPay —
    из доков. Песочница `api-qecom.novapay.ua`, `merchant_id=2`, тестовые карты. Официальный SDK
    `novapay-ua/novapay` (PHP 8.1+). Лимиты — не подтверждено.

### 2.6. iPay.ua

Источник: <https://checkout.ipay.ua/doc> (Checkout API v1.17, правка 29.08.2026; одна страница).

1. Только страница шлюза: `PaymentCreate` → `payment.url`; Apple Pay, Google Pay — на проде;
   виджет и рассрочка — не подтверждено.
2. `POST /api302`, **XML в поле `data`**; обязательные `auth{mch_id, salt, sign}`, `urls`,
   `transaction{amount, currency, desc, info}`, `lifetime` (часы); `ext_id` уникален у мерчанта и
   служит ключом статуса; фискальный чек — `info.receipt_data` прямо в API.
3. Предавторизация — **режим мерчанта**, не параметр запроса; `Completion` (меньшая сумма —
   частичное); неподтверждённую авторизацию **банк спишет сам через 14 дней**; `Reversal` — только в
   день оплаты и целиком.
4. Адрес уведомлений **прописывает iPay по заявке**; POST, XML в поле `xml`; ответ HTTP 200, иначе
   повтор каждые 2 минуты, дубли возможны. **Подпись не покрывает данные:** `salt = sha1(microtime)`,
   `sign = HMAC-SHA512(salt, sign_key)` — подделку тела по ней не поймать, статус перепроверяется
   `Status`. Возврат покупателя — `urls.good` / `urls.bad`.
5. `1` создан, `3` авторизован (холд), `4` неуспешный, `5` успешный, `9` отменён.
6. `Refund` — по согласию iPay, со следующего дня; частичный; ответ синхронный.
7. Споры — не подтверждено.
8. Сумма — в копейках; **комиссия видна**: `invoice` без комиссии, `amount` с ней.
9. Токены — не подтверждено.
10. В панели: `mch_id`, `sign_key`. Песочница `sandbox-checkout.ipay.ua`, тестовые карты. SDK и
    лимиты — не подтверждено.

### 2.7. Tranzzo

Источники: варианты интеграции — <https://docs.tranzzo.com/uk/docs/integration/options/>; API платежа —
<https://docs.tranzzo.com/uk/docs/transactions-purchase/api/>; двухстадийная —
<https://docs.tranzzo.com/uk/docs/transactions-2-step/auth/>; подпись — <https://docs.tranzzo.com/uk/docs/api-usage/security/>;
вебхуки — <https://docs.tranzzo.com/uk/docs/webhooks/structure/>; статусы —
<https://docs.tranzzo.com/uk/docs/status-codes/payment-cycle/>; возвраты —
<https://docs.tranzzo.com/uk/docs/transactions-refund/overview/>; части —
<https://docs.tranzzo.com/uk/docs/payment-methods/buy-now-pay-later/privatpay-by-parts/>; тест —
<https://docs.tranzzo.com/uk/docs/testing/cards/>.

Взят как шлюз с самой полной публичной документацией среди «прочих» и как агрегатор «Оплаты частинами»
ПриватБанка. Platon и EasyPay не проверялись.

1. Страница шлюза, виджет, платёжные ссылки из портала, CMS-плагины; части ПриватБанка — `payway:
privat_payparts` (2–25 частей, от 300 UAH).
2. `POST /api/v1/payment` JSON; обязательны `pos_id`, `mode`, `method` (`auth`/`purchase`), `amount`,
   `currency`, `description`, `order_id` (уникален, повтор — ошибка), **`customer_email`,
   `customer_phone`**; ответ hosted — **HTTP 303 и `location`**. Запросы — `X-API-AUTH:
CPAY-HMAC-SHA256 {key}:{hex(HMAC-SHA256(тело))}`.
3. `method=auth` → capture / void; пример срока холда — 24 ч; частичный capture — не подтверждено.
4. Вебхук — form-urlencoded `data` (base64url JSON) и `signature = base64url(SHA1(secret + data +
secret))` — **алгоритм другой, чем у запросов**; с IP `35.187.74.148`; дубли возможны; повторную
   отправку можно запросить API. Ожидаемый ответ и расписание повторов — не подтверждено.
5. `Init`, `Pending` (в том числе ждёт 3DS), `Success`, `Failure`.
6. Полный и частичный; нужен баланс в Tranzzo; вебхук `refund`; несколько частичных — не подтверждено.
7. Споры — не подтверждено.
8. Сумма десятичная (≤ 2 знака); комиссия `fee`, `fixed_fee`, `percent_fee`.
9. Токены, подписки — есть.
10. В панели: `pos_id`, `API_KEY`, `API_SECRET`; тест — отдельный тестовый проект со своими ключами.
    SDK, лимиты — не подтверждено.

### 2.8. ПриватБанк — «Оплата частинами» (прямой API)

Источники: <https://bw.gitbooks.io/api-oc/content/> (с чего начать); создание —
<https://bw.gitbooks.io/api-oc/content/Create.html>; с холдом — <https://bw.gitbooks.io/api-oc/content/hold.html>;
колбэк — <https://bw.gitbooks.io/api-oc/content/callback.html>; подтверждение —
<https://bw.gitbooks.io/api-oc/content/confirm.html>; возврат — <https://bw.gitbooks.io/api-oc/content/decline.html>;
статус — <https://bw.gitbooks.io/api-oc/content/state.html>. Часть страниц GitBook отдаёт 404.

1. Только рассрочка, четыре вида (`II`, `PP`, `PB`, `IA`); `create` → `token` → редирект на страницу
   банка (можно во фрейме).
2. `POST /ipp/v2/payment/create` или `/payment/hold`; обязательны `storeId`, `orderId`, `amount`
   (**300…50 000**), `partsCount` (**2…25**), `merchantType`, **`products[]`** (входят в подпись);
   подпись `base64(SHA1(password + storeId + orderId + сумма_без_точки + … + товары + password))`.
3. С холдом: клиент подтверждает картой и OTP → колбэк `LOCKED` → магазин **`confirm` или `cancel`**;
   срок холда — не подтверждено. Без холда подтверждать не нужно.
4. Колбэк POST JSON `{storeId, orderId, paymentState, message, signature}`, подпись общим паролем;
   **с фиксированных IP банка**. Ответ и повторы — не подтверждено. Опрос — `payment/state`.
5. `CREATED`, `CLIENT_WAIT`, `OTP_WAITING`, `PP_CREATION`, `LOCKED`, `SUCCESS`, `FAIL`, `CANCELED`.
6. `payment/decline` с суммой — частичный; ответ синхронный.
7. Споры — не подтверждено.
8. Суммы десятичные в JSON, в подписи — без точки; валюты в запросе нет; комиссия — не подтверждено.
9. Токены — неприменимо.
10. В панели: `storeId`, пароль магазина; песочница `/ipp/sandbox`; SDK нет (пакет на Packagist
    заброшен с 2016).

### 2.9. Stripe

Источники: Checkout Sessions — <https://docs.stripe.com/api/checkout/sessions/create>; объект —
<https://docs.stripe.com/api/checkout/sessions/object>; выдача заказа — <https://docs.stripe.com/checkout/fulfillment?ui=full-page>;
вебхуки — <https://docs.stripe.com/webhooks>; идемпотентность — <https://docs.stripe.com/api/idempotent_requests>;
холд — <https://docs.stripe.com/payments/place-a-hold-on-a-payment-method>; возвраты —
<https://docs.stripe.com/refunds>; события — <https://docs.stripe.com/api/events/types>; валюты —
<https://docs.stripe.com/currencies>; страны — <https://stripe.com/global>; лимиты —
<https://docs.stripe.com/rate-limits>; SDK — <https://packagist.org/packages/stripe/stripe-php>.

1. Checkout Session: страница Stripe (`url`), встроенная форма, Payment Element; Payment Links; Apple
   Pay / Google Pay; BNPL (Klarna и др.).
2. `POST /v1/checkout/sessions`, **`line_items` обязательны** в `mode=payment` (можно одной строкой
   `price_data`); связь с заказом — `client_reference_id`, `metadata`. Идемпотентность — заголовок
   `Idempotency-Key`. Срок сессии `expires_at` 30 мин…24 ч.
3. `capture_method=manual` → `requires_capture`; частичное списание одно, остаток освобождается;
   карта — 7 дней; отмена — `cancel` PaymentIntent.
4. Вебхук JSON Event; `Stripe-Signature: t=…,v1=…` — HMAC-SHA256 секретом `whsec_…` по
   `"{t}.{raw_body}"`, допуск по времени; повторы до 3 дней; **порядок не гарантирован, дубли** —
   дедуп по `event.id`; ответ 2xx. Страница возврата не доверенная — сессия дочитывается API. Опрос —
   `GET` сессии / PaymentIntent.
5. Сессия `open` / `complete` / `expired` и **отдельно** `payment_status` `paid` / `unpaid` /
   `no_payment_required`: для асинхронных методов сессия `complete`, а деньги — позже
   (`async_payment_succeeded`). PaymentIntent: `requires_action` (3DS), `processing`,
   `requires_capture`, `succeeded`, `canceled`.
6. Несколько частичных; асинхронно (`pending` → `succeeded` / `failed` до 30 дней); события `refund.*`,
   `charge.refunded`.
7. **Споры — событиями** `charge.dispute.*`.
8. Сумма — целым в минимальных единицах, с исключениями (zero-decimal, ISK, UGX); UAH принимается как
   валюта оплаты, но **Украина не в списке стран аккаунта** (страны) — нужно юрлицо в поддерживаемой
   стране. Комиссия — в `balance_transaction`, позже.
9. Сохранение карт, подписки — есть.
10. Ключи `sk_`/`rk_`, секрет вебхука на каждый адрес и режим; тестовый режим — отдельные ключи.
    SDK `stripe/stripe-php` официальный. Лимиты 100 запросов/с live, 25 — тест.

### 2.10. PayPal

Источники: Orders v2 — <https://developer.paypal.com/docs/api/orders/v2/>; Payments v2 —
<https://developer.paypal.com/docs/api/payments/v2/>; интеграция — <https://developer.paypal.com/checkout/integrate/>;
вебхуки — <https://developer.paypal.com/api/rest/webhooks/rest/>, <https://developer.paypal.com/api/rest/webhooks/>;
события — <https://developer.paypal.com/api/rest/webhooks/event-names/>; авторизация —
<https://developer.paypal.com/docs/checkout/standard/customize/authorization/>; валюты —
<https://developer.paypal.com/api/rest/reference/currency-codes/>; идемпотентность —
<https://developer.paypal.com/api/rest/reference/idempotency/>; SDK —
<https://packagist.org/packages/paypal/paypal-server-sdk>.

1. Кнопки JS SDK (всплывающее окно) или редирект по ссылке `approve`; Payment Links; Apple Pay /
   Google Pay; Pay Later (в 8 странах).
2. `POST /v2/checkout/orders` (OAuth2 Bearer): `intent`, `purchase_units[].amount`; позиции
   необязательны. Идемпотентность — `PayPal-Request-Id`. 6 ч до одобрения и 6 ч после.
3. `intent=AUTHORIZE` → capture; honor period 3 дня, авторизация 29 дней; **несколько списаний**
   (`final_capture=false`); void.
4. **Одобрение покупателем — ещё не оплата: магазин обязан сам вызвать `capture`** (или `authorize`)
   после возврата покупателя, иначе денег нет и заказ протухнет (интеграция). Вебхук JSON; подпись —
   строка `transmissionId|timeStamp|webhookId|crc32(raw_body)`, проверяемая сертификатом по
   `paypal-cert-url`, или вызов `verify-webhook-signature`; до 25 повторов за 3 дня; ответ 2xx;
   порядок — не подтверждено. Опрос — `GET` заказа.
5. Заказ: `CREATED`, `SAVED`, `APPROVED`, `VOIDED`, `COMPLETED`, `PAYER_ACTION_REQUIRED`; списание:
   `COMPLETED`, `PENDING`, `DECLINED`, `REFUNDED`, `PARTIALLY_REFUNDED`, `FAILED`. `COMPLETED` заказа
   при `PENDING`/`DECLINED` списания — не оплата.
6. Несколько частичных; асинхронно (`PENDING` → `COMPLETED` / `FAILED`); событие
   `PAYMENT.CAPTURE.REFUNDED`.
7. **Споры — событиями** `CUSTOMER.DISPUTE.*`.
8. Сумма — десятичная строка; 25 валют, **UAH нет**; комиссия — `seller_receivable_breakdown`, у
   `PENDING` её нет. Приём платежей украинским бизнесом — не подтверждено.
9. Vault, подписки — есть.
10. Client ID и Secret (токен OAuth кешировать), Webhook ID; песочница — отдельные ключи. SDK
    `paypal/paypal-server-sdk` (старые — в архиве). Лимиты — цифр нет, `429`.

### 2.11. Fondy — не работает в Украине

`fondy.ua` отвечает 502, `docs.fondy.eu` недоступен; `fondy.io` — британская Fondy Ltd под надзором FCA,
Украины и UAH в документации нет (<https://fondy.io/>, <https://docs.fondy.io/getting-started/fondy-gateway/>).
Причина — из новостей, не с сайта шлюза: в 2024 НБУ отменил регистрацию украинского оператора Fondy
в Visa/Mastercard (<https://interfax.com.ua/news/general/983650.html>). Официального «Fondy → Flitt»
нет; Flitt (<https://docs.flitt.com/supported-countries/>) Украину не обслуживает. В план спутников не
входит.

## 3. Требования к контракту базы

Главный вывод обзора: **общий скелет спеки оплаты (страница шлюза → подписанное уведомление и/или опрос
→ `settle()`) держит все шлюзы**, но восемь мест в контракте нужно расширить, иначе часть шлюзов
потребует правки базы. Они собраны в §3.9 и §6.

### 3.1. Общий знаменатель — что умеют все

| Что                                      | Все? | Примечание                                                                             |
| ---------------------------------------- | ---- | -------------------------------------------------------------------------------------- |
| страница шлюза, куда уходит покупатель   | да   | редирект на адрес от API или автоотправка подписанной формы                            |
| свой номер платежа магазина (наш `uuid`) | да   | уникален у шлюза; повтор номера — ошибка «дубль» (LiqPay, WayForPay, Tranzzo, Приват)  |
| запрос статуса по API                    | да   | единственное, чему можно верить у Portmone и iPay                                      |
| серверное уведомление о смене статуса    | да   | у Portmone и iPay адрес задаётся при подключении, а не в запросе                       |
| адрес возврата покупателя                | да   | у всех **не доверенный**; у Portmone — POST                                            |
| одностадийная оплата                     | да   | у NovaPay с доставкой «Новой почтой» холд обязателен — только без блока доставки       |
| тестовый режим                           | да   | отдельные ключи (LiqPay, mono, Stripe, PayPal, NovaPay, Tranzzo) или режим у менеджера |
| маска карты, причина отказа в ответе     | да   | без PAN и CVV — страница шлюза                                                         |

**Чего нет у всех** (значит, не может быть обязательным): подпись уведомления, покрывающая данные;
определённый ответ на уведомление; возврат через API без ограничений; холд; ссылка шлюза на оплату;
виджет; комиссия в ответе; список товаров; несколько валют.

### 3.2. Флаги возможностей — необязательные контракты

По образцу доставки (`CreatesWaybills`, `TracksShipments`, `DescribesWaybill::supportsCod()`): тип
спутника реализует интерфейс — база показывает действие; нет — не показывает. Новая возможность —
новый интерфейс, старые спутники не ломаются.

| Возможность                        | Контракт                                     | У кого                                                                                                        | v1?                                         |
| ---------------------------------- | -------------------------------------------- | ------------------------------------------------------------------------------------------------------------- | ------------------------------------------- |
| онлайн-платёж через страницу шлюза | `ChargesOnline` (есть)                       | все                                                                                                           | да                                          |
| закрыть незавершённую попытку      | `CancelsPayments` (есть)                     | mono (`remove`), LiqPay (`invoice_cancel`), Stripe (`expire`), NovaPay (`/v1/expire`), PayPal (протухает сам) | да                                          |
| **серверный шаг после одобрения**  | `CompletesApproval` (новый, §3.5)            | PayPal (обязательно)                                                                                          | да — иначе PayPal не ложится                |
| холд и списание                    | `CapturesPayments` (новый)                   | все, кроме iPay в обычном режиме                                                                              | нет (§21.2 спеки) — но контракт заложить    |
| возврат через шлюз                 | `RefundsPayments` (новый)                    | все, с ограничениями (NovaPay, iPay)                                                                          | нет (решение 13)                            |
| список товаров / фискальный чек    | `StartRequest::$lines` + флаг `needsLines()` | WayForPay, Приват, Stripe — обязательно; mono, LiqPay, iPay, Portmone — для ПРРО                              | да — поле запроса (§3.4)                    |
| встраиваемый виджет / iframe       | `Handoff::embed()` (новый вид передачи)      | LiqPay, mono, WayForPay, Portmone, NovaPay, Tranzzo, Stripe                                                   | нет: v1 — только страница шлюза (решение 3) |
| ссылка шлюза на оплату             | не нужна                                     | LiqPay, mono, WayForPay, Portmone                                                                             | — наша `/pay/{number}` ведёт туда же        |
| «оплата частинами» через агрегатор | ничего: настройка способа (`paytypes`)       | LiqPay, WayForPay, Portmone, Tranzzo                                                                          | да — магазин получает всю сумму сразу       |
| рассрочка банка напрямую           | `CapturesPayments` (подтверждение магазином) | Приват «Частинами» с холдом, mono «Частинами»                                                                 | нет — вместе с холдом                       |
| проверка подключения               | `Gateway::testConnection()` (есть)           | все; дешёвый запрос — статус несуществующего платежа (не подтверждено, §7)                                    | да                                          |
| споры событиями                    | только журнал, `ignored`                     | Stripe, PayPal                                                                                                | нет                                         |

### 3.3. Словарь статусов базы и таблица соответствия

Закрытый список `Reported` спеки (`Pending`, `Paid`, `Failed`, `Cancelled`, `Expired`, `Refunded`)
покрывает одностадийную оплату у всех шлюзов. Нужны два дополнения: **`Authorized`** (деньги
заблокированы, ждут списания) — чтобы холд, когда до него дойдёт (§21.2 спеки), лёг новым интерфейсом
без нового значения перечисления; и **сумма возврата** в `GatewayStatus` (`refundedAmount`) — у всех,
кто сообщает возврат, он бывает частичным.

| Шлюз      | `Pending`                                                                                        | `Paid`                              | `Failed`                     | `Cancelled`                | `Expired`                        | `Refunded` (+ сумма)                                  | `Authorized`                                  |
| --------- | ------------------------------------------------------------------------------------------------ | ----------------------------------- | ---------------------------- | -------------------------- | -------------------------------- | ----------------------------------------------------- | --------------------------------------------- |
| LiqPay    | `processing`, `prepared`, `*_verify`, `wait_secure`, `wait_accept`?, `invoice_wait`, `cash_wait` | `success`, `wait_compensation`      | `failure`, `error`           | — (?)                      | по `expired_date` — ? (опрос)    | `reversed`, `refund_amount`                           | `hold_wait`                                   |
| mono      | `created`, `processing`                                                                          | `success`                           | `failure`                    | после `invoice/remove` — ? | `expired` (только опросом)       | `reversed`; частичный — `cancelList[].amount`         | `hold`                                        |
| WayForPay | `InProcessing`, `Pending`, код 5100 (3DS)                                                        | `Approved`                          | `Declined`                   | —                          | `Expired`                        | `Refunded`, `RefundInProcessing` → `Pending` возврата | `WaitingAuthComplete`; `Voided` → `Cancelled` |
| Portmone  | `CREATED`; `REJECTED` во время 3DS                                                               | `PAYED`                             | `REJECTED` окончательный     | `REJECTED` (отмена)        | `REJECTED` по `exp_time`         | документы `RETURN` (сумма по ним)                     | `PREAUTH`                                     |
| NovaPay   | `created`, `processing`, `processing_*`                                                          | `paid`                              | `failed`                     | `voided` (до оплаты)       | `expired`                        | `voided` после оплаты, `refunded_amount`              | `holded`, `hold_confirmed`                    |
| iPay      | `1`                                                                                              | `5`                                 | `4`                          | `9` до оплаты              | по `lifetime` — ?                | `9` после оплаты, частичный — `5` с меньшей суммой    | `3`                                           |
| Tranzzo   | `Init`, `Pending`                                                                                | `Success` (purchase / capture)      | `Failure`                    | void — ?                   | по `order_timeout` — ?           | операция `refund`                                     | `Success` операции `auth`                     |
| Приват    | `CREATED`, `CLIENT_WAIT`, `OTP_WAITING`, `PP_CREATION`                                           | `SUCCESS`                           | `FAIL`                       | `CANCELED`                 | ?                                | `decline` (сумма в `state`)                           | `LOCKED`                                      |
| Stripe    | `open`; `complete` + `unpaid`; PI `requires_*`, `processing`                                     | `complete` + `paid`; PI `succeeded` | `async_payment_failed`       | PI `canceled`              | `expired`                        | `charge.refunded`, `amount_refunded`                  | PI `requires_capture`                         |
| PayPal    | `CREATED`, `SAVED`, `APPROVED`, `PAYER_ACTION_REQUIRED`; capture `PENDING`                       | capture `COMPLETED`                 | capture `DECLINED`, `FAILED` | `VOIDED`                   | протухание по окну 6 ч (опросом) | `REFUNDED`, `PARTIALLY_REFUNDED`                      | authorization `CREATED`                       |

Правила перевода, общие для всех спутников (в контрактный тест):

- **неизвестный статус — `Pending` с `reason`** (как в спеке); база дождётся окончательного или срока;
- **статус по сумме, а не по слову**: «завершено» ≠ «оплачено» (Stripe `complete` + `unpaid`, PayPal
  `COMPLETED` с `PENDING`-списанием) — `Paid` только при подтверждённом списании;
- **неоднозначный «отказ»** (Portmone `REJECTED` во время 3DS) — `Pending`, пока шлюз не скажет
  окончательно или не выйдет срок;
- **порядок событий не гарантирован** (mono, Stripe) — уже решено спекой: `paid` сильнее всего,
  повтор — `same`; спутнику достаточно не выдумывать порядок.

### 3.4. Запрос на платёж и что возвращает спутник

`StartRequest` спеки покрывает LiqPay, mono, Portmone, iPay, PayPal, Stripe (одной строкой), но не
WayForPay, NovaPay, Tranzzo и Приват. Нужно:

| Поле                                                                            | Зачем                                                                                                                       | Кто требует                                                                |
| ------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| `uuid`, `amount`, `currency`, `description`, `returnUrl`, `notifyUrl`, `locale` | есть                                                                                                                        | все                                                                        |
| `customerEmail`                                                                 | есть                                                                                                                        | Tranzzo — обязательно; LiqPay, mono — для чека                             |
| **`customerPhone`**                                                             | без него шлюз не создаст платёж                                                                                             | NovaPay, Tranzzo, mono «Частинами» — обязательно; WayForPay-виджет         |
| **`customerName`**                                                              | имя и фамилия                                                                                                               | WayForPay-виджет; NovaPay, Portmone — необязательно                        |
| **`lines`**                                                                     | позиции заказа: название, количество, цена за единицу, сумма строки, код товара, УКТЗЕД, ставка налога (если знает каталог) | WayForPay, Приват, Stripe — обязательно; ПРРО mono, LiqPay, iPay, Portmone |
| **`orderNumber`**                                                               | номер заказа для людей (`1024`) рядом с `uuid` попытки — в кабинете шлюза менеджер ищет по номеру                           | NovaPay `external_id`, mono `reference`, PayPal `invoice_id`               |
| **`expiresAt`**                                                                 | срок жизни платежа, если способ его задаёт                                                                                  | WayForPay, LiqPay, mono, iPay, Portmone, Stripe                            |

- **`lines` не нарушает решение 5** («оплата не знает, из чего сумма»): база не считает по ним сумму, а
  передаёт снимок позиций заказа как есть. Если сумма строк не равна `amount` (доплата после правки,
  скидки, зачёты), база отдаёт **одну строку** «Заказ 1024» на `amount` — шлюзы со строгой сверкой
  (WayForPay, Portmone, Stripe) её принимают. Вопрос — §7, п. 3.
- **`Handoff`** — два вида спеки (`redirect`, `post`) покрывают всех; `post` нужен ещё `expiresAt`, а
  `redirect` — признак «адрес пришёл 303-м ответом» спутнику не нужен (Tranzzo: спутник сам читает
  `location`). Виджет (`embed`) — не в v1 (§3.2).
- **Повторный `start()` той же попытки** (таймаут после того, как шлюз создал платёж): у шлюзов без
  ключа идемпотентности повтор номера даёт «дубль» (LiqPay 2915, WayForPay 1112, Tranzzo) или второй
  платёж (mono, NovaPay). Правило контракта: база не зовёт `start()` дважды для одной попытки
  (`status ≠ created` → страница оплаты, как в §8.2 спеки), а спутник со «вторым платежом» при
  таймауте сначала спрашивает статус (так прямо советует SDK NovaPay).

### 3.5. Возврат покупателя и серверный шаг после одобрения

- **Адрес возврата — GET и POST**: Portmone возвращает покупателя POST-ом с данными платежа; LiqPay
  `result_url` — не подтверждено. Маршрут `/pay/return/{uuid}` принимает оба метода без CSRF; тело и
  параметры база по-прежнему **не читает для статуса** (решение 26), но **пишет в журнал** как есть
  (§3.7).
- **PayPal: одобрение ≠ оплата.** После возврата магазин обязан вызвать `capture`, иначе денег нет.
  `fetchStatus()` — чтение и не должен списывать деньги. Нужен необязательный контракт
  `CompletesApproval::complete(Method, Credentials, Payment): GatewayStatus`, который база зовёт на
  возврате и из опроса **перед** `fetchStatus()`, под тем же замком попытки, идемпотентно (у PayPal —
  `PayPal-Request-Id` = `uuid` попытки). Без него спутник PayPal пришлось бы делать списание внутри
  `fetchStatus()` — побочное действие в чтении, которое зовут и кнопка менеджера, и MCP `dry_run`.
- Тот же контракт закрывает Stripe-сценарии с ручным подтверждением, если до них дойдёт.

### 3.6. Входящее уведомление

- **Маршрутизация** — как в спеке: `POST /payments/notify/{method}`, способ → тип → спутник. Годится
  всем: у Portmone и iPay адрес вводится в кабинете или заявкой один раз — **панель должна показывать
  готовый адрес уведомлений способа для копирования** (у Stripe и PayPal — тоже: адрес вебхука
  регистрируется в их кабинете).
- **Подпись проверяет спутник** — алгоритмы у всех разные: HMAC (WayForPay, Stripe, mono «Частинами»),
  хеш с секретом по краям (LiqPay, Tranzzo, Приват), ECDSA с ключом из API (mono), RSA с ключом шлюза
  (NovaPay), сертификат + CRC32 или сетевая проверка (PayPal). База алгоритма не знает — верно.
- **Неподписанные и «слабо подписанные» уведомления** (Portmone — подписи нет; iPay — подпись не
  покрывает данные): контракт спеки требует `InvalidNotification` на неверную подпись, проверить
  нечего. Нужен флаг типа **`trustsNotifications(): bool`** (по умолчанию `true`): `false` — база
  считает уведомление только **поводом**: пишет его в журнал, находит попытку и зовёт `check()`
  (`fetchStatus` по API), не беря статус из тела. Контрактный тест для таких спутников проверяет, что
  статус из тела не применяется.
- **Нужны ключи и в ответе:** WayForPay требует подписанный ответ (`orderReference;status;time`), без
  него повторяет 4 суток. `acceptResponse(Notification)` спеки ключей не получает — нужен
  `acceptResponse(Method, Credentials, Notification)`.
- **Ответы:** любой 2xx (mono, iPay, Stripe, PayPal, NovaPay), подписанный JSON (WayForPay), XML /
  JSON `RESULT` (Portmone); у LiqPay, Tranzzo, Приват — не подтверждено (спутник отвечает пустым 200).
- **Уведомления не о платеже** (возврат, спор, токен карты у mono, десятки типов событий Stripe и
  PayPal): `readNotification()` должен уметь сказать «это не про статус попытки» —
  `Notification::unrelated(eventKey, reason)` → журнал `ignored` с причиной, ответ «принято», без
  `settle()`. Иначе спутник бросал бы `InvalidNotification`, а шлюз повторял бы три дня.
- **Повторы и дубли** — у всех (mono 3 раза, iPay каждые 2 минуты, WayForPay 4 суток, Stripe и PayPal 3
  дня, Tranzzo и NovaPay — «возможны»). `event_key` спеки годится: id события (Stripe `event.id`,
  PayPal `transmission_id`) или sha256 сырого тела. У NovaPay подпись без метки времени — повтор
  старого постбека отсекает тот же `event_key`.
- **Нужен сетевой вызов при проверке** (mono — ключ из API, PayPal — сертификат или verify API): спутник
  кеширует ключ (кеш Laravel), сетевой запрос идёт через журналируемый клиент (§3.7).
- **IP-списки** (Приват, Tranzzo, Stripe публикуют адреса) — необязательная настройка `notify_ips`
  спеки, в дополнение к подписи. Обратная сторона: NovaPay может требовать **регистрации IP нашего
  сервера** — это шаг подключения в гайде спутника, не контракт.

### 3.7. Сырой обмен для журнала и маскирование — одинаково у всех

Решение 2 задачи: всё, что шлюз прислал и что мы ему отправили, хранится полностью и без изменений,
кроме секретов. Обзор показывает, что **только база может гарантировать это одинаково для всех
шлюзов**, поэтому сырой обмен ловит база, а не спутник:

| Событие                                                                                                   | Кто ловит                                                                                              | Что пишется                                                                                                                       |
| --------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------- |
| исходящий запрос спутника (создание, статус, отмена, списание, проверка ключа, токен OAuth, ключ подписи) | **HTTP-клиент базы**, который спутник обязан использовать (`GatewayHttp`, обёртка `Http` с middleware) | метод, адрес, заголовки, тело как отправлено; код, заголовки, тело ответа как пришло; длительность; таймаут или ошибка транспорта |
| передача покупателя (`Handoff`)                                                                           | база в `/pay/go`                                                                                       | адрес и поля формы (у форм LiqPay, WayForPay, Portmone это и есть «запрос шлюзу»)                                                 |
| входящее уведомление                                                                                      | база в контроллере, **до** `readNotification()` и до транзакции                                        | метод, адрес, заголовки, сырое тело (`$request->getContent()`), IP; потом — итог подписи и решение                                |
| возврат покупателя                                                                                        | база в `/pay/return`                                                                                   | метод, строка запроса, тело (Portmone — POST), без cookie                                                                         |
| ответ шлюзу на уведомление                                                                                | база                                                                                                   | код и тело (у WayForPay — подписанный JSON)                                                                                       |

- **Тело — байтами как есть**, а не разобранным json: подпись у mono, Stripe, NovaPay, PayPal считается
  по сырому телу, у LiqPay и Tranzzo — по строке base64; разбор и повторная сериализация делают запись
  бесполезной для разбора «почему подпись не сошлась». Кодировка — как пришла (у iPay и Portmone — XML в
  form-поле).
- **Маскирование — по значениям, а не по именам полей.** База знает значения ключей способа
  (`Credentials`) и заменяет каждое вхождение — как есть, url-кодированным, json-экранированным и в
  base64 — на `[secret:<имя поля>]`. Это закрывает Portmone (`login`/`password` открыто в теле), mono
  (`X-Token`), Tranzzo (`X-API-AUTH` содержит ключ), Stripe (`Authorization: Bearer sk_…`), PayPal
  (`Authorization: Basic base64(id:secret)`). Плюс всегда: заголовки `Authorization`, `Cookie`,
  `Set-Cookie`, `X-Token`, `X-API-AUTH`; плюс то, что объявил спутник: **производные секреты**, которых
  нет в `Credentials`) — токен OAuth PayPal (`Gateway::secretPatterns()` или маскирование значения по
  имени в ответе). Номер карты — правило спеки §15.3 (13–19 цифр, кроме последних четырёх); маска
  шлюза `4444****1111` остаётся как прислана.
- **Подпись не секрет** и нужна для разбора — спека §15.3 её затирает (`signature|sign|hash`);
  предложение — хранить (§6, п. 7). Секрет из подписи не восстановить: HMAC, SHA1/SHA3 с ключом, ECDSA,
  RSA.
- **Что база решила** — в той же записи: результат подписи (`valid`, `invalid`, `unsigned`), найденная
  попытка и заказ, статус до и после или `ignored: <почему>` (спека: `outcome`, `message`).
- **Запись журнала не роняет оплату:** ошибка записи — в лог; входящее пишется до разбора отдельной
  короткой вставкой, решение дописывается после (или второй строкой — выбор этапа 2).

### 3.8. Деньги, валюта, ключи, тестовый режим

- **Сумма** — десятичная у семи шлюзов из десяти, целым у mono, iPay, Stripe; помощник `Money` спеки
  годится. Стоит записать в контрактный тест: сумма **в подписи** бывает другого вида, чем в теле
  (Приват: `400.00` в JSON и `40000` в подписи); Stripe — исключения zero-decimal.
- **Валюта** — у NovaPay и Привата её нет в запросе (только гривна) — `currencies(): ['UAH']`; mono —
  числовой код ISO 4217 (`980`); у mono холд только в гривне.
- **Ключи — разные формы:** строка (большинство), **многострочный PEM** (приватный ключ NovaPay; Apple
  Pay mono — сертификат, не в v1), пара для теста и боя (LiqPay `sandbox_`, Stripe `sk_test_`). Поле
  `Field` спеки должно уметь «секретный многострочный текст».
- **Тестовый режим:** отдельные ключи (большинство), отдельный хост (NovaPay, iPay, Приват, mono
  «Частинами», PayPal), режим у менеджера на боевом адресе (Portmone, WayForPay — публичный тестовый
  мерчант). Настройка `mode` шлюза из спеки годится; адреса — в конфиге спутника (§14.2 спеки).
- **Комиссия шлюза** приходит у шести (LiqPay, mono, WayForPay, Portmone, iPay, Tranzzo), у Stripe и
  PayPal — позже. Предложение — класть в `GatewayStatus::$meta['fee']` (несекретное), без колонки и
  логики в v1.

### 3.9. Сводка: что добавить в контракт, чтобы новый шлюз не требовал правки базы

1. `StartRequest`: `customerPhone`, `customerName`, `orderNumber`, `lines` (с правилом одной строки),
   `expiresAt` у `Handoff::post()`.
2. `CompletesApproval` — серверный шаг после возврата покупателя (PayPal).
3. `trustsNotifications()` — уведомление как повод для опроса (Portmone, iPay).
4. `acceptResponse()` и `rejectResponse()` получают `Method` и `Credentials` (WayForPay).
5. `Notification::unrelated()` — уведомления не о статусе попытки.
6. `Reported::Authorized` и `GatewayStatus::$refundedAmount` — в перечень заранее, без поведения в v1.
7. Сырой обмен ловит база: `GatewayHttp` для исходящих, запись входящего до разбора, возврат GET и POST.
8. Маскирование по значениям `Credentials` и объявленным спутником производным секретам; подпись не
   затирается.

## 4. Ложится ли на контракт без правки базы

Проверка против **текущей** спеки оплаты и против спеки **с дополнениями §3.9**.

| Шлюз               | Текущая спека | С §3.9   | Что мешает в текущей / что остаётся                                                                                                                                      |
| ------------------ | ------------- | -------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| LiqPay             | да*           | да       | *если журнал — разобранный json: подпись по base64-строке не разобрать без сырого тела. Алгоритм хеша — настройка спутника (§7, п. 1)                                    |
| mono               | да*           | да       | *нет вебхука на `expired` — закрывает опрос §9.3; ключ подписи из API — сетевой вызов внутри `readNotification()` должен попасть в журнал (§3.7)                         |
| WayForPay          | **нет**       | да       | подписанный ответ на уведомление требует ключей (§3.6); подпись запроса требует позиций (`productName[]` …) — `lines` (§3.4)                                             |
| Portmone           | **нет**       | да       | уведомление без подписи — `InvalidNotification` по контракту невозможна (§3.6); возврат покупателя POST-ом; пароль открыто в теле — маскирование по значению (§3.7)      |
| NovaPay            | **нет**       | да*      | обязательный телефон покупателя (§3.4); PEM-ключ (§3.8); *с доставкой «Новой почтой» холд обязателен — только без блока доставки, пока нет `CapturesPayments`            |
| iPay               | **нет**       | да       | подпись не защищает данные — нужен режим «повод для опроса» (§3.6); холд — режим мерчанта, магазин должен работать без предавторизации                                   |
| Tranzzo            | **нет**       | да       | обязательные e-mail и телефон (§3.4)                                                                                                                                     |
| Приват «Частинами» | нет           | частично | без холда (`/create`) ложится при `lines` и телефоне; с холдом — нужен `CapturesPayments` (не v1). Части через LiqPay / WayForPay / Portmone / Tranzzo ложатся без этого |
| mono «Частинами»   | нет           | нет      | магазин **обязан** подтвердить после выдачи товара — это холд и списание, `CapturesPayments` и веха спеки §21.2; отдельный тип спутника `payment-mono` (`mono.parts`)    |
| Stripe             | да*           | да       | *`line_items` обязательны — одной строкой из `description` можно и сейчас; аккаунт только для юрлица вне Украины                                                         |
| PayPal             | **нет**       | да       | без `CompletesApproval` списание пришлось бы делать в `fetchStatus()`; UAH нет — способ только для валютного магазина                                                    |

Вывод: из десяти рабочих вариантов текущая спека держит без правки базы три (LiqPay, mono, Stripe — и
то с оговорками про журнал), с дополнениями §3.9 — все одностадийные; рассрочка банка напрямую и
NovaPay с доставкой ждут холда (§21.2 спеки), что согласуется с решением 13 — но контракт холда
(`CapturesPayments`, `Authorized`) стоит описать в спеке сразу, чтобы этот шаг потом тоже был «новым
интерфейсом», а не правкой.

## 5. Порядок спутников после LiqPay

Оценка сложности — по объёму расхождений с моделью «страница → подписанное уведомление → опрос»;
распространённость — не по статистике (официальных долей рынка нет), а по тому, что шлюз агрегирует и
кого чаще просят магазины.

| Порядок | Спутник                            | Почему                                                                                                                                        | Сложность                                                    |
| ------- | ---------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| 1       | **LiqPay**                         | решение человека; самый распространённый у магазинов, части Привата через `paytypes`                                                          | низкая; вопрос алгоритма хеша                                |
| 2       | **mono (plata)**                   | второй по массовости эквайринг, простой JSON, суммы целым, токен один, тест — любому клиенту банка                                            | низкая; ECDSA и кеш ключа, нет вебхука на `expired`          |
| 3       | **WayForPay**                      | широко используется в малом бизнесе, части шести банков одним шлюзом                                                                          | средняя: подписанный ответ, позиции в подписи, SDK заброшен  |
| 4       | **NovaPay**                        | естественная пара к `delivery-novaposhta`; полный смысл — «Чекаут» с оплатой при получении и холдом                                           | средняя без доставки, высокая с ней (холд, RSA, телефон, IP) |
| 5       | Portmone                           | по запросу                                                                                                                                    | средняя: неподписанные уведомления, документы `shopBillId`   |
| 6       | iPay, Tranzzo                      | по запросу                                                                                                                                    | средняя: XML и слабая подпись (iPay); простой (Tranzzo)      |
| 7       | Приват / mono «Частинами» напрямую | только вместе с холдом; до того рассрочку дают агрегаторы (LiqPay, WayForPay, Portmone, Tranzzo)                                              | высокая                                                      |
| —       | Stripe, PayPal                     | только магазинам с юрлицом в поддерживаемой стране (Stripe) или валютным магазинам (PayPal, UAH нет); полезны как эталон контракта уже сейчас | низкая (Stripe), средняя (PayPal — шаг `capture`)            |

## 6. Расхождения со спекой оплаты (правит этап 2)

Ссылки — на разделы [`WEBX_UI_MODULE_PAYMENTS.md`](WEBX_UI_MODULE_PAYMENTS.md).

1. **§5 `payment_events`, решение 2 задачи** — `payload` json «после маскирования, обрезан» противоречит
   «сырое тело полностью и без изменений»: нужны сырое тело (`longtext`/`mediumblob`), заголовки,
   метод, адрес, код, длительность, направление (`out` / `in`), шлюз, итог подписи, статус до и после;
   `kind` — ещё `capture`/`refund`/`key` для исходящих служебных запросов; хранение 180 дней и ночная
   чистка `prune` противоречат «автоочистки по умолчанию нет» (решение задачи) — чистка только явным
   запуском.
2. **§6.3 `StartRequest`** — нет `customerPhone`, `customerName`, `orderNumber`, `lines` (§3.4);
   `Handoff::post()` без `expiresAt`.
3. **§6.3 `acceptResponse(Notification)`** — без `Method` и `Credentials` WayForPay не ответит (§3.6).
4. **§6.3 `readNotification()`** — нет пути «уведомление не о статусе» (`unrelated`) и режима
   «неподписанное — повод для опроса» (`trustsNotifications()`); §14.3 контрактный тест требует
   `InvalidNotification` на неверную подпись — у Portmone проверять нечего.
5. **§6.4, §14.1** — нет `CompletesApproval` (PayPal); таблица «шлюзы A и B покрываются так» —
   дополнить строками «одобрение ≠ оплата», «уведомление без подписи», «подписанный ответ».
6. **§6.3 `Reported`** — нет `Authorized`; `GatewayStatus` без `refundedAmount` (частичный возврат из
   кабинета шлюза сейчас неотличим от полного, §8.1).
7. **§15.3 маскирование** — по именам полей и затирает `signature|sign|hash`: заменить на
   маскирование по значениям `Credentials` (во всех кодировках) + заголовки + объявленные спутником
   производные секреты; подпись хранить (§3.7).
8. **§14.2 «HTTP — через `Http` Laravel»** — исходящие запросы должны идти через клиент базы, который
   пишет журнал (`GatewayHttp`), иначе сырой обмен у каждого спутника свой или его нет.
9. **§8.3 `GET /pay/return/{uuid}`** — нужен и POST (Portmone), без CSRF; сырой возврат — в журнал.
10. **§13.3 форма способа** — показать адрес уведомлений для копирования в кабинет шлюза (Portmone,
    iPay, Stripe, PayPal); поле ключа «многострочный секрет» (PEM NovaPay).
11. **§1 «первые спутники — два шлюза» (решение 4)** — обновить: первый LiqPay (решение 03.11), второй —
    по §5 этого обзора.
12. **§21.2 холд** — отложен верно, но описать контракт `CapturesPayments` и `Authorized` заранее
    (§4, вывод).
13. **§8.2 таймаут `start()` без повторов** — дописать правило §3.4: спутник шлюза без идемпотентности
    при таймауте сначала спрашивает статус.

## 7. Открытые вопросы

1. **LiqPay: SHA3-256 или SHA1?** Доки и SDK расходятся (§2.1). Предложение: алгоритм — настройка
   спутника LiqPay с проверкой на песочнице в его задаче; уведомление принимать, если сошёлся любой из
   двух (оба по секрету, подделка не проще). Решает задача спутника, но стоит подтвердить у поддержки
   LiqPay.
2. **Журнал: одна запись на событие или две** (сырой вход до разбора + решение после)? Решение задачи —
   «журнал пишется до разбора и до транзакции статуса». Предлагаю одну строку, вставленную до разбора, и
   дописывание полей решения после (запрет правки — только для панели и MCP); вопрос к этапу 2.
3. **`lines` в запросе на платёж** — передавать снимок позиций заказа (с правилом «не сошлось — одна
   строка») или всегда одну строку «Заказ N»? Для ПРРО через шлюз нужны настоящие позиции; для
   WayForPay и Stripe хватает одной. Предложение — снимок позиций, одна строка как запасной путь.
4. **Телефон покупателя** — отдавать ли шлюзу всегда, когда он есть в заказе, или только тем, кто
   требует (`needsPhone()`)? Предложение — только тем, кто требует (меньше персональных данных у
   третьих лиц).
5. **Подпись в журнале** — хранить (предложение §3.7) или затирать (спека §15.3)?
6. **Проверка подключения** (`testConnection`) — у LiqPay, WayForPay, Portmone нет описанного «дешёвого»
   вызова; вероятно, статус несуществующего платежа с разбором ошибки — не подтверждено, решать в
   задачах спутников.
7. **NovaPay с доставкой** — нужна ли клиентам связка «оплата + накладная НП в одном потоке» до общего
   холда? Если да, `CapturesPayments` переезжает из «отложено» в план.
8. **Неподтверждённое в доках** — ожидаемый ответ на уведомление у LiqPay, Tranzzo, Привата; порядок
   уведомлений у PayPal; подпись постбека NovaPay в справочнике (есть только в SDK); несколько частичных
   возвратов у LiqPay и WayForPay — проверять на песочнице в задаче каждого спутника.
