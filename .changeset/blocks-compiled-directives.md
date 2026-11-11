---
'@webx-ui/php': patch
---

Blocks: a compiled block template is named by its template and by the directives Blade knows. Blade leaves a directive it does not know in the output as text, so a template compiled while one was missing — code a step behind the database during a deploy, a branch switched under a running site — printed `@webxReveal(…)` into the page, and kept printing it after the code caught up. Now a different set of directives is a different file, compiled again.
