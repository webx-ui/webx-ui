---
'@webx-ui/module-auth': minor
---

The sign-in card and the user menu speak the panel's language. Every string is still a prop and
a prop given still wins; what changed is the default, which now comes from the dictionary rather
than from English hardcoded in the component.

The user menu gained a language picker — it belongs within reach rather than three clicks into a
section somebody cannot read — and `auth.setLocale()` stores the choice against the
administrator, so it follows them to the next machine.

A throttle notice takes `:seconds` rather than `{seconds}`, matching the way the same string is
written in the `lang` file it now comes from.
