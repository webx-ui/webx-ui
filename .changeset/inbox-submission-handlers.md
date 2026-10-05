---
'@webx-ui/php': minor
'@webx-ui/module-inbox': minor
---

A site can act on a stored submission without forking `module-inbox`. `SubmissionStored` is
dispatched once the submission, its answers and its files are written (after commit), for the
site's form and one typed in by hand, never for what the antispam stopped. For the common case,
`handlers` in `config/webx-inbox.php` names `SubmissionHandler` classes by form slug or `*`: each
runs as a queued job of its own, and its outcome shows in the submission's log in the panel and
in `inbox_get` as "Handed to …" or "… failed: reason". A failing handler never reaches the
visitor or stops the others.
