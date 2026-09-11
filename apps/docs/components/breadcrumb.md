<script setup>
import BreadcrumbDemo from '../components/demos/BreadcrumbDemo.vue'
</script>

# Breadcrumb

`WxBreadcrumb` is the trail above the title of a screen: where this record sits, and the way back
up. It renders a `<nav>` around an ordered list, because a trail read out of order is not a trail.

<BreadcrumbDemo />

## Usage

```vue
<template>
  <wx-breadcrumb>
    <wx-breadcrumb-item href="/admin" icon="home">Dashboard</wx-breadcrumb-item>
    <wx-breadcrumb-item href="/admin/pages">Pages</wx-breadcrumb-item>
    <wx-breadcrumb-item>About us</wx-breadcrumb-item>
  </wx-breadcrumb>
</template>
```

The last crumb links nowhere, and that is how it is recognised: a crumb with no `href` and no `as`
is the page you are on, and gets `aria-current="page"`. Pass `current` to say so explicitly — for
a trail whose last crumb is still a link.

## Separators

A character by default, an icon when one is named:

```vue
<template>
  <wx-breadcrumb separator="›">…</wx-breadcrumb>
  <wx-breadcrumb separator-icon="chevron-right">…</wx-breadcrumb>
</template>
```

## Routing

Like [Menu](/components/menu), crumbs render through whatever you give `as`:

```vue
<template>
  <wx-breadcrumb-item :as="RouterLink" to="/admin/pages">Pages</wx-breadcrumb-item>
</template>
```

Building the trail from the route usually beats writing it out by hand:

```vue
<template>
  <wx-breadcrumb separator-icon="chevron-right">
    <wx-breadcrumb-item
      v-for="crumb in crumbs"
      :key="crumb.to"
      :as="RouterLink"
      :to="crumb.to"
      :current="crumb.to === route.path"
    >
      {{ crumb.title }}
    </wx-breadcrumb-item>
  </wx-breadcrumb>
</template>
```

## Breadcrumb

| Prop            | Type           | Default        | Description                          |
| --------------- | -------------- | -------------- | ------------------------------------ |
| `separator`     | `string`       | `'/'`          | Character drawn between items        |
| `separatorIcon` | `IconName`     | —              | Icon drawn instead of that character |
| `size`          | `'sm' \| 'md'` | `'md'`         | Text size of the trail               |
| `label`         | `string`       | `'Breadcrumb'` | Accessible name of the landmark      |

## BreadcrumbItem

| Prop      | Type                  | Default   | Description                             |
| --------- | --------------------- | --------- | --------------------------------------- |
| `href`    | `string`              | —         | Where the crumb points                  |
| `target`  | `string`              | —         | Link target; `_blank` gets a safe `rel` |
| `as`      | `string \| Component` | —         | Renders through another component       |
| `icon`    | `IconName`            | —         | Icon before the label                   |
| `current` | `boolean`             | by `href` | Marks the crumb as the page you are on  |

**Events:** `click` (`MouseEvent`).

**Slots:** `default` — the label.
