---
'@webx-ui/php': minor
---

The FAQ for agents and for a new site. `faq_list`, `faq_get`, `faq_create`, `faq_update`,
`faq_delete` and `faq_reorder` go through the same list, form and order code as the panel; a
question is named by its id or its anchor, and every row says the languages a reader sees it in.
`faq_create` writes the question, its categories and the project's fields in one transaction, so a
refusal leaves nothing behind, and a category that does not exist is refused rather than dropped.
`faq://catalog` lists every category with its questions in its own order, unpublished ones marked,
and the questions in no category at the end. The categories get the shared `faq_categories_*`
tools, which for categories without addresses (`prefix: null`) no longer take or answer with a
slug and find a category by its title. Every module's `*_categories_create` now answers with the
category as stored: a new visible category was reported as hidden.

`webx:demo` seeds three categories and ten questions, installs the offered FAQ block type when the
site lacks it, puts a page `/faq` with every category and the filter on a site with
`module-pages`, and adds "Questions about payment" to a demo service on a site with
`module-services`.

The agent's catalogue of field types now describes `wx-collection`.
