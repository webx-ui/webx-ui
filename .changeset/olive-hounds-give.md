---
'@webx-ui/php': patch
---

Fewer words in the article editor's bar

`webx-blog::article.save` is "Save" rather than "Save draft" in all ten languages: the button
beside it is the publication, so there is nothing left to tell apart. The three lines the bar
used to build its sentence from — `live-never`, `live-edited`, `live-off` — go with the sentence;
what is left of it is the day, and the day is said by `live-since` and `live-scheduled` under the
article's name.
