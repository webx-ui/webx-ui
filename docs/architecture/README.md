# Архитектура: спеки и справочники

Здесь лежат спецификации модулей и справочники по тому, как устроена система целиком: реестр
пакетов, выпуск, экраны, адреса, развёртывание. Спека выпущенного модуля — это описание того, что
он есть, а не план: пошаговые планы сессий сжаты в разделы «Выпуск», а сами промпты остались в
истории git. Ловушки, которые стоили времени, живут отдельно — в [`../pitfalls/`](../pitfalls/):
`layout-and-browser.md`, `vue-and-tests.md`, `laravel-and-php.md`,
`packages-and-demo-sites.md`, `release-and-ci.md`.

## Справочники

| Файл                                                         | О чём                                                 | Статус                                       |
| ------------------------------------------------------------ | ----------------------------------------------------- | -------------------------------------------- |
| [WEBX_UI_COMPOSER_PACKAGES.md](WEBX_UI_COMPOSER_PACKAGES.md) | Реестр composer-пакетов: назначение, состав, npm-пара | на 28.09.2026, v0.49.1                       |
| [WEBX_UI_PHP_RELEASE.md](WEBX_UI_PHP_RELEASE.md)             | Как php-пакеты попадают на Packagist через зеркала    | действует                                    |
| [WEBX_UI_RELEASE_SPEED.md](WEBX_UI_RELEASE_SPEED.md)         | Параллельный CI, релизный PR от App, канал `next`     | сделано 24.09.2026                           |
| [WEBX_UI_SCREENS.md](WEBX_UI_SCREENS.md)                     | Экраны панели как JSON-дерево, патчи, реестр типов    | третья редакция, в работе                    |
| [WEBX_UI_ROUTING.md](WEBX_UI_ROUTING.md)                     | Плоский реестр адресов сайта и резолвер               | выпущен 15.09.2026, v0.15.0                  |
| [WEBX_UI_VISUAL.md](WEBX_UI_VISUAL.md)                       | Визуальная переделка панели: 21 пункт с решениями     | этапы сделаны 17.09.2026                     |
| [WEBX_UI_NEW_SITE.md](WEBX_UI_NEW_SITE.md)                   | Новый сайт одной командой: скелет, `webx:setup`       | сделано 22.09.2026                           |
| [WEBX_UI_MCP_ACCESS.md](WEBX_UI_MCP_ACCESS.md)               | Подключение AI-агентов: OAuth, согласие, журнал       | выпущен 21.09.2026, v0.27.0                  |
| [WEBX_UI_BACKUPS.md](WEBX_UI_BACKUPS.md)                     | Ночные дампы базы и строка о них в панели             | выпущен 21.09.2026, v0.27.0                  |
| [WEBX_UI_BLOCK_COMPONENTS.md](WEBX_UI_BLOCK_COMPONENTS.md)   | Тип блока как компонент `<x-webx-block>` в шаблонах   | выпущен 25.09.2026, v0.42.0                  |
| [WEBX_UI_LAYOUT_REGIONS.md](WEBX_UI_LAYOUT_REGIONS.md)       | Шапка и подвал сайта деревьями блоков                 | выпущен 28.09.2026, v0.49.0                  |
| [WEBX_UI_CATALOG.md](WEBX_UI_CATALOG.md)                     | Каталог товаров: ядро и спутники, контракты           | ядро написано 29.09.2026, спутники не начаты |
| [WEBX_UI_HISTORY.md](WEBX_UI_HISTORY.md)                     | Журнал изменений в module-admin, узел `wx-history`    | сделано 29.09.2026 (H1), ждёт релиза         |

## Модули

| Файл                                                       | О чём                                                                | Статус                                    |
| ---------------------------------------------------------- | -------------------------------------------------------------------- | ----------------------------------------- |
| [WEBX_UI_MODULE_BANNERS.md](WEBX_UI_MODULE_BANNERS.md)     | Баннеры в именованных местах, только хелпер                          | выпущен 28.09.2026, v0.47.0               |
| [WEBX_UI_MODULE_BLOCKS.md](WEBX_UI_MODULE_BLOCKS.md)       | Конструктор блоков: схема, Blade, CSS, скрипт                        | выпущен 16.09.2026, v0.16.0               |
| [WEBX_UI_MODULE_BLOG.md](WEBX_UI_MODULE_BLOG.md)           | Статьи из блоков, рубрики, теги, дата публикации                     | выпущен 19.09.2026, v0.23.0               |
| [WEBX_UI_MODULE_CATALOG.md](WEBX_UI_MODULE_CATALOG.md)     | Ядро каталога: товары, категории, движок, витрина, массовые действия | код готов 29.09.2026 (K1–K4), выпуск — K5 |
| [WEBX_UI_MODULE_EVENTS.md](WEBX_UI_MODULE_EVENTS.md)       | События: дата, место, цена, `.ics`, разметка `Event`                 | выпущен 25.09.2026, v0.43.0               |
| [WEBX_UI_MODULE_FAQ.md](WEBX_UI_MODULE_FAQ.md)             | Вопросы и ответы, вставка блоком, `FAQPage`                          | выпущен 24.09.2026, v0.37.0               |
| [WEBX_UI_MODULE_INBOX.md](WEBX_UI_MODULE_INBOX.md)         | Формы сайта и заявки с них, мини-CRM                                 | выпущен, v0.22.0                          |
| [WEBX_UI_MODULE_MEDIA.md](WEBX_UI_MODULE_MEDIA.md)         | Файловый менеджер: папки, загрузка, редактор картинок                | выпущен 13.09.2026, v0.7.0                |
| [WEBX_UI_MODULE_MENU.md](WEBX_UI_MODULE_MENU.md)           | Именованные меню, дерево пунктов, контракт ссылок                    | выпущен 22.09.2026, v0.30.0               |
| [WEBX_UI_MODULE_PAGES.md](WEBX_UI_MODULE_PAGES.md)         | Дерево страниц, главная — корень, содержимое блоками                 | выпущен 16.09.2026, v0.18.0               |
| [WEBX_UI_MODULE_PRESS.md](WEBX_UI_MODULE_PRESS.md)         | Пресса о нас: издания и материалы, три блока                         | выпущен 27.09.2026, v0.44.0               |
| [WEBX_UI_MODULE_RECIPES.md](WEBX_UI_MODULE_RECIPES.md)     | Рецепты, каталог блоком, связи между записями                        | выпущен 24.09.2026, v0.41.0               |
| [WEBX_UI_MODULE_REVIEWS.md](WEBX_UI_MODULE_REVIEWS.md)     | Отзывы с оценкой, на сайт предложенным блоком                        | выпущен 24.09.2026, v0.40.0               |
| [WEBX_UI_MODULE_SEO.md](WEBX_UI_MODULE_SEO.md)             | Правила по адресам, редиректы, `<head>`, sitemap                     | выпущен 14.09.2026, v0.14.0               |
| [WEBX_UI_MODULE_SERVICES.md](WEBX_UI_MODULE_SERVICES.md)   | Услуги как страницы, общие категории, поля проекта                   | выпущен 23.09.2026, v0.35.0               |
| [WEBX_UI_MODULE_TARIFFS.md](WEBX_UI_MODULE_TARIFFS.md)     | Карточки цен группами, предложенный блок                             | выпущен 28.09.2026, v0.48.0               |
| [WEBX_UI_MODULE_TEAM.md](WEBX_UI_MODULE_TEAM.md)           | Люди организации, соцсети, `RecordQuery`                             | выпущен 27.09.2026, v0.45.0               |
| [WEBX_UI_MODULE_VACANCIES.md](WEBX_UI_MODULE_VACANCIES.md) | Вакансии под приставкой, `JobPosting`, отклик формой                 | выпущен 28.09.2026, v0.48.0               |

## Не начато

| Файл                                         | О чём                                      | Статус                               |
| -------------------------------------------- | ------------------------------------------ | ------------------------------------ |
| [WEBX_UI_MULTISITE.md](WEBX_UI_MULTISITE.md) | Одна установка, много доменов, одна панель | спроектирована 24.09.2026, не начата |
