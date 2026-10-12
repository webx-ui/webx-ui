---
'@webx-ui/php': minor
---

Words of the layout (THEMES §15.2, TH4.3). `webx-ui/theme-default`: **every word of the shell and of the six blocks is `theme::`** in ten languages — "Skip to content" (`shell.skip`) and the footer navigation's `aria-label` (`shell.footer`) were the last ones written into a template and printed in English on every language of the site; the manifest lists its **`locales`** (en ru uk de fr es it pl pt tr). `webx-ui/themes`: **`webx:theme:check` compares the words of every layer** — each language a layer lists in `locales` has the keys of its `en` in `lang/<locale>/theme.php`, no more and no fewer, with a line per missing file, missing keys, extra keys and a file that is not a dictionary (`Checks\LineCheck`); a language a layer does not list is not compared, so a local theme may still rename one word in it.
