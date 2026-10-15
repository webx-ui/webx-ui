# Пакеты, демо-сайты и окружение

Читать, когда правка пакета не доезжает до сайта, когда работаешь из worktree, перед локальным
гейтом и smoke, при работе с Docker и когда смотришь экран панели в плейграунде. Почти всё здесь
выглядит как «ничего не изменилось» или «сломалась ветка», хотя сломано окружение.

## Где смотреть правку

- **Live-сайты платформы** (`webx status` показывает, какие в live): пакеты `webx-ui/*` в них —
  ссылки на эту монорепу, правка PHP/Blade/lang видна сразу. После правки `composer.json` пакета,
  новых миграций или ассетов при выключенном watch — `webx sync <сайт>`; что пошло не так —
  `webx logs <сайт> [-f]` (app, очередь, сборка ассетов). Строка «lock ≠ релиз» в `webx status` —
  подсказка, что lock сайта отстал от версии монорепы. Включать и выключать live — только человек.
- **Dev-копии** — `site up <имя>`: рабочая копия `~/work/<имя>`, адрес `https://<имя>.$DEV_DOMAIN`,
  artisan и composer — через `site exec <имя> …`.
- **Сайт панели** переключается между монорепой и реестром `scripts/link-panel.sh` (обратно —
  `--registry`), путь к сайту — `APP_PATH` (здесь — `APP_PATH=~/work/webx-cms`, рабочая копия
  сайта хомлаба; у неё свой `scripts/packages.mjs`, которому скрипт и передаёт ход). **Стартовый сайт** пересобирается с нуля `scripts/starter-site.sh` (см. ниже).
- **Хомлаб** — https://webx-cms.alexx.group на опубликованных пакетах, деплой при каждом пуше и
  ночью. Ветку без релиза туда везёт канал `next` (`docs/architecture/WEBX_UI_PHP_RELEASE.md`).

## Линковка сайта с монорепой

- **`vite build` сайта падает «Rolldown failed to resolve import "@webx-ui/<x>" from
  …/packages/<y>/dist/index.js», а `<y>` никто не трогал.** `dist` — не в git и остаётся от той
  ветки, на которой его собирали последним: собранный там, где `<y>` зависит от `<x>`, он
  импортирует пакет, которого в `package.json` текущей ветки нет. Лечится
  `pnpm --filter @webx-ui/<y> build` на текущей ветке. Проверка — `grep "@webx-ui/"
packages/<y>/dist/index.js` против `dependencies` его `package.json`.
- **Правка PHP видна на страницах, но не в том, что идёт через очередь.** Прогон аудита (и любая
  задача) снова находит исправленное, хотя тот же код из `tinker` отвечает уже по-новому.
  `queue:work` загрузил классы при старте и держит их, пока жив, а симлинк меняет только файлы.
  После правки PHP — `php artisan queue:restart` в сайте (dev-копия — через `site exec`);
  проверка — время старта процесса `queue:work` позже правки, на live-сайте — `webx logs`.
- Было: новая строка в `lang/*` пакета сутки приезжала в панель английским дефолтом. Починено
  ключом кэша по lang-файлам (`DictionaryBuilder::fingerprint()`).
- **Правка npm-половины доезжает до сайта только сборкой.** Сайт грузит `dist` пакетов, значит
  нужен `pnpm --filter <пакет> build` в монорепе, иначе смотришь вчерашний бандл. И **каждая
  половина бывает копией, а не симлинком**: копия живёт своей жизнью, правка
  `resources/screens/*.json` или свежий `dist` до сайта не доходят вовсе. Проверять `ls -la` в
  `vendor/webx-ui` и `node_modules/@webx-ui`. Копию npm-половины освежает `npm install
"@webx-ui/<пакет>@file:<монорепа>/packages/<пакет>"` в сайте, composer-половину —
  `composer update "webx-ui/*"`. Доехала ли php-часть, показывает ответ `/api/cms/screens/<экран>`.
- **Правка базового CSS модуля (`resources/theme/*.css`) не видна, хотя `vendor/webx-ui/<модуль>`
  — симлинк на чекаут.** Страница грузит копию: `webx:theme:sync` склеивает стили всех модулей в
  `public/themes/modules/<хеш>/modules.css`. Похоже на кеш браузера или на то, что правило
  перебивает тема. Лечится `php artisan webx:theme:sync` после каждой правки; проверка — новый
  хеш в `<link>` страницы. То же у `dist/` тем и виджетов.
- **`rm -rf` внутри `vendor/webx-ui/*` сносит исходники монорепы.** `path`-репозиторий без
  `"symlink": false` кладёт в `vendor` симлинк на чекаут, и каталог внутри него — тот же самый
  каталог. Команда выглядит как «обновить копию в тестовом сайте», а удаляет оригинал; `cp` следом
  говорит «are the same file», но уже после `rm`. Перед любой правкой или удалением там —
  `ls -la vendor/webx-ui`: если это симлинки, править нечего. Сносить такой сайт целиком — только
  после `find <сайт> -type l -delete` (там ещё `public/storage`).
- **Сайт, слинкованный с монорепой, получает вторую копию Vue — и реактивность рвётся по шву.**
  `vue` изнутри пакета резолвится в `packages/core/node_modules/vue` чекаута: `createApp` — из Vue
  пакета, `ref()` в коде сайта — из своего. Ничего не падает, `ref` меняется, перерисовки нет.
  Диагноз: выставить ref в `window` и убедиться, что присвоение не меняет DOM. Лечится
  `resolve: { dedupe: ['vue'] }` в `vite.config.js` сайта; скелет (`php/site/vite.config.js`) это
  уже делает, сайт не на нём — проверить. В registry-режиме не воспроизводится.
- **Composer не «пропускает» `path`-репозиторий, каталога которого нет, — он падает** («The url
  supplied for the path repository does not exist»); у npm то же с `file:`. Поэтому сайт
  переключается скриптом (`scripts/packages.mjs` у него, `scripts/link-panel.sh` здесь), а не живёт
  в одном состоянии. **Вернуть сайт после local-режима из git нельзя:** `composer.lock` там не
  отслеживается, а `packages.mjs local --no-install` всё равно переписывает `package.json`. Перед
  переключением копировать в скретчпад `composer.json`, `composer.lock`, `package.json`,
  `package-lock.json`, `database/database.sqlite` и, если был `webx:demo`,
  `storage/app/webx-demo.json` (журнал демо не в git и не в базе); потом положить назад и
  `composer install`. **Новый пакет в local-режиме одним `composer require` не ставится:** частичное
  обновление держит соседей на версиях из lock'а («fixed to … by a partial update»). Два шага:
  `require "webx-ui/<пакет>:*" --no-update`, потом `update "webx-ui/*"`.
- **Перевести слинкованный сайт на другой чекаут (worktree) — это не `composer update`.**
  `MONOREPO=<worktree> node scripts/packages.mjs local` переписывает манифесты, `composer update`
  пишет новый путь в lock — а симлинки в `vendor/webx-ui` остаются на прежний чекаут: версия та же,
  и composer считает пакет установленным. Снять сами ссылки (`find vendor/webx-ui -maxdepth 1 -type
l -delete` — не `rm -rf`) и `composer install`; с `node_modules/@webx-ui` так же и `npm install`.
- **На сайте, где панель уже есть, `webx:panel --sync` новый модуль в неё не впишет.** Команда
  нарочно не трогает существующий `resources/js/admin.ts`: маршруты сервера на месте, пункт в
  манифесте есть, а раздела в панели нет. Импорт, стиль и `...services()` дописываются руками.
- **Компонент, поставленный в лэйаут сайта, не печатается на страницах конструктора.** Статьи,
  услуги и каталог стоят в `components/layout.blade.php`, а страница `module-pages` — в
  `resources/views/pages/show.blade.php`, отдельном документе со своими `<head>` и регионами, без
  `<main>` и `{{ $slot }}`. Всё, что сайт печатает на каждой странице, ставить в оба файла;
  проверка — `curl` статьи и `/about`.
- **Статический `public/robots.txt` отдаётся раньше Laravel, и маршрут `module-seo` не отвечает
  никогда** — а проверка «`/robots.txt` отвечает 200» проходит на этом же файле. Скелет
  `webx-ui/site` файла не несёт; сайт из обычного скелета Laravel — удалить его. Проверять
  содержимое ответа, а не код.
- **После проверки «Publish» на демо запись возвращена из снимка строки, а версий на одну меньше.**
  `publish()` удаляет автосейвы записи, и чужой черновик уходит вместе с публикацией — снимок самой
  строки его не вернёт; публикуется и черновик целиком, с чужими правками. Для живой проверки брать
  запись без черновика (`draft` пуст) или снимать заодно её строки `cms_versions`; содержимое
  пропавшего автосейва — в версии `overwritten`. Проверка — число строк `cms_versions` до и после.

## Worktree и pnpm

- **В worktree `node_modules` — симлинк на основной чекаут, и `pnpm` это не переживает.** Любой
  `pnpm <скрипт>` оттуда либо отказывается («Refusing to use task run state directory … because it
  is a symbolic link»), либо идёт по симлинку и **сносит содержимое `node_modules` основного
  чекаута**: пропадают `.pnpm` и `.bin`, ломается всё, включая соседнюю сессию. Дело в симлинке:
  `git worktree add` в обычный каталог приезжает без `node_modules`, и `pnpm install` там делает
  свой. Из worktree запускать бинарники напрямую (`npx vite build`, `npx vue-tsc -p tsconfig.json
--noEmit` в каждом пакете вместо рекурсивного `pnpm typecheck`,
  `node <основной>/apps/docs/node_modules/vitepress/bin/vitepress.js dev --host --port 5177`).
  Снос лечится `pnpm install --frozen-lockfile` в основном чекауте — проверить, что
  `node_modules/.pnpm` на месте.
- **Worktree бывает и вовсе без `node_modules` — и `npx` это прячет.** `npx prettier` молча
  скачивает себе какой-то Prettier, а `npx vue-tsc` в пакете падает «Cannot find type definition
  file for 'node'» — похоже на сломанный `tsconfig`. Лечится симлинками на основной чекаут: корень,
  `packages/*/node_modules` и `apps/*/node_modules` (они в `.gitignore`); pnpm после этого из
  worktree по-прежнему не звать. Проверка — `ls node_modules/.bin/prettier` перед первым
  форматированием; отформатированное до неё — прогнать ещё раз.
- **Файл, скопированный «в сайт», оказывается в основном чекауте.** `vendor/webx-ui/*` сайта,
  слинкованного с монорепой, — симлинки на `php/packages` **основного** чекаута: `cp` из worktree
  в `<сайт>/vendor/webx-ui/…` тихо правит чужой рабочий каталог. Свежая сборка попадает на сайт
  только через коммит → ff в основной чекаут → `webx:theme:sync`. Случайную запись снимать
  `git -C <основной> checkout -- <файл>`.
- **Сайт, слинкованный с worktree, собирается из двух чекаутов сразу.** Симлинк
  `packages/<пакет>/node_modules` целиком смотрит в основной чекаут, а там `@webx-ui/<сосед>` —
  ссылка на **его** `packages/<сосед>`. Сборка сайта падает `[MISSING_EXPORT]` с путём в чужой
  `dist` — похоже на забытый экспорт; хуже, когда экспорт есть: сайт молча собирается с прошлой
  версией соседа. Для линковки `node_modules` пакета в worktree делать настоящим каталогом: каждая
  запись — ссылка на основной чекаут, а `@webx-ui/*` — на пакеты самого worktree. Проверка —
  `ls -la packages/<пакет>/node_modules/@webx-ui` показывает путь worktree.
- **Удалять отработавший worktree — сначала ссылки, потом каталог:** `find <worktree> -type l
-delete`, потом `git worktree remove --force` и `git branch -D`. Проверка — `node_modules/vue` и
  `php/packages/*/src` основного чекаута на месте. Для `vitepress build` из worktree нужны ещё
  ссылки на `packages/*/dist`: доки резолвят `@webx-ui/*` из `dist`.
- **Рукописная правка `pnpm-lock.yaml` проверяется в отдельном worktree без `node_modules`.**
  Зависимость, вписанная не в тот `importers`, роняет каждую джобу CI на
  `ERR_PNPM_OUTDATED_LOCKFILE`, а локально этого не видно. Закоммитить, `git worktree add --detach
<скретчпад> HEAD`, там `pnpm install --frozen-lockfile --lockfile-only --ignore-scripts`
  (`node_modules` не создаётся, секунды), потом `git worktree remove --force`.
- **На пустом `php/vendor` первый `analyse` — гонка за манифест Testbench.** `composer install`
  манифест не пишет, и первым приложение поднимает phpstan — сразу в несколько процессов. Итог —
  «undefined method» на **всех** Blueprint-макросах (`draft()`, `blocks()`, `nestedSet()`) плюс
  претензии к типам в чужих пакетах, похоже на сломанную ветку. После `composer install` прогреть
  манифест **один раз и последовательно**: `TESTBENCH_WORKING_PATH="$PWD" php
vendor/bin/testbench package:discover` из `php/`, убрать `bootstrap/cache/*.tmp`, потом
  `analyse`. Если `analyse` уже упал — сначала `php vendor/bin/phpstan clear-result-cache -c
phpstan.neon.dist`: ошибки приезжают из `php/.phpstan.cache`. **Новый пакет в `php/` — это
  `composer update webx-ui/<пакет>`** и прогрев заново (снести `packages.php` и `services.php`),
  иначе «Class …ServiceProvider not found». В свежем worktree `php/composer.lock` нет, и
  `update <пакет>` отказывается «Cannot update only a partial set of packages without a lock file»
  — скопировать lock основного чекаута.
- **«Call to undefined function wx_text()» после rebase — функция в ветке есть, `vendor` о ней не
  знает.** Пакет завёл `autoload.files`, а `composer dump-autoload` строит карту из
  `vendor/composer/installed.json` со старым `composer.json` пакета. Лечится `composer update
webx-ui/<пакет>` из `php/`. Проверка — `grep helpers.php vendor/composer/autoload_files.php`.
- **Скрипт пакетной темы собирается в `dist/theme2.js`, а `@webxTheme` его не ставит.** Стилевой
  вход Vite оставляет пустой JS-чанк, и тот занимает имя `theme.js` первым. В заготовке
  `webx:theme:make` входы названы `theme` и `script`, а `entryFileNames` — функцией (`script` →
  `theme.js`, остальное — `[name]-entry.js`). Правя `vite.config.js` темы, это не трогать.
  Проверка — `ls dist` после `npm run build`: `sources.json`, `theme.css`, `theme.js`.

## Стартовый сайт

- **`scripts/starter-site.sh` одноразовый: удаляет каталог сайта целиком.** Поэтому каталог по
  умолчанию — `webx-starter-test.local` (база `webx_starter_test`), и скрипт падает «is a git
  checkout», если в `STARTER_PATH` есть `.git`: клон `~/work/webx-starter` под ним не живёт. Все
  умолчания переопределяются окружением (`STARTER_PATH`, `STARTER_URL`, `PHP_BIN`, `DB_*` — шапка
  скрипта). Проверка — `STARTER_PATH=<клон> scripts/starter-site.sh --down` отказывает, не тронув ни
  файла.
- **Стартовый сайт показывает не твою ветку, хотя пересобран только что.** Скрипт линкует тот
  чекаут, из которого запущен (`MONOREPO` — родитель скрипта), а в основном может стоять ветка
  другой сессии. PHP из worktree проверяется запуском скрипта из worktree с явными
  `STARTER_PATH`, `STARTER_URL` и `STARTER_ENV=<основной>/.env.starter`; шаг npm требует
  `packages/*/dist` — взять ссылками из основного, если npm-пакеты не менялись. Проверка —
  `ls -la vendor/webx-ui` на сайте показывает путь worktree. Перед удалением worktree пересобрать
  сайт из основного.
- **`starter-site.sh` зелёный по шагам, а главная отвечает 404.** Это `webx:demo`, остановившийся
  на полпути: в логе выше — `Data too long for column 'description'` при вставке демо-типа блока
  (`title` и `description` — 255 символов). Укоротить описание в
  `theme-default/demo/blocks/<тип>.json` (`ThemeDefaultTest` меряет каждое), затем на сайте
  `php artisan webx:demo --remove && php artisan webx:demo`.
- **`ThemeDefaultTest` падает «array_push() does not accept unknown named parameters».** Ключ
  `items` в `values` демо-блока — это вложенные блоки, строка или карта языков там — не список.
  Поле демо-типа называть иначе (`goods`, `rows`).
- **Тег в тексте витрины пропал на странице.** Тело блока `text` печатается как HTML, и браузер
  съедает `<x-…>` как неизвестный элемент. Код в демо-тексте — `<code>&lt;x-webx-…&gt;</code>`.
- **После пересборки браузер показывает старый вид блока, хотя `webx:theme:sync` сказал
  «updated».** Стили блоков отдаются одним `/blocks/<хеш>.css` с `immutable` на год, а хеш назван
  по версиям блоков — в свежей базе номера начинаются заново, адрес совпадает с прошлой сборкой.
  `curl` отдаёт новое. В браузере — `fetch(href, { cache: 'reload' })` для каждого
  `link[rel=stylesheet]`, потом `location.reload()`; Playwright не задет.

## Гейт и проверки локально

- **Гейт упирается в память, а не в ошибки.** `pnpm build` и `pnpm test` идут параллельно,
  dts-шаг `@webx-ui/core` и тесты с `WxCodeEditor` тяжёлые — прогон срывается в `Fatal process out
of memory: Zone` каждый раз в разном месте. Перед гейтом гасить dev-серверы. Отдельный тест —
  `npx vitest run <файл> --pool=forks --poolOptions.forks.singleFork` **из корня репозитория**: из
  каталога пакета jsdom не поднимается, и всё падает «document is not defined».
- **`composer test` обрывается с «The process "phpunit" exceeded the timeout of 300 seconds».**
  Похоже на зависший тест, а это таймаут скриптов composer: полный PHPUnit идёт дольше. Звать
  напрямую: `php -d memory_limit=-1 vendor/bin/phpunit --colors=never` и `php -d memory_limit=-1
vendor/bin/phpstan analyse --no-progress --error-format=raw` (или `COMPOSER_PROCESS_TIMEOUT=0`).
  `--colors=never` — иначе `grep` по итогу ничего не находит.
- **Страница, переписанная в Playwright через `page.route()` + `route.fulfill()`, остаётся без
  стилей и скриптов**, а в консоли «blocked by CORS policy: … more-private address space».
  Документ, отданный `fulfill`, для браузера уже не с адреса сайта, и Private Network Access режет
  каждый его запрос. Добавлять разметку не в ответ, а при разборе: `page.addInitScript()` с
  `MutationObserver`, который вставляет её в `<main>` до модульных скриптов
  (`tests/starter/starter.pw.mjs`, согласие).
- **Полный прогон стартового сайта идёт 40 минут вместо десяти.** Изредка воркер виснет так, что
  таймаут теста не срабатывает, а `| tail` ничего не печатает до конца. Прогон — `--reporter=line`
  в файл под `timeout 1500`; повис — убить дерево процесса, тест отдельно (`-g "<describe>"`),
  остальное догнать `-g` по оставшимся describe (`--list` сначала: `›` в фильтре не находит ничего).
- **`pnpm test:starter` из worktree: «Playwright Test did not expect test.describe() to be called
  here».** Две копии `@playwright/test`: CLI — из `node_modules` основного чекаута, тест
  импортирует пакет из worktree. Звать CLI worktree:
  `node node_modules/@playwright/test/cli.js test -c tests/starter/playwright.config.mjs`.
- **`require('playwright')` из скрипта в скретчпаде не находится.** pnpm кладёт в корень только
  прямые зависимости, а `playwright` — транзитивная. `createRequire('…/node_modules/.pnpm/playwright@<версия>/node_modules/playwright/package.json')`;
  версию показывает `ls -d node_modules/.pnpm/playwright@*`.
- **`docs:preview` (sirv) строит список файлов при старте.** После `docs:build` перезапустить:
  новые хеши → 404, страница без стилей и гидрации.
- **Проверка smoke падает на тексте, в котором искомое есть**, рядом `printf: write error: Broken
pipe`. `… | grep -q` под `pipefail`: grep уходит на первом совпадении, писатель упирается в
  закрытую трубу, конвейер — провал; растёт страница — проверка начинает падать сама. В smoke —
  `… | has <шаблон>` или `grep -q … <<< "$текст"`, никогда `| grep -q`.
- **Редирект `module-seo` в smoke отвечает 404 вместо 301, а на сайте работает.** Запрос на
  `127.0.0.1` без `X-Forwarded-*` — для `Probes` проба здоровья, редиректы ей не отвечают. Всё, что
  ждёт 3xx, smoke спрашивает по имени (`visit "$VISITOR/…"`, `http://localhost:<порт>`) **без общего
  cookie-jar**: вторая сессия в нём отдаёт чужой `xsrf_token()`, и согласие OAuth не возвращает
  `code`.
- **Локальный smoke против MariaDB.** Перед прогоном `DROP DATABASE` обеих баз (`webx_smoke`,
  `webx_smoke_site`) и `CREATE DATABASE webx_smoke`: скрипт базу не чистит, и прошлый прогон роняет
  миграцию Passport «table oauth_auth_codes already exists». Порты 8123/8124 должны быть свободны
  (`ss -ltnp`): `artisan serve` упавшего прогона другой сессии остаётся жить и отвечает из своего,
  уже удалённого скретчпада.
- **`site exec <сайт> php artisan tinker файл.php` висит до таймаута.** Tinker с файлом всё равно
  ждёт stdin. Скрипт — `tinker --execute="require 'storage/app/…/x.php';"` с `</dev/null` под
  `timeout 60`; русский текст — в файле, не в аргументе.

## Shell и инструменты агента

- **Bash-инструмент съедает обратные слэши, даже в закавыченном heredoc'е.** Так ломаются psr-4 в
  `composer.json` («unescaped backslash») и perl/sed-выражения. Файлы со слэшами и большие `.vue`
  писать Write, точечные правки — Edit или скриптом, который сам записан Write: скрипт из heredoc'а
  теряет те же слэши и молча не находит, что заменять (`git diff --stat` меньше ожидаемого).
- **Текст с обратными кавычками внутри `node -e "…"` Bash выполняет как подстановку команды** — так
  правка спеки однажды запустила `pnpm install` в worktree. Такой текст — только скриптом из файла.
  Проверка — `git status` и `ls -ld node_modules` в обоих чекаутах.
- **Правка скриптом на node задвоила половину файла.** `replace` со строкой замены понимает `$'`,
  `$&`, `$$` как шаблоны. Заменять функцией — `s.replace(a, () => b)`. Проверка — `php -l` на
  каждом тронутом файле.
- **`cat > файл` без heredoc'а ждёт stdin до таймаута** — и всё после него не выполняется. node-
  скрипты запускать с `</dev/null`.
- **Юникод-экранирование (`<`) в тексте Write, Edit или heredoc'а приезжает раскрытым** —
  проверка теста начинает проверять другое. Собирать из кусков (в PHP — слэш отдельной строкой, в
  `.ts` — `String.fromCharCode(92)`). Проверка — `grep -n u003C` по файлу после записи.
- **Не-ASCII в аргументе команды приезжает вопросительными знаками** — похоже на сломанный экран,
  а не на кодировку. Русский текст — в файл (Write) и `--data-binary @файл`.

## Docker и хомлаб

- **Контейнер скелета: «The database did not answer within 120s … Access denied», хотя `.env`
  верный.** Compose подставляет `${DB_USERNAME}` сначала из окружения shell, а в CI и smoke там
  `DB_USERNAME=root`. Звать `docker compose` с `env -u DB_USERNAME -u DB_PASSWORD …` (так делает
  `platform_compose` в `scripts/php-smoke.sh`); проверка — `docker compose config`.
- **`mysql-client` в Alpine против MySQL 8 падает дважды:** на TLS («self-signed certificate») —
  `--ssl-verify-server-cert=0`, не `--skip-ssl`; на `caching_sha2_password` — его приносит
  `mariadb-connector-c`. Флаги — через `WEBX_BACKUP_OPTIONS`: их знает машина, а не пакет.
  `php/site/Dockerfile` коннектор ставит; сайт со своим образом — сверить.
- **Крон в отдельном контейнере пишет дамп от root, и панель его не видит** (каталог `0700 root`).
  Планировщик ходит тем же пользователем, что и приложение (`user: www-data`).
- **Artisan в контейнере — всегда `exec -u www-data`.** `docker exec` — root, и всё созданное в
  `storage` становится `root:root`: страницы с картинкой отвечают 500 (`UnableToCreateDirectory`),
  `webx:doctor --strict` жалуется «storage owner … is not www-data's» на каталог, созданный его же
  проверкой. Лечится `chown -R www-data:www-data storage`.
- **Том берёт содержимое из образа один раз, а ключи Passport лежат в `storage/`.** Скелетный
  `docker/entrypoint.sh` создаёт дерево `storage` на каждой загрузке, том — на весь `storage`;
  сайт со своим Dockerfile — сверить.
- **Порт Vite в контейнере публикуется одинаковым с обеих сторон:** `public/hot` пишет тот порт,
  который слушает сервер, и `5199:5173` даёт страницу с ассетами в никуда.
- **`webx:doctor`: «has changed since the bundle was built» на свежем образе.** Слой сборки фронта
  взят из кеша, а mtime исходников новее. Скелет пишет плагином `sources()` в `vite.config.js`
  sha256 каждого файла (`public/build/webx-sources.json`); сайт без плагина — скопировать его из
  `php/site/vite.config.js`.
- **`COPY . .` везёт в образ то, что хост сгенерировал для себя** — `bootstrap/cache/packages.php`
  с dev-провайдерами, и entrypoint падает на `package:discover`. `.dockerignore` сверять с
  `.gitignore` (кеши `bootstrap/cache/*.php`, `auth.json`, `storage/*.key`).
- **nginx: не `expires 1y` вместе с `add_header Cache-Control`** — уйдут два заголовка. Один
  `add_header Cache-Control "public, max-age=31536000, immutable"`; проверка — `curl -sI … | grep
-i cache-control` даёт ровно одну строку.
- Было: Traefik выводил сайт из маршрутизации, потому что healthcheck `http://127.0.0.1/up` получал
  редирект на https. Починено: нормализация и редиректы `module-seo` не трогают health-маршрут и
  запрос на `127.0.0.1` без `X-Forwarded-*`.

## Плейграунд

- **Экран панели смотреть на плейграунде (`/panel/`, порт 5174), а не на сайте.** Плейграунд
  второй страницей поднимает настоящую панель (`createAdmin` с модулями из
  `apps/playground/src/panel/main.ts`), а `apps/playground/server/panel/` отвечает за `/api/cms/*`
  фикстурами в памяти: экраны — из `php/packages/*/resources/screens/*.json` с патчами
  `module-seo`, словарь — из `php/packages/*/lang/`. В devbox — `--host`, адрес
  `https://ws-5174.$DEV_DOMAIN/panel/`. Нет входа и редактора изображений. **Vite следит только за
  импортами, а `.php` и `.json` он не импортирует** — фикстура читает их с диска на каждый запрос;
  кеш в памяти отдавал бы словарь со старта сервера.
- **Blade плейграунда — подмножество, и `@php` в него не входит.** Шаблон блока читают настоящий
  Blade и `apps/playground/server/panel/blade.ts`; второй знает `@if`/`@foreach`, выражения и
  короткий список функций, а `@php(…)`, `$list[] = …` и вызов с ведущим `\` печатает как текст.
  Выражение ставить прямо в `@foreach (…)` и `{{ }}`, статику пакета заменять тем, что есть у обоих
  (`config()`, `__()`); функцию модуля фикстура объявляет `defineFunction`.
- **Обработку значений блока класть в `renderTemplate()`**, через который идут и конструктор, и
  предпросмотр страницы, — не в `draw()` из `blocks.ts`.
- **«Новый» артикул в фикстуре уже занят.** Товары генерируются с артикулами
  `WX-${1000 + id * 7}` и `WX-${2000 + index}` — заняты почти все от `WX-1007` до `WX-2050`, и
  импорт «новых» обновит чужие товары. Новое в фикстурах — `WX-9xxx`.
- **Плейграунд показывает то, что посчитал мок, а не сервер.** Фикстура пишется по спеке, сервер —
  по коду, и где они разошлись, плейграунд честно показывает правильное (счётчик наличия, формат
  `extra` в журнале). Число, счётчик и запись журнала сверять на сайте или тестом php-половины.
