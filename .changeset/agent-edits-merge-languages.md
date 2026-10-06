---
'@webx-ui/php': patch
---

An agent's `*_update` (pages, blog, events, services, recipes, vacancies, FAQ, press, reviews, tariffs, team, banners) and `settings_set` change a translated field only in the languages they name: `{"slug": {"de": …}}` leaves the other languages alone, `null` empties one, a plain string is the main language. A language the site is not published in is refused — dry run included — instead of being dropped and emptying the field (`ScreenValues::patch`).
