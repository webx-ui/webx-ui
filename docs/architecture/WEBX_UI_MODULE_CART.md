# `webx-ui/module-cart` — корзина — спецификация

Статус: спроектировано, ждёт приёмки (webx-ui#54, этап 1); пакет не начат. Карта семейства —
[`WEBX_UI_COMMERCE.md`](WEBX_UI_COMMERCE.md): границы, принципы, цепочка итогов, реестры. Здесь — как
устроена сама корзина и куда к ней подключаются соседи. Пакет — `webx-ui/module-cart` (composer,
namespace `WebxUi\Cart`, словарь `webx-cart`). Id модуля — `cart` (область MCP `cart:read`, право
`cart.view`). npm-пары в этой задаче нет: раздела в панели пока нет (§2.12).

Корзина — гостевая: строки лежат в базе, посетитель узнаётся по токену в httpOnly-cookie. Что именно
лежит в строке и сколько оно стоит сейчас, корзина каждый раз спрашивает у поставщика через
контракт `Purchasable`; сама она не знает ни одной таблицы каталога. Своей страницы у корзины нет:
JSON API, хелпер `cart()` для Blade и виджет в трёх режимах.

## 1. Границы

**Внутри:** таблицы `carts` и `cart_items`; токен и cookie; контракт `Purchasable`, класс
`PurchasableLine`, необязательные `ResolvesWithOptions` / `ChecksOptions` и реестр `Purchasables`;
источник валюты сайта `CurrencySource` с реализацией по умолчанию (§4.2); дерево строк (`parent_id`,
один уровень) и атомарное добавление пачкой; вычислитель итогов и пять пустых реестров его звеньев —
генерируемые строки, корректировки, оплаты, привилегии, подсказки (§5); колонка `declined_rules` и
место подарочных строк в ответе; JSON API с готовыми строками сумм (§6); хелпер `cart()` (§8);
наполнение виджета в режимах `drawer` / `dialog` / `dropdown` и кнопка «в корзину» (§9); чистка
старых гостевых корзин (`webx:cart:prune`); MCP на чтение (§10); словарь `webx-cart`.

**Рядом, но в чужом пакете:** адаптер `catalog.product` живёт в `module-catalog` (§7) — владелец
сущности сам регистрирует поставщика. Кнопка «Купить» на карточке товара — тоже в вьюхах каталога.
Каркас выдвижной панели — общий `<x-webx-dialog drawer>` в `widgets` (решение 29). Кнопка «купить
комплект» и комплекты на витрине — спутник каталога, не эта задача (решение 28).

**Снаружи:**

- **Правила цены, скидки, подарки, купоны** — `module-cart-rules`, `module-coupons`. Здесь — только
  точки подключения (§5) и колонка отказов.
- **Кошелёк, сертификаты, кешбек** — `module-wallet` и спутники; здесь — реестр оплат (§5.4).
- **Оформление и страница корзины.** Шаги, доставка, сверка суммы — `module-checkout`. Сайт при
  желании сам объявит роут и вью поверх API (§6).
- **Покупатель.** Аккаунтов пока нет; привязка корзины к покупателю и слияние гостевой при входе —
  позже, в `module-users` (отложенный вопрос 2 §15).
- **Раздел в панели** — позже, отдельной задачей.
- **Склад и резервы.** Остаток корзина проверяет, только если поставщик отдаёт `maxQty` или
  `available` (§2.4). Резерва при добавлении нет.

## 2. Принятые решения (не переоткрывать)

Решения человека от 23.10.2026 (спецификация задачи webx-ui#54).

1. **Две таблицы — `carts` и `cart_items`** (§3). Имя `carts` расходится с правилом «таблицы
   раздела — под его именем» (`cart_*`); человек оставил его (решение 20).
2. **Корзина гостевая.** Токен — в httpOnly-cookie; покупательских аккаунтов нет, привязка — позже в
   `module-users`.
3. **Цена живая.** В строке всегда текущая цена поставщика; `price_at_add` — только для информации
   (аналитика, «подешевело»). Замораживает цену заказ.
4. **Остатки необязательны.** Проверка — только если поставщик отдал `maxQty` или `available`. Нет
   склада — нет проверки.
5. **Разные сущности — через контракт `Purchasable`.** Модуль-владелец сам регистрирует поставщика,
   если корзина установлена (`class_exists`); `module-cart` у него в `suggest`. Первый поставщик —
   `catalog.product` (простой товар; конфигурируемых в каталоге пока нет).
6. **Контракт:** `resolve(array $refs, string $locale): array<ref_id, PurchasableLine|null>` —
   пачкой; `null` — запись удалена (строка «недоступна», не в сумме, её можно удалить).
   `PurchasableLine`: `name`, `sku`, `price` (int, минимальные единицы), `currency`, `optionsLabel`,
   `image`, `url`, `shippable = true`, `maxQty = null`, `available = true`. Новые поля — только
   опциональными в конце (так добавится `weight`, когда понадобится).
7. **Комплекты — строки, связанные `parent_id`.** Добавление пачкой атомарно (все или ни одной),
   ребёнок ссылается на родителя внутри пачки. Удаление родителя — дети остаются, `parent_id` →
   `null` (FK `nullOnDelete`).
8. **Итоги — цепочка:** сумма строк → adjustments → итог → tenders → к оплате; рядом benefits и
   hints. Реестры корректировок, оплат, привилегий и подсказок в этом модуле пустые — их заполнят
   спутники. Скидки нигде не хранятся, вычисляются из текущего состава.
9. **Подарочные строки правил** (`source: rule#id`) генерируются при расчёте и не пишутся в
   `cart_items`; отказ от подарка — `declined_rules` в `carts`. Механизм — в `cart-rules`, здесь —
   место в ответе и колонка.
10. **Страницы корзины нет.** Сайт при желании объявит роут и вью сам.
11. **JSON API** из пяти эндпоинтов (§6); каждый ответ — полное состояние.
12. **Хелпер `cart()` для Blade** — счётчик в шапке рендерится сервером, без мигания.
13. **Виджет:** режимы `drawer` / `dialog` / `dropdown` (мини-корзина у иконки: итог, кнопка
    оформления, список строк); вид — тема.
14. **MCP — чтение корзин** (`cart:read`). Раздел в панели — позже.

Решения спеки (предложены здесь, спорное человек закрыл решениями 20–32):

15. **Корзина заводится первой записью.** `GET` без cookie отдаёт пустое состояние и ничего не
    пишет; строка `carts` и cookie появляются при первом `POST /api/cart/items`. Обходчики и
    случайные посетители корзин не плодят.
16. **В базе — хеш токена,** в cookie — сам токен (§4.1). Утёкшая база не даёт войти в чужую
    корзину; MCP токен не показывает никогда.
17. **Деньги — целым везде,** включая ответ API и MCP; рядом с каждой суммой — готовая строка
    `*_formatted` от сервера (решение 32). Форматирует один `Money::format()` по правилам валюты из
    `CurrencySource` (символ и его место, разделители, число знаков = экспонента ISO 4217), а не по
    языку; виджет и Blade показывают его строку, а не форматируют сами.
18. **Одна и та же позиция не двоится.** Строка того же `type`, `ref_id` и тех же `options` без
    родителя увеличивает `qty` существующей строки. Строки внутри комплекта не сливаются никогда.
19. **Чтение не пишет.** `GET` и `cart()` не меняют строки: превышение `maxQty`, удалённая запись,
    чужая валюта — предупреждения в ответе (§6.3), а не тихая правка. Исключение — отметка
    активности для чистки (§4.3), не чаще раза в сутки.

Решения человека от 26.10.2026 (ответы на открытые вопросы этапа 1):

20. **Имена таблиц — `carts` и `cart_items`,** без `cart_carts`.
21. **Опции, от которых зависит цена, — сейчас.** Необязательные `ResolvesWithOptions` и
    `ChecksOptions` поверх `Purchasable` (§5.1) делаются в этапе 2; второй поставщик, который их
    реализует, — конфигуратор (webx-ui#44).
22. **Снимок названия — колонка `name_at_add`** (json, все языки сайта:
    `{"en": "Item D", "uk": "…", "pl": "…"}`). Названия по языкам поставщик отдаёт необязательным
    полем `PurchasableLine::$names` (§5.1); не отдал — пишется `[$locale => $name]`. Строка удалённой
    записи показывается с ним: язык запроса → основной язык сайта → любой имеющийся (§3, §6.2).
23. **`qty` ребёнка — на единицу родителя:** родитель ×2 — дети ×2 сами; удаление родителя умножает
    `qty` детей.
24. **Валюта — через маленький контракт «источник валюты сайта»** (`CurrencySource`, §4.2). В v1
    реализация по умолчанию: `webx-cart.currency` → валюта каталога → отказ в записи и строка в
    `webx:doctor`. Тот же контракт отдаёт правила записи суммы в валюте (`format()`, §4.2): формат
    зависит от валюты, а не от языка. Вероятный модуль валют позже подменит реализацию и сделает
    правила настраиваемыми (отложенный вопрос 1 §15).
25. **Глубина — один уровень:** родитель и дети, внуков нет.
26. **Вернуть отказанный подарок — целиком `module-cart-rules`;** корзина только хранит
    `declined_rules` и вычитает их.
27. **CSRF остаётся** (§6.5).
28. **Комплекты на тестовом сайте откладываются** — это будет спутник каталога. Механизм `parent_id`
    в корзине остаётся и покрыт тестами (API и `window.webx.cart.add()` принимают пачку с
    `parent`), но кнопки «купить комплект» и data-атрибута для неё в этой задаче нет; кнопки «в
    корзину» в листинге каталога тоже нет.
29. **`drawer` — общий каркас в `widgets`** (`<x-webx-dialog drawer>`); наполнение корзины — за
    `module-cart`.
30. **Без JavaScript — как в §9.5:** содержимое панели печатается только после добавления формой.
31. **Нулевая цена поставщика — «цена по запросу»:** строка недоступна и в сумму не идёт. Строка-
    подарок генерируется с ценой ≥ 1: ровно ноль нельзя (юридическое требование), минимальное
    значение — настройка `module-cart-rules` (§5.4).
32. **Рядом с каждой суммой — `*_formatted`:** `"price": 4999, "price_formatted": "49,99 €"`,
    строка отформатирована сервером по правилам валюты (§4.2) и от языка не зависит: с английским и
    с украинским интерфейсом покупатель видит одну и ту же строку. Деньги по-прежнему целые в
    минимальных единицах.

## 3. Таблицы

### `carts`

| Колонка                    | Тип                                  | Примечание                                                               |
| -------------------------- | ------------------------------------ | ------------------------------------------------------------------------ |
| `id`                       | bigint                               | MCP и логи называют корзину по нему                                      |
| `token`                    | char(64), **unique**                 | sha256 токена из cookie (решение 16), не сам токен                       |
| `locale`                   | string(12)                           | язык последнего запроса (`pt-BR` влезает); в нём MCP показывает названия |
| `currency`                 | char(3)                              | ISO 4217, заглавными; ставится при создании из валюты сайта (§4.2)       |
| `declined_rules`           | json, null                           | список `source` отказанных генерируемых строк: `["rule#12"]` (решение 9) |
| `created_at`, `updated_at` | timestamp, **index** на `updated_at` | `updated_at` — последняя правка или отметка активности; по нему чистка   |

### `cart_items`

| Колонка        | Тип                                   | Примечание                                                                        |
| -------------- | ------------------------------------- | --------------------------------------------------------------------------------- |
| `id`           | bigint                                | id строки в API (`PATCH`/`DELETE /api/cart/items/{id}`)                           |
| `cart_id`      | FK `carts`, `cascadeOnDelete`         | index; удалили корзину — ушли строки                                              |
| `type`         | string(64)                            | ключ поставщика: `catalog.product`                                                |
| `ref_id`       | bigint unsigned                       | id записи у поставщика                                                            |
| `options`      | json, null                            | выбор покупателя для поставщиков, которые его понимают (§5.1); у товара — `null`  |
| `qty`          | int unsigned                          | ≥ 1; у ребёнка — на одну единицу родителя (решение 23)                            |
| `parent_id`    | FK `cart_items`, null, `nullOnDelete` | index; строка комплекта; только на строку без родителя (решение 25)               |
| `name_at_add`  | json                                  | снимок названия на всех языках сайта: `{"en": "Item D", "uk": "…"}` (решение 22)  |
| `price_at_add` | bigint unsigned                       | цена поставщика в минимальных единицах в момент добавления; только для информации |
| `added_at`     | timestamp                             | когда положили; при слиянии (решение 18) не меняется                              |

- **Индексы:** `cart_items (cart_id)`, `(parent_id)`, `(type, ref_id)` — последний для MCP («в каких
  корзинах товар A», `cart_summary`) и будущей чистки строк удалённого поставщика; `carts (token)`
  unique, `carts (updated_at)` для `prune`. Каждый проверяется `EXPLAIN` на MariaDB в этапе 2.
- **Валюта строки** не хранится: у корзины она одна (`carts.currency`), `price_at_add` — в ней.
- **Название в строке — только снимок.** Показывается всегда живое имя от поставщика;
  `name_at_add` пишется тем же `resolve()`, что и `price_at_add`, и нужен одному случаю — записи
  больше нет (`null`), строка показывается «недоступной» с этим названием. В снимке — все языки,
  которые отдал поставщик (`PurchasableLine::$names`, §5.1); не отдал — один язык добавления
  `[$locale => $name]`. Показывается название на языке запроса, иначе на основном языке сайта
  (`Locales::defaultCode()`), иначе первое имеющееся. Снимок не обновляется; слитая строка
  (решение 18) его не меняет.
- **Миграции откатываются** (`down()` удаляет обе таблицы в обратном порядке).

## 4. Токен, cookie, жизнь корзины

### 4.1. Токен и cookie

- Токен — 32 случайных байта в base64url (`random_bytes`), 43 символа. В cookie — он, в `carts.token`
  — `hash('sha256', $token)`. Поиск корзины — по хешу, одним запросом по unique-индексу.
- Cookie `webx_cart` (имя — `webx-cart.cookie.name`): `HttpOnly`, `Secure` на https, `SameSite=Lax`,
  `Path=/`, срок — `webx-cart.cookie.days` (по умолчанию 60), продлевается при каждой записи.
  Шифруется `EncryptCookies` Laravel, как любая cookie группы `web`.
- В согласии на cookie (§9 [`WEBX_UI_WIDGETS.md`](WEBX_UI_WIDGETS.md)) — категория `necessary`:
  корзина — то, о чём посетитель попросил сам. Строка в описании категории — словом виджетов, не
  корзины.
- Cookie есть, а строки в базе нет (вычистили, удалили через `DELETE /api/cart`) — корзина пустая,
  следующая запись заводит новую с новым токеном; старая cookie гасится.

### 4.2. Язык и валюта

- **Язык** запроса — заголовок `X-Webx-Locale` (виджет шлёт `<html lang>` страницы), иначе язык
  приложения. Поставщик получает его в `resolve()`; `carts.locale` обновляется при записи.
- **Валюта сайта** — у контракта `CurrencySource` (решение 24); корзина не читает настройки
  других модулей сама:

  ```php
  namespace WebxUi\Cart\Contracts;

  /** The site's currency. A currencies module will bind its own; the cart only asks. */
  interface CurrencySource
  {
      /** @return string|null  ISO 4217, upper case; null — not configured */
      public function currency(): ?string;

      /** How an amount in this currency is written — the same in every language of the site. */
      public function format(string $currency): CurrencyFormat;
  }
  ```

  ```php
  namespace WebxUi\Cart;

  final class CurrencyFormat
  {
      public function __construct(
          public readonly string $symbol,       // "€", "$", or the code itself: "XYZ"
          public readonly bool $symbolFirst,    // "$49.99" — true; "49,99 €" — false
          public readonly bool $spaced,         // a no-break space between the symbol and the number
          public readonly string $thousands,    // "\u{00A0}", ",", ".", "'"
          public readonly string $decimal,      // "," or "."
          public readonly int $exponent,        // ISO 4217 minor digits: 2; 0 for JPY; 3 for KWD
      ) {}
  }
  ```

  Реализация по умолчанию `WebxUi\Cart\ConfigCurrency` (привязка `singletonIf`, чтобы чужая
  победила): `webx-cart.currency` (`WEBX_CART_CURRENCY`) → `webx-catalog.price.currency`, если
  каталог стоит → `null`. На `null` корзина не принимает записи (422 `cart.currency`), а
  `webx:doctor` называет настройку. Модуль валют позже привяжет свою реализацию — корзина не
  меняется (отложенный вопрос 1 §15).

- **Формат суммы зависит от валюты, а не от языка.** Сама сумма от локали не зависит, и строка
  `*_formatted` тоже: покупатель с английским интерфейсом и с украинским видит одну и ту же
  `"49,99 €"`. Правила — символ, его место, разделители тысяч и дробной части, число знаков — даёт
  `CurrencySource::format()` для валюты. `ConfigCurrency` знает разумные правила распространённых
  валют (`EUR` → `"1 234,56 €"`, `USD` → `"$1,234.56"`, `JPY` → `"¥1,235"` …); незнакомая валюта —
  запасной вариант: код после числа (`"1 234,56 XYZ"`), экспонента — из таблицы ISO 4217, а если
  кода нет и там — 2. Настраиваемыми правила сделает модуль валют своей реализацией контракта.
- `Money::format(int $minor, string $currency): string` — один форматтер на ответ API, Blade и
  MCP. Берёт правила из `CurrencySource::format()`, не из `intl` и не из языка запроса; языка в
  сигнатуре нет. `Money::toMinor()` (§7) берёт экспоненту оттуда же.
- Строка, у которой `PurchasableLine::currency` не совпала с валютой корзины, — недоступна с
  предупреждением `line.currency`: пересчёта курсов в базе нет (принцип 4 карты коммерции).

### 4.3. Жизнь и чистка

- Корзина живёт, пока ей пользуются. Каждая запись трогает `updated_at`; чтение (`GET`, `cart()` со
  строками) трогает его, только если он старше суток, — одна запись в сутки на активную корзину.
- `php artisan webx:cart:prune [--days=] [--dry-run]` удаляет корзины с `updated_at` старше
  `webx-cart.prune_after_days` (по умолчанию 60) пачками по 500 (`chunkById`), строки уходят
  каскадом. Отвечает числом удалённых; `--dry-run` только считает.
- Провайдер ставит команду в расписание `daily()` через `callAfterResolving(Schedule::class)`, как
  `module-audit`; выключатель — `webx-cart.prune_after_days = null`.
- `cart_items` без корзины не бывает (каскад); строки поставщика, которого больше нет, остаются до
  чистки и показываются недоступными.

## 5. Контракты

### 5.1. `Purchasable` и `PurchasableLine`

```php
namespace WebxUi\Cart\Contracts;

/**
 * Who sells what lies in a line: the catalogue's product, later a variant, a bundle, a gift card,
 * a configuration. Asked for a batch of a type at a time, never per line.
 */
interface Purchasable
{
    /**
     * What these records are and cost right now, in this language.
     *
     * @param  list<int>  $refs  ref ids of one type, no duplicates
     * @return array<int, PurchasableLine|null>  ref id → line; null — the record is gone.
     *                                           A ref left out of the answer counts as null.
     */
    public function resolve(array $refs, string $locale): array;
}
```

```php
namespace WebxUi\Cart;

/** What a line holds now. New fields only ever come last and optional. */
final class PurchasableLine
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $sku,
        public readonly int $price,          // minor units of $currency; 0 — price on request
        public readonly string $currency,    // ISO 4217, upper case
        public readonly ?string $optionsLabel = null,
        public readonly ?string $image = null,   // absolute or root-relative URL
        public readonly ?string $url = null,
        public readonly bool $shippable = true,
        public readonly ?int $maxQty = null,     // null — no stock to check
        public readonly bool $available = true,
        public readonly ?array $names = null,    // locale → name in every language of the site
    ) {}
}
```

- **Поставщик создаёт строку именованными аргументами** — так новое поле в конце не ломает ни
  одного поставщика. `AGENTS.md` пакета говорит это первым пунктом контракта.
- `sku` — nullable: артикул у товара каталога необязателен.
- **`names`** — название на всех языках сайта (`array<locale, string>`), только для снимка
  `name_at_add` (решение 22, §3); показывается по-прежнему `name` на языке `resolve()`. `null` —
  снимок получит один язык: `[$locale => $name]`. Читается только при записи строки (`POST`).
- **`available = false`** — запись есть, но купить нельзя (снята, «цена по запросу», нет в
  наличии): строка видна с названием и предупреждением `line.unavailable`, в сумму не идёт.
  **`price = 0`** — то же самое: «цена по запросу», корзина считает строку недоступной, даже если
  поставщик отдал `available = true` (решение 31). **`null`** — записи нет: строка видна как
  «недоступна» с названием из `name_at_add` (§3), в сумму не идёт, её можно удалить. Купить нельзя
  ни одну из них; добавить такую — 422.
- **`options` строки** простому поставщику не передаются: `resolve()` получает только id
  (решение 6). Поставщик, у которого выбор меняет цену или состав (конфигуратор webx-ui#44, позже
  вариант с опциями), реализует необязательные интерфейсы поверх `Purchasable` — сам контракт не
  меняется (решение 21):

  ```php
  namespace WebxUi\Cart\Contracts;

  /** A provider whose price and label depend on the options of a line, not only on its record. */
  interface ResolvesWithOptions
  {
      /**
       * @param  list<CartRef>  $lines  lines of one type, each with its own options
       * @return array<string, PurchasableLine|null>  CartRef::$key → line; null — gone.
       *                                              A key left out counts as null.
       */
      public function resolveLines(array $lines, string $locale): array;
  }

  /** A provider that accepts options; without it a line takes empty options only. */
  interface ChecksOptions
  {
      /**
       * Whether these options make a valid choice of this record. Called before a write.
       *
       * @param  array<string, mixed>  $options
       * @return list<string>  messages in $locale; empty — accepted
       */
      public function checkOptions(int $ref, array $options, string $locale): array;
  }
  ```

  ```php
  namespace WebxUi\Cart;

  final class CartRef
  {
      public function __construct(
          public readonly string $key,       // the line's id, or a batch key before the write
          public readonly int $ref,
          public readonly array $options,    // as stored, never null — [] when empty
      ) {}
  }
  ```

  - Вычислитель зовёт `resolveLines()`, если поставщик его реализует, иначе `resolve()`; так же
    пачкой — один вызов на тип за расчёт.
  - `ChecksOptions` нужен для непустых `options` в `POST` и `PATCH`; поставщик без него принимает
    только пустые — непустые дают 422 `items.N.options`. Сообщения `checkOptions()` встают в
    `errors` под тем же ключом.
  - Слияние одинаковых строк (решение 18) сравнивает `options` нормализованными (ключи по
    алфавиту): одна и та же конфигурация не двоится.
  - `optionsLabel` от `resolveLines()` — подпись выбора («Цвет: белый, 120 × 60») на языке запроса.

### 5.2. Реестр `Purchasables`

```php
namespace WebxUi\Cart;

final class Purchasables
{
    /** @param  class-string<Purchasable>|Purchasable  $provider  a class is made on first use */
    public function register(string $type, string|Purchasable $provider): void;

    public function has(string $type): bool;

    /** @throws UnknownPurchasable */
    public function get(string $type): Purchasable;

    /** @return list<string> */
    public function types(): array;
}
```

- **Ключ типа** — `<модуль>.<сущность>`, `[a-z0-9-]+\.[a-z0-9-]+`: `catalog.product`, позже
  `catalog.variant`, `catalog.bundle`, `certificates.nominal`, `configurators.configuration`.
  Повторная регистрация того же ключа — исключение (два поставщика одного типа — ошибка сборки).
- **Регистрация у владельца**, в `boot()` его провайдера, только если корзина стоит:

  ```php
  if (class_exists(Purchasables::class)) {
      $this->callAfterResolving(Purchasables::class, static fn (Purchasables $cart) =>
          $cart->register('catalog.product', ProductPurchasable::class));
  }
  ```

  `module-cart` — в `suggest` владельца; корзина о владельце не знает ничего.

- **Строка неизвестного типа** (модуль удалили) — недоступна, как `null`; в `POST` — 422
  `items.N.type` со списком известных типов.
- **Пачкой.** Вычислитель группирует строки по типу и зовёт `resolve()` один раз на тип за расчёт;
  ответ запоминается на запрос (`RequestMemo`-подобная память корзины), сбрасывается записью.

### 5.3. Вычислитель и контекст

`WebxUi\Cart\Totals\Calculator::calculate(Cart $cart, string $locale, Purpose $purpose):
CartState` — единственное место, где считается цепочка (принцип 5 карты). `Purpose` — `Cart`
(виджет, API), `Checkout` (превью оформления), `Order` (создание заказа): кошелёк по нему решает,
превью это или списание. Порядок:

```
1. строки        stored lines → resolve() / resolveLines() по типам → ResolvedLine
2. генерируемые  GeneratedLines → минус declined_rules → resolve() их типов → ResolvedLine (source)
3. сумма строк   subtotal = Σ effectivePrice × effectiveQty доступных строк
4. корректировки adjustments по источникам, по порядку; total = max(0, subtotal + Σ amount)
5. оплаты        tenders по источникам; Σ не больше total — лишнее срезает вычислитель
6. к оплате      payable = total − Σ tenders
7. рядом         benefits, hints — без влияния на суммы
```

- **`effectiveQty`** строки = `qty` × `effectiveQty` родителя (решение 23), не больше `maxQty`
  (тогда — предупреждение `line.qty_limited`).
- **`CartContext`** — неизменяемый снимок, который получает каждый источник: `cart` (id, locale,
  currency, `declinedRules`), `lines` (`list<ResolvedLine>`: id или ключ генерируемой, `type`,
  `refId`, `qty`, `effectiveQty`, `parentId`, `line` — `PurchasableLine|null`, `source`),
  `subtotal`, `purpose`, а на шагах 4–5 — уже собранные `adjustments` и `total`. Источник не видит
  модель Eloquent и не пишет в базу.
- **Порядок источников** — `priority` при регистрации (меньше — раньше), при равенстве — порядок
  регистрации. Так `cart-rules` (скидки) и `coupons` (код) договариваются числами, не зная друг
  друга.
- **Расчёт — на запрос один:** API, `cart()->state()` и виджет одного запроса получают один и тот же
  `CartState`; любая запись его сбрасывает.

### 5.4. Реестры звеньев цепочки

Все пять — в `WebxUi\Cart\Totals`, по одному методу `register(Source $source, int $priority = 0)`.
В этом модуле они пустые; ни один спутник не правит корзину, чтобы подключиться.

```php
interface LineSource       // шаг 2 — cart-rules: подарок, «купи X — получи Y»
{
    /** @return list<GeneratedLine> */
    public function lines(CartContext $cart): array;
}

final class GeneratedLine
{
    public function __construct(
        public readonly string $source,       // 'rule#12' — also the key declined_rules holds
        public readonly string $type,         // a Purchasables type
        public readonly int $refId,
        public readonly int $qty = 1,
        public readonly ?int $parentId = null,  // attach under a stored line
        public readonly ?int $price = null,     // null — the provider's price; else ≥ 1
        public readonly bool $declinable = true,
        public readonly ?string $label = null,  // "Gift", shown beside the name
    ) {}
}

interface AdjustmentSource // шаг 4 — cart-rules, coupons, позже доставка в checkout
{
    /** @return list<Adjustment> */
    public function adjustments(CartContext $cart): array;
}

final class Adjustment
{
    public function __construct(
        public readonly string $source,       // 'rule#12', 'coupon#SPRING'
        public readonly string $label,        // in the cart's language
        public readonly int $amount,          // minor units; < 0 a discount, > 0 a surcharge
        public readonly int|string|null $line = null,  // a line it belongs to; null — the cart
        public readonly array $meta = [],     // for the order snapshot and MCP, not for the sum
    ) {}
}

interface TenderSource     // шаг 5 — wallet (certificates, cashback)
{
    /** @return list<Tender> */
    public function tenders(CartContext $cart): array;   // $cart->total is known here
}

final class Tender
{
    public function __construct(
        public readonly string $source,       // 'wallet'
        public readonly string $label,
        public readonly int $amount,          // minor units, > 0, taken off the total
        public readonly array $meta = [],
    ) {}
}

interface BenefitSource    // шаг 7 — cart-rules («доставку оплачивает магазин»), проект
{
    /** @return list<Benefit> */
    public function benefits(CartContext $cart): array;
}

final class Benefit
{
    public function __construct(
        public readonly string $key,          // 'free_shipping' — what other modules ask for
        public readonly string $label,
        public readonly string $source,
    ) {}
}

interface HintSource       // шаг 7 — cart-rules: «до бесплатной доставки осталось N»
{
    /** @return list<Hint> */
    public function hints(CartContext $cart): array;
}

final class Hint
{
    public function __construct(
        public readonly string $source,
        public readonly string $text,         // in the cart's language
        public readonly ?int $missing = null, // minor units still to go, for a progress bar
    ) {}
}
```

- **Цена подарка — не ноль** (решение 31). `GeneratedLine::price` либо `null` (цена поставщика),
  либо ≥ 1; конструктор отказывает на `0` и отрицательной. Минимальное значение («1 копейка» или
  больше) — настройка `module-cart-rules`, корзина проверяет только нижнюю границу 1.
- **Генерируемая строка встаёт только под хранимую строку без родителя** (`parentId`) или в корень:
  глубина — один уровень (решение 25); иначе — в корень с записью в лог.
- **Скидка на строку** — `Adjustment` с `line`; строка в ответе получает её в своём `adjustments`, а
  итог корзины — в общем списке. В снимок заказа она попадёт строкой заказа (`module-orders`).
- **Привилегию читают по ключу:** `CartState::hasBenefit('free_shipping')`. Список ключей — у тех,
  кто их ставит и читает; корзина не держит словаря.
- **Чего вычислитель не делает:** не округляет (суммы уже целые), не считает налог и доставку (это
  `module-checkout`), не хранит ни одной скидки.
- **Падение источника** не роняет корзину: исключение пишется в лог, источник пропускается,
  в `warnings` — `totals.source_failed` (без текста исключения). При `Purpose::Order` — наоборот,
  исключение летит дальше: заказ по неполной цепочке создаваться не должен.

### 5.5. Состояние `CartState`

Результат расчёта — то же, что уходит в API (§6.2): `lines` (дерево), `units`, `subtotal`,
`adjustments`, `total`, `tenders`, `payable`, `benefits`, `hints`, `warnings`, плюс `currency`,
`locale`, `isEmpty()`, `hasBenefit()`, `toArray()` (суммы — целым и `*_formatted` через
`Money::format()`, решение 32). Его же получают `cart()->state()`, MCP
`cart_get`, позже — оформление и заказ.

## 6. JSON API

### 6.1. Эндпоинты

```
GET    /api/cart                         → состояние; без cookie — пустое, ничего не пишет
POST   /api/cart/items                   { items: [ { type, ref, qty?, options?, key?, parent? } ] }
PATCH  /api/cart/items/{id}              { qty?, options? }
DELETE /api/cart/items/{id}              строка; id генерируемой — отказ от неё (§6.4)
DELETE /api/cart                         вся корзина: строки, отказы и cookie
```

- **Каждый ответ — полное состояние** (§6.2), 200; `POST` добавляет `added: [id, …]` — что
  записано, для анимации и аналитики. Префикс — `webx-cart.path` (по умолчанию `api/cart`); адрес
  занят маршрутом, и `routing` не отдаст его странице (`Reserved` спрашивает роутер).
- **`POST` — пачка, атомарно.** До 20 строк (`webx-cart.batch_max`); `key` — локальная метка строки в
  пачке, `parent` — `key` родителя в той же пачке (решение 7). Проверка всей пачки — до транзакции:
  тип известен, `resolve()` вернул доступную строку, `qty` от 1 до `min(maxQty, webx-cart.qty_max)`,
  `options` поставщик принимает (`ChecksOptions`, §5.1), ссылка `parent` указывает на строку пачки
  без своего родителя (глубина — один уровень, решение 25), строк в корзине после записи не больше
  `webx-cart.lines_max` (100). Одна ошибка — 422 на всю пачку, ничего не записано. Затем одна
  транзакция: корзина (если её нет), строки, `price_at_add` и `name_at_add` из того же `resolve()`.
- **`PATCH`** меняет `qty` (≥ 1; ноль — это `DELETE`) и `options` (только у поставщиков, которые
  их понимают). Строка чужой корзины или несуществующая — 404.
- **`DELETE /api/cart/items/{id}`** у родителя: в одной транзакции дети получают
  `qty × qty родителя` (решение 23) и `parent_id = null`, затем родитель удаляется. FK
  `nullOnDelete` — страховка базы на путь в обход приложения.
- **`DELETE /api/cart`** удаляет строку `carts` (строки — каскадом) и гасит cookie.
- **Заголовки:** ответ `Cache-Control: no-store`, `Vary: Cookie`. Язык — `X-Webx-Locale` (§4.2).
- **Ошибки** — формат Laravel `{ message, errors }`, ключи по полям (`items.0.ref`,
  `items.1.parent`, `qty`), слова — из `webx-cart::errors` на языке запроса.

### 6.2. Формат ответа

Пример: комплект из товара и двух дополнений, одно из которых удалено у поставщика; отдельный товар
с упавшей ценой; ни одного спутника — `adjustments`, `tenders`, `benefits`, `hints` пустые. Валюта
`EUR`, экспонента 2: `250000` — это 2 500,00. Строки `*_formatted` — от `Money::format()` по правилам
валюты (§4.2, решение 32) и от языка запроса не зависят; в настоящем ответе разделитель тысяч у `EUR`
— неразрывный пробел. Язык запроса — `en`: на нём `name`, подписи и `warnings`.

```json
{
  "currency": "EUR",
  "exponent": 2,
  "locale": "en",
  "lines": [
    {
      "id": 101,
      "type": "catalog.product",
      "ref": 42,
      "source": null,
      "qty": 1,
      "effective_qty": 1,
      "name": "Bundle A",
      "sku": "A-100",
      "options": null,
      "options_label": null,
      "image": "/storage/catalog/42/a-100-320.webp",
      "url": "/bundle-a-42",
      "price": 250000,
      "price_formatted": "2 500,00 €",
      "price_at_add": 250000,
      "price_at_add_formatted": "2 500,00 €",
      "price_change": null,
      "line_total": 250000,
      "line_total_formatted": "2 500,00 €",
      "shippable": true,
      "max_qty": null,
      "available": true,
      "gone": false,
      "declinable": false,
      "label": null,
      "adjustments": [],
      "children": [
        {
          "id": 102,
          "type": "catalog.product",
          "ref": 57,
          "source": null,
          "qty": 2,
          "effective_qty": 2,
          "name": "Item B",
          "sku": "B-200",
          "options": null,
          "options_label": null,
          "image": "/storage/catalog/57/b-200-320.webp",
          "url": "/item-b-57",
          "price": 15000,
          "price_formatted": "150,00 €",
          "price_at_add": 15000,
          "price_at_add_formatted": "150,00 €",
          "price_change": null,
          "line_total": 30000,
          "line_total_formatted": "300,00 €",
          "shippable": true,
          "max_qty": null,
          "available": true,
          "gone": false,
          "declinable": false,
          "label": null,
          "adjustments": [],
          "children": []
        },
        {
          "id": 103,
          "type": "catalog.product",
          "ref": 58,
          "source": null,
          "qty": 1,
          "effective_qty": 1,
          "name": "Item D",
          "sku": null,
          "options": null,
          "options_label": null,
          "image": null,
          "url": null,
          "price": null,
          "price_formatted": null,
          "price_at_add": 9000,
          "price_at_add_formatted": "90,00 €",
          "price_change": null,
          "line_total": 0,
          "line_total_formatted": "0,00 €",
          "shippable": true,
          "max_qty": null,
          "available": false,
          "gone": true,
          "declinable": false,
          "label": null,
          "adjustments": [],
          "children": []
        }
      ]
    },
    {
      "id": 104,
      "type": "catalog.product",
      "ref": 77,
      "source": null,
      "qty": 1,
      "effective_qty": 1,
      "name": "Item C",
      "sku": null,
      "options": null,
      "options_label": null,
      "image": null,
      "url": "/item-c-77",
      "price": 48000,
      "price_formatted": "480,00 €",
      "price_at_add": 52000,
      "price_at_add_formatted": "520,00 €",
      "price_change": "down",
      "line_total": 48000,
      "line_total_formatted": "480,00 €",
      "shippable": true,
      "max_qty": null,
      "available": true,
      "gone": false,
      "declinable": false,
      "label": null,
      "adjustments": [],
      "children": []
    }
  ],
  "units": 5,
  "subtotal": 328000,
  "subtotal_formatted": "3 280,00 €",
  "adjustments": [],
  "total": 328000,
  "total_formatted": "3 280,00 €",
  "tenders": [],
  "payable": 328000,
  "payable_formatted": "3 280,00 €",
  "benefits": [],
  "hints": [],
  "warnings": [
    {
      "code": "line.gone",
      "line": 103,
      "message": "Item D is no longer available. You can remove it."
    },
    { "code": "price.down", "line": 104, "message": "Item C is now cheaper." }
  ]
}
```

- **`lines`** — дерево: дети под родителем в `children`, в порядке `id`; внуков нет (решение 25).
  Генерируемая строка встаёт под `parentId` или в конец корня, её `id` — строка `"rule#12:0"`
  (источник и номер), `source` — `"rule#12"`, `declinable` — из `GeneratedLine`, `label` — его
  подпись («Gift»).
- **`gone`** — записи у поставщика нет (`null` или тип не зарегистрирован): `name` — из
  `name_at_add` (решение 22: язык запроса → основной язык сайта → любой), `sku`, `image`, `url`, `price` — `null`. У «цены по запросу» и
  `available = false` — `gone: false`, имя и картинка живые, `price` — как отдал поставщик.
- **`line_total`** = `price × effective_qty` плюс скидки строки; у недоступной — 0. Сумма
  `line_total` доступных строк до скидок корзины — `subtotal`.
- **`*_formatted`** — у каждой суммы ответа: `price`, `price_at_add`, `line_total`, `subtotal`,
  `total`, `payable`, а в элементах списков — `amount_formatted` у корректировки и оплаты,
  `missing_formatted` у подсказки. Сумма `null` — и строка `null`. Виджет и тема показывают строку
  и не форматируют сами; целое остаётся для расчётов и аналитики.
- **Элементы списков** — поля классов §5.4 в `snake_case` плюс строки сумм: корректировка —
  `{ source, label, amount, amount_formatted, line }`, оплата — `{ source, label, amount,
amount_formatted }`, привилегия — `{ key, label, source }`, подсказка — `{ source, text, missing,
missing_formatted }`. `meta` в ответ API не идёт (только MCP).
- **`price_change`** — `down` / `up` / `null` по сравнению с `price_at_add`.
- **`units`** — Σ `effective_qty` хранимых строк, включая недоступные: столько посетитель видит в
  списке, столько показывает счётчик. Генерируемые не считаются.
- **Пустая корзина** — `lines: []`, все суммы 0 с отформатированными нулями, остальные списки
  пустые; та же форма, без `null`.
- **Чего в ответе нет никогда:** токена, id корзины, `price_at_add` в чужой валюте, текста
  исключений.

### 6.3. Предупреждения

| Код                      | Когда                                                              |
| ------------------------ | ------------------------------------------------------------------ |
| `line.gone`              | `resolve()` вернул `null` или тип не зарегистрирован               |
| `line.unavailable`       | `available = false` или цена 0 — «цена по запросу» (решение 31)    |
| `line.currency`          | валюта строки ≠ валюта корзины                                     |
| `line.qty_limited`       | `qty` больше `maxQty`: в сумму идёт `maxQty`, хранимое не меняется |
| `price.down`, `price.up` | цена отличается от `price_at_add`                                  |
| `tenders.capped`         | оплаты больше итога — лишнее срезано                               |
| `totals.source_failed`   | источник звена упал и пропущен (§5.4)                              |

`line` — id строки или `null` для корзины, `message` — готовая фраза на языке запроса.

### 6.4. Отказ от генерируемой строки

`DELETE /api/cart/items/rule%2312:0` у строки с `declinable: true` дописывает её `source` в
`declined_rules` и отвечает состоянием без неё; у `declinable: false` — 422. Вернуть отказанный
подарок и показать кнопку «вернуть подарок» — целиком `module-cart-rules` (решение 26): своего
эндпоинта «вернуть» у корзины нет. Корзина хранит список и вычитает его на шаге 2. `DELETE /api/cart` сбрасывает и отказы.

### 6.5. Защита

- **Стек — группа `web`** (сессия, cookie, `VerifyCsrfToken`) плюс `throttle:webx-cart` на запись
  (60 в минуту на IP, `webx-cart.throttle`). Скрипт шлёт `X-XSRF-TOKEN` из cookie и на 419 берёт
  свежий токен у `GET {webx-widgets.path}/csrf`, повторяя запрос один раз — как оболочка форм (§17.3
  спеки виджетов). CSRF остаётся (решение 27): подделка здесь — чужая позиция в корзине, а не
  утечка, но защита дешёвая и уже обслужена скриптом форм; дверь без CSRF, как у inbox, не нужна.
- **Без JavaScript** `POST /api/cart/items` принимает обычную форму (`items[0][type]=…`, `@csrf`) и
  отвечает по соглашению `FormResponse` (§17.4 спеки виджетов): redirect обратно с flash
  `webx-cart`, страница печатает виджет открытым (§9.5).
- Строка — только своей корзины (404 иначе), лимиты строк, пачки и `qty` — §6.1. Cookie, у которой
  нет корзины, ничего не открывает.

## 7. Адаптер `catalog.product`

Живёт в `module-catalog`: `WebxUi\Catalog\Cart\ProductPurchasable`, регистрация — по `class_exists`
(§5.2), `webx-ui/module-cart` — в `suggest` каталога. Корзина каталог не знает; каталог корзины не
требует. Делается в этапе 3.

- **`resolve()` пачкой:** товары `withTrashed()` по `whereIn` одним запросом, первая картинка —
  одним запросом по `position`, адреса — пачкой через `routing`, ответ «можно ли купить» —
  `Purchasability::forMany()` (§7.5 спеки каталога). Бюджет не растёт с числом строк.
- **Соответствие полей:**

  | `PurchasableLine` | Откуда                                                                                 |
  | ----------------- | -------------------------------------------------------------------------------------- |
  | `name`            | `name` на языке `$locale`                                                              |
  | `sku`             | `sku`                                                                                  |
  | `price`           | `price` (decimal(12,2)) → минимальные единицы строкой, без `float`: `Money::toMinor()` |
  | `currency`        | `webx-catalog.price.currency`                                                          |
  | `optionsLabel`    | `null`                                                                                 |
  | `image`           | адрес превью первой картинки галереи                                                   |
  | `url`             | адрес товара из реестра `routing`                                                      |
  | `shippable`       | `true`                                                                                 |
  | `maxQty`          | `null`: количеств у наличия пока нет (решение 5 каталога)                              |
  | `available`       | `Purchasability` ответил «да», `price` не `null` и больше 0                            |

| `names` | переводимое `name` товара на всех языках сайта (`Locales::codes()`), пустые — пропуск |

- **Удалённый** (soft delete) — `null`: строка «недоступна». **Снятый, невидимый, «нет в наличии»,
  «цена по запросу»** — строка с `available = false`; цена `0` — тоже «цена по запросу» (решение 31).
- **Цена выключена** (`webx-catalog.price.enabled = false`) — поставщик не регистрируется: продавать
  без цены нечего.
- **Кнопка «Купить»** вьюхи `webx-catalog::product` (сейчас `<button data-product>` без поведения)
  становится кнопкой корзины (§9.3), если корзина стоит; иначе остаётся как есть. Кнопки «в
  корзину» в карточке листинга в этой задаче нет (решение 28).

## 8. Хелпер `cart()`

```php
cart()->count(): int                         // units, for the header counter
cart()->isEmpty(): bool
cart()->has(string $type, int $ref): bool   // "in the cart" state of a buy button
cart()->state(?string $locale = null): CartState   // the full calculation, §5.5
```

- `cart()` — глобальная функция пакета под `function_exists` (чужая с тем же именем побеждает, и
  тогда — `WebxUi\Cart\Facades\Cart`). Возвращает `CartHandle` текущего запроса.
- **`count()` и `has()` дешёвые:** без cookie — ноль запросов; с cookie — один запрос
  (`sum(qty)` и пары `type, ref_id` строк корзины по хешу токена), память на запрос. Поставщиков они
  не спрашивают: шапка не платит за полный расчёт.
- **`count()` расходится с `units` полного расчёта только у детей комплекта** (там `qty` — на
  единицу родителя): лёгкий запрос считает их с умножением в SQL, чтобы цифра в шапке и в виджете
  совпадала.
- Blade: `<span data-webx-cart-count>{{ cart()->count() }}</span>` — сервер печатает цифру, скрипт
  виджета обновляет её после каждой записи (§9.4). Полностраничного кеша для гостей нет (§7
  [`WEBX_UI_PERFORMANCE.md`](WEBX_UI_PERFORMANCE.md)), поэтому цифра сервера не устаревает; страница
  из bfcache обновляет её по `pageshow`.

## 9. Виджет

### 9.1. Компонент и режимы

```blade
<x-webx-cart />                    {{-- mode from config --}}
<x-webx-cart mode="dropdown" />
```

| Режим      | Что это                                                                                                                 |
| ---------- | ----------------------------------------------------------------------------------------------------------------------- |
| `drawer`   | выдвижная панель с края экрана во всю высоту: строки со степперами и удалением, цепочка итогов, кнопка оформления       |
| `dialog`   | диалог по центру (`<x-webx-dialog>`, шторка снизу на телефоне): то же содержимое                                        |
| `dropdown` | мини-корзина у иконки (`<x-webx-dropdown placement="bottom-end">`): итог, кнопка оформления, список строк без степперов |

- **Режим выбирает тема:** пропом компонента в своей вьюхе шапки или `webx-cart.widget.mode`
  (по умолчанию `drawer`). Неизвестный режим — исключение, как опечатка в `placement` у выпадашки.
- **Иконка и счётчик** — кнопка-триггер компонента; иконка — вьюха `webx-cart::icons.cart`,
  перекрываемая темой. В шапку `theme-default` компонент встаёт в `actions` (там уже оставлено
  место «позже корзина», §6.2 спеки виджетов), если `module-cart` стоит.
- **`drawer`** — общий каркас `<x-webx-dialog drawer>` в `widgets` (решение 29): модальный
  `<dialog>` того же поведения `dialog` (фокус, Esc, подложка, возврат фокуса), прижатый к краю
  во всю высоту. Каркас — правка `widgets` (проп, класс, стили, changeset), пригодится фильтрам
  каталога и меню; корзина кладёт в него только своё наполнение (§9.2).
- **Суммы виджет не форматирует:** вьюхи печатают `*_formatted` из `CartState`, скрипт — те же
  строки из ответа API (решение 32).
- **Кнопка оформления** ведёт на `webx-cart.checkout_url` (или именованный маршрут `checkout`, если
  он есть); ни того ни другого — кнопки нет. `module-checkout` позже займёт маршрут сам.

### 9.2. Содержимое — Blade, а не шаблон в JavaScript

Строки, итоги и пустое состояние рисует сервер вьюхами модуля, а не скрипт из JSON: так тема меняет
разметку той же дверью, что у любого модуля (§10.5 [`WEBX_UI_THEMES.md`](WEBX_UI_THEMES.md)).

- Запрос виджета несёт `X-Webx-Cart-View: drawer|dialog|dropdown`; ответ API тогда добавляет к
  состоянию `html` — содержимое панели этого режима. Без заголовка `html` нет.
- На странице печатается только триггер со счётчиком и пустая панель; содержимое приходит при
  первом открытии (`GET /api/cart`) и после каждой записи. Полный расчёт не стоит каждой странице.
- Вьюхи: `webx-cart::widget` (оболочка режима), `widget.lines`, `widget.line`, `widget.totals`,
  `widget.empty`, `add` (кнопка §9.3). Точки расширения: `cart.line.after` (под строкой — кнопка
  «отказаться от подарка» у `cart-rules`), `cart.totals.before` и `cart.totals.after` (кошелёк —
  «оплатить бонусами», подсказки), `cart.footer` (над кнопкой оформления).

### 9.3. Кнопка «в корзину»

```blade
<x-webx-cart::add type="catalog.product" :ref="$product->id" />

<button type="button" data-webx-cart-add="catalog.product:42" data-webx-cart-qty="1">В кошик</button>
```

| Атрибут                             | Что значит                                                                        |
| ----------------------------------- | --------------------------------------------------------------------------------- |
| `data-webx-cart-add="<type>:<ref>"` | одна строка                                                                       |
| `data-webx-cart-qty="N"`            | количество (по умолчанию 1)                                                       |
| `data-webx-cart-qty-from="<css>"`   | взять количество из поля на странице (`#qty`)                                     |
| `data-webx-cart-options='{…}'`      | `options` строки — для поставщиков, которые их понимают                           |
| `data-webx-cart-open="false"`       | не открывать виджет после добавления (по умолчанию открывает `drawer` и `dialog`) |

- Во время запроса — `is-busy` и `aria-busy` на кнопке, повторный клик гасится; после — `is-added`
  на 2 с и живая область «Додано в кошик»; 422 — сообщение ошибки у кнопки (`role="alert"`).
- `<x-webx-cart::add>` печатает `<form method="post">` с `@csrf` и скрытыми полями вокруг кнопки:
  работает и без JavaScript (§6.5). Голый `data-webx-cart-*` на чужой кнопке — только со скриптом.
- `cart()->has()` даёт кнопке начальное `is-in-cart` без запроса со страницы.
- **Кнопки «купить комплект» и data-атрибута для неё нет** (решение 28): комплекты на витрине —
  будущий спутник каталога. Он получит готовое — пачку с `parent` в `POST /api/cart/items` и
  `window.webx.cart.add(items)` (§9.4) — и сам решит, какой кнопкой её отправлять.

### 9.4. Скрипт

- Виджет `cart` на `webx.widget('cart', '[data-webx-cart]', setup)` (§4 спеки виджетов) и
  делегированный обработчик кликов по `[data-webx-cart-add]` на документе. Свой вход сборки модуля (`dist/cart.js`, закоммичен, маршрут модуля с хешем), как у
  конфигуратора (решение 2 его спеки); бюджет ≤ 6 КБ gzip, тест держит.
- `window.webx.cart` наружу: `add(items)` (пачка в формате `items` §6.1, с `key` и `parent`),
  `remove(id)`, `setQty(id, qty)`, `open()`, `state()`.
- Событие `webx:cart:change` на `document` с `detail.state` после каждой записи — по нему
  обновляются все `[data-webx-cart-count]` и `is-in-cart` кнопок, на него вешаются аналитика (с
  согласием) и чужие модули. Деньги в `state` — целые с `*_formatted` рядом; своего
  форматирования (`Intl.NumberFormat`) у скрипта нет — строка сервера одна на сайт.
- Степпер количества шлёт `PATCH` с паузой 300 мс после последнего нажатия; ответ, пришедший после
  более нового запроса, отбрасывается.

### 9.5. Без JavaScript

Счётчик напечатан сервером; `<x-webx-cart::add>` отправляет форму и возвращает на ту же страницу, а
она печатает виджет открытым с содержимым (flash `webx-cart`). В остальное время триггер `dropdown`
— `<details>`, `drawer` и `dialog` — ссылка на `#webx-cart`, и панель без скрипта пуста
(«Увімкніть JavaScript, щоб побачити кошик»): рисовать содержимое на каждой странице ради редкого
случая — полный расчёт на каждый показ (решение 30).

### 9.6. Что отдано теме

- Классы `webx-cart`, `__trigger`, `__count`, `__panel`, `__lines`, `__line`, `__line-image`,
  `__line-name`, `__line-options`, `__line-price`, `__line-old`, `__stepper`, `__remove`,
  `__warning`, `__totals`, `__total-row`, `__payable`, `__hints`, `__checkout`, `__empty`,
  модификаторы `--drawer`, `--dialog`, `--dropdown`; кнопка — `webx-cart-add`, состояния
  `is-busy`, `is-added`, `is-in-cart`, `is-unavailable`. Публичный контракт, список — `### Classes`
  в `AGENTS.md`.
- Базовый вид — `resources/theme/cart.css` модуля на `--site-*`, `@container`, без `@media` и
  `!important` (`ModuleThemeContract`); локальные токены `--webx-cart-drawer-width`,
  `--webx-cart-image-size`, `--webx-cart-gap` на `:where(:root)`.
- Вьюхи §9.2 и иконка перекрываются в `views/vendor/webx-cart/`; точки расширения — §9.2. Режим —
  §9.1. Проверка — в тёмной схеме и на 360 px, `scrollWidth ≤ clientWidth` у панели.

## 10. MCP

Область `cart:read`, право `cart.view` (объявлено в `Module::permissions()`; раздела в меню нет,
право выдаётся ролью). Изменяющих инструментов нет: корзина — данные посетителя (решение 14).

| Инструмент     | Что делает                                                                                                                                                                                                                                           |
| -------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `cart_list`    | корзины страницами: id, язык, валюта, строк, `units`, сумма по `price_at_add` (без пересчёта — дёшево), `created_at`, `updated_at`; фильтры: `updated_since`, `updated_before`, `contains` (`type` + `ref`), `min_units`; сортировка по `updated_at` |
| `cart_get`     | по id — полное состояние вычислителем (§5.5) на языке корзины, как ответ API, плюс даты; `locale` — показать на другом языке                                                                                                                         |
| `cart_summary` | сводка: корзин всего, активных за 1/7/30 дней, брошенных (старше N дней, `days`), топ-N позиций по числу корзин с названиями от поставщиков                                                                                                          |
| `cart_quote`   | пробный расчёт без записи: `items` в формате `POST`, `locale`, `declined` → состояние и ошибки проверки, как `POST` ответил бы; «почему не сработала скидка» у `cart-rules` начинается здесь                                                         |

- Токен, IP и cookie не показываются нигде. Отказ — `ToolFailure` со словами («Cart 15 does not
  exist»; неизвестный тип — со списком известных).
- **Ресурс `cart://catalog`:** зарегистрированные типы `Purchasables`; источники каждого звена с
  приоритетами; настройки (валюта, сроки cookie и чистки, лимиты); формат ответа и коды
  предупреждений §6.3 — собирается из констант, а не пишется рукой.
- `cart_list` и `cart_summary` — в `PerformanceTest` тёплыми; `cart_get` — бюджет как у
  `GET /api/cart`.

## 11. Слова

Словарь `webx-cart` на десять языков (`en`, `ru`, `uk` вычитаны носителем, остальные — машинный
перевод), паритет `en`/`ru`/`uk` держит тест.

- `module.php` — название модуля, право `cart.view` (слово действия — `webx-auth::permissions`).
- `widget.php` — «Кошик», «Оформити замовлення», «Кошик порожній», «Видалити», «Кількість»,
  «Разом», «До оплати», «Знижка», «Додано в кошик», «Закрити», «Подарунок», «Відмовитися»,
  подписи степпера и ARIA. Счётчик — «Товарів: :count» (число в конце), а не «:count товарів».
  Любое слово виджета перекрывается пропом компонента и темой, как у виджетов.
- `errors.php` — коды §6.1 и §4.2: неизвестный тип, недоступно, количество, пачка, лимит строк,
  `options` не принимаются, родитель вне пачки или с родителем, нет строки, нет валюты, цена
  подарка меньше 1 (для лога спутника).
- `warnings.php` — по фразе на код §6.3.

Тексты строк (названия, подписи опций) — от поставщика на языке `resolve()`; слова скидок, оплат и
подсказок — у их источников.

## 12. Демо

Своего `ProvidesDemo` у корзины нет: корзина — данные посетителя, демо-корзина на чужом сайте никому
не нужна, а тестовые корзины для MCP появляются от кликов по витрине. Что нужно приёмке:

- кнопка «Купить» товара каталога — уже есть в демо каталога (§7);
- кнопки «купить комплект» нет (решение 28): комплект на приёмке кладётся через API — тестом и
  `window.webx.cart.add()` из консоли;
- виджет в шапке `theme-default` — сам, если модуль стоит (§9.1).

## 13. Регистрации и что держат тесты

**Регистрации** (CLAUDE.md §5): `php/composer.json` (`require`, `autoload-dev.psr-4`),
`phpunit.xml.dist`, `phpstan.neon.dist`, `node scripts/sync-php-version.mjs`; `scripts/php-smoke.sh`;
`Setup\Catalogue` — `cart`, `default: false`. Раздела панели нет — значит, нет `extra.webx.npm`,
`extra.webx.panel`, строки в `apps/playground/src/panel/main.ts` и `SiteBar` (адресов у корзины
нет). `scripts/packages.mjs` демо-сайта — человеку, когда модуль поедет на демо-сайты. Строка в
реестре [`WEBX_UI_COMPOSER_PACKAGES.md`](WEBX_UI_COMPOSER_PACKAGES.md) и `AGENTS.md` пакета
(`AgentDocsCoverageTest`). Changeset — на `@webx-ui/php`; в этапе 4 — ещё на `widgets` за каркас
`drawer` (решение 29).

**Тесты:**

- Пачка атомарна: одна плохая строка — ничего не записано; `parent` вне пачки или на строку с
  родителем (внук) — 422.
- Слияние одинаковых строк, `options` с ключами в другом порядке — одна строка; дети не
  сливаются.
- Удаление родителя: дети остаются, `parent_id = null`, `qty` умножено; FK держит и путь в обход.
- `name_at_add`: поставщик с `names` — снимок на всех его языках; без `names` — `[$locale => $name]`;
  удалённая строка — название на языке запроса, без него — на основном языке сайта, без него —
  первое имеющееся.
- `Money::format()`: одна и та же строка при любом `X-Webx-Locale`; правила `EUR`, `USD`, `JPY`
  (экспонента 0) и запасной вариант с кодом после числа; своя привязка `CurrencySource` меняет
  формат.
- `null` и неизвестный тип — `line.gone`, не в сумме, `name` из `name_at_add`; `available = false`
  и цена 0 — `line.unavailable`, не в сумме, добавить — 422; чужая валюта; `maxQty`.
- Поставщик с `ResolvesWithOptions` получает `options` строк и отдаёт разные цены одной записи;
  `ChecksOptions` — 422 с его сообщениями; поставщик без него — 422 на непустые `options`.
- `CurrencySource`: настройка корзины → валюта каталога → отказ в записи и строка `webx:doctor`;
  своя привязка контракта побеждает реализацию по умолчанию.
- `*_formatted` у каждой суммы ответа и элементов списков; `null` у суммы — `null` у строки.
- `GeneratedLine` с ценой 0 — отказ конструктора; под строкой с родителем — в корень.
- `GET` без cookie — ни одной записи и ни одной cookie; `GET` с корзиной не пишет чаще раза в сутки.
- Чужая строка — 404; CSRF — 419 без токена; лимиты.
- Цепочка с подставными источниками всех пяти звеньев: порядок `priority`, срез оплат, отказ
  `declined_rules`, упавший источник.
- `webx:cart:prune` и `--dry-run`; cookie без корзины.
- `cart()->count()` — 0 запросов без cookie, 1 с ней; совпадает с `units`.
- `PerformanceTest`: `GET /api/cart` на 10 и на 40 строк — один бюджет, `assertNoRepeatedQueries`;
  MCP-списки тёплыми. Кеша групп у корзины нет — данные посетителя не кешируются; причина — в тесте.
- `AccessCoverageTest`: инструменты без права не видны и отказывают; публичные `api/cart*` — в списке
  открытых с причиной «корзина посетителя».
- `ModuleThemeContract`, вес скрипта, паритет словаря, `AgentDocsCoverageTest`.
- В `module-catalog`: адаптер на удалённом, снятом, «цена по запросу», без цены; бюджет `resolve()`
  на 1 и 30 товаров одинаковый; без `module-cart` ничего не регистрируется.

## 14. Этапы реализации

Задача webx-ui#54, этапы 2–5 (этап 1 — эта спека):

- **2/5 Пакет и API.** `webx-ui/module-cart`: таблицы (с `name_at_add`), модели, токен и cookie,
  `Purchasables`, `PurchasableLine`, `ResolvesWithOptions` и `ChecksOptions` (§5.1),
  `CurrencySource` с реализацией по умолчанию и строкой `webx:doctor` (§4.2), вычислитель и пять
  пустых реестров (§5), `Money` с `format()`, JSON API с `*_formatted`, CSRF и формой без JS (§6), `webx:cart:prune` в расписании, `cart()`, MCP и ресурс (§10), словарь, `AGENTS.md`,
  регистрации. Проверяется подставными поставщиками в тестах (простым и с опциями) — каталог ещё
  не подключён; реальный поставщик с опциями — конфигуратор (webx-ui#44), своей задачей.
- **3/5 Каталог как поставщик.** `ProductPurchasable` в `module-catalog` (§7), регистрация по
  `class_exists`, `suggest`; кнопка «Купить» товара — `<x-webx-cart::add>`, если корзина стоит.
  Changeset на `@webx-ui/php`.
- **4/5 Виджет.** Компонент и три режима (§9), вьюхи и точки, `cart.css`, скрипт и его вход сборки,
  кнопка `data-webx-cart-add`, без JS; триггер в `actions` шапки `theme-default`; каркас
  `<x-webx-dialog drawer>` в `widgets` с changeset на него; гайд в `apps/docs/guide/`. Проверка в браузере: три режима, 360 px,
  тёмная схема.
- **5/5 Сквозная проверка.** Тестовый сайт с каталогом: товар из карточки, три режима с текущей
  ценой и суммами сервера; комплект — пачкой через `window.webx.cart.add()` (кнопки нет,
  решение 28) и виден деревом; правка цены товара видна в корзине, удалённый товар — «недоступна», корзины через
  MCP; гейты PHP и JS зелёные.

## 15. Отложенные вопросы

Вопросы этапа 1 закрыты ответами человека от 26.10.2026 (решения 20–32 §2). Остались два —
отложены до спек соседних модулей; на реализацию этапов 2–5 они не влияют.

1. **Модуль валют.** Вероятен отдельный модуль: валюта сайта, несколько валют, курсы, показ в
   валюте посетителя. Корзина уже спрашивает валюту через `CurrencySource` (§4.2), модуль валют
   привяжет свою реализацию. Открыто, что меняется в корзине при нескольких валютах (`carts.currency`
   при смене валюты посетителем, пересчёт `price_at_add`), — решается его спекой; строка в §9
   [`WEBX_UI_COMMERCE.md`](WEBX_UI_COMMERCE.md).
2. **Привязка к покупателю.** Когда появится `module-users`: колонка `user_id` в `carts` или своя
   таблица связи у `module-users`; и что при входе — слить гостевую корзину с сохранённой или
   заменить. Решается спекой `module-users`.
