---
'@webx-ui/php': minor
'@webx-ui/module-admin': patch
'@webx-ui/module-audit': patch
'@webx-ui/module-auth': patch
'@webx-ui/module-banners': patch
'@webx-ui/module-blocks': patch
'@webx-ui/module-blog': patch
'@webx-ui/module-catalog': patch
'@webx-ui/module-catalog-collections': patch
'@webx-ui/module-catalog-feeds': patch
'@webx-ui/module-catalog-landings': patch
'@webx-ui/module-catalog-manticore': patch
'@webx-ui/module-catalog-properties': patch
'@webx-ui/module-configurator': patch
'@webx-ui/module-delivery': patch
'@webx-ui/module-events': patch
'@webx-ui/module-faq': patch
'@webx-ui/module-inbox': patch
'@webx-ui/module-menu': patch
'@webx-ui/module-notifications': patch
'@webx-ui/module-orders': patch
'@webx-ui/module-pages': patch
'@webx-ui/module-payments': patch
'@webx-ui/module-press': patch
'@webx-ui/module-recipes': patch
'@webx-ui/module-reviews': patch
'@webx-ui/module-seo': patch
'@webx-ui/module-services': patch
'@webx-ui/module-settings': patch
'@webx-ui/module-snippets': patch
'@webx-ui/module-tariffs': patch
'@webx-ui/module-tasks': patch
'@webx-ui/module-team': patch
'@webx-ui/module-users': patch
'@webx-ui/module-vacancies': patch
---

Покрытие справки панели включено: `AssertsHelp` и `HelpCoverageTest` в каждом пакете с экраном, патчем или разделом, `HelpPackagesCoverageTest`, `scripts/help-coverage.test.mjs`, команда `webx:help --check`, гайд `panel-help`. Всё, что ещё без текста, перечислено в `help-pending.json` и вычёркивается волнами наполнения. Пакеты регистрируют `resources/help`, у всех маршрутов панели есть `meta.help`, формы в панели списка передают ключ своей шапке. Кнопка появится, когда у экрана будет текст.
