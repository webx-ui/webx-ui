---
'@webx-ui/php': patch
---

The demo contacts belong to no country: `module-settings` seeds `+800` numbers, an address in "Sample City" with made-up coordinates and hours in `UTC`; `module-team` seeds people with neutral names; the examples in the panel's phone hints and the `theme-default` demo use `+800 1234 5678`. A theme's `demo/settings/settings.json` and `demo/team/team.json` now replace the modules' own demo, as `demo/media/` already did. Phone validation is unchanged: `+00…` is still refused.
