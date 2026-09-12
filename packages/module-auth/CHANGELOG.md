# @webx-ui/module-auth

## 0.2.0

### Minor Changes

- 9c0a762: The sign-in card and the user menu speak the panel's language. Every string is still a prop and
  a prop given still wins; what changed is the default, which now comes from the dictionary rather
  than from English hardcoded in the component.

  The user menu gained a language picker — it belongs within reach rather than three clicks into a
  section somebody cannot read — and `auth.setLocale()` stores the choice against the
  administrator, so it follows them to the next machine.

  A throttle notice takes `:seconds` rather than `{seconds}`, matching the way the same string is
  written in the `lang` file it now comes from.

### Patch Changes

- Updated dependencies [9c0a762]
  - @webx-ui/admin@0.2.0

## 0.1.0

### Minor Changes

- 266f47d: The password reveal is a bare icon at the end of the field. It was a button with a surface of
  its own, and a second box inside an input reads as a second control — it took up a third of the
  field to say something the icon says on its own.

### Patch Changes

- Updated dependencies [752adf0]
- Updated dependencies [266f47d]
  - @webx-ui/core@0.14.2
  - @webx-ui/admin@0.1.0
