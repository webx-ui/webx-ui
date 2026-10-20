---
'@webx-ui/php': minor
---

Default templates of notification events. `EventDefinition::$sample` is the event's default
template by provider: one text, or a function of the language that answers null where the module
has no words; a new channel of the event starts with it in every language it has (the main one
always), `default_template: true` on a save puts it back, and the MCP tool
`notifications_default_template` shows it with what it renders to. `inbox.submission` brings an
HTML letter (the answers as a table, the files, the button to the panel, the page, a note that a
reply goes to the visitor — neutral, no colour or name of a site), a Telegram message and a text,
in all ten languages of the inbox (`SubmissionTemplates`).

A letter's template that starts with a tag is HTML: its tags and attributes are checked on save
(tables, paragraphs, lists, links and inline styles; no script, form or handler), values are
escaped into it with their line breaks kept, and the plain part is read out of it. A template that
starts with text — every channel saved before — is sent exactly as it was.
