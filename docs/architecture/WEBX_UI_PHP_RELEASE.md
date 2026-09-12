# WebX UI — как php-пакеты попадают на Packagist

Composer ставит пакет из его собственного репозитория: подпапку монорепо он видеть не умеет.
Поэтому каждый пакет из `php/packages/` зеркалится в отдельный публичный репозиторий
`github.com/webx-ui/<имя>`, и уже туда приезжает тег, который Packagist показывает как версию.

Публикации как таковой нет. **Версия существует ровно тогда, когда существует тег** в
репозитории пакета. Секрет для публикации не нужен — нужно только право на запись в
split-репозитории, и его даёт GitHub App организации.

## Поток

```
PR с changeset'ом на @webx-ui/php
        │  merge
        ▼
release.yml → changesets/action открывает «chore: version packages»
        │  merge этого PR
        ▼
release.yml:
   ├─ changeset version → php/package.json: 0.1.0
   │                      scripts/sync-php-version.mjs правит внутренние констрейнты
   ├─ changeset publish → npm-пакеты (php-пакет приватный, пропускается)
   └─ тег php-v0.1.0 в монорепо
        │  uses: ./.github/workflows/php-split.yml с tag: v0.1.0
        ▼
php-split.yml → для каждого php/packages/<pkg>:
        push содержимого в webx-ui/<pkg> + тег v0.1.0
        │  вебхук
        ▼
Packagist: webx-ui/<pkg> 0.1.0
```

Push в `main`, который трогает `php/**`, запускает `php-split.yml` без тега — зеркала держатся
в актуальном состоянии между релизами.

## Версии

Одна версия на все php-пакеты, как у `illuminate/*`. Её носит `php/package.json` — приватный
npm-пакет `@webx-ui/php`, который существует только ради changesets: он не публикуется, но
changesets его версионирует и пишет ему `CHANGELOG.md`.

Зависимость между своими пакетами пишется как `^<версия>`;
`scripts/sync-php-version.mjs` переписывает такие констрейнты в том же коммите, что и бамп, —
разъехаться они не могут.

Пока `php/package.json` на `0.0.0`, шаг с тегом в `release.yml` ничего не делает: первый релиз
надо выпустить осознанно, после того как репозитории созданы и пакеты отправлены на Packagist.

## Что сделать один раз на GitHub

1. **Создать пустые публичные репозитории** — по одному на пакет, имя ровно как во второй части
   имени пакета:

   ```bash
   gh repo create webx-ui/nested-set --public \
     --description "Read-only split of webx-ui/webx-ui — php/packages/nested-set"
   ```

   Без README и лицензии при создании: всё содержимое привезёт сплит. Пустой репозиторий
   воркфлоу засеет первым коммитом сам — сам экшен сплита этого не умеет и молча пушит пустоту.

2. **GitHub App на запись в зеркала.** Не личный токен: App не протухает, не привязан к одному
   человеку, и новое зеркало не требует потом никаких действий.

   https://github.com/organizations/webx-ui/settings/apps/new
   - GitHub App name: `webx-ui-split` (имя глобально уникальное)
   - Homepage URL: `https://github.com/webx-ui/webx-ui`
   - Webhook → снять галку **Active**: события нам не нужны
   - Permissions → Repository permissions → **Contents: Read and write**, больше ничего
     (`Metadata: Read-only` добавится сам)
   - Where can this GitHub App be installed → **Only on this account**
   - Create GitHub App → запомнить **Client ID** (вида `Iv23li…`, он публичный) →
     Generate a private key, скачается `.pem`
   - Install App → организация `webx-ui` → **All repositories**

   «All repositories» тут не расточительность: воркфлоу выпускает токен на каждый job отдельно
   и сужает его до одного репозитория (`repositories:` + `permission-contents: write`), так что
   у каждого прогона прав ровно на своё зеркало. Взамен новое зеркало подхватывается само.

3. **Положить Client ID и ключ в репозиторий:**

   ```bash
   gh variable set PHP_SPLIT_APP_CLIENT_ID --repo webx-ui/webx-ui --body "<Client ID>"
   gh secret set PHP_SPLIT_APP_PRIVATE_KEY --repo webx-ui/webx-ui < webx-ui-split.private-key.pem
   ```

   Client ID не секрет, поэтому он переменная. Из PowerShell редирект `<` не работает —
   `cmd /c "gh secret set … < ключ.pem"`, так байты ключа доедут без перекодировки.
   Скачанный `.pem` после этого удалить: ключ живёт только в секрете, а при утере на странице
   App выпускается новый.

4. **Добавить чеки в ruleset** `main`. У матричного job'а имя чека своё на каждый вариант:
   `PHP lint, analyse, test (8.3)` и `PHP lint, analyse, test (8.4)` — нужны оба.

## Что сделать один раз на packagist.org

1. **Войти через GitHub** (https://packagist.org/login/) тем аккаунтом, у которого есть доступ к
   организации `webx-ui`. Именно вход через GitHub, а не логин с паролем: тогда Packagist сам
   ставит вебхук при отправке пакета.

2. **Дать доступ к организации.** На экране авторизации GitHub рядом с `webx-ui` есть кнопка
   Grant. Если её пропустить — потом
   https://github.com/settings/connections/applications/ → Packagist → Organization access →
   Grant.

3. **Отправить пакет:** https://packagist.org/packages/submit, вставить
   `https://github.com/webx-ui/nested-set`, Check → Submit. Имя пакета Packagist возьмёт из
   `composer.json`.

   Первая же отправка **закрепляет вендора `webx-ui`** за аккаунтом — дальше никто чужой под ним
   опубликоваться не сможет. Сейчас вендор свободен, занять его стоит раньше, чем позже.

4. **Проверить автообновление.** На странице пакета не должно быть плашки «This package is not
   auto-updated». Если она есть — вебхук не встал, настроить руками, лучше сразу на уровне
   организации (один хук на все split-репозитории):

   GitHub → организация `webx-ui` → Settings → Webhooks → Add webhook
   - Payload URL: `https://packagist.org/api/github?username=<логин на packagist>`
   - Content type: `application/json`
   - Secret: API-токен со страницы https://packagist.org/profile/ (кнопка «Show API Token»)
   - Which events: Just the `push` event — теги приезжают тем же событием

5. **Включить 2FA** в профиле Packagist. Красть там, кроме самого аккаунта, нечего — токенов
   публикации не существует, — но вендор `webx-ui` стоит того.

6. Повторять шаг 3 для каждого нового пакета. Шаги 1–2, 4–5 — один раз навсегда.

## Первый релиз, по порядку

1. Смержить PR с инфраструктурой. Push в `main` запустит `php-split.yml` без тега и зальёт
   содержимое в `webx-ui/nested-set`. **Репозиторий к этому моменту должен существовать**, иначе
   job упадёт (создать и перезапустить).
2. Отправить пакет на Packagist (выше).
3. Выпустить версию:

   ```bash
   pnpm changeset      # выбрать @webx-ui/php, minor, описать одной строкой
   ```

   PR → merge → бот открывает «chore: version packages» → merge этого PR. Дальше всё само:
   тег `php-v0.1.0` в монорепо, `v0.1.0` в `webx-ui/nested-set`, версия на Packagist.

4. Проверить:

   ```bash
   composer show webx-ui/nested-set --all
   ```

   Смотреть надо именно так или на `https://repo.packagist.org/p2/webx-ui/<имя>.json` — это то,
   что читает Composer, и там версия появляется сразу. А `packagist.org/packages/<имя>.json`
   кешируется и отстаёт на десятки минут: выглядит как «релиз не доехал», хотя он уже доехал.

## Чем это отличается от npm

|                  | npm                      | Packagist                                   |
| ---------------- | ------------------------ | ------------------------------------------- |
| что публикуется  | артефакт через OIDC      | ничего: версия = тег в репо пакета          |
| секреты          | нет (Trusted Publishing) | GitHub App с правом записи в зеркала        |
| версии           | своя у каждого пакета    | одна на все php-пакеты                      |
| тег              | `@webx-ui/core@0.14.0`   | `php-v0.1.0` в монорепо, `v0.1.0` в зеркале |
| задержка реестра | ~5 минут после воркфлоу  | секунды, по вебхуку                         |

## Грабли

- **Тег в зеркале обязан выглядеть как версия.** `php-v0.1.0` Composer версией не считает,
  поэтому в split-репозиторий уезжает `v0.1.0`, а `php-v*` остаётся меткой в монорепо.
- **Пуш тега из workflow с `GITHUB_TOKEN` не запускает другие workflow.** Поэтому `release.yml`
  вызывает сплит напрямую через `uses:`, а не надеется на триггер по тегу.
- **Версию для тега надо читать из коммита, а не из рабочего дерева.** Когда changesets только
  открывает version-PR, он оставляет бамп в чекауте: шаг тега прочитал бы 0.1.0 и повесил тег
  на релиз, которого на `main` ещё нет. Отсюда `git show HEAD:php/package.json`.
- **Токен App отдаётся экшену как `x-access-token:<токен>`.** Экшен собирает
  `https://<токен>@github.com/...`, то есть кладёт токен в имя пользователя. Для PAT так можно,
  для installation-токена App — нет, он должен быть паролем; иначе git молча просит пароль и
  падает с `could not read Password`. Всплывает только на сплите, где реально есть что пушить.
- **Тег в зеркале ставится через API, а не входом `tag` у экшена сплита.** Если в самом пакете
  изменений нет — а у релиза, который двигает только общую версию, их обычно и нет, — экшен
  уходит в ветку «No files to change» и пушит тег из каталога без учётных данных:
  `could not read Password`.
- **Репозиторий-зеркало сплит не создаёт.** Нет репозитория — красный job. А вот пустой
  репозиторий воркфлоу починит сам: шаг «Seed an empty mirror» кладёт первый коммит, потому
  что `monorepo-split-github-action` в пустом клоне создаёт ветку локально и пушит ничего —
  `src refspec main does not match any`.
- **Имя репозитория = вторая часть имени пакета.** Матрица сплита собирается из листинга
  `php/packages/*/composer.json`, имя берётся оттуда: `webx-ui/module-seo` → репозиторий
  `module-seo`.
- **`export-ignore` в `.gitattributes` пакета** убирает `tests/` из zip-архива, который Composer
  скачивает с GitHub. В самом зеркале тесты остаются — это нормально.
- **Вендор на Packagist закрепляется за первым отправителем.**
- **`packagist.org/packages/<имя>.json` отстаёт от реальности.** Проверять публикацию по
  `repo.packagist.org/p2/<имя>.json` или `composer show`.
