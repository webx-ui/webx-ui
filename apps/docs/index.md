---
layout: home

hero:
  name: WebX UI
  text: Vue 3 design system for admin panels
  tagline: Tokens, components and a JSON renderer for Laravel-backed CMS interfaces.
  actions:
    - theme: brand
      text: Get started
      link: /guide/
    - theme: alt
      text: Components
      link: /components/button
    - theme: alt
      text: GitHub
      link: https://github.com/webx-ui/webx-ui

features:
  - title: Tokens first
    details: Every component is styled through --wx-* CSS variables. Light and dark themes come from one JSON source of truth.
  - title: No data fetching inside components
    details: Data arrives through props or a data adapter, changes leave as events. Tables accept Laravel's paginate() payload as is.
  - title: Own implementations
    details: Element Plus is used as a checklist, not a dependency. Headless logic comes from Reka UI where it makes sense.
  - title: JSON-driven screens
    details: '@webx-ui/schema fixes the contracts for rendering admin screens from JSON, backend-agnostic by design.'
---
