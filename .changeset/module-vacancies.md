---
'@webx-ui/php': minor
---

`webx-ui/module-vacancies`: open positions of the organisation, each a page of fixed structure
under a prefix (`careers/…`) — where the work is (on site, remote, hybrid), the kinds of
employment, the salary in words and in numbers in a currency of the site's list, a description,
duties, requirements and what is offered — with schema.org `JobPosting`. A vacancy is closed by
hand or after its last day: it leaves the lists, keeps its page with a note, loses the posting and
says `noindex`. Flat categories without pages are the groups and the filter
(`?category=development`) of the index and of `vacancies()`. The application form is a form of
`module-inbox`, chosen in the vacancy. Draft, history, ordering by hand, "Duplicate", SEO.
`webx:setup` knows the module as `vacancies`, and `webx:doctor` checks `vacancies()`.

For an agent, eleven `vacancies_*` tools — list, get, create, update, duplicate, publish,
unpublish, close, reopen, delete, reorder — through the panel's own doors, `vacancy_categories_*`,
and `vacancies://catalog`. `webx:demo` seeds three categories and seven vacancies with days counted
from the moment of seeding, and with `module-inbox` a `job-application` form chosen in the open ones.

`webx-ui/module-inbox`: a form is a relation target, `inbox-form`, that anybody who edits
another section may pick — no permission of the inbox needed. A deleted form takes the choices
pointing at it along.

`webx-ui/module-seo`: the SEO card on the editor of a vacancy.
