---
'@webx-ui/php': minor
---

`webx-ui/module-team`: the people of the organisation — a photo, a name, a job title, a short
text, social links from a list the site configures, and the services each of them provides when
the site has `module-services`. No page of their own and no categories: they reach the site in the
offered block **Team** (a grid, a slider or a list — "who does this" on a service's page is the
list with "only related to the current one") and through `team()` in a template. A person is a
relation target, `team-member`, for other modules. `webx:setup` knows the module as `team`, and
`webx:doctor` checks `team()`.
