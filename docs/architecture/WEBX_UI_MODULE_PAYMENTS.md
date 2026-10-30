# `webx-ui/module-payments` — оплата — спецификация

Статус: спроектировано, ждёт приёмки (webx-ui#60, этап 1); пакет не начат. Карта семейства —
[`WEBX_UI_COMMERCE.md`](WEBX_UI_COMMERCE.md): принципы, цепочка итогов, события заказа. Соседи:
заказы — [`WEBX_UI_MODULE_ORDERS.md`](WEBX_UI_MODULE_ORDERS.md) (вехи §5, блоки снимка §6.4, возврат
§9), оформление — часть II [`WEBX_UI_MODULE_ORDERS.md`](WEBX_UI_MODULE_ORDERS.md) (слот `PaymentSlot`
§6.3, подтверждение §8, «Спасибо» §10), доставка — [`WEBX_UI_MODULE_DELIVERY.md`](WEBX_UI_MODULE_DELIVERY.md)
(наложенный платёж в накладной §11.2, шаблон спутника §13), доступ агентов —
[`WEBX_UI_MCP_ACCESS.md`](WEBX_UI_MCP_ACCESS.md).

Пакет — `webx-ui/module-payments` (composer, namespace `WebxUi\Payments`, словарь
`webx-payments`), npm-пара — `@webx-ui/module-payments` (экраны панели). Id модуля — `payments`
(области MCP `payments:read` / `payments:write`, права `payments.*`, §13.1). Таблицы —
`payment_*` и `payments` (§5).

Оплата отвечает на три вопроса: **чем** покупатель может заплатить за этот заказ (список способов,
доступных при выбранной доставке и сумме), **как** он платит (по реквизитам, при получении или на
странице шлюза) и **заплатил ли** (подтверждение шлюза или отметка менеджера → веха заказа
«оплачен»). Из коробки — два способа без внешних сервисов: оплата при получении и оплата по
реквизитам. Онлайн-оплату приносят спутники `payment-<шлюз>`; база задаёт им контракт, страницы
возврата, приём уведомлений, журнал и идемпотентность, а спутник только переводит язык своего шлюза
на язык базы.

## 1. Границы

**Внутри:**

- реестры типов способов и шлюзов, способы сайта и их настройки (§6, §7);
- доступность способа: сумма, валюта, выбранная доставка (§7.3);
- выбор покупателя по корзине и реализация слота оформления `PaymentSlot` (§10);
- попытки платежа (`payments`), их статусы, журнал событий шлюза (§5, §8);
- процесс онлайн-оплаты: переход в шлюз, адрес возврата, проверка статуса по API, приём
  server-to-server уведомлений с проверкой подписи, идемпотентная обработка (§8, §9);
- страница оплаты заказа: «в обработке», «не прошла», «оплатить повторно», «сменить способ» (§8.4);
- веха заказа «оплачен» и «оплата не прошла» — через `Orders` (§11);
- событие уведомлений менеджеру `payments.problem` (§11.5);
- встроенные способы: при получении и по реквизитам (§12);
- шаблон спутника шлюза и контрактный тест (§14); хранение ключей шлюзов (§15);
- раздел панели, вкладка «Оплата» в карточке заказа (§13), MCP (§16), словарь (§17).

**Рядом, но в чужом пакете:**

- **Сумма к оплате** — цепочка итогов `module-cart` и снимок `module-orders` (`orders.payable`).
  Оплата её не считает и не знает, из чего она сложилась (решение 4): бонусы, промокоды,
  сертификаты, кешбек меняют итог раньше, в оплате они не учитываются и не показываются.
- **Заказ, вехи, история, отмена и возврат как запись** — `module-orders`; оплата пишет в заказ
  только через сервис `Orders` (решение 18 спеки заказов).
- **Порядок шагов, сверка суммы, создание заказа, «Спасибо»** — оформление `module-orders`; оплата отдаёт ему
  слот (§10).
- **Способ доставки и наложенный платёж в накладной** — `module-delivery`; оплата говорит, какую
  сумму взять при получении (§12.1), доставка — поддерживает ли выбранный способ наложенный платёж
  (через оформление, §7.3).
- **Письма** — `module-notifications` по событиям заказа; оплата добавляет переменные (§11.5).

**Снаружи:**

- **приём карты на сайте** — никогда (решение 2): ни номера, ни срока, ни кода в формах сайта; карту
  покупатель вводит только на странице шлюза;
- конкретные шлюзы — только спутники `payment-<шлюз>` (§14); в базе, её тестах и примерах — «шлюз
  A», «шлюз B» и подставной шлюз набора тестов (`FakeGateway`);
- фискальные чеки, счета в PDF, поля юрлица, налоги — не в базе (решение 8; чеки зависят от страны
  — дело спутника или отдельного модуля);
- возврат денег через шлюз и автоотмена неоплаченного заказа — не в v1 (решения 13, 15);
- холдирование (двухстадийная оплата), рассрочка, подписки и рекуррентные платежи — отложены
  (§21.2);
- комиссия за способ («+2 % за оплату картой») — в v1 нет (§22.3 спеки заказов).

### 1.1. Без соседей

`module-payments` требует `module-orders` (а через него — `module-cart`): без заказа платить не за
что. Остальное — в `suggest`, подключается по `class_exists` в `boot()`:

| Нет модуля             | Что меняется                                                                                                    |
| ---------------------- | --------------------------------------------------------------------------------------------------------------- |
| оформление выключено   | слота нет; способ в заказ кладёт код сайта или менеджер (`switchMethod`, §8.5); страница оплаты заказа работает |
| `module-delivery`      | правило доступности по доставке не применяется (§7.3): «при получении» доступен как «по договорённости»         |
| `module-notifications` | переменных оплаты в письмах нет; ссылку на оплату менеджер копирует из карточки                                 |
| спутники `payment-*`   | только встроенные способы; онлайн-оплаты, адреса уведомлений и опроса нет                                       |

Зависимость от `module-orders` — решение 12; карту коммерции (§3.1 там пишет `module-admin`)
поправляет отдельная правка.

## 2. Принятые решения (не переоткрывать)

Решения человека от 23.10.2026 и 27.10.2026 (спецификация задачи webx-ui#60); при расхождении
действуют решения 27.10.

1. **Реестр способов оплаты.** Оплата ставит веху заказа «оплачен» (`OrderPaid`) — по
   подтверждению шлюза или вручную менеджером; на неё завязан выпуск сертификатов (23.10).
   «Возвраты — через способ, которым платили» отложено до первого клиента (решение 13).
2. **База — только два способа:** оплата при получении и оплата по реквизитам. Всё остальное —
   спутники.
3. **Карту на своём сайте не принимаем никогда.** Онлайн-оплата — только через страницу шлюза
   (redirect / hosted checkout).
4. **Первые спутники — два шлюза интернет-эквайринга** с hosted-страницей, server-to-server
   уведомлением (POST с подписанным пакетом) и API проверки статуса. В базе они не называются
   («шлюз A», «шлюз B»); контракт обязан покрыть оба. Пакеты спутников — отдельные задачи.
5. **Оплата получает итоговую сумму к списанию** («к оплате») и не знает, откуда она. Бонусы,
   промокоды, сертификаты, кешбек — отдельные модули, в оплате не учитываются и не показываются.
   **Сумма к оплате 0 — способ оплаты не нужен** (ставить ли тогда веху «оплачен» — вопрос 1 §21.1).
6. **Процесс онлайн-оплаты:** выбор способа и подтверждение → страница шлюза (карта и 3-D Secure
   там) → возврат на сайт, где сайт **сам спрашивает статус у шлюза по API**, не доверяя параметрам
   возврата → параллельно шлюз шлёт **server-to-server POST** с подписанным пакетом: проверка
   подписи, идемпотентность, повторный и запоздалый колбэк не ломают статус, «возврат раньше
   колбэка» и наоборот — оба корректны → оплачено: веха «оплачен», страница успеха; не оплачено,
   отклонено, отменено — страница ошибки.
7. **Зависимость от доставки:** способ оплаты сам говорит, при каких способах доставки доступен
   («при получении» — если доставка поддерживает наложенный платёж; самовывоз из магазина — «оплата
   на кассе»). Оплата и доставка друг от друга не зависят — договариваются через общую проверку
   доступности в оформлении. Комиссия службы за наложенный платёж — как «по тарифам перевозчика».
8. **Заказ создаётся до оплаты:** с онлайн-способом — сразу «ожидает оплаты», потом переход в шлюз.
   Оплата не прошла — заказ остаётся: **оплатить повторно** (новая попытка по тому же заказу, ссылка
   — и в письме) или **сменить способ**. Менеджер видит такие заказы (фильтры «ожидает оплаты» /
   «оплата не прошла»), может связаться, сменить способ, отметить оплату вручную, отменить.
   У заказа может быть несколько попыток; оплаченной считается та, которую подтвердил шлюз (статус
   по API или подписанное уведомление). Автоотмены нет — решение 15 (27.10, вечер) меняет эту часть
   решения 8.
9. **Оплата по реквизитам — только реквизиты:** получатель, счёт, назначение платежа с номером
   заказа — на «Спасибо» и в письме. Счёта в PDF и полей юрлица нет. Реквизиты — настройка способа
   в панели на языках сайта. Оплату отмечает менеджер; до этого заказ «ожидает оплаты».
10. **Обезличивание:** без названий шлюзов и привязки к стране; реквизиты в примерах — фейковые;
    деньги — целым в минимальных единицах; ключи шлюзов — не в git и не в открытом виде в панели.

Решения человека от 27.10.2026, вечер (ответы на открытые вопросы этой спеки); при расхождении
сильнее решений выше:

11. **v1 — упрощённая, наворачиваем по запросу клиента** — общий принцип коммерции (решение 17 спеки
    заказов).
12. **`module-payments` требует `module-orders`** (§1.1). Карту коммерции §3.1 правят отдельно.
13. **Возврата через шлюз в v1 нет** — ни контракта, ни этапа. Возврат — только ручной (`manual`) в
    заказе, как в решении 27 спеки заказов. «Возвраты через способ, которым платили» (решение 1)
    отложено до первого клиента: сразу списание или холдирование — у каждого своё (§21.2).
14. **`ShippingChoice` — через оформление** (§7.3): `ShippingSlot::choice()` и
    `CheckoutContext::shippingChoice()`; контракт оформления приводится к этой спеке.
15. **Автоотмены нет нигде** — ни в заказах, ни в оплате (меняет решение 8). Неоплаченный заказ
    отменяет менеджер, позже — учётная программа через API (§11.4).
16. **`Orders::stopAwaitingPayment(order, source)`** — снимает «ожидает оплаты» без вехи `paid`:
    строка истории, флаг снят, без события (§8.5; правка спеки заказов).
17. **Событие уведомлений `payments.problem`** у `module-payments`: двойная оплата, не та сумма,
    деньги на отменённый заказ (§9.4, §11.5).
18. **Страница «в обработке»** — опрос ~2 минуты, затем «можно закрыть страницу, пришлём письмо»
    (§8.4). **Письмо покупателю «оплата получена» по вехе `paid` есть в наборе по умолчанию** (канал
    на адресата покупателя по `orders.paid`), владелец сайта может отключить. Это исключение из
    решения 31 спеки заказов (правка спеки заказов).
19. **Шов переменных в `module-notifications`:** чужой модуль добавляет переменные к событиям
    заказа — оплата кладёт ссылку «оплатить повторно» и реквизиты (§11.5).
20. **Отдельного права на ключи (`payments.credentials`) нет** — хватает `payments.manage`.
21. **Ключи шлюза — оба пути:** зашифрованно в панели и из окружения сайта (§15.1).
22. **Имя спутников — `payment-<шлюз>`**, как `delivery-<служба>` у доставки (§14).
23. **Частичной оплаты по реквизитам в v1 нет** — менеджер отмечает оплату целиком или связывается
    с покупателем (§12.2).

Решения спеки (предложены здесь; спорное — в §21):

24. **Попытка платежа — своя строка** (`payments`, §5.3), заказ хранит только блок способа. Попыток
    у заказа сколько угодно; веха `paid` — одна (решение 24 спеки заказов).
25. **Один путь к статусу.** Возврат покупателя, уведомление шлюза, фоновый опрос и кнопка
    менеджера «Проверить у шлюза» зовут один метод `Payments::settle()` под замком строки попытки
    (§9.1). Кто пришёл первым — тот и применил; второй видит тот же статус и ничего не делает.
26. **Параметрам возврата не верим никогда:** адрес возврата несёт только наш `uuid` попытки;
    статус берётся из API шлюза (`fetchStatus`) или из уведомления с проверенной подписью.
27. **Двойная оплата не допускается, а проскочившая — видна** (§9.4): перед новой попыткой база
    спрашивает шлюз о прежней незавершённой; вторая подтверждённая оплата того же заказа — попытка
    `paid` с пометкой `duplicate`, строка истории и уведомление менеджеру; деньги менеджер
    возвращает вне сайта и записывает возврат `manual` в заказе (решение 13).
28. **Ключи шлюза — зашифрованным полем способа** или из окружения сайта (решение 21); в панели —
    «задано / заменить», в MCP, журнале и логах — никогда (§15).

Предложено частью III спеки заказов (webx-ui#92, этап 7; решение 100 там) — ждёт приёмки вместе с
ней:

29. **Сумма попытки — долг заказа, а не снимок.** Оплата берёт `Orders::balance()->due` на момент
    `begin()` и не считает сама (§36 спеки заказов). Первая оплата — `mark(paid, …, amount)`,
    следующие — доплата `Orders::recordPayment()`: у заказа может быть несколько оплаченных попыток,
    двойная оплата — только та, что пришла, когда долга уже не было (§9.1, §9.4). Ссылка
    `/pay/{number}` служит и доплате после правки состава, и оплате онлайн заказа, оформленного «при
    получении» (§8.4, §8.5).

## 3. Слова

| Слово               | Что значит                                                                                    |
| ------------------- | --------------------------------------------------------------------------------------------- |
| **тип способа**     | код, который умеет платить: `store.on_receipt`, `store.bank_transfer`, `gateway-a.card`       |
| **способ**          | то, что видит покупатель: тип + название, описание, правила доступности, настройки сайта      |
| **шлюз**            | платёжный сервис спутника: ключи, режим (тест / боевой), проверка подключения                 |
| **онлайн-способ**   | способ, который платит на странице шлюза и подтверждается шлюзом                              |
| **попытка**         | одна попытка оплатить заказ онлайн-способом или отметка оплаты менеджером: строка `payments`  |
| **уведомление**     | server-to-server POST шлюза (callback, webhook) с подписанным пакетом о попытке               |
| **возврат на сайт** | браузер покупателя вернулся со страницы шлюза на адрес возврата (return URL)                  |
| **проблема**        | попытка подтверждена, но заказ оплаченным не стал: двойная оплата, сумма не та, заказ отменён |

## 4. Адреса

Маршруты сайта — внутри `{locale?}`, `SiteRoutes::SERVICE`, стек `web`, `Cache-Control: no-store`.
Префикс — `webx-payments.path` (по умолчанию `pay`).

```
GET  /pay/{number}?signature=…      страница оплаты заказа (§8.4): состояние, «Оплатить», «Сменить способ»
POST /pay/{number}/attempts         новая попытка выбранным онлайн-способом → redirect на /go
POST /pay/{number}/method           сменить способ (§8.5); подпись — та же
GET  /pay/go/{uuid}                 переход в шлюз: start() → 302 или автоотправка формы (§8.2)
GET  /pay/return/{uuid}             возврат со страницы шлюза (§8.3); success и fail — один адрес
GET  /api/payments/{uuid}/status    JSON для страницы «в обработке» (§8.4)

GET  /api/payments/methods          способы для шага оформления (§10.2)
PUT  /api/payments/selection        выбор покупателя по корзине (§10.2)

POST /payments/notify/{method}      уведомление шлюза (§9.2): без сессии, без CSRF, без {locale}
```

- **Страница оплаты — подписанная ссылка без срока,** как страница заказа (решение 32 спеки
  заказов): её получает покупатель в письме и на «Спасибо». Подпись — `URL::signedRoute`; смена
  `APP_KEY` ломает старые ссылки, как и у заказа.
- **`uuid` попытки** — 26 знаков ULID, единственный идентификатор в адресах шлюза. Номер заказа и
  `id` строки наружу через шлюз не ходят: угадать соседнюю попытку нельзя.
- **Уведомление — без `{locale}` и без сессии,** маршрут в группе `api` без `web`: CSRF и cookie
  ему не нужны, а редирект на язык сломал бы POST. Адрес уведомлений способа показывается в форме
  способа для копирования в кабинет шлюза (§13.3). Ограничение частоты — `throttle:payments-notify`
  (по IP, 120 в минуту), тело — не больше 64 КБ.
- Все маршруты сайта — в списке открытых `AccessCoverageTest` с причиной «оплата покупателя» и
  «уведомление шлюза».

## 5. Таблицы

Деньги — `bigint` в минимальных единицах валюты (`currency`, ISO 4217), `float` нет нигде (принцип 4
карты). Языковые поля — json `{locale: text}`. Миграции откатываются.

### `payment_methods` — способы сайта

| Колонка                    | Тип                    | Примечание                                                                    |
| -------------------------- | ---------------------- | ----------------------------------------------------------------------------- |
| `id`                       | bigint                 |                                                                               |
| `key`                      | string(64), **unique** | ключ способа сайта: `on-receipt`, `bank-transfer`, `gateway-a-card`; навсегда |
| `type`                     | string(64), index      | ключ типа из реестра (§6.2)                                                   |
| `title`                    | json                   | название по языкам                                                            |
| `description`              | json, null             | подпись под названием в шаге оплаты                                           |
| `icon`                     | string(255), null      | значок из медиатеки                                                           |
| `enabled`                  | bool, default false    |                                                                               |
| `position`                 | smallint unsigned      | ручной порядок                                                                |
| `delivery_rule`            | json                   | при какой доставке доступен (§7.3): `{"mode": "any"}` и др.                   |
| `min_amount`, `max_amount` | bigint unsigned, null  | границы суммы к оплате; `null` — без границы                                  |
| `settings`                 | json                   | несекретные поля типа (§6.1): реквизиты, режим шлюза, срок ссылки             |
| `credentials`              | text, null             | ключи шлюза: `encrypted:array` (§15); `null` — не заданы или из окружения     |
| `created_at`, `updated_at` | timestamp              |                                                                               |

- Способ неизвестного типа (спутник удалили) — не показывается покупателю, экран способов помечает
  его «тип не установлен»; блоки заказов и попытки с ним живут (в них копия).
- Миграция пакета создаёт два встроенных способа выключенными; миграция спутника — способ на каждый
  свой тип, тоже выключенным. Включает магазин.

### `payment_selections` — выбор покупателя для корзины

`cart_id` (bigint unsigned, **unique**, без FK — корзины чистят), `method_key` (string(64)),
`updated_at`. По образцу `delivery_selections` (решение 56 спеки заказов): выбор переживает
перезагрузку и возврат на следующий день; чистится вместе с корзиной (слушатель удаления корзины) и
ночной командой по сроку черновиков оформления.

### `payments` — попытки

| Колонка                    | Тип                     | Примечание                                                                     |
| -------------------------- | ----------------------- | ------------------------------------------------------------------------------ |
| `id`                       | bigint                  |                                                                                |
| `uuid`                     | char(26), **unique**    | ULID — в адресах и у шлюза (§4)                                                |
| `order_id`                 | FK `orders`, restrict   | index                                                                          |
| `method_key`               | string(64)              | ключ способа на момент попытки                                                 |
| `type`                     | string(64)              | ключ типа — какой спутник отвечает за попытку                                  |
| `amount`                   | bigint unsigned         | сколько просим: `Orders::balance()->due` на момент создания (решение 29)       |
| `currency`                 | char(3)                 | валюта заказа                                                                  |
| `status`                   | string(16), index       | §8.1                                                                           |
| `problem`                  | string(24), null, index | `duplicate`, `order_cancelled`, `amount_mismatch` (§9.4); `null` — проблем нет |
| `gateway_ref`              | string(190), null       | идентификатор платежа у шлюза; unique `(type, gateway_ref)`                    |
| `paid_amount`              | bigint unsigned, null   | сколько подтвердил шлюз                                                        |
| `paid_at`                  | timestamp, null         | время оплаты по шлюзу                                                          |
| `failure_reason`           | string(500), null       | причина отказа словами шлюза, без данных карты                                 |
| `expires_at`               | timestamp, null         | когда ссылка шлюза перестаёт работать (если шлюз её задаёт)                    |
| `source`                   | string(64)              | `checkout`, `pay-page`, `panel`, `mcp`, `api`                                  |
| `admin_id`                 | bigint unsigned, null   | если попытку создал или отметил человек (§12.2)                                |
| `meta`                     | json, null              | несекретное от спутника: маска карты `**** 1234`, способ внутри шлюза          |
| `created_at`, `updated_at` | timestamp               |                                                                                |

- **Индексы:** `order_id` — вкладка заказа; `(status, created_at)` — опрос незавершённых (§9.3) и
  журнал; `problem` — фильтр проблем; unique `(type, gateway_ref)` — уведомление находит попытку по
  идентификатору шлюза, а один платёж шлюза не привязывается к двум попыткам. Каждый — `EXPLAIN` на
  MariaDB в этапе 2.
- Попытка не удаляется: это след денег.

### `payment_events` — журнал общения со шлюзом

| Колонка      | Тип                 | Примечание                                                                               |
| ------------ | ------------------- | ---------------------------------------------------------------------------------------- |
| `id`         | bigint              |                                                                                          |
| `payment_id` | FK `payments`, null | index; `null` — уведомление не нашло попытку                                             |
| `method_key` | string(64)          |                                                                                          |
| `kind`       | string(16)          | `start`, `return`, `notify`, `poll`, `check` (кнопка или MCP), `manual`, `expire`        |
| `event_key`  | char(64), null      | sha256 идентификатора уведомления у шлюза или тела; unique `(method_key, event_key)`     |
| `reported`   | string(16), null    | статус, который сообщил шлюз, переведённый в §8.1                                        |
| `outcome`    | string(16)          | `applied`, `same`, `ignored`, `duplicate`, `rejected`, `unmatched`, `error`              |
| `message`    | string(500), null   | «подпись не сошлась», «сумма 9900 вместо 12000», текст ошибки транспорта без тела        |
| `payload`    | json, null          | пакет шлюза после маскирования (§15.3), обрезан до `webx-payments.events.payload_length` |
| `created_at` | timestamp           | index — чистка                                                                           |

- Журнал — для разбора «почему заказ не оплачен», а не источник правды: статус попытки меняет только
  `settle()`. Хранение — `webx-payments.events.days` (180), чистит `webx:payments:prune` ночью.
- Unique `(method_key, event_key)` — первый рубеж идемпотентности уведомлений (§9.2).

## 6. Контракт способа и реестр

### 6.1. `MethodType`

```php
namespace WebxUi\Payments\Contracts;

/**
 * One way to pay: on receipt, by bank transfer, a gateway's hosted page.
 * The site turns a type into methods; a buyer sees methods.
 */
interface MethodType
{
    /** 'store.on_receipt', 'gateway-a.card' — <provider>.<kind>, stable forever. */
    public function key(): string;

    /** 'store' for the built-in ones, or a satellite's gateway key (§14.1). */
    public function provider(): string;

    /** Offline — the manager marks payment; Online — the gateway confirms it (§6.3). */
    public function kind(): Kind;                // Kind::Offline | Kind::Online

    /** Whether the order waits for payment once placed (§11.1): online and bank transfer — yes. */
    public function awaitsPayment(): bool;

    /** Fields of payment_methods.settings this type reads, in the shape the panel draws (§13.3). */
    public function settings(): array;           // list<Field>

    /** Currencies the type can take; null — any. ISO 4217. */
    public function currencies(): ?array;

    /** Cheap, no gateway call: type-level limits beyond the method's own (§7). */
    public function available(Method $method, PaymentContext $context): Availability;

    /** The `payment` block of the order (§10.4): common part plus the type's own details. */
    public function snapshot(Method $method, PaymentContext $context): PaymentSnapshot;
}
```

```php
namespace WebxUi\Payments;

enum Kind: string
{
    case Offline = 'offline';
    case Online = 'online';
}

/** What a method is asked against: never a model, never the database. */
final readonly class PaymentContext
{
    public function __construct(
        public int $payable,                     // «к оплате», minor units; the only amount payments sees
        public string $currency,
        public string $locale,
        public ?ShippingChoice $shipping,        // null — no delivery step or no module-delivery (§7.3)
        public Purpose $purpose,                 // the cart's Purpose: Checkout | Order (the pay page asks as Order)
    ) {}
}

final readonly class Availability
{
    public static function yes(): self;
    /** A reason for MCP and the panel; the buyer never sees an unavailable method (§7.2). */
    public static function no(string $reasonKey, array $replace = []): self;
}
```

- **Тип не трогает базу и модели,** как тип доставки (§6.1 её спеки): способ ему даёт база
  неизменяемым `Method` (ключ, тип, названия, настройки, правило доставки, границы суммы).
- **Новые возможности — только необязательными интерфейсами** поверх `MethodType`: онлайн-платёж
  (`ChargesOnline`, §6.3), отмена незавершённой попытки (`CancelsPayments`), проверка подключения
  у шлюза (§14.1). Старые спутники не ломаются: возврат через шлюз, когда до него дойдёт (§21.2),
  добавится так же.

### 6.2. Реестры `MethodTypes` и `Gateways`

```php
namespace WebxUi\Payments;

final class MethodTypes
{
    /** @param  class-string<MethodType>|MethodType  $type  a class is made on first use */
    public function register(string|MethodType $type): void;
    public function has(string $key): bool;
    /** @throws UnknownMethodType */
    public function get(string $key): MethodType;
    /** @return list<MethodType> */
    public function all(): array;
}

final class Gateways
{
    public function register(string|Gateway $gateway): void;   // §14.1
    public function get(string $key): Gateway;
    /** @return list<Gateway> */
    public function all(): array;
}
```

- **Ключ типа** — `<провайдер>.<вид>`, `[a-z0-9-]+\.[a-z0-9_-]+`. Повторная регистрация —
  исключение. Встроенные — `store.on_receipt`, `store.bank_transfer` (§12).
- **Регистрация** — в `boot()` провайдера спутника через `callAfterResolving(MethodTypes::class)`,
  как типы доставки. Онлайн-тип без зарегистрированного шлюза — ошибка сборки.

### 6.3. `ChargesOnline` — онлайн-платёж

```php
namespace WebxUi\Payments\Contracts;

interface ChargesOnline extends MethodType
{
    /**
     * Opens a payment at the gateway for this attempt and tells where the buyer goes.
     * Called on GET /pay/go/{uuid}, never at order commit (§8.2). May throw GatewayUnavailable.
     */
    public function start(Method $method, Credentials $credentials, StartRequest $request): Handoff;

    /** Asks the gateway's API what happened to the payment. The only truth on return (§8.3). */
    public function fetchStatus(Method $method, Credentials $credentials, Payment $payment): GatewayStatus;

    /**
     * Reads a server-to-server notification: checks the signature with the credentials,
     * finds the gateway's payment id and status. Throws InvalidNotification (bad signature,
     * malformed body) — the base answers with rejectResponse() and journals it (§9.2).
     */
    public function readNotification(Method $method, Credentials $credentials, Request $request): Notification;

    /** The answer the gateway expects for an accepted notification ('OK', JSON, an empty 200). */
    public function acceptResponse(Notification $notification): Response;

    /** The answer for a rejected one; most gateways retry on non-2xx. */
    public function rejectResponse(InvalidNotification $e): Response;
}
```

```php
namespace WebxUi\Payments;

final readonly class StartRequest
{
    public function __construct(
        public string $uuid,           // our attempt id: the gateway's order id / merchant reference
        public int $amount,            // minor units; the satellite converts to the gateway's format without float
        public string $currency,
        public string $description,    // "Order 1024" in the order's locale
        public string $returnUrl,      // /pay/return/{uuid} — absolute
        public string $notifyUrl,      // /payments/notify/{method} — absolute
        public string $locale,         // the gateway page's language, if it can
        public ?string $customerEmail, // only if the gateway needs it for its own receipt
    ) {}
}

/** Where the buyer goes: a plain redirect or a form posted to the gateway (hosted checkout). */
final readonly class Handoff
{
    public static function redirect(string $url, ?string $gatewayRef = null, ?CarbonInterface $expiresAt = null): self;
    /** @param array<string, string> $fields  auto-submitted by a tiny page; no card fields, ever */
    public static function post(string $url, array $fields, ?string $gatewayRef = null): self;
}

final readonly class GatewayStatus
{
    public function __construct(
        public Reported $status,       // Pending | Paid | Failed | Cancelled | Expired | Refunded
        public ?string $gatewayRef,
        public ?int $amount,           // what the gateway took, minor units; null — not said
        public ?string $currency,
        public ?CarbonImmutable $paidAt,
        public ?string $reason,        // gateway's words for a failure; no card data
        public array $meta = [],       // masked card '**** 1234', payment kind — non-secret
    ) {}
}

final readonly class Notification
{
    public function __construct(
        public string $eventKey,       // the gateway's notification id, or sha256 of the raw body
        public ?string $uuid,          // our attempt id, if the gateway echoes it
        public ?string $gatewayRef,
        public GatewayStatus $status,
        public array $payload,         // the parsed body for the journal; masked by the base again (§15.3)
    ) {}
}
```

- **Обе формы передачи покупателя** — `redirect` (шлюз создал платёж по API и дал адрес) и `post`
  (шлюз принимает форму с подписанными полями на своей странице). Так контракт покрывает оба вида
  hosted-страниц; полей карты в форме нет и быть не может (решение 3), страница автоотправки
  проверяет это тестом: имя поля из списка `card|pan|cvv|cvc|expiry|exp_` — исключение.
- **Подпись проверяет спутник,** ключами из `Credentials` (§15). База не знает алгоритма, но
  контрактный тест (§14.3) требует: неверная подпись → `InvalidNotification`, сравнение — за
  постоянное время (`hash_equals`).
- **Сумму спутник переводит без `float`:** `Money::toDecimalString(int, currency)` и обратно
  (`Money::fromDecimalString`) — общий помощник `module-cart`; экспонента — из ISO 4217.
- **Статус шлюза переводит спутник** в закрытый список `Reported`; неизвестное — `Pending` с
  `reason` (база дождётся окончательного или истечения, §9.3).

### 6.4. Необязательные возможности

```php
interface CancelsPayments extends ChargesOnline
{
    /** Closes a pending payment so it cannot be paid any more (§9.4). False — the gateway refused. */
    public function cancel(Method $method, Credentials $credentials, Payment $payment): bool;
}
```

## 7. Способы для покупателя

### 7.1. Список

`WebxUi\Payments\Methods::available(PaymentContext $context): list<MethodOption>` — что видит
покупатель в шаге оплаты и на странице оплаты заказа:

1. включённые способы известных типов по `position` — из кеша группы `payments` (§18);
2. `payable > 0` — иначе список пуст и шага нет (решение 5);
3. валюта — в `currencies()` типа;
4. `min_amount ≤ payable ≤ max_amount`, если заданы;
5. правило доставки способа (§7.3);
6. `available()` типа;
7. у онлайн-способа заданы ключи (§15) — без ключей способ не показывается, а экран способов и
   `webx:doctor` предупреждают.

`MethodOption` — ключ, название, описание, значок, вид (`online` / `offline`) и подпись для
покупателя (§7.3: «Оплата на кассе» вместо «При получении» при самовывозе из магазина).

### 7.2. Почему способа нет

Покупатель недоступный способ не видит (как доставка: «способа нет — строки нет»). Причину видят
менеджер и агент: `Availability::no()` с ключом словаря — `amount_below_min`, `amount_above_max`,
`currency`, `delivery_rule`, `no_credentials`, `type_missing` и причины типа; MCP
`payments_available` отдаёт их по каждому способу (§16).

### 7.3. Правило доставки

Оплата не знает модуль доставки (решение 7). Оформление, у которого есть оба слота, кладёт в
контекст оплаты нейтральное описание выбранной доставки:

```php
namespace WebxUi\Orders\Checkout\Slots;

/** What the payment step may know about the chosen delivery; built by ShippingSlot. */
final readonly class ShippingChoice
{
    public function __construct(
        public string $method,           // delivery method key: 'store-pickup', 'carrier-a-branch'
        public string $carrier,          // 'store' or a carrier key
        public bool $cashOnDelivery,     // the carrier collects money on handover for this method and point
        public bool $storePickup,        // the buyer collects it at the shop's own point
    ) {}
}
```

- `ShippingChoice` — класс оформления `module-orders`; его даёт `ShippingSlot::choice(CheckoutContext): ?ShippingChoice`
  (новый метод слота доставки), а `PaymentSlot` получает через `CheckoutContext::shippingChoice()`.
  Ни доставка, ни оплата друг друга в `require` не держат. Решение 14; контракт оформления (§6.2 и
  §6.3 там) приводится к этой спеке.
- `cashOnDelivery` у доставки — признак способа (и точки: почтомат может не принимать наложенный
  платёж, `cod` ограничений точки §7.4 спеки доставки); `storePickup` — тип `store.pickup`.
- **`delivery_rule` способа:**

| `mode`    | Доступен, когда                                                                                         |
| --------- | ------------------------------------------------------------------------------------------------------- |
| `any`     | всегда (по реквизитам, онлайн-способы — по умолчанию)                                                   |
| `on_hand` | `cashOnDelivery` или `storePickup` (по умолчанию у «при получении»); без доставки — §1.1                |
| `methods` | ключ способа доставки — в списке `methods` правила (`{"mode": "methods", "methods": ["store-pickup"]}`) |
| `except`  | ключ способа доставки — не в списке `methods`                                                           |

- **Без выбранной доставки** (шага нет: в корзине только услуги и сертификаты, или доставка не
  стоит) `shipping = null`: правило `any` проходит, `on_hand` — проходит как «оплата по
  договорённости» (самовывоз, услуга на месте), `methods` / `except` — не проходит / проходит.
- **Доставка ещё не выбрана** (шаг доставки есть, покупатель его не прошёл) — шаг оплаты
  показывает все способы, кроме зависящих от доставки, и строку «Выберите доставку — от неё зависят
  способы оплаты»; после выбора доставки (`webx:checkout:slot-changed`) шаг перерисовывается. Выбор,
  ставший недоступным, сбрасывается с сообщением «Способ оплаты недоступен для этой доставки».
- **Комиссия службы за наложенный платёж** — не в сумме: у «при получении» при `cashOnDelivery`
  подпись «Комиссию за наложенный платёж берёт служба доставки по своим тарифам» (как «по тарифам
  перевозчика» у доставки). Своей комиссии способа в v1 нет.

## 8. Онлайн-оплата

### 8.1. Статусы попытки

| Статус      | Что значит                                                         | Куда может перейти                                       |
| ----------- | ------------------------------------------------------------------ | -------------------------------------------------------- |
| `created`   | строка есть, покупатель ещё не ушёл в шлюз                         | `pending`, `failed` (шлюз не открыл платёж), `cancelled` |
| `pending`   | платёж открыт у шлюза, результата нет                              | `paid`, `failed`, `cancelled`, `expired`                 |
| `paid`      | шлюз подтвердил оплату                                             | — (конечный)                                             |
| `failed`    | шлюз отказал (отклонено банком, 3-D Secure не пройден)             | `paid` (запоздалое подтверждение шлюза)                  |
| `cancelled` | покупатель отменил на странице шлюза или мы закрыли попытку (§9.4) | `paid` (запоздалое подтверждение шлюза)                  |
| `expired`   | срок ссылки шлюза вышел или шлюз не ответил до срока опроса (§9.3) | `paid` (запоздалое подтверждение шлюза)                  |

- **`paid` сильнее всего.** Подтверждение оплаты от шлюза применяется из любого незавершённого и
  неудачного статуса: деньги списаны — значит, списаны. Обратного нет: «отказ» или «отмена» после
  `paid` — `ignored` в журнале и строка «шлюз сообщил отказ уже оплаченной попытки» для менеджера.
- **Повтор того же статуса** — `same`, ничего не меняется и событий нет.
- `Reported::Refunded` от шлюза у попытки `paid` (вернули из кабинета шлюза) — не меняет заказ сам:
  строка истории заказа «Шлюз сообщил о возврате … — отметьте возврат» и `problem` нет; запись
  возврата `manual` в заказе — менеджер (§9.4 спеки заказов, решение 13).

### 8.2. Переход в шлюз

```
подтверждение оформления (§24.1 спеки заказов)
  → Orders::place(awaitPayment: true, blocks.payment = snapshot)       заказ «ожидает оплаты»
  → после коммита PaymentSlot::next(order)
        Payments::begin(order, method, source: checkout)               строка payments, status = created
        → '/pay/go/{uuid}'                                             ни одного запроса к шлюзу
  → браузер: GET /pay/go/{uuid}
        status ≠ created → страница оплаты заказа (§8.4): повторный «Назад» не открывает второй платёж
        start() → Handoff; gateway_ref, expires_at; status = pending; журнал kind = start
        redirect → 302 на страницу шлюза
        post     → маленькая страница с формой и автоотправкой (кнопка «Перейти к оплате» без JS)
        GatewayUnavailable → status = failed, paymentFailed(), страница оплаты «Не удалось открыть
                             оплату, попробуйте ещё раз или выберите другой способ»
```

- **Шлюз не вызывается в момент создания заказа:** `next()` не должен бросать (§6.3 спеки
  оформления), а запрос к шлюзу может упасть или зависнуть. Строка попытки и адрес `/pay/go` — без
  сети; сеть — на следующем запросе, где у ошибки есть своя страница.
- **Таймауты:** `start()` и `fetchStatus()` — `webx-payments.http.timeout` (10 с), без повторов в
  запросе покупателя.

### 8.3. Возврат на сайт

`GET /pay/return/{uuid}` — один адрес и для «успеха», и для «отказа»: какие параметры добавил шлюз,
база не читает (решение 26).

```
1. попытка по uuid; нет — 404
2. статус уже окончательный (уведомление пришло раньше) → сразу шаг 4
3. fetchStatus() → settle(kind = return)        таймаут или ошибка шлюза — статус не трогаем
4. paid                → успех: «Спасибо» оформления, если номер в сессии (§26 спеки заказов),
                         иначе подписанная страница заказа; flash «Оплата получена»
   failed / cancelled / expired → страница оплаты заказа с причиной и кнопками (§8.4)
   pending / created     → страница оплаты заказа в состоянии «в обработке» (§8.4)
```

### 8.4. Страница оплаты заказа

`GET /pay/{number}?signature=…` — что видит покупатель после шлюза, по ссылке из письма и с кнопки
«Оплатить» на «Спасибо» (`PaymentSlot::payAgainUrl()`). Состояние берётся из заказа и последней
попытки:

| Состояние                              | Что на странице                                                                                                                                                                                                                                                               |
| -------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| заказ оплачен                          | «Заказ 1024 оплачен», ссылка на страницу заказа                                                                                                                                                                                                                               |
| **нужна доплата** (оплачен, `due > 0`) | «Нужна доплата 15,00 по заказу 1024» (после правки состава, §41.3 спеки заказов) и **«Доплатить»** — новая попытка на `due` тем же или другим онлайн-способом                                                                                                                 |
| ждёт оплаты при получении              | способ, который оплаты не ждёт (`store.on_receipt`), и **«Оплатить онлайн сейчас»** со списком онлайн-способов (§8.5), если они доступны для заказа; нет таких — только способ                                                                                                |
| **в обработке** (последняя `pending`)  | «Проверяем оплату. Обычно это занимает до минуты» и индикатор; скрипт опрашивает `GET /api/payments/{uuid}/status` раз в 3 с до 2 минут. Пришёл `paid` — переход на успех; отказ — перерисовка в «не прошла»                                                                  |
| в обработке дольше 2 минут             | «Банк ещё не подтвердил оплату. Если деньги списались — заказ отметится оплаченным сам, мы пришлём письмо. Страницу можно закрыть» + «Проверить ещё раз» + ссылка на страницу заказа. Кнопки «Оплатить» нет: вторая оплата той же суммы — ровно то, что делает двойную оплату |
| оплата не прошла / отменена / истекла  | причина словами («Банк отклонил оплату»), **«Оплатить ещё раз»** (новая попытка тем же способом), **«Выбрать другой способ»** (§8.5)                                                                                                                                          |
| ждёт оплаты по реквизитам              | реквизиты способа (§12.2) и «Выбрать другой способ»                                                                                                                                                                                                                           |
| заказ отменён                          | «Заказ отменён», без кнопок оплаты                                                                                                                                                                                                                                            |

- **Опрос «в обработке» не ходит в шлюз** на каждый запрос: `/api/payments/{uuid}/status` читает
  строку попытки; в шлюз раз в 15 с ходит только он же, если с прошлой проверки прошло ≥ 15 с
  (`fetchStatus` с замком), — один покупатель не превратится в поток запросов к API шлюза.
- **«Проверить ещё раз»** — тот же `fetchStatus`, не чаще раза в 15 с на попытку.
- Без JavaScript — мета-обновление страницы раз в 5 с на те же 2 минуты.
- Опрос ~2 минуты, затем «страницу можно закрыть» и письмо «оплата получена» по вехе — решение 18.
- **Сумма на странице — всегда `Orders::balance()->due` сейчас** (решение 29): ссылка не хранит
  сумму, поэтому старая ссылка не возьмёт лишнего после правки состава, а у заказа без долга
  показывает «Заказ оплачен». Ссылка — без срока (вопрос 6 §44 спеки заказов).

### 8.5. Оплатить повторно и сменить способ

- **«Оплатить ещё раз»** — `POST /pay/{number}/attempts`: `Payments::begin()` новой попыткой. Перед
  ней база закрывает прежние незавершённые (§9.4). Сумма — `Orders::balance()->due` заказа на
  сейчас; снимок заказа не меняется.
- **«Выбрать другой способ»** — список §7.1 с `Purpose::Order` (контекст — сумма заказа, валюта,
  `ShippingChoice` из блока доставки заказа, если он есть). `POST /pay/{number}/method` →
  `Payments::switchMethod(order, method, source: 'pay-page')`:
  - `Orders::patchBlock($order, 'payment', snapshot, 'payment:<method>')` — новый блок, строка
    истории, `OrderBlockUpdated`;
  - незавершённые попытки прежнего способа закрываются (§9.4);
  - новый онлайн-способ — сразу `begin()` и переход в шлюз; по реквизитам — страница с
    реквизитами; при получении — «Заказ ждёт оплаты при получении» и флаг заказа «ожидает оплаты»
    снимается `Orders::stopAwaitingPayment($order, 'payment:<method>')` (решение 16).
- **Смена на способ, который ждёт оплаты** (заказ оформлен «при получении», покупатель решил
  платить онлайн): после `patchBlock` — `Orders::awaitPayment($order, source)` (§36.3 спеки заказов),
  заказ снова «ожидает оплаты»; новый онлайн-способ — сразу `begin()`, как выше. Со страницы оплаты
  это кнопка «Оплатить онлайн сейчас» (§8.4), из панели — «Сменить способ» и «Ссылка на оплату»
  (§13.4); дальше путь тот же, что после оформления (§8.2).
- Сменить способ может и менеджер в карточке заказа (§13.4) — тот же метод.
- **Доплата** (заказ оплачен, `due > 0` после правки состава): «Доплатить» — `begin()` на `due`;
  способ можно выбрать другой онлайн-способ, блок `payment` тогда не меняется — он про первую
  оплату, попытка хранит свой `method_key`.
- **Заказ отменён** — оба действия отказывают словами; **оплачен без долга** — тоже.

## 9. Подтверждение и идемпотентность

### 9.1. `Payments::settle()`

```php
namespace WebxUi\Payments;

final class Payments
{
    /** A new attempt for an order; closes earlier unfinished ones first (§9.4). */
    public function begin(Order $order, Method $method, string $source): Payment;

    /**
     * The only door from "the gateway says" to our state. One transaction:
     * SELECT … FOR UPDATE on the payments row, transition by §8.1, journal line,
     * then the order through Orders (mark / paymentFailed / note) in the same transaction.
     */
    public function settle(Payment $payment, GatewayStatus $status, string $kind): SettleResult;

    /** Asks the gateway and settles; the panel's and MCP's «Check with the gateway» (§13.4). */
    public function check(Payment $payment, bool $dryRun = false): SettleResult;

    /** Bank transfer or a manual mark (§12.2): a paid row with admin_id, then Orders::mark(paid). */
    public function markPaid(Order $order, ?string $reference, ?string $note, string $source,
        bool $dryRun = false): SettleResult;

    public function switchMethod(Order $order, Method $method, string $source,
        bool $dryRun = false): SwitchResult;
}
```

`settle()` по новому статусу:

| Шлюз сообщил                       | Попытка                               | Заказ                                                                                                                                                                                                                                                                                                                                                                 |
| ---------------------------------- | ------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Paid`, сумма и валюта сошлись     | → `paid`, `paid_amount`, `paid_at`    | вехи `paid` нет — `Orders::mark(paid, source: 'payment:<method>', at: paidAt, reference: gateway_ref, amount: paid_amount)`; веха есть и `due > 0` — доплата `Orders::recordPayment(paid_amount, 'payment:<method>', gateway_ref, paidAt)` (решение 29); веха есть и `due = 0` — `problem = duplicate` (§9.4); заказ отменён — `problem = order_cancelled` и `note()` |
| `Paid`, сумма не та                | → `paid`, `problem = amount_mismatch` | веха **не** ставится; `note()` «Шлюз подтвердил 99,00 вместо 120,00 — проверьте и отметьте оплату вручную»                                                                                                                                                                                                                                                            |
| `Failed` / `Cancelled` / `Expired` | → соответствующий                     | `Orders::paymentFailed(source, reason)` — только если это последняя попытка заказа и заказ не оплачен                                                                                                                                                                                                                                                                 |
| `Pending`                          | без изменений (`created` → `pending`) | —                                                                                                                                                                                                                                                                                                                                                                     |

- **Порядок «возврат раньше уведомления» и наоборот** — одинаков: оба зовут `settle()` с одним и
  тем же статусом, замок строки ставит их в очередь, второй видит `same`. Веха `paid` повторно не
  ставится (`AlreadyMarked` от своей же попытки — не проблема: база сравнивает `reference`).
- **Сумма сверяется с попыткой**, а не с заказом: `amount_mismatch` — шлюз подтвердил не ту сумму,
  на которую открыта попытка. Если долг заказа изменился, пока попытка ждала (менеджер правил
  состав), подтверждённая сумма всё равно входит в `paid_total`, а баланс заказа показывает остаток
  или переплату (§41.3 спеки заказов).
- **Одна транзакция на попытку и заказ:** `Orders::mark()` сам берёт `FOR UPDATE` на заказ (§5.3
  спеки заказов); порядок замков всегда «попытка → заказ», чтобы две попытки одного заказа не
  взаимоблокировались. Событие `OrderPaid` уходит после коммита (`ShouldDispatchAfterCommit`).
- **События оплаты** (`WebxUi\Payments\Events`, после коммита): `PaymentSucceeded`, `PaymentFailed`,
  `PaymentProblem` (`problem`). Заказной веер (`OrderPaid`,
  `OrderPaymentFailed`) остаётся главным; события оплаты — для уведомлений менеджеру о проблемах и
  для спутников; `PaymentProblem` бросает событие уведомлений `payments.problem` (§11.5).

### 9.2. Уведомление шлюза

`POST /payments/notify/{method}`:

```
1. способ по ключу, онлайн-тип, ключи заданы; иначе 404 и journal(rejected) без тела
2. readNotification() — подпись и разбор; InvalidNotification → journal(rejected, «подпись не сошлась»),
   rejectResponse() (обычно 400 — шлюз повторит; наш журнал покажет, что ключи не те)
3. дубль: insert payment_events (method_key, event_key) — нарушение unique → acceptResponse(), outcome = duplicate
4. попытка: по uuid из уведомления, иначе по (type, gateway_ref); нет → journal(unmatched),
   acceptResponse() — чужой платёж повторами не исправить, менеджер видит его в журнале
5. settle(kind = notify) → journal(applied | same | ignored), acceptResponse()
6. исключение на шаге 5 → откат, journal(error) отдельной транзакцией, 500 — шлюз повторит
```

- **Доверяем только подписанному пакету.** IP-список шлюза — необязательная настройка способа
  (`notify_ips`), дополнительная, а не вместо подписи.
- **Статус в уведомлении** — не единственный путь: спутник может в `readNotification()` дозапросить
  `fetchStatus`, если шлюз шлёт только «что-то изменилось» (у некоторых шлюзов так); контракт это
  допускает.
- **Повторы шлюза** (тот же `event_key`) отсекаются на шаге 3 до замков и запросов к заказу;
  **запоздалое уведомление** (другой `event_key`, тот же статус) доходит до `settle()` и даёт `same`.

### 9.3. Фоновый опрос

`webx:payments:poll` (по расписанию раз в 5 минут): попытки в `created` / `pending` старше 5 минут и
моложе `expires_at` (или 48 часов, если шлюз срока не дал) — `check()` пачкой по 50, не больше
одного запроса к шлюзу на попытку за запуск. Уведомление потерялось, покупатель закрыл вкладку —
опрос всё равно приведёт попытку к окончательному статусу. Попытка старше срока без ответа шлюза —
`expired` с `reason` «шлюз не ответил».

### 9.4. Двойная оплата и проблемы

- **Не допускается:** `begin()` перед новой попыткой берёт прежние `created` / `pending` попытки
  заказа и для каждой: `check()` — уже `paid`? Тогда новой попытки нет, покупатель видит «Заказ уже
  оплачен». Иначе — `cancel()`, если тип умеет (`CancelsPayments`), и попытка → `cancelled`; не
  умеет — попытка остаётся `pending` до своего срока, а страница оплаты предупреждает: «Если вы уже
  оплатили в другой вкладке — подождите минуту, прежде чем платить снова».
- **Возвращается, если проскочила:** подтверждённая попытка заказа, у которого долга уже нет
  (`due = 0`; доплата после правки состава — не дубль, решение 29), — `paid` с
  `problem = duplicate`, строка истории заказа «Повторная оплата 120,00 через шлюз A, платёж … —
  верните деньги», событие `PaymentProblem` → уведомление менеджеру `payments.problem` (решение 17).
  Деньги менеджер возвращает вне сайта и записывает возврат `manual` в заказе (решение 13).
- **Деньги на отменённый заказ** — `problem = order_cancelled`, `note()` по §5.4 спеки заказов.
- **Сумма не та** — `problem = amount_mismatch` (§9.1).
- **Менеджер видит проблемы** вкладкой «Проблемы» журнала оплат (§13.2), отметкой в карточке
  заказа и в MCP `payments_problems`; «проблема решена» — кнопка с заметкой (попытка остаётся
  `paid`, `problem` не сбрасывается, рядом — `resolved_at` и автор в `meta`).

## 10. Шаг оформления — `PaymentSlot`

### 10.1. Слот

`WebxUi\Payments\Checkout\PaymentsCheckoutSlot` реализует `PaymentSlot` (§22.3 спеки заказов) и
регистрируется в `boot()`, если стоит оформление:

| Метод             | Что делает                                                                                                                                  |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| `needed()`        | `payable > 0` и `Methods::available()` не пуст (решение 5)                                                                                  |
| `render()`        | `<x-webx-payments::step>` — радиокнопки: значок, название, описание; один способ — свёрнутая строка с названием                             |
| `summary()`       | название выбранного способа; `null` — не выбран                                                                                             |
| `check(Order)`    | выбор есть, способ включён и доступен для `ShippingChoice` и суммы прямо сейчас; иначе — «Способ оплаты больше недоступен, выберите другой» |
| `snapshot()`      | блок `payment` (§10.4)                                                                                                                      |
| `awaitsPayment()` | `awaitsPayment()` типа выбранного способа                                                                                                   |
| `next()`          | онлайн — `begin()` и `/pay/go/{uuid}`; иначе `null` («Спасибо»). Не бросает: ошибка → лог и `null`, на «Спасибо» есть «Оплатить»            |
| `payAgainUrl()`   | подписанная `/pay/{number}`, если заказ ждёт оплаты и не отменён                                                                            |

- **Сумма 0 в момент подтверждения** (зачёт кошелька погасил всё после выбора способа) — `needed()`
  ложно, шаг скрыт, `awaitPayment = false`, блок `payment` — `null`, заказ оплаты не ждёт; веху
  `paid` ставит менеджер. Ставить ли её сразу — вопрос 1 §21.1.

### 10.2. API шага

- `GET /api/payments/methods` — способы для своей корзины (по cookie `module-cart`): `MethodOption`
  каждого, выбранный, подсказка про доставку (§7.3). `ShippingChoice` сервер берёт сам через
  `CheckoutContext`, клиент его не присылает.
- `PUT /api/payments/selection` (`method`) — проверка §7.1 и запись `payment_selections`; ответ —
  `summary` и событие страницы `webx:checkout:slot-changed`.
- Без JavaScript — обычная форма шага с перезагрузкой (решение 55 спеки заказов).

### 10.3. Компоненты

`<x-webx-payments::step>` (шаг), `<x-webx-payments::instructions :order>` (реквизиты на «Спасибо»,
странице заказа и странице оплаты), `<x-webx-payments::status :order>` (строка «Ждёт оплаты» /
«Оплачен» с кнопкой «Оплатить»). Классы — `.wx-payments-*`, вид — тема (§27.2 спеки заказов).

### 10.4. Блок `payment` заказа

```json
{
  "method": "bank-transfer",
  "type": "store.bank_transfer",
  "kind": "offline",
  "title": { "en": "Bank transfer", "ru": "По реквизитам" },
  "price": null,
  "instructions": {
    "recipient": { "en": "Shop A Ltd" },
    "account": { "en": "Account 0000 0000 0000 0000, Bank A, code 000000" },
    "purpose": { "en": "Payment for order 1024" }
  }
}
```

- Общая часть по §6.4 спеки заказов: `method`, `title`, `price` (`null` — комиссий в v1 нет).
  `type`, `kind` и детали — оплаты. Реквизиты копируются в блок на языках сайта: правка реквизитов в
  панели не меняет уже выданные (заказ — снимок). Назначение платежа — шаблон способа с подставленным
  номером заказа.
- `summary` блока для списка и письма — название способа на языке заказа.
- Попытки в блок не пишутся: они в `payments`, вкладка «Оплата» показывает их рядом.

## 11. Заказ и вехи

### 11.1. Какие вехи ставит оплата

| Что случилось                             | Вызов `Orders`                                                       |
| ----------------------------------------- | -------------------------------------------------------------------- |
| заказ с онлайн-способом или по реквизитам | `place(awaitPayment: true)` — делает оформление по `awaitsPayment()` |
| шлюз подтвердил оплату                    | `mark(paid, 'payment:<method>', paidAt, gateway_ref)`                |
| менеджер отметил оплату по реквизитам     | `markPaid()` → `mark(paid, 'panel' / 'mcp', now, reference)`         |
| попытка не прошла                         | `paymentFailed('payment:<method>', reason)`                          |
| деньги на отменённый заказ, сумма не та   | `note()`                                                             |
| смена способа                             | `patchBlock('payment', …)`                                           |
| смена на способ, который оплаты не ждёт   | `stopAwaitingPayment('payment:<method>')` (решение 16)               |
| смена на способ, который ждёт оплаты      | `awaitPayment('payment:<method>')` (решение 29)                      |
| доплата после правки состава подтверждена | `recordPayment(paid_amount, 'payment:<method>', gateway_ref)`        |

- Ручная отметка «Отметить оплату» в шапке карточки заказа (§11.3 спеки заказов) — тоже
  `Orders::mark()`; оплата слушает `OrderPaid` и, если источник не её, закрывает незавершённые
  попытки (§9.4) — чтобы запоздалая оплата шлюза стала видимой проблемой, а не тишиной.
- Веху `received` оплата не ставит и не читает.

### 11.2. Связь с зачётами кошелька

Оплата не видит зачётов (решение 5): сумма попытки — `Orders::balance()->due` (решение 29), где
`payable = total − tenders_total` уже посчитал снимок. Сертификаты и кешбек списываются в
`OrderPlacing` до любой попытки (решение 25 спеки заказов) и возвращаются отменой (§9.1 там);
оплата их не возвращает и в блок не пишет. На странице оплаты и в шаге — только «К оплате» без
расшифровки.

### 11.3. Сумма для наложенного платежа

`Payments::cashOnDelivery(OrderData $order): ?int` — шов для накладной доставки (§11.2 её спеки):
способ заказа — `store.on_receipt` и доставка с `cashOnDelivery`, у заказа есть долг →
`Orders::balance()->due` (после правки состава — новая сумма, решение 29); иначе `null`. Доставка зовёт его по `class_exists(Payments::class)`.

### 11.4. Неоплаченный заказ

Автоотмены нет — ни у заказов, ни у оплаты (решение 15). Неоплаченный заказ менеджер находит
вкладками «Ожидают оплаты» и «Оплата не прошла» (§5.4–§5.5 спеки заказов) и решает сам: связаться,
сменить способ (§8.5), отметить оплату, отменить (`Orders::cancel()`). Позже то же сделает учётная
программа через API сайта. Отменённый заказ, на который потом пришли деньги, — проблема
`order_cancelled` (§9.4).

### 11.5. Письма

**Переменные оплаты в событиях заказа** (`module-notifications`, если оба стоят): `payment_url`
(подписанная страница оплаты — «оплатить повторно»; пусто у оплаченного и отменённого),
`payment_instructions` (реквизиты текстом на языке заказа, пусто у других способов),
`payment_method` (название).

Такого шва у уведомлений сейчас нет: событие объявляет свои переменные одним `EventDefinition`, а
значения целиком собирает тот, кто зовёт `Notifications::send()`. **Правка пакета
`module-notifications`** (решение 19, этап 8):

```php
namespace WebxUi\Notifications;

final class NotificationEvents
{
    /**
     * Another module adds variables to an event it does not own: payments adds a pay link to the
     * order's events. The values are computed at send() from the subject, before the snapshot.
     *
     * @param  list<string>  $events  event keys or a prefix with '*': 'orders.*'
     * @param  list<Variable>  $variables
     * @param  callable(?Model $subject, string $event): array<string, mixed>  $values
     */
    public function extend(array $events, array $variables, callable $values): void;
}
```

- Переменные расширения видны в редакторе канала и в примерах события (`examples()`), как свои.
- Имя, которое уже объявил владелец события, расширение не перекрывает: регистрация — исключение.
- Ошибка в `$values` не роняет отправку: переменные расширения пустые, предупреждение в лог.

**Письмо покупателю «оплата получена»** (решение 18): канал `orders.paid` на адресата
`customer_email` — в наборе каналов по умолчанию; владелец сайта может его выключить. Канал и шаблон
объявляет `module-orders` (исключение из решения 31 там), оплата только даёт переменные.

**Проблема оплаты менеджеру** (решение 17): событие уведомлений `payments.problem` объявляет
`module-payments` (`PaymentProblem`: двойная оплата, не та сумма, деньги на отменённый заказ).
Переменные: `number`, `order_url`, `problem` (вид словами), `amount`, `method`, `gateway_ref`.
Каналы — настройка `payments.notify.problem`, как у событий заказа.

### 11.6. Возврат

Возврата через шлюз в v1 нет (решение 13): ни контракта у спутника, ни таблицы, ни этапа. Возврат —
только ручная запись `manual` в заказе (решение 27 спеки заказов): менеджер вернул деньги в кабинете
шлюза или переводом и записал это в карточке заказа. Оплата в возврате не участвует.

## 12. Встроенные способы

### 12.1. `store.on_receipt` — оплата при получении

- `Kind::Offline`, `awaitsPayment() = false`: заказ не ждёт оплаты, в фильтр «ожидает оплаты» не
  попадает.
- `delivery_rule` по умолчанию — `on_hand` (§7.3). Подпись для покупателя зависит от доставки:
  `storePickup` — «Оплата на кассе» (настройка `title_pickup` по языкам), `cashOnDelivery` —
  «Оплата при получении» с подписью о комиссии службы.
- Настройки: `title_pickup`, `cod_note` (подпись про комиссию службы, по языкам; пусто — по
  умолчанию из словаря).
- Оплату отмечает менеджер («Отметить оплату» карточки заказа) или учётная программа через API;
  автоматической связи «получен → оплачен» в v1 нет (§21.2).
- Сумма для накладной — §11.3.

### 12.2. `store.bank_transfer` — по реквизитам

- `Kind::Offline`, `awaitsPayment() = true`: заказ «ожидает оплаты» до отметки менеджера.
- Настройки по языкам сайта: `recipient` (получатель), `account` (счёт и банк одним многострочным
  полем — формат реквизитов зависит от страны, база его не разбирает), `purpose` (шаблон назначения
  с `{number}`; по умолчанию «Payment for order {number}»), `note` (срок оплаты словами, «Оплатите
  в течение 3 дней»).
- Реквизиты видны: на «Спасибо» (`<x-webx-payments::instructions>`), на странице заказа и странице
  оплаты, в письме (`payment_instructions`, §11.5). Счёта PDF и полей юрлица нет (решение 9).
- **Отметка оплаты** — «Отметить оплату по реквизитам» на вкладке «Оплата» (§13.4) или MCP
  `payments_mark_paid`: номер платёжного документа (`reference`, необязательно), дата поступления,
  заметка → `markPaid()`: строка `payments` (`status = paid`, `source = panel`, `admin_id`) и
  `Orders::mark(paid)`. Сумму менеджер не вводит: оплата — на `payable`; частичной оплаты по
  реквизитам в v1 нет (решение 23) — перевели меньше, менеджер связывается с покупателем.
- В примерах и демо — только фейковые реквизиты («Shop A Ltd», «Account 0000 0000 0000 0000»,
  «Bank A»).

## 13. Панель

Раздел «Оплата» (`payments`) в меню магазина. Экраны — JSON ([`WEBX_UI_SCREENS.md`](WEBX_UI_SCREENS.md)),
npm-пара `@webx-ui/module-payments` регистрирует раздел, узлы `wx-payments-attempts`,
`wx-payments-events` и вкладку заказа.

### 13.1. Права

| Право             | Что даёт                                                                         |
| ----------------- | -------------------------------------------------------------------------------- |
| `payments.view`   | способы (без ключей), журнал попыток и событий, вкладка «Оплата» заказа — чтение |
| `payments.manage` | способы: включить, порядок, тексты, реквизиты, правила, границы; ключи шлюзов    |

- Действия с заказом на вкладке «Оплата» — **права заказов**: «Отметить оплату по реквизитам»,
  «Сменить способ», «Проверить у шлюза», «Ссылка на оплату», «Проблема решена» — `orders.manage`.
  Оплата не заводит второго права на те же действия.
- Ключи — `payments.manage`; отдельного права на секреты нет (решение 20).
- Права — в `PaymentsModule::permissions()` и `### Permissions` `AGENTS.md`; маршруты — `cms.can`;
  `AccessCoverageTest` зелёный. Роль «Менеджер заказов» (решение 33 спеки заказов) получает
  `payments.view`.

### 13.2. Экраны раздела

| Экран                   | Что                                                                                                                                                                                                      |
| ----------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `payments.methods`      | способы ручного порядка (перетаскивание): значок, название, тип, шлюз, режим (тест / боевой), ключи заданы ли, включён; «Добавить способ» — выбор типа; предупреждения «нет ключей», «тип не установлен» |
| `payments.methods.form` | §13.3                                                                                                                                                                                                    |
| `payments.index`        | журнал попыток (`->paginate()`): дата, заказ (ссылка), способ, сумма, статус, проблема; вкладки «Все», «В обработке», «Не прошли», **«Проблемы»**; фильтры по способу, периоду                           |
| `payments.show`         | попытка: статусы, суммы, `gateway_ref`, события журнала (`wx-payments-events`: вид, что сообщил шлюз, итог, пакет свёрнутым JSON после маскирования), «Проверить у шлюза»                                |

### 13.3. Форма способа

- Название, описание, подпись при самовывозе — по языкам; значок из медиатеки.
- Правило доставки (§7.3): «Всегда», «Когда деньги берут при получении», «Только для способов
  доставки…», «Кроме способов доставки…» — список способов доставки показывается, если стоит
  `module-delivery` (по `class_exists`, через его публичный список, без `require`); без него — поле
  ключей текстом.
- Границы суммы.
- Поля типа (`settings()`): реквизиты у «по реквизитам», режим и параметры шлюза у онлайн-способа.
- **Ключи шлюза** (`credentials()` шлюза, §15): каждое поле — «задано · заменить» или пустое поле
  ввода; значение не возвращается в браузер никогда. «Проверить подключение» — `testConnection()`
  шлюза с введёнными или сохранёнными ключами, ответ словами.
- **Адрес уведомлений** способа (`/payments/notify/{key}`, абсолютный) и адрес возврата — только
  чтение, с кнопкой «Копировать»: их вписывают в кабинет шлюза.

### 13.4. Вкладка «Оплата» в карточке заказа

Регистрируется патчем узла `tabs` карточки `orders.show` (§11.3 спеки заказов):

- блок способа (название, реквизиты для «по реквизитам»), состояние: ждёт оплаты · оплачен · не
  прошла;
- попытки заказа таблицей: дата, способ, сумма, статус, проблема, `gateway_ref`; раскрытие —
  события журнала;
- действия: «Отметить оплату по реквизитам» (§12.2), «Проверить у шлюза» (последняя незавершённая),
  «Сменить способ» (диалог со способами §7.1 с `Purpose::Order` и последствиями из `dry_run`),
  «Ссылка на оплату» (копировать подписанную ссылку или «Отправить покупателю» — письмо
  `orders.payment_requested`, §40.2 спеки заказов; рядом — сумма `due`, которую увидит покупатель),
  «Проблема решена»;
- у оплаченного заказа с долгом после правки состава — строка «Нужна доплата 15,00» и та же
  «Ссылка на оплату»; доплаты — строками в таблице попыток.

## 14. Спутник шлюза — шаблон пакета

Шаблон без конкретного шлюза; пример в тексте и в наборе тестов — «шлюз A». Имя пакета —
`payment-<шлюз>` (решение 22), как `delivery-<служба>` у доставки.

### 14.1. Что спутник регистрирует

```php
namespace WebxUi\Payments\Contracts;

/** A payment gateway on the site: who holds the keys and how to test them. */
interface Gateway
{
    public function key(): string;                    // 'gateway-a'
    public function title(string $locale): string;
    public function icon(): ?string;

    /** Non-secret settings of the gateway shared by its methods: shop id, test mode. */
    public function settings(): array;                // list<Field>

    /** Secret fields: stored encrypted (§15), never shown back. */
    public function credentials(): array;             // list<Field>, each secret: true

    /** The cheapest authenticated API call; words for the panel on failure, never the raw body. */
    public function testConnection(Credentials $credentials, array $settings): ConnectionResult;
}
```

Провайдер спутника, если стоит `module-payments`:

```php
$this->callAfterResolving(Gateways::class, fn (Gateways $g) => $g->register(GatewayA::class));
$this->callAfterResolving(MethodTypes::class, fn (MethodTypes $t) => $t->register(CardType::class)); // gateway-a.card
```

Тип спутника реализует `ChargesOnline` и, если шлюз умеет, `CancelsPayments`. Миграция спутника создаёт способ на тип выключенным. Шлюзы A и B из решения 4
покрываются так:

| Что умеет шлюз                                | Чем закрыто в контракте                                        |
| --------------------------------------------- | -------------------------------------------------------------- |
| создаёт платёж по API и даёт адрес страницы   | `Handoff::redirect()`                                          |
| принимает подписанную форму на своей странице | `Handoff::post()`                                              |
| server-to-server POST с подписанным пакетом   | `readNotification()` + `acceptResponse()` / `rejectResponse()` |
| уведомление без статуса («изменилось»)        | `readNotification()` дозапрашивает `fetchStatus()`             |
| API статуса платежа                           | `fetchStatus()`                                                |
| отмена незавершённого                         | `CancelsPayments`                                              |
| возврат                                       | не в v1 (решение 13): менеджер возвращает в кабинете шлюза     |
| тестовый режим с отдельными ключами           | `settings()` шлюза (`mode`) и отдельные поля `credentials()`   |

### 14.2. Ключи и адреса

- Адрес API шлюза — из конфига спутника (тестовый и боевой), не ввод человека: `RemoteFetcher` не
  нужен («Правило: скачивание по адресу»). HTTP — через `Http` Laravel с таймаутом базы (§8.2).
- Ключи — `Credentials` от базы (§15); спутник не читает `payment_methods` и `env()` сам.

### 14.3. Что спутник сдаёт

- контрактный тест `WebxUi\Payments\Testing\GatewayContractTest` (абстрактный, из базы) на
  записанных ответах и уведомлениях шлюза: верная подпись принимается, неверная и подменённая сумма
  — `InvalidNotification`; повтор уведомления даёт тот же `eventKey`; статусы шлюза переведены в
  `Reported` полностью (неизвестный — `Pending`); суммы туда и обратно без потерь для валют с
  экспонентой 0, 2 и 3; `Handoff::post()` без полей карты; ответы на ошибки шлюза — словами, без
  тела;
- `AGENTS.md` по формату, словарь `webx-payment-<шлюз>` (слова только своего шлюза: статусы,
  причины отказов);
- своя спека — `WEBX_UI_PAYMENT_<ШЛЮЗ>.md` в репозитории спутника (§8 карты);
- проверку на тестовом контуре шлюза: оплата, отказ, отмена на странице шлюза, уведомление раньше
  и позже возврата.

### 14.4. Подставной шлюз

`WebxUi\Payments\Testing\FakeGateway` (тип `fake.card`) — в пакете базы для тестов, плейграунда и
демо-сайта: «страница шлюза» — маршрут базы, доступный только при `app.debug` и `fake` в списке
`webx-payments.fake`, с кнопками «Оплатить», «Отказ», «Отменить», «Оплатить, но уведомление
позже», «Уведомление дважды»; подпись — HMAC с тестовым ключом. Им проверяются все сценарии §8–§9
до первого настоящего спутника.

## 15. Ключи шлюзов

### 15.1. Где лежат

- **В базе — зашифрованными:** `payment_methods.credentials` с приведением `encrypted:array`
  (ключ — `APP_KEY` сайта). Ни в git, ни в сидерах, ни в демо, ни в фикстурах плейграунда — там
  только ключи `FakeGateway`.
- **Или в окружении:** `WEBX_PAYMENTS_<METHOD>_<FIELD>` (например `WEBX_PAYMENTS_GATEWAY_A_CARD_SECRET_KEY`)
  — если задано, сильнее значения в базе; форма показывает «задано в окружении» без кнопки
  «заменить». Для тех, кто не хочет держать боевые ключи в базе. Оба пути — решение 21.
- `Credentials` — неизменяемый объект с `get(name)`; `__toString`, `__debugInfo`, `jsonSerialize`
  отдают `[hidden]`, чтобы ключ не уехал в лог, дамп исключения или ответ.
- Смена `APP_KEY` делает сохранённые ключи нечитаемыми: способ становится «нет ключей», экран и
  `webx:doctor` говорят «ключи нужно ввести заново» (как подписанные ссылки, решение 32 спеки
  заказов).

### 15.2. Где не видны

- **Панель:** значение не уходит в браузер — ни в форме, ни в JSON экрана; поле — «задано /
  заменить», пустая отправка не стирает (стереть — отдельная кнопка «Удалить ключ»).
- **MCP:** не показываются и не задаются никогда — `credentials: ["secret_key: set"]` в ответах;
  `payments_methods_save` с полем ключа отказывает словами «Gateway keys are set in the panel only»
  (§16).
- **Журнал:** ни `payment_events`, ни `mcp_calls`, ни история заказа ключей не содержат.
- **Логи:** ошибки шлюза пишутся без заголовков авторизации и тела запроса.

### 15.3. Маскирование пакетов

Перед записью в `payment_events.payload` база затирает ключи
`password|secret|token|api_key|key|signature|sign|hash|authorization|cookie` (как журнал MCP, §10
спеки доступа) и всё, что похоже на номер карты (13–19 цифр подряд — кроме последних четырёх),
даже если шлюз не должен такое присылать. Спутник маскирует своё до базы; база — второй рубеж.

## 16. MCP

Области `payments:read` / `payments:write`; права по умолчанию (`payments.view` / `payments.manage`),
действия с заказом — `permission: 'orders.manage'` (§13.1).
Каждый изменяющий инструмент — с `dry_run`, с тем же ответом, что диалог панели. Деньги — целым и
строкой `*_formatted`. **Ключей шлюзов инструменты не видят и не принимают** (§15.2).

| Инструмент                 | Что делает                                                                                                                                                                           |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `payments_methods_list`    | способы: ключ, тип, шлюз, название, включён, порядок, правило доставки, границы, `credentials: ["secret_key: set" / "missing"]`                                                      |
| `payments_methods_save`    | создать или изменить способ по `key`: названия, описания, реквизиты, правило доставки, границы, включён; поле ключа — отказ                                                          |
| `payments_methods_reorder` | порядок способов                                                                                                                                                                     |
| `payments_available`       | пробный список для суммы, валюты и способа доставки (или `cart_id`): каждый способ — доступен или причина (§7.2)                                                                     |
| `payments_list`            | попытки страницами: заказ, способ, сумма, статус, проблема, период                                                                                                                   |
| `payments_get`             | попытка или все попытки заказа (по номеру) с журналом событий после маскирования                                                                                                     |
| `payments_check`           | спросить шлюз о попытке и применить (`orders.manage`); `dry_run` — что сообщил шлюз и что изменится, без записи                                                                      |
| `payments_mark_paid`       | отметить оплату по реквизитам или вручную (`orders.manage`): номер заказа, `reference`, дата, заметка; `dry_run` — веха, события, что закроется                                      |
| `payments_switch_method`   | сменить способ заказа (`orders.manage`); `dry_run` — новый блок, что закроется, снимется ли «ожидает оплаты»                                                                         |
| `payments_pay_link`        | подписанная ссылка на страницу оплаты заказа и сумма `due`, которую покупатель увидит (`orders.manage`: ссылка открывает оплату чужого заказа); долга нет — ссылка и «заказ оплачен» |
| `payments_problems`        | сводка: проблемы по видам, попытки «в обработке» дольше часа, заказы «оплата не прошла», способы без ключей                                                                          |
| `payments_resolve_problem` | «проблема решена» с заметкой (`orders.manage`)                                                                                                                                       |

- **Ресурс `payments://catalog`:** типы (ключ, шлюз, вид, ждёт ли оплаты, умеет ли отмену),
  режимы правила доставки, статусы попыток и переходы §8.1, виды проблем, адреса уведомлений
  способов — из реестров и enum'ов.
- Отказ — `ToolFailure` словами: «Order 1024 is paid already», «Method gateway-a-card has no keys»,
  «Gateway keys are set in the panel only».
- `payments_list`, `payments_problems`, `payments_methods_list` — в `PerformanceTest` тёплыми.

## 17. Словарь `webx-payments`

Ни одной строки в коде; десять языков, `en` / `ru` / `uk` — совпадающие наборы ключей. Группы:

- `module.php` — раздел, пункты меню, экраны;
- `methods.php` — названия встроенных типов и способов по умолчанию («Payment on receipt», «Pay at
  the counter», «Bank transfer»), подпись про комиссию службы, «Payment for order {number}»;
- `buyer.php` — шаг и страница оплаты: «Choose delivery first — payment options depend on it»,
  «Checking your payment…», «The bank has not confirmed the payment yet…», «Pay again», «Choose
  another way to pay», «Order 1024 is paid»;
- `statuses.php` — статусы попыток, виды проблем, исходы журнала;
- `reasons.php` — причины недоступности (§7.2) и общие причины отказа («The bank declined the
  payment», «The payment page could not be opened»);
- `errors.php` — отказы API и MCP.

Слова покупателя — английские дефолты в словаре, перевод — тема и сайт; слова шлюза — словарь
спутника.

## 18. Производительность и кеш

- **Группа кеша `payments`:** включённые способы с настройками (без ключей) — `bumpOn` модели
  способа; тест свежести: правка способа в панели — шаг оформления показывает новое.
- **Шаг оплаты:** список способов — из кеша, без запросов к шлюзу (§7.1); бюджет — 1 запрос
  (выбор по корзине) на тёплом кеше.
- **Возврат на сайт:** один `fetchStatus()` с таймаутом; страница «в обработке» — опрос строки по
  `uuid`, без запросов к заказу на каждый тик.
- **Уведомление:** постоянное число запросов (журнал, попытка с замком, заказ с замком, веха);
  дубль отсекается одной вставкой.
- **Опрос** — пачками по индексу `(status, created_at)` попыток; `EXPLAIN` на MariaDB.
- `PerformanceTest`: шаг оплаты, страница оплаты, уведомление, списки панели и MCP тёплыми с
  `assertNoRepeatedQueries`.

## 19. Регистрации и что держат тесты

- Четыре места composer-пакета в `php/` и четыре места раздела (`Setup\Catalogue`, `extra.webx.npm`
  и `extra.webx.panel`, список модулей плейграунда, `scripts/packages.mjs` демо-сайта); адресов
  `RouteType` у раздела нет — `SiteBar::entity()` не нужен.
- Тесты, без которых этап не сдан: переходы §8.1 таблицей; «возврат раньше уведомления» и наоборот
  — одна веха, одно `OrderPaid`; повтор уведомления — `duplicate` без запросов к заказу; неверная
  подпись — `rejected` и заказ не тронут; двойная оплата — `problem = duplicate`; сумма не та — веха
  не поставлена; смена способа на «при получении» снимает «ожидает оплаты» без вехи; ни одного
  ключа в ответах панели, MCP, журнале (`assertNoSecrets` по всем ответам набора); форма
  `Handoff::post()` без полей карты.

## 20. Этапы реализации

Задача webx-ui#60, этапы 2–9 (этап 1 — эта спека). Каждый — один проверяемый результат, гейты JS
и PHP зелёные. Все — после выпуска `module-orders` (нужны `Orders::mark`, `paymentFailed`,
`patchBlock`, `stopAwaitingPayment`, блоки снимка); этапы 3 и дальше — после оформления `module-orders`
(слот, `ShippingChoice`).

| Этап | Что                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      | Проверка                                                                                                                                                                                                                                                                                                                                                               |
| ---- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2    | **Пакет, таблицы, реестры, встроенные способы** (§5, §6, §7, §12): миграции, модели, `MethodTypes`, `Gateways`, `Methods::available`, правило доставки, `store.on_receipt`, `store.bank_transfer`, `Credentials`, словарь, `AGENTS.md`, регистрации                                                                                                                                                                                                                                                                      | миграции накатываются и откатываются; `EXPLAIN`; таблица доступности §7.3 тестами; ключ не сериализуется ни одним способом                                                                                                                                                                                                                                             |
| 3    | **Слот оформления** (§10): `PaymentsCheckoutSlot`, `payment_selections`, API шага, компоненты, блок `payment`, реквизиты на «Спасибо» и странице заказа, `ShippingChoice` из оформления (решение 14), шов `cashOnDelivery()`                                                                                                                                                                                                                                                                                             | контрактный `SlotContractTest` оформления; сумма 0 — шага нет; смена доставки сбрасывает недоступный способ; браузер 360 px, тёмная тема                                                                                                                                                                                                                               |
| 4    | **Попытки и онлайн-процесс** (§8, §9.1): `payments`, `payment_events`, `Payments::begin/settle/check/switchMethod`, `/pay/go`, `/pay/return`, страница оплаты со всеми состояниями, `ChargesOnline`, `FakeGateway`                                                                                                                                                                                                                                                                                                       | на `FakeGateway`: оплата, отказ, отмена, «в обработке» → оплачен; параметры возврата подменены — статус не меняется; «Назад» после шлюза не открывает второй платёж; смена на «при получении» снимает «ожидает оплаты»                                                                                                                                                 |
| 5    | **Уведомления шлюза** (§9.2): маршрут, подпись через спутник, дубль, `unmatched`, ответы шлюзу, маскирование (§15.3), `throttle`, `GatewayContractTest`                                                                                                                                                                                                                                                                                                                                                                  | гонка возврата и уведомления — одна веха; повтор — `duplicate`; неверная подпись — `rejected`; номер карты в пакете затёрт                                                                                                                                                                                                                                             |
| 6    | **Фон и проблемы** (§9.3, §9.4): `webx:payments:poll`, закрытие прежних попыток, двойная оплата, сумма не та, деньги на отменённый, «проблема решена», `webx:payments:prune`                                                                                                                                                                                                                                                                                                                                             | потерянное уведомление доводит опрос; двойная оплата — `problem = duplicate`; деньги на отменённый — `order_cancelled` и строка истории                                                                                                                                                                                                                                |
| 7    | **Панель** (§13): npm-пара, экраны способов, формы с ключами «задано / заменить», «Проверить подключение», журнал и попытка, вкладка заказа, права, `AccessCoverageTest`, фикстуры плейграунда                                                                                                                                                                                                                                                                                                                           | `/panel/` плейграунда; ключ не приходит в JSON экрана; действия вкладки — по правам заказов; тёмная тема                                                                                                                                                                                                                                                               |
| 8    | **MCP и письма** (§16, §11.5): инструменты, `payments://catalog`, `dry_run`, шов `NotificationEvents::extend()` в `module-notifications` (решение 19), переменные оплаты, событие `payments.problem`, `PerformanceTest`                                                                                                                                                                                                                                                                                                  | паритет панели и MCP; ни одного ключа в ответах; `payments_methods_save` с ключом — отказ; переменные оплаты видны в редакторе канала `orders.*`; проблема доходит каналом `payments.problem`                                                                                                                                                                          |
| 9    | **Демо, гайды, сквозная проверка**: `ProvidesDemo` (оба встроенных способа и `FakeGateway` на демо), `apps/docs/guide/payments.md` и `guide/payment-gateways.md` (как написать спутник, контрактный тест), строка в реестре пакетов                                                                                                                                                                                                                                                                                      | живой сайт на `site up`: заказ по реквизитам с отметкой, онлайн через `FakeGateway` со всеми кнопками, письмо «оплата получена»; гейты PHP и JS                                                                                                                                                                                                                        |
| 10   | **Доплата и онлайн после «при получении»** (решение 29; после этапа 23 задачи `module-orders` — баланс, `recordPayment()`, `awaitPayment()` — и этапов 4 и 8 этой задачи): сумма попытки — `due`, `settle()` по §9.1 (первая оплата, доплата, дубль), состояния страницы «Нужна доплата» и «Оплатить онлайн сейчас», смена способа с `awaitPayment()`, `cashOnDelivery()` по долгу, «Отправить покупателю» на вкладке, `payments_pay_link` с суммой, `payment_url` в `orders.items_changed` и `orders.payment_requested` | на `FakeGateway`: доплата после правки — `recordPayment`, веха одна, `OrderPaid` один; оплата при долге 0 — `duplicate`; правка во время ожидающей попытки — попытка закрыта, запоздалая оплата входит в `paid_total`; «при получении» → онлайн: заказ «ожидает оплаты», оплата ставит веху; старая ссылка после правки берёт новую сумму; браузер 360 px, тёмная тема |

**Вне этой задачи:** спутники шлюза A и шлюза B — отдельные задачи по шаблону §14 после утверждения
спеки (решение 4); правки соседей, которые требует спека: `ShippingChoice` в оформлении `module-orders`
(решение 14), `Orders::stopAwaitingPayment()` и канал покупателю на `orders.paid` в наборе по
умолчанию в `module-orders` (решения 16, 18), зависимость на карте коммерции (решение 12).

## 21. Открытые вопросы и отложенное

### 21.1. Открыто (решает человек)

1. **Сумма к оплате 0** (всё погасили зачёты): ставить ли сразу веху `paid` — от неё зависит выпуск
   купленных сертификатов (§6 карты). Связано с лимитом «зачётом можно оплатить не более N % суммы»:
   где он живёт (настройки кошелька или конфиг сайта) и бывает ли вообще 100 % зачётом. Решится со
   спекой кошелька. До решения: шага оплаты нет, блок `payment = null`, заказ не ждёт оплаты, `paid`
   ставит менеджер (§10.1).

### 21.2. Отложено (не v1, по первому запросу клиента — решение 11)

- **Возврат через шлюз** («через способ, которым платили», решение 1) — до первого клиента: сразу
  списание или холдирование — у каждого своё (решение 13).
- **Автоотмена неоплаченного** (решение 15). Если вернёмся: команда оплаты поверх `Orders::cancel()`,
  срок — у способа.
- Холдирование и списание при завершении заказа (новая веха между `awaiting_payment` и `paid`,
  §18 спеки заказов); частичная оплата и предоплата (в том числе по реквизитам, решение 23). Доплата
  после правки состава (решение 29) — не частичная оплата: первая оплата закрывает весь долг на
  тот момент, доплата — разницу, которую создала правка.
- Связь «получен → оплачен» у оплаты при получении.
- Рассрочка, подписки, сохранённые карты (только у шлюза, никогда на сайте); комиссия способа;
  фискальные чеки.
