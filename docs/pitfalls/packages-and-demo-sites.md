# Пакеты, демо-сайты и локальное окружение

Читать, когда правка пакета не доезжает до `webx-cms.local`, когда работаешь из worktree, когда
команда в Git Bash или OSPanel ведёт себя странно, перед локальным гейтом и smoke, при деплое на
хомлаб и когда смотришь экран панели в плейграунде. Почти всё здесь выглядит как «ничего не
изменилось» или «сломалась ветка», хотя сломано окружение.

## Линковка сайта с монорепой

- **Правка PHP на слинкованном сайте видна на страницах, но не в том, что идёт через очередь.**
  Прогон аудита (и любая задача) снова находит то, что исправлено, хотя тот же код, вызванный
  из `tinker`, отвечает уже по-новому. `queue:work` загрузил классы при старте и держит их, пока
  жив, а симлинк меняет только файлы. После правки PHP — `php artisan queue:restart` в сайте;
  проверка — у процесса `php artisan queue:work` время старта позже правки.
- Было: новая строка в `lang/*` пакета сутки приезжала в панель английским дефолтом — словарь
  панели жил в кэше без привязки к файлам. Починено: в ключе кэша — число lang-файлов и время
  самого свежего (`DictionaryBuilder::fingerprint()`), новые слова видны со следующей загрузки
  панели. `webx:locales:clear` остаётся для кэша списка языков.
- **Правка пакета доезжает до `webx-cms.local` только сборкой, и с двух сторон по-разному.** Сайт
  грузит `dist` («The packages ship compiled»), значит нужен `pnpm --filter <пакет> build` в
  монорепе, иначе смотришь вчерашний бандл. И **каждая половина бывает копией, а не симлинком**,
  причём в разные дни по-разному: `vendor/webx-ui/*` может оказаться симлинками, а
  `node_modules/@webx-ui/*` — копиями двухдневной давности (npm с `file:` на Windows копирует).
  Копия живёт своей жизнью: правка `resources/screens/*.json` или свежий `dist` до сайта не
  доходят вовсе. Проверять `ls -la` в обоих каталогах. Копию npm-половины освежает
  `npm install "@webx-ui/<пакет>@file:../webx-ui.local/packages/<пакет>"` в самом сайте,
  composer-половину — `composer update "webx-ui/*"`. Доехала ли php-часть, показывает ответ
  `/api/cms/screens/<экран>`. Собирать `npx vite build` в самом сайте: dev-сервер сайта панели
  браузера не годится — его `http://127.0.0.1:<порт>` рядом с `https://webx-cms.local` отлетает как
  `ERR_BLOCKED_BY_CLIENT`, и страница молча остаётся на старом бандле.
- **`rm -rf` внутри `vendor/webx-ui/*` сносит исходники монорепы.** `path`-репозиторий без
  `"symlink": false` кладёт в `vendor` симлинк на чекаут, и каталог внутри него — тот же самый
  каталог. Команда выглядит как «обновить копию в тестовом сайте», а удаляет оригинал; `cp` следом
  честно говорит «are the same file», но уже после `rm`. Перед любой правкой или удалением внутри
  `vendor/webx-ui/*` смотреть `ls -la vendor/webx-ui`: если это симлинки, править нечего — сайт и
  так видит чекаут. Сносить такой сайт целиком тоже нельзя, пока не сняты симлинки: сначала
  `find <сайт> -type l -delete` (там ещё `public/storage`), потом `rm -rf`.
- **Сайт, слинкованный с монорепой, получает вторую копию Vue — и реактивность рвётся по шву.**
  В local-режиме `vue` изнутри пакета резолвится в `packages/core/node_modules/vue` чекаута:
  `createApp` приходит из Vue пакета, а `ref()` в коде сайта — из своего. Ничего не падает, в
  консоли пусто — `ref` меняется, а перерисовки нет. Диагноз: выставить ref в `window` и убедиться,
  что присвоение не меняет DOM. Лечится `resolve: { dedupe: ['vue'] }` в `vite.config.js` сайта;
  скелет (`php/site/vite.config.js`) это уже делает, сайт, собранный не на нём, — проверить. В
  registry-режиме не воспроизводится, то есть ломается ровно там, где ведётся разработка.
- **Composer не «пропускает» `path`-репозиторий, каталога которого нет, — он падает.** «The url
  supplied for the path repository does not exist» — ошибка, а не предупреждение; у npm то же с
  `file:`. Поэтому сайт переключается между монорепой и реестром скриптом (`scripts/packages.mjs`
  у него, `scripts/link-panel.sh` здесь), а не живёт в одном состоянии. **Вернуть сайт после
  local-режима из git нельзя:** `composer.lock` там не отслеживается, а
  `packages.mjs local --no-install` всё равно переписывает `package.json` — `git checkout` отвечает
  «pathspec did not match». Перед переключением копировать в скретчпад `composer.json`,
  `composer.lock`, `package.json`, `package-lock.json` и `database/database.sqlite`, потом положить
  назад и `composer install`. Если на сайте был `webx:demo` — туда же
  `storage/app/webx-demo.json`: журнал демо не в git и не в базе, и вернувшаяся база иначе
  остаётся рядом с журналом строк, которых в ней уже нет. **Новый пакет в local-режиме одним
  `composer require` не ставится:** он тянет свежие диапазоны соседей, а частичное обновление
  держит их на версиях из lock'а («fixed to … by a partial update»). Два шага:
  `require "webx-ui/<пакет>:*" --no-update`, потом `update "webx-ui/*"`.
- **Перевести слинкованный сайт на другой чекаут (worktree) — это не `composer update`.**
  `MONOREPO=<worktree> node scripts/packages.mjs local` переписывает манифесты, `composer update
"webx-ui/*"` пишет новый путь в `composer.lock` — а симлинки в `vendor/webx-ui` остаются на
  прежний чекаут: версия та же, и composer считает пакет установленным. Выглядит как «правка не
  доехала». Снять сами ссылки (`find vendor/webx-ui -maxdepth 1 -type l -delete` — только ссылки,
  не `rm -rf`) и `composer install`; с `node_modules/@webx-ui` так же и `npm install`. **Обратно —
  подождать минуту:** PHP OSPanel держит realpath-кеш, и после возврата на основной чекаут сайт ещё
  около минуты отвечает 500 «Class … not found» с путём `webx-ui-<ветка>/…` в логе. Не чинить, а
  подождать и проверить по содержимому страницы, а не по коду ответа.
- **На сайте, где панель уже есть, `webx:panel --sync` новый модуль в неё не впишет.** Команда
  нарочно не трогает существующий `resources/js/admin.ts`: пишет конфиги, говорит
  «npm run build» — маршруты сервера на месте, пункт в манифесте есть, а раздела в панели нет.
  Импорт, стиль и `...services()` в `modules` на таком сайте дописываются руками.
- **Компонент, поставленный в лэйаут `webx-cms.local`, не печатается на страницах конструктора.**
  Статьи, услуги и каталог стоят в `components/layout.blade.php`, а страница `module-pages` — в
  `resources/views/pages/show.blade.php`, отдельном документе со своими `<head>` и регионами, без
  `<main>` и без `{{ $slot }}`. Выглядит как «компонент на этой странице ничего не нашёл», хотя
  данные на месте. Всё, что сайт печатает на каждой странице, ставить в оба файла; проверка —
  `curl` статьи и `/about`.
- **Статический `public/robots.txt` отдаётся раньше Laravel, и маршрут `module-seo` не отвечает
  никогда.** Веб-сервер (и `artisan serve`) отдаёт файл сам, так что `seo.robots-txt` и строка
  `Sitemap:` до посетителя не доходят, а проверка «`/robots.txt` отвечает 200» проходит на этом же
  файле. Скелет `webx-ui/site` файла уже не несёт; сайт, собранный из обычного скелета Laravel, —
  удалить его. Проверять содержимое ответа, а не код.

## Worktree и pnpm

- **Файл, скопированный «в стартовый сайт», оказывается в основном чекауте.** `vendor/webx-ui/*`
  сайта, слинкованного с монорепой, — симлинки на `php/packages` **основного** чекаута, не
  worktree: `cp dist/contacts.css <сайт>/vendor/webx-ui/widgets/dist/` из worktree тихо правит
  чужой рабочий каталог, и `git status` основного чекаута показывает изменённый `dist/`. Свежая
  сборка попадает на сайт только через коммит → ff в основной чекаут → `php artisan
webx:theme:sync`. Случайную запись снимать `git -C <основной> checkout -- <файл>`.
- **В worktree `node_modules` — симлинк на основной чекаут, и `pnpm` это не переживает.** Любой
  `pnpm <скрипт>` оттуда либо отказывается («Refusing to use task run state directory … because it
  is a symbolic link»), либо, решив, что сменился пакетный менеджер, идёт по симлинку и **сносит
  содержимое `node_modules` основного чекаута**: пропадают `.pnpm` и `.bin`, ломается всё сразу,
  включая соседнюю сессию. Дело в симлинке, а не в worktree: `git worktree add` в обычный каталог
  приезжает без `node_modules`, и `pnpm install` там делает свой, основной чекаут цел. Так же ведёт
  себя `preview_start` по имени из `.claude/launch.json` — он запускает pnpm. Из worktree запускать
  бинарники напрямую (`npx vite build`, `npx vue-tsc -p tsconfig.json --noEmit` в каждом пакете
  вместо рекурсивного `pnpm typecheck`,
  `node <основной чекаут>/apps/docs/node_modules/vitepress/bin/vitepress.js dev --port 5177`), а
  dev-сервер поднимать фоновой командой и открывать `preview_start` с `url`. Снос лечится одним
  `pnpm install --frozen-lockfile` в основном чекауте — проверить, что `node_modules/.pnpm` на
  месте.
- **Worktree бывает и вовсе без `node_modules` — и `npx` это прячет.** Свежий worktree приезжает
  без единого `node_modules`, ни в корне, ни в пакетах: `npx prettier` молча скачивает себе
  какой-то Prettier и форматирует им, а `npx vue-tsc` в пакете падает «Cannot find type definition
  file for 'node'» — похоже на сломанный `tsconfig`. Лечится junction'ами на основной чекаут, корень
  и каждый пакет: `cmd //c mklink //J node_modules <основной>\node_modules`, то же для
  `packages/*/node_modules` и `apps/*/node_modules` (они в `.gitignore`). Пакетные junction'ы
  циклом — из PowerShell (`New-Item -ItemType Junction`), не из Bash: собранный в Bash путь теряет
  обратные слэши, `mklink` молча делает ссылку в никуда, и vitest падает «Failed to resolve import
  "vue-router"» на каждом файле пакета. Битая ссылка видна по пустому `Target` у `Get-Item`;
  снимать её `cmd /c rmdir`, а не `Remove-Item -Recurse`, который уходит за ссылку. pnpm после этого из
  worktree по-прежнему не звать. Проверка — `ls node_modules/.bin/prettier` перед первым
  форматированием; отформатированное до неё — прогнать ещё раз.
- **Сайт, слинкованный с worktree, собирается из двух чекаутов сразу.** Junction
  `packages/<пакет>/node_modules` целиком смотрит в основной чекаут, а там `@webx-ui/<сосед>` —
  ссылка на **его** `packages/<сосед>`. Пакет из worktree импортирует соседа из чужого `dist`, и
  сборка сайта падает `[MISSING_EXPORT]` с путём `../webx-ui.local/packages/<сосед>/dist` —
  похоже на забытый экспорт в ветке. Хуже, когда экспорт есть: тогда сайт молча
  собирается с прошлой версией соседа. Для линковки сайта `node_modules` пакета в worktree делать
  настоящим каталогом: каждая запись — junction на основной чекаут, а `@webx-ui/*` — junction'ы на
  пакеты самого worktree. Проверка — `ls -la packages/<пакет>/node_modules/@webx-ui` показывает
  путь worktree. Сборку пакета из worktree `npx vite build` делает в его каталоге; без junction'а
  на `node_modules` пакета она падает «Cannot find package 'vite-plugin-dts'».
- **Удалять отработавший worktree — сначала ссылки, потом каталог.** В нём junction'ы
  `node_modules` на основной чекаут, симлинки `php/vendor/webx-ui/*` и тысячи ссылок pnpm, и
  удаление, которое пойдёт за ссылку, снесёт чужое. Порядок: обойти дерево, не заходя в точки
  повторной обработки (`FileAttributes.ReparsePoint`), и снять каждую ссылку
  (`[IO.Directory]::Delete(path, $false)` для каталога), потом `git worktree remove --force` и
  `git branch -D`. Проверка — `node_modules/vue` и `php/packages/*/src` основного чекаута на месте.
  Пустая папка, которая не удаляется «Device or resource busy», — чей-то процесс держит её текущим
  каталогом; оставить до освобождения. Для `vitepress build` из worktree нужны ещё junction'ы
  `packages/*/dist`: доки резолвят `@webx-ui/*` из `dist`.
- **Рукописная правка `pnpm-lock.yaml` из worktree проверяется в отдельном worktree без
  `node_modules`.** Зависимость, вписанная не в тот `importers`, роняет каждую джобу CI на
  `ERR_PNPM_OUTDATED_LOCKFILE` («1 dependency was removed»), а локально этого не видно — pnpm из
  worktree звать нельзя. Проверка без риска: закоммитить, `git worktree add --detach <скретчпад>
HEAD`, там `pnpm install --frozen-lockfile --lockfile-only --ignore-scripts` (симлинков нет,
  `node_modules` не создаётся, секунды), потом `git worktree remove --force`.
- **На пустом `php/vendor` первый `analyse` — гонка за манифест Testbench.** В worktree своего
  `php/vendor` нет, `composer install` манифест не пишет (он пишется при первой загрузке
  приложения), и первым приложение поднимает phpstan — сразу в несколько процессов. Дальше либо
  «undefined method» на **всех** Blueprint-макросах (`draft()`, `blocks()`, `nestedSet()`) плюс
  претензии к типам в чужих пакетах — похоже на сломанную ветку, — либо
  `Laravel framework bootstrap failed` с `rename(…serXXXX.tmp, …services.php): Access is denied`,
  что читается как права на каталог. После `composer install` в свежем `php/` прогреть манифест
  **один раз и последовательно**:
  `TESTBENCH_WORKING_PATH="$(cygpath -m $PWD)" php vendor/bin/testbench package:discover` из
  `php/`, убрать `bootstrap/cache/*.tmp`, потом `analyse`. Если `analyse` уже успел упасть,
  прогрева мало: те же ошибки приезжают из `php/.phpstan.cache` — сначала
  `php vendor/bin/phpstan clear-result-cache -c phpstan.neon.dist`. Голый
  `php vendor/orchestra/testbench-core/laravel/artisan` не годится: без рабочего пути он ищет
  `vendor/autoload.php` внутри testbench и падает на `require`. **Новый пакет в `php/` — это
  `composer update webx-ui/<пакет>`**, после которого манифест прогревается заново (снести
  `packages.php` и `services.php`), иначе тест отвечает «Class …ServiceProvider not found».
  В свежем worktree `php/composer.lock` нет (он не отслеживается), и `update <пакет>` отказывается
  «Cannot update only a partial set of packages without a lock file» — скопировать lock основного
  чекаута, тогда ставится всё остальное ровно его версиями и новый пакет сверху.
  Если и после прогрева `analyse` падает на том же `rename(…services.php)`, — так было после
  `composer update` с новым пакетом, — один последовательный прогон греет манифест в том виде, в
  каком его строит Larastan: `php vendor/bin/phpstan analyse --debug <любой путь пакета>`, убрать
  `*.tmp`, потом полный `analyse`.
- **«Call to undefined function wx_text()» после rebase — функция в ветке есть, `vendor` о ней
  не знает.** Пакет завёл `autoload.files` (`src/helpers.php`), а `composer dump-autoload` строит
  карту из `vendor/composer/installed.json`, где записан старый `composer.json` пакета, — и
  helpers.php в неё не попадает. Похоже на сломанную чужую ветку. Лечится `composer update
webx-ui/<пакет>` из `php/`: path-репозиторий перечитывается. Проверка — `grep helpers.php
vendor/composer/autoload_files.php`.

## Windows, Git Bash, OSPanel

- **Команда, которой не было, запустилась сама — `pnpm install` прямо в worktree.** Текст с
  обратными кавычками (markdown: `` `pnpm test:starter` ``) внутри `node -e "…"` в двойных
  кавычках Bash выполняет как подстановку команды — до node. Так правка спеки поставила зависимости
  в worktree и запустила Playwright; рядом «header: command not found» от соседних кавычек. Текст с
  обратными кавычками — только скриптом из файла (Write), никогда в аргументе Bash. Проверка —
  `git status` и `ls -ld node_modules` в обоих чекаутах.

- **Правка скриптом на node задвоила половину файла.** `String.prototype.replace` со строкой
  замены понимает `$'`, `$&` и `$$` (и обратную кавычку после доллара) как шаблоны: PHP с
  `'$'.$name` в замене вставил «остаток файла» посреди класса. Заменять функцией —
  `s.replace(a, () => b)`. И `sed` из Bash с обратной кавычкой после слэша: слэш съеден, а эта
  пара в GNU sed значит «начало строки» — кавычка встаёт в начало каждой строки диапазона.
  Проверка — `php -l` на каждом тронутом файле.

- **Bash-инструмент съедает обратные слэши, даже в закавыченном heredoc'е.** Двойной слэш
  приезжает одинарным, `\N` — просто буквой; так ломаются psr-4 в `composer.json` (composer
  отвечает «unescaped backslash») и любые perl/sed-выражения с экранированием. Файлы со слэшами
  писать инструментом Write, точечные правки — скриптом на php/node, который держит шаблон в
  файле, а не в командной строке. Большие `.vue` — тоже Write: heredoc ломается на обратных
  кавычках и `$`. **Скрипт-правка, сам записанный heredoc'ом, теряет те же слэши**: `.cjs` с
  `s.replace("use WebxUi\\Themes\\…")` после heredoc'а ищет `WebxUiThemes…`, не находит и молча
  оставляет файл как был — `git diff --stat` показывает меньше строк, чем ждали. Такой скрипт —
  тоже Write, а правку со слэшами проще сделать Edit.
- **`cat > файл` без heredoc'а ждёт stdin до таймаута.** Команда висит две минуты и уходит в фон,
  ничего не написав; всё после неё в той же строке не выполняется. Писать файл — Write или
  `cat > файл <<'X' … X`; node-скрипты запускать с `</dev/null`.
- **Юникод-экранирование в тексте вызова инструмента приезжает раскрытым.** Обратный слэш, `u` и
  четыре шестнадцатеричные цифры в содержимом Write, Edit или heredoc'а становятся самим символом
  ещё до записи: экранированный `<b>` в проверке теста становится сырым `<b>` (и проверка «в
  JSON-LD нет сырого `<`» ищет ровно то, чего быть не должно), BOM в шаблонной строке мока —
  невидимым символом в исходнике. Ничего не падает, тест просто проверяет другое. Такую строку
  собирать из кусков — в PHP слэш отдельной строкой, склеенной с `u003C`; в `.ts` — скриптом
  через `String.fromCharCode(92)`. Проверка — `grep -n u003C` (или `uFEFF`) по файлу после записи.
- **Не-ASCII в аргументе команды приезжает вопросительными знаками.** `curl -d '{"title":"Панель"}'`
  кладёт в фикстуру `??????`, и выглядит это не как кодировка, а как сломанный экран. Тело запроса
  и любой русский текст писать в файл (Write) и передавать `--data-binary @файл`.
- **`/tmp` у bash и у php — разные каталоги.** Bash подставляет реальный путь только в аргументах
  запуска, а строка `/tmp/x.php` внутри php-кода на Windows никуда не ведёт. Временное — в
  скретчпад сессии по абсолютному пути.
- **Скрипт `.js` во временном каталоге — ES-модуль.** `node /tmp/fix.js` падает «require is not
  defined in ES module scope»: в `%TEMP%` лежит чужой `package.json` с `"type": "module"`, и node
  читает по нему всё под ним. Похоже на опечатку в скрипте. Одноразовую правку писать `.cjs` (или
  через `import`).
- **Git Bash переписывает всё, похожее на абсолютный posix-путь, по дороге в дочерний процесс.**
  `WEBX_BASE=/webx/` приезжает как `C:/Program Files/Git/webx/`: сборка проходит, потом 404 на
  каждом шрифте. Передавать имя, а путь собирать на той стороне; `MSYS_NO_PATHCONV=1` не лечит — он
  ломает сам pnpm. Так же гибнет `//` в jq: `.conclusion // "-"` отвечает «cannot add: string and
  array» — писать `if .conclusion == null then …` или держать фильтр в файле. **Обратная сторона:**
  путь внутри JSON-строки не переписывается, поэтому `--repository='{"url":"/c/Work/…"}'` доезжает
  до composer как есть, а такого каталога на Windows нет — собирать такой JSON на стороне php,
  передав путь отдельным аргументом. **И путь после префикса в аргументе:**
  `curl -F "fields[attachment]=@/c/Users/…"` — `curl.exe` получает `/c/…` как есть и выходит с
  кодом 26 без единой строки. Путь для Windows-программ — в виде `C:/…` (`cygpath -m`).
- **PHP-часть проверяется настоящим PHP.** Локально есть модули OSPanel
  (`C:\Work\OSPanel\modules\PHP-8.3\php.exe` и `PHP-8.4`); composer в PATH нет, phar кладётся в
  скретчпад. Гейт php-половины — `composer lint && composer analyse && composer test` из `php/`.
  Скрипты composer зовут `php` по имени, и без него в PATH все три падают с «'php' is not
  recognized» и вопросом про `allow-plugins` — похоже на сломанный `composer.json`. В фоне и с
  выводом в `| tail` ошибки не видно вовсе: composer молча ждёт ответа на этот вопрос, и гейт
  «висит» без дочернего процесса. Либо каталог
  `php.exe` в PATH, либо сами бинарники: `php.exe vendor/bin/pint --test`, `… phpstan analyse`,
  `… phpunit`.
- Было: dev-корень `php/` не запускался на 8.3 при пакетах на `^8.3` (PHPUnit 13 требует 8.4.1).
  Нижняя версия пакетов поднята до `^8.4`, матрица CI — 8.4 и 8.5.

## Гейт и проверки локально

- **Гейт локально упирается в память, а не в ошибки.** `pnpm build` и `pnpm test` идут
  параллельно по числу ядер, dts-шаг `@webx-ui/core` и тесты с `WxCodeEditor` (CodeMirror)
  тяжёлые — и прогон срывается в `Fatal process out of memory: Zone` каждый раз в разном месте, а
  со второго раза проходит. Перед гейтом гасить dev-серверы. Отдельный тест —
  `npx vitest run <файл> --pool=forks --poolOptions.forks.singleFork` **из корня репозитория**:
  `vitest.config.ts` один, в корне; из каталога пакета jsdom не поднимается, и падает всё подряд с
  «document is not defined», что читается как сломанная ветка.
- **`composer test` локально обрывается на 93% с «The process "phpunit" exceeded the timeout of
  300 seconds».** Похоже на зависший тест, а это таймаут процесса у самого composer: весь набор
  на Windows идёт дольше пяти минут. `composer analyse` из той же сессии падает «Result is
  incomplete because of severe errors» — ему не хватило памяти. Запускать напрямую:
  `php -d memory_limit=-1 vendor/bin/phpunit --colors=never` и
  `php -d memory_limit=-1 vendor/bin/phpstan analyse --no-progress --error-format=raw`
  (`--colors=never`, иначе `grep` по итогу не находит ничего — строки обёрнуты в коды цвета).
- **Страница, переписанная в Playwright через `page.route()` + `route.fulfill()`, остаётся без
  стилей и скриптов** — баннер не появляется, клик ждёт минуту и падает по таймауту, а в консоли
  «blocked by CORS policy: … the resource is in more-private address space `loopback`». Документ,
  отданный `fulfill`, для браузера уже не с loopback-адреса сайта, и Private Network Access режет
  каждый его запрос к `*.local`. Убрать `Content-Length` и `Content-Encoding` (их тоже приходится
  убирать: тело из `route.fetch()` уже разжато) не помогает. Добавлять разметку не в ответ, а при
  разборе страницы: `page.addInitScript()` с `MutationObserver`, который вставляет её в `<main>`,
  как только тот появится, — до модульных скриптов (`tests/starter/starter.pw.mjs`, согласие).
- **`pnpm test:starter` из worktree падает до первого теста: «Playwright Test did not expect
  test.describe() to be called here … No tests found».** Похоже на сломанный файл тестов, а это
  две копии `@playwright/test`: CLI запущен из `node_modules` основного чекаута, а
  `tests/starter/starter.pw.mjs` импортирует пакет из `node_modules` worktree. В worktree со
  своими `node_modules` звать его CLI —
  `node node_modules/@playwright/test/cli.js test -c tests/starter/playwright.config.mjs`; путь
  основного чекаута годится, только пока у worktree своих `node_modules` нет.
- **`docs:preview` (sirv) строит список файлов при старте.** После `docs:build` сервер надо
  перезапустить: свежий HTML тянет новые хеши, их нет в списке → 404, страница без стилей и без
  гидрации. Выглядит как «правка не помогла».
- **`webx:doctor --strict` в контейнере: «storage owner: storage/app/public/media is not
  www-data's», хотя entrypoint только что сделал `chown`.** Доктор, запущенный от root
  (`docker compose exec app php artisan …`), сам создаёт этот каталог своей проверкой «Media
  disk» — следующей проверке он уже чужой. Artisan в контейнере — всегда `exec -u www-data`.
- **Проверка smoke падает на тексте, в котором искомое есть** («the consent page did not draw»,
  а в выводе ровно эта страница), рядом `printf: write error: Broken pipe`. `… | grep -q` под
  `pipefail`: `grep -q` уходит на первом совпадении, писатель упирается в закрытую трубу, как
  только текст больше её буфера, и конвейер — провал. Растёт страница — проверка начинает
  падать сама. В smoke — `… | has <шаблон>` (дочитывает вход до конца) или
  `grep -q … <<< "$текст"`, никогда `| grep -q`.
- **Редирект `module-seo` в smoke отвечает 404 вместо 301, а на сайте работает.** Запрос с этой
  же машины на хост `127.0.0.1` без `X-Forwarded-*` — для `Probes` проба здоровья контейнера, и
  ни редирект, ни нормализация адреса ей не отвечают. Smoke ходит в `artisan serve` по
  `http://127.0.0.1:<порт>` — поэтому всё, что ждёт 3xx, спрашивает по имени: `visit
"$VISITOR/…"` (`http://localhost:<порт>`), **без общего cookie-jar**: вторая сессия под
  `localhost` в нём отдаёт `xsrf_token()` чужой токен, и согласие OAuth дальше не возвращает
  `code`. Проверка руками: `artisan serve --host=127.0.0.1` и `curl` одного
  адреса через `127.0.0.1` и через `localhost`.
- **Локальный smoke против MariaDB запускается так, и никак иначе.** MariaDB OSPanel слушает
  `127.0.1.14:3306` под `root` без пароля (`DB_HOST=127.0.1.14`). Перед прогоном:
  - `DROP DATABASE` обеих баз (`webx_smoke`, `webx_smoke_site`) и `CREATE DATABASE webx_smoke`:
    скрипт создаёт базу, только если её нет, и не чистит её — и прошлый прогон роняет свежую
    миграцию Passport «table oauth_auth_codes already exists».
  - `SMOKE_DIR` виндовым путём (`C:/…`): `curl -F …=@/c/…` из Git Bash файл не читает и выходит с
    кодом 26 на вложении формы.
  - `COMPOSER_BIN` строкой `"<php.exe> <путь>/composer.phar"`: скрипт вытаскивает из неё phar и
    передаёт `webx:setup --composer`; обёртка-скрипт вместо этого даёт «Could not open input
    file: \composer.phar» на второй половине. Плюс `composer` в PATH (`composer.bat` рядом с
    phar): `webx:setup` зовёт его по имени, иначе «'composer' is not recognized».
  - Порты 8123/8124 свободны: `artisan serve` упавшего прогона другой сессии остаётся жить, и
    сайт второй половины отвечает из _его_ скретчпада (`require(…/helpers.php)` чужого,
    удалённого каталога). Смотреть `Get-NetTCPConnection -LocalPort 8123,8124` и гасить `php.exe`
    с `-S 127.0.0.1:812x`.

## Docker и хомлаб

- **Контейнер скелета не поднимается: «The database did not answer within 120s … Access denied»,
  хотя в `.env` пользователь и пароль верные.** Compose подставляет `${DB_USERNAME}` и остальное в
  `docker-compose.yml` сначала из окружения shell, а `.env` — только для того, чего там нет. В CI
  и в смоуке окружение несёт `DB_USERNAME=root` для хоста, и MariaDB в контейнере заводится не под
  тем пользователем, которым приложение потом входит по `.env`. Звать `docker compose` с
  `env -u DB_USERNAME -u DB_PASSWORD …` (так делает `platform_compose` в `scripts/php-smoke.sh`);
  проверка — `docker compose config` показывает `MARIADB_USER` из `.env`.
- **`mysql-client` в Alpine — это клиент MariaDB, и против MySQL 8 он падает дважды.** Сначала на
  TLS: клиент сам предлагает шифрование и отказывается от самоподписанного сертификата
  («self-signed certificate in certificate chain») — лечится `--ssl-verify-server-cert=0`, не
  `--skip-ssl`. Потом на аутентификации: плагина `caching_sha2_password` в `mariadb-client` нет
  («could not be loaded»), его приносит `mariadb-connector-c`. Ни Testbench, ни smoke против
  MariaDB этого не видят — только дамп на настоящем сайте. Поэтому флаги передаются через
  `WEBX_BACKUP_OPTIONS`: их знает машина, а не пакет. `php/site/Dockerfile` ставит
  `mariadb-connector-c` сам; сайт со своим образом — сверить.
- **Крон в отдельном контейнере пишет дамп от root, и панель его не видит.** Каталог под снимки
  создаёт тот, кто дампит, и Laravel создаёт его `0700` — `drwx------ root`, куда php-fpm под
  `www-data` не заглянет. Файл лежит, строка «последний снимок» пуста, ошибок нигде. Планировщик
  должен ходить тем же пользователем, что и приложение (`user: www-data` у сервиса).
- **Том берёт содержимое из образа один раз, а ключи Passport лежат в `storage/`, не в
  `storage/app/`.** Скелет это учитывает: `php/site/docker/entrypoint.sh` создаёт дерево `storage`
  на каждой загрузке, том — на весь `storage`; сайт со своим Dockerfile — сверить с ним.
- **Порт Vite в контейнере публикуется одинаковым с обеих сторон.** Адрес в `public/hot` пишет сам
  dev-сервер, и пишет тот порт, который слушает; вывод наружу на другой номер (`5199:5173`) даёт
  страницу, все ассеты которой смотрят туда, где ничего нет, — при том что в логе сервера всё
  правильно.
- **`webx:doctor` говорит «has changed since the bundle was built» на только что собранном
  образе.** Стадию сборки фронта Docker взял из кеша слоёв — бандл от старой сборки, а исходники
  скопированы заново, и mtime у них новее. Скелет это лечит плагином `sources()` в
  `vite.config.js`: он пишет `webx-sources.json` с sha256 каждого файла сайта из графа сборки, и
  doctor сравнивает содержимое. Сайт со своим `vite.config.js` без плагина по-прежнему
  сравнивается по времени — скопировать `sources()` из `php/site/vite.config.js`. Проверка —
  `public/build/webx-sources.json` после `npm run build`.
- **`COPY . .` везёт в образ то, что хост сгенерировал для себя.** После локального
  `composer install` в `bootstrap/cache/packages.php` записаны провайдеры dev-пакетов (Pail), в
  образе их нет — entrypoint падает на `package:discover` с «Class … not found», хотя сборка
  прошла. `.dockerignore` сверять с `.gitignore`: всё, что git не видит, кроме исходников, —
  кандидат и туда (кеши `bootstrap/cache/*.php`, `auth.json`, ключи `storage/*.key`).
- **После `docker exec … php artisan …` каждая страница с картинкой отвечает 500
  (`UnableToCreateDirectory` для `storage/app/public/media/thumbs/<key>`), а команда сказала
  «Restored.».** `docker exec` — это root: всё, что команда создала в `storage`, — `root:root`, и
  php-fpm под `www-data` не режет превью в таких папках. `webx:snapshot` и
  `webx:snapshot:restore` теперь отдают написанное владельцу `storage` сами (`Support\Ownership`),
  но любая другая artisan-команда под root оставит тот же след — логи, кеш. Звать
  `docker exec -u www-data …`; проверка — `webx:doctor` под root перечисляет пути в `storage`
  чужого владельца, лечится `chown -R www-data:www-data storage`.
- **Весь сайт вместе с панелью — «404 page not found» от Traefik, а в контейнере всё живое.**
  Traefik выводит из маршрутизации контейнер с упавшим healthcheck, а тот спрашивает
  `http://127.0.0.1/up` изнутри: любой редирект на https (нормализация `module-seo`, правило
  редиректа) — провал проверки. Нормализация и таблица редиректов теперь не трогают health-маршрут
  и запрос с 127.0.0.1 на `http://127.0.0.1` без `X-Forwarded-*`, а `seo.normalise-*` при restore
  остаются стендовыми. Проверка — `docker inspect --format '{{.State.Health.Status}}' <контейнер>`
  и `curl -si http://127.0.0.1/up` внутри.

## Плейграунд

- **Экран панели смотреть на `http://localhost:5174/panel/`, а не на сайте.** Плейграунд второй
  страницей поднимает настоящую панель (`createAdmin` с модулями из
  `apps/playground/src/panel/main.ts`), а `apps/playground/server/panel/` отвечает ей за
  `/api/cms/*` фикстурами в памяти. Экраны настоящие, поэтому правка едет в релиз; Laravel, база и
  сборка сайта не нужны. Описанные экраны мок собирает из `php/packages/*/resources/screens/*.json`
  вместе с патчами `module-seo`, словарь читает из `php/packages/*/lang/` — язык переключается
  по-настоящему. Меню там и объявленные конфигом, и заведённые руками, включая объявленное и ни
  разу не сохранённое (`id: null`). Чего нет: входа и редактора изображений — файлы библиотеки
  `editable: false`, а картинки — SVG, собранные из имени файла (загруженное в сессии отдаётся
  своими байтами). **Vite следит только за тем, что импортирует, а `.php` и `.json` он не
  импортирует**, поэтому фикстура читает их с диска на каждый запрос; кеш в памяти отдавал бы
  словарь, снятый при старте сервера, и новая строка молча приезжала бы английским дефолтом.
- **Blade плейграунда — подмножество, и `@php` в него не входит.** Шаблон предложенного блока
  читают двое: настоящий Blade на сайте и `apps/playground/server/panel/blade.ts` в превью. Второй
  знает `@if`/`@foreach`, выражения и короткий список функций; `@php(…)`, `$list[] = …` и вызов с
  ведущим `\` он печатает как текст — сырой шаблон в превью при зелёных серверных тестах. Писать
  так, чтобы выражение стояло в `@foreach (…)` и `{{ }}` прямо (`press()->kind($kind)->get()`),
  статику пакета заменять тем, что есть у обоих (`config()`, `__()`); функцию модуля фикстура
  объявляет `defineFunction`.
- **Обработку значений блока класть в `renderTemplate()`**, через который идут и конструктор, и
  предпросмотр страницы, — не в `draw()` из `blocks.ts`.
- **«Новый» артикул в фикстуре плейграунда уже занят.** Товары каталога генерируются с
  артикулами `WX-${1000 + id * 7}` и `WX-${2000 + index}`, то есть заняты почти все от `WX-1007` до
  `WX-2050`: файл импорта с «новыми» `WX-2001…` обновил чужие товары — «создано: 0, обновлено: 16»
  и смартфон с ценой чехла. Выглядит как ключ, который ищет не то. Новое в фикстурах — `WX-9xxx`.
- **Проверка в плейграунде показывает то, что посчитал мок, а не сервер.** Фикстура пишется по
  спеке, сервер — по коду, и где они разошлись, плейграунд честно показывает правильное: у
  статуса наличия по умолчанию мок считал и товары без строки, а сервер — только строки, и на
  настоящем сайте «В наличии: 37» стояло при ~115 товарах в нём. Так же журнал: мок пишет поле
  `extra` строкой на ключ, сервер — одной строкой с объектом. Число, счётчик и запись журнала
  после плейграунда сверять на `webx-cms.local` или тестом php-половины.
