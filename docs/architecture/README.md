# Архитектура: спеки и справочники

Спека описывает то, что есть сейчас, а не план: пошаговые планы и журналы сессий сжаты в разделы
«Выпуск», история осталась в git. Статус — без версий и дат: что в каком релизе, скажут
`CHANGELOG.md` пакетов и `npm view` / `composer show`. Ловушки, которые стоили времени, живут
отдельно — в [`../pitfalls/`](../pitfalls/).

Номера разделов (§N) у спек не меняются: на них ссылаются код и другие документы.

## Справочники

| Файл                                                         | О чём                                                                                                 | Статус                                    |
| ------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------- | ----------------------------------------- |
| [WEBX_UI_COMPOSER_PACKAGES.md](WEBX_UI_COMPOSER_PACKAGES.md) | Реестр composer-пакетов: назначение, состав, npm-пара                                                 | действует                                 |
| [WEBX_UI_PHP_RELEASE.md](WEBX_UI_PHP_RELEASE.md)             | Релиз: npm, зеркала php-пакетов для Packagist, канал `next`                                           | действует                                 |
| [WEBX_UI_RELEASE_SPEED.md](WEBX_UI_RELEASE_SPEED.md)         | Скорость релиза: как мерить и замеры от PR до Packagist                                               | замеры                                    |
| [WEBX_UI_SCREENS.md](WEBX_UI_SCREENS.md)                     | Экраны панели как JSON-дерево, патчи, реестр типов                                                    | действует; не все экраны переведены       |
| [WEBX_UI_ROUTING.md](WEBX_UI_ROUTING.md)                     | Плоский реестр адресов сайта и резолвер                                                               | выпущено                                  |
| [WEBX_UI_NEW_SITE.md](WEBX_UI_NEW_SITE.md)                   | Новый сайт одной командой: скелет, `webx:setup`                                                       | выпущено                                  |
| [WEBX_UI_MCP_ACCESS.md](WEBX_UI_MCP_ACCESS.md)               | Подключение AI-агентов: OAuth, согласие, журнал                                                       | выпущено                                  |
| [WEBX_UI_PERMISSIONS.md](WEBX_UI_PERMISSIONS.md)             | Права панели: модель, карта прав модулей, защита от повышения прав, готовые роли, миграция            | аудит и спека (webx-ui#17, этап 1)        |
| [WEBX_UI_BLOCK_FRAGMENTS.md](WEBX_UI_BLOCK_FRAGMENTS.md)     | Общий блок: зона, которую ставят блоком на страницу или тегом в шаблон                                | выпущено                                  |
| [WEBX_UI_LAYOUT_REGIONS.md](WEBX_UI_LAYOUT_REGIONS.md)       | Шапка и подвал сайта деревьями блоков                                                                 | выпущено                                  |
| [WEBX_UI_COMPOSITIONS.md](WEBX_UI_COMPOSITIONS.md)           | Композиции: layout и составные виды деревом с id, патчи тем по id, компиляция в Blade                 | спроектировано, ждёт приёмки (webx-ui#49) |
| [WEBX_UI_BLOCK_SCHEMAS.md](WEBX_UI_BLOCK_SCHEMAS.md)         | Схемы блоков: кто ставит типы, деплой их не пишет, импорт с расхождениями и `--replace`               | реализовано (webx-ui#40)                  |
| [WEBX_UI_CATALOG.md](WEBX_UI_CATALOG.md)                     | Каталог товаров: ядро и спутники, контракты, таблица статусов (§10)                                   | волна A выпущена, волна B не начата       |
| [WEBX_UI_COMMERCE.md](WEBX_UI_COMMERCE.md)                   | Коммерция: карта модулей магазина — база и спутники, цепочка итогов, события заказа, открытые вопросы | спроектировано, ждёт приёмки (webx-ui#53) |
| [WEBX_UI_HISTORY.md](WEBX_UI_HISTORY.md)                     | Журнал изменений в module-admin, узел `wx-history`                                                    | выпущено                                  |
| [WEBX_UI_AGENT_DOCS.md](WEBX_UI_AGENT_DOCS.md)               | `AGENTS.md` в пакетах и корневой файл сайта                                                           | AG1–AG4 выпущены, AG5 впереди             |
| [WEBX_UI_THEMES.md](WEBX_UI_THEMES.md)                       | Тема по умолчанию и локальная тема сайта: слои, токены, блоки, модули, правила агентов                | TH1–TH5 выпущены; §21 не начат            |
| [WEBX_UI_WIDGETS.md](WEBX_UI_WIDGETS.md)                     | Виджеты сайта и формы сайта (§17–§18)                                                                 | W1–W6 выпущены                            |
| [WEBX_UI_SITE_BAR.md](WEBX_UI_SITE_BAR.md)                   | Панель на сайте для вошедшего администратора: ссылка в панель, SEO, правка блоков                     | выпущено; проверка на телефоне открыта    |
| [WEBX_UI_CACHE.md](WEBX_UI_CACHE.md)                         | Кеш на версионных ключах: группы, версии, сброс по моделям и явный, экран «Кеш», MCP                  | выпущено                                  |
| [WEBX_UI_PERFORMANCE_AUDIT.md](WEBX_UI_PERFORMANCE_AUDIT.md) | Производительность PHP: замеры «было/стало», находки, карта кеша, решения на утро                     | задача webx-ui#16 выполнена               |
| [WEBX_UI_PERFORMANCE.md](WEBX_UI_PERFORMANCE.md)             | Правила производительности модулей: N+1, кеш и сброс, индексы, пагинация, бюджеты                     | действует                                 |
| [WEBX_UI_DEBUGBAR.md](WEBX_UI_DEBUGBAR.md)                   | Отладочная панель на сайте: SQL, время, блоки, кеш; MCP `debug_page`                                  | спроектировано, не начато                 |

## Модули

| Файл                                                                     | О чём                                                                                                             | Статус                                                                    |
| ------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| [WEBX_UI_MODULE_AUDIT.md](WEBX_UI_MODULE_AUDIT.md)                       | Аудит сайта: обход, снимок страниц, проверки, исправления модулей                                                 | выпущено; открытое — §12                                                  |
| [WEBX_UI_MODULE_BANNERS.md](WEBX_UI_MODULE_BANNERS.md)                   | Баннеры в именованных местах и их слои                                                                            | выпущено                                                                  |
| [WEBX_UI_MODULE_BLOCKS.md](WEBX_UI_MODULE_BLOCKS.md)                     | Конструктор блоков: схема, Blade, CSS, скрипт; блок как компонент (§24)                                           | выпущено                                                                  |
| [WEBX_UI_MODULE_BLOG.md](WEBX_UI_MODULE_BLOG.md)                         | Статьи из блоков, рубрики, теги, дата публикации                                                                  | выпущено                                                                  |
| [WEBX_UI_MODULE_CART.md](WEBX_UI_MODULE_CART.md)                         | Корзина: гостевая по cookie, контракт `Purchasable`, цепочка итогов и её реестры, JSON API, виджет, `cart()`, MCP | спроектировано, ждёт приёмки (webx-ui#54, этап 1); открытые вопросы — §15 |
| [WEBX_UI_MODULE_CATALOG.md](WEBX_UI_MODULE_CATALOG.md)                   | Ядро каталога: товары, категории, движок, витрина, массовые действия                                              | выпущено                                                                  |
| [WEBX_UI_CATALOG_VIDEO.md](WEBX_UI_CATALOG_VIDEO.md)                     | Видео в галерее товара: свой файл или YouTube, постер — картинка                                                  | выпущено                                                                  |
| [WEBX_UI_CATALOG_DICTIONARIES.md](WEBX_UI_CATALOG_DICTIONARIES.md)       | Справочники каталога: метки, наличие, бренды                                                                      | выпущено                                                                  |
| [WEBX_UI_CATALOG_PROPERTIES.md](WEBX_UI_CATALOG_PROPERTIES.md)           | Свойства каталога: типы, значения, наборы по категориям, фасеты, характеристики                                   | выпущено                                                                  |
| [WEBX_UI_CATALOG_MANTICORE.md](WEBX_UI_CATALOG_MANTICORE.md)             | Движок Manticore: таблицы на язык, поиск с опечатками и по кодам, подмена при перестройке                         | выпущено                                                                  |
| [WEBX_UI_CATALOG_LANDINGS.md](WEBX_UI_CATALOG_LANDINGS.md)               | Посадочные фильтров: листинг с зашитым набором, свой адрес, SEO, тексты, перелинковка каталога                    | выпущено                                                                  |
| [WEBX_UI_MODULE_CATALOG_EXCHANGE.md](WEBX_UI_MODULE_CATALOG_EXCHANGE.md) | Обмен каталога: импорт и экспорт CSV и XLSX, профили, журнал ошибок                                               | выпущено                                                                  |
| [WEBX_UI_MODULE_CONFIGURATOR.md](WEBX_UI_MODULE_CONFIGURATOR.md)         | Конфигуратор товара: шаги, размеры, цена с разбивкой, заявка в Inbox                                              | спроектировано (webx-ui#44, этап 1); открытые вопросы — §20               |
| [WEBX_UI_MODULE_EVENTS.md](WEBX_UI_MODULE_EVENTS.md)                     | События: дата, место, цена, `.ics`, разметка `Event`                                                              | выпущено                                                                  |
| [WEBX_UI_MODULE_FAQ.md](WEBX_UI_MODULE_FAQ.md)                           | Вопросы и ответы, вставка блоком, `FAQPage`                                                                       | выпущено                                                                  |
| [WEBX_UI_MODULE_INBOX.md](WEBX_UI_MODULE_INBOX.md)                       | Заявки с форм сайта, мини-CRM                                                                                     | выпущено                                                                  |
| [WEBX_UI_MODULE_MEDIA.md](WEBX_UI_MODULE_MEDIA.md)                       | Файловый менеджер: папки, загрузка, редактор картинок                                                             | выпущено                                                                  |
| [WEBX_UI_MODULE_NOTIFICATIONS.md](WEBX_UI_MODULE_NOTIFICATIONS.md)       | Каналы уведомлений: Telegram и e-mail, шаблоны, события модулей, очередь, журнал, MCP                             | спроектировано; открытые вопросы — §17                                    |
| [WEBX_UI_MODULE_MENU.md](WEBX_UI_MODULE_MENU.md)                         | Именованные меню, дерево пунктов, контракт ссылок                                                                 | выпущено                                                                  |
| [WEBX_UI_MODULE_PAGES.md](WEBX_UI_MODULE_PAGES.md)                       | Дерево страниц, главная — корень, содержимое блоками                                                              | выпущено                                                                  |
| [WEBX_UI_MODULE_PRESS.md](WEBX_UI_MODULE_PRESS.md)                       | Пресса о нас: издания и материалы, три блока                                                                      | выпущено                                                                  |
| [WEBX_UI_MODULE_RECIPES.md](WEBX_UI_MODULE_RECIPES.md)                   | Рецепты, каталог блоком, связи между записями                                                                     | выпущено                                                                  |
| [WEBX_UI_MODULE_REVIEWS.md](WEBX_UI_MODULE_REVIEWS.md)                   | Отзывы с оценкой, на сайт предложенным блоком                                                                     | выпущено                                                                  |
| [WEBX_UI_MODULE_SEO.md](WEBX_UI_MODULE_SEO.md)                           | Правила по адресам, редиректы, `<head>`, sitemap; перелинковка и FAQ страниц (§18)                                | выпущено                                                                  |
| [WEBX_UI_MODULE_SERVICES.md](WEBX_UI_MODULE_SERVICES.md)                 | Услуги как страницы, общие категории, поля проекта                                                                | выпущено                                                                  |
| [WEBX_UI_MODULE_SNIPPETS.md](WEBX_UI_MODULE_SNIPPETS.md)                 | Коды внешних сервисов в head/body: места, охват, согласие, MCP                                                    | в работе: PHP-ядро есть; MCP и панель — §16                               |
| [WEBX_UI_MODULE_TARIFFS.md](WEBX_UI_MODULE_TARIFFS.md)                   | Карточки цен группами, предложенный блок                                                                          | выпущено                                                                  |
| [WEBX_UI_MODULE_TASKS.md](WEBX_UI_MODULE_TASKS.md)                       | Очередь работы людей и агентов: задачи, этапы по порядку, отчёты, проверка, журнал                                | выпущено                                                                  |
| [WEBX_UI_MODULE_TEAM.md](WEBX_UI_MODULE_TEAM.md)                         | Люди организации, соцсети, `RecordQuery`                                                                          | выпущено                                                                  |
| [WEBX_UI_MODULE_VACANCIES.md](WEBX_UI_MODULE_VACANCIES.md)               | Вакансии под приставкой, `JobPosting`, отклик формой                                                              | выпущено                                                                  |

## Не начато

| Файл                                         | О чём                                      | Статус                    |
| -------------------------------------------- | ------------------------------------------ | ------------------------- |
| [WEBX_UI_MULTISITE.md](WEBX_UI_MULTISITE.md) | Одна установка, много доменов, одна панель | спроектировано, не начато |
