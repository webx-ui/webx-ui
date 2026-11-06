---
'@webx-ui/module-admin': minor
'@webx-ui/module-media': patch
'@webx-ui/php': minor
---

Справка панели в интерфейсе: кнопка «?» в шапке экрана открывает немодальную боковую панель `WxHelpPanel` со справкой экрана, форма рядом остаётся доступной. Новое: `useHelp()`, проп `help` у `WxScreenHead` и `WxListScreen`, `meta.help` у маршрутов, `loadHelp` в контексте панели, слова `webx-admin::help.*` на 10 языках. Кнопка видна, только если ключ есть в манифесте. Стили прозы `.wx-help` вынесены в общий файл.
