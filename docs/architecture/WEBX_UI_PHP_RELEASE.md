# WebX UI — как php-пакеты попадают на Packagist

Composer ставит пакет из его собственного репозитория: подпапку монорепо он видеть не умеет.
Поэтому каждый пакет из `php/packages/` зеркалится в отдельный публичный репозиторий
`github.com/webx-ui/<имя>`, и уже туда приезжает тег, который Packagist показывает как версию.

Публикации как таковой нет. **Версия существует ровно тогда, когда существует тег** в
репозитории пакета. Секрет для публикации не нужен — нужен только токен на запись в
split-репозитории.

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

   Без README и лицензии при создании: всё содержимое привезёт сплит.

2. **Токен на запись в эти репозитории.** Fine-grained PAT,
   https://github.com/settings/personal-access-tokens/new:
   - Resource owner — организация `webx-ui`
   - Repository access — Only select repositories → все split-репозитории
     (сам `webx-ui/webx-ui` не нужен)
   - Repository permissions → **Contents: Read and write** (`Metadata: Read-only` GitHub
     добавит сам, он обязательный; больше ничего не нужно)
   - срок максимальный, напоминание о продлении в календарь

   Если организация требует одобрения fine-grained токенов — одобрить в
   Settings → Personal access tokens → Pending requests.

   Доступ выбран точечно, поэтому **у каждого нового зеркала надо дописать доступ в этот же
   токен** — иначе сплит для него упадёт с 403.

3. **Положить токен в секрет репозитория:**

   ```bash
   gh secret set PHP_SPLIT_TOKEN --repo webx-ui/webx-ui
   ```

   Команда спросит значение — токен не надо передавать ни аргументом, ни через файл.

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

## Чем это отличается от npm

|                  | npm                      | Packagist                                   |
| ---------------- | ------------------------ | ------------------------------------------- |
| что публикуется  | артефакт через OIDC      | ничего: версия = тег в репо пакета          |
| секреты          | нет (Trusted Publishing) | `PHP_SPLIT_TOKEN` на запись в зеркала       |
| версии           | своя у каждого пакета    | одна на все php-пакеты                      |
| тег              | `@webx-ui/core@0.14.0`   | `php-v0.1.0` в монорепо, `v0.1.0` в зеркале |
| задержка реестра | ~5 минут после воркфлоу  | секунды, по вебхуку                         |

## Грабли

- **Тег в зеркале обязан выглядеть как версия.** `php-v0.1.0` Composer версией не считает,
  поэтому в split-репозиторий уезжает `v0.1.0`, а `php-v*` остаётся меткой в монорепо.
- **Пуш тега из workflow с `GITHUB_TOKEN` не запускает другие workflow.** Поэтому `release.yml`
  вызывает сплит напрямую через `uses:`, а не надеется на триггер по тегу.
- **Репозиторий-зеркало сплит не создаёт.** Нет репозитория — красный job.
- **Имя репозитория = вторая часть имени пакета.** Матрица сплита собирается из листинга
  `php/packages/*/composer.json`, имя берётся оттуда: `webx-ui/module-seo` → репозиторий
  `module-seo`.
- **`export-ignore` в `.gitattributes` пакета** убирает `tests/` из zip-архива, который Composer
  скачивает с GitHub. В самом зеркале тесты остаются — это нормально.
- **Вендор на Packagist закрепляется за первым отправителем.**
