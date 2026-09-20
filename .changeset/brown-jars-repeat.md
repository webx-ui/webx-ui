---
'@webx-ui/module-blog': minor
'@webx-ui/php': minor
---

An article can be taken off the site from its editor, and its tags stand on one line

**Off the site, from the publication card.** Taking an article off the site was a line in the
`···` of a row of the list and nowhere else — so an editor looking at the article, on the tab
where its day and its author are decided, had to go back to the list to pull it. Now
`wx-article-unpublish` sits under the date, where the rest of the publication is settled. It is
offered only while there is something to take off — a draft was never there, and one already
off has nowhere further to go; the way back is "Publish", which stays in the bar. It asks
first, because this is the one thing on that tab visitors see happen, and the question names
what survives: the draft, the history and the rubrics all stay, and publishing puts the article
back exactly where it was. A scheduled article gets its own sentence — it never went out, and
the day it was set for will pass without it.

**Tags.** A chip carried a `WxAction` in its slot, and an icon button of the panel is thirty
pixels tall inside a badge whose words are fifteen: the chip grew to fit the button, the word
sat three pixels below the cross it stood beside, and the air to the left of the word was half
the air to its right. `WxBadge` has had `closable` all along, sized to the words — measured, the
chip is 22.6 px instead of 37.6 and the drift is zero.

**Rubrics.** The "main" badge stood against the name of the first rubric with nothing between
them, because the cell a row's content goes into is a block and the `gap` meant for it was
never applied — and neither was the clipping on the name, which had been written for a flex
parent that was not there. The slot now makes a line of its own contents: eight pixels between
the name and the badge, and a rubric with a long name is cut with an ellipsis rather than
pushing the badge to the far end of the row.
