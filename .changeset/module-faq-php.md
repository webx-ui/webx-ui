---
'@webx-ui/php': minor
---

New package `webx-ui/module-faq`: questions and answers with flat categories. A question has no
page of its own; it reaches the site in the FAQ block the module offers
(`webx:blocks:offered --install --module=faq`), on any page. The block shows every category with a
filter, or the categories the editor picks, as an accordion that works without JavaScript, and
opens the question a `#anchor` link points at. A question is shown in a language only when both
the question and the answer are written in it. The anchor is made once from the question and never
changes. One `FAQPage` per page through `module-seo`, on by default only when the block shows all
categories. In the panel: `faq.form` and `faq.category-form`, two orders, the bin, and fields of
the project in `extra`. `webx:setup` offers the module.
