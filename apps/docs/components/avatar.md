<script setup>
import AvatarDemo from '../components/demos/AvatarDemo.vue'
</script>

# Avatar

`WxAvatar` is a person in a list: a picture where there is one, initials where there is not.
`WxAvatarGroup` stacks a few of them and counts the rest.

<AvatarDemo />

## Usage

```vue
<template>
  <wx-avatar :src="user.avatar_url" :name="user.name" />
</template>
```

A `name` is all it needs. It supplies the initials, the `alt` text of the picture, and the colour.

## Initials, and where they come from

Two letters: the first of the first word and the first of the last, so `Ada Lovelace` is `AL` and
`Анна Марія Роут` is `АР`. A single word gives its first two — `Nova` is `NO` rather than a lonely
`N`, which reads as a mistake.

Pass anything else in the default slot and it wins:

```vue
<wx-avatar name="Ada Lovelace">7</wx-avatar>
```

## The colour is the name

`tone` is `auto` by default, which picks a tint from the name itself. The same person is therefore
the same colour on every screen — in the table, in the drawer, in the comment thread — and a list
of twenty becomes scannable without anybody choosing twenty colours.

It is a hash, so it is stable across reloads and across machines, and it is one of six tints from
the palette rather than an arbitrary hue: a hundred users still look like this design system.

Name a tone to override it, or `neutral` to opt out entirely:

```vue
<wx-avatar name="Ada Lovelace" tone="neutral" />
```

## A picture that is still arriving

The initials are always in the markup and the picture lies on top of them. While it loads, the
picture is an empty box and the initials show through; the moment it lands, it covers them. So
there is no flash of one followed by the other, and nothing in the layout moves.

A picture that fails is dropped and the initials stay. `@error` fires once if you want to know.

## Groups

```vue
<template>
  <wx-avatar-group :max="3">
    <wx-avatar v-for="member in team" :key="member.id" :name="member.name" :src="member.avatar" />
  </wx-avatar-group>
</template>
```

The ones past `max` are **not rendered** — they become a `+N` chip. Eight hundred people in a room
cost three avatars and a number.

## Avatar

| Prop    | Type                                                                               | Default    | Description                              |
| ------- | ---------------------------------------------------------------------------------- | ---------- | ---------------------------------------- |
| `src`   | `string`                                                                           | —          | The picture                              |
| `alt`   | `string`                                                                           | `name`     | Alternative text                         |
| `name`  | `string`                                                                           | —          | Initials, title and — with `auto` — tone |
| `icon`  | `IconName`                                                                         | —          | Shown when there is no name              |
| `size`  | `'xs' \| 'sm' \| 'md' \| 'lg' \| 'xl'`                                             | `'md'`     | 20 / 26 / 32 / 40 / 56 px                |
| `shape` | `'circle' \| 'square'`                                                             | `'circle'` | Shape of the box                         |
| `tone`  | `'auto' \| 'neutral' \| 'primary' \| 'success' \| 'warning' \| 'danger' \| 'info'` | `'auto'`   | Colour of the fallback                   |
| `fit`   | `'cover' \| 'contain' \| 'fill' \| 'none' \| 'scale-down'`                         | `'cover'`  | How the picture fills its box            |

**Events:** `error` (`Event`).

**Slots:** `default` — replaces the initials.

## AvatarGroup

| Prop      | Type                   | Default    | Description                                |
| --------- | ---------------------- | ---------- | ------------------------------------------ |
| `max`     | `number`               | `0`        | How many to show; `0` shows every one      |
| `size`    | `AvatarSize`           | `'md'`     | Size for the group                         |
| `shape`   | `'circle' \| 'square'` | `'circle'` | Shape of the counting chip                 |
| `overlap` | `number`               | `0.3`      | How far each slides over the one before it |

## Accessibility

The initials are decoration: a screen reader reads the name beside the avatar, not the two letters
inside it. Give the picture an `alt` only where the avatar stands alone — in a row that already
says `Ada Lovelace`, `alt=""` is the honest answer, and passing `name` as the alt would have it
read out twice.
