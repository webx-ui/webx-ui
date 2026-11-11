---
'@webx-ui/php': minor
---

Test traits for a site module, which runs its tests on the site with every package loaded: `AssertsHelp::assertModuleScreensHaveHelp($folder, $languages = null)` checks only the screens registered from the module's folder (`ScreenRegistry::registeredFrom()`) against its `resources/help`, in `en` and the languages of the site's panel (`webx-localization.panel`) — a package's screen without help, or a language the panel does not speak, is not the module's fault. `WebxUi\Localization\Testing\AssertsTranslations::assertTranslationsMatch($lang, $languages)`: `en` and the listed languages are there, and every language present has the keys of `en`. The monorepo's help and translation tests are unchanged.
