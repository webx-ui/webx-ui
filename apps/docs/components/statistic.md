<script setup>
import StatisticDemo from '../components/demos/StatisticDemo.vue'
</script>

# Statistic

`WxStatistic` is one number with its caption — the row of figures at the top of a dashboard.
`WxCountdown` is the same block counting down to a moment.

<StatisticDemo />

## Usage

```vue
<template>
  <wx-statistic title="Daily active users" :value="268500" />
  <wx-statistic title="Revenue" :value="172000" :precision="2" prefix="₴" />
  <wx-statistic title="Ratio of men to women" value="138/100" />
</template>
```

Numbers are grouped with `Intl.NumberFormat`, so `268500` reads as `268,500` or `268 500` depending
on the locale — an admin panel that ships in several languages should not have to decide. Pin it
with `locale` when the figure must look the same everywhere, or turn the grouping off entirely.

A string value is printed as given: a ratio, a range, anything already formatted upstream.

For full control, `formatter` wins over everything:

```vue
<template>
  <wx-statistic
    title="Conversion"
    :value="0.734"
    :formatter="(v) => `${(Number(v) * 100).toFixed(1)}%`"
  />
</template>
```

## Layout

The block is a column: caption, number, then whatever the default slot holds — a trend line, a
comparison, a link to the report. A row of them is a grid on the page, not a prop:

```vue
<template>
  <div
    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 24px"
  >
    <wx-statistic title="Orders" :value="431" />
    <wx-statistic title="Revenue" :value="172000" prefix="₴" tone="success" />
  </div>
</template>
```

Digits are tabular, so a number that changes does not make the row jump.

## Statistic props

| Prop        | Type                                        | Default    | Description                           |
| ----------- | ------------------------------------------- | ---------- | ------------------------------------- |
| `value`     | `number \| string`                          | —          | The figure; a string is printed as-is |
| `title`     | `string`                                    | —          | Caption above the number              |
| `precision` | `number`                                    | —          | Digits after the decimal point        |
| `locale`    | `string`                                    | browser's  | Grouping and decimal mark             |
| `grouping`  | `boolean`                                   | `true`     | Thousands grouping                    |
| `prefix`    | `string`                                    | —          | Before the number                     |
| `suffix`    | `string`                                    | —          | After the number                      |
| `formatter` | `(value) => string`                         | —          | Full control over the printed value   |
| `size`      | `'sm' \| 'md' \| 'lg'`                      | `'md'`     | Size of the number                    |
| `tone`      | `TextTone`                                  | `'strong'` | Colour of the number                  |
| `align`     | `'start' \| 'center' \| 'end' \| 'justify'` | —          | Alignment                             |

**Slots:** `title`, `prefix`, `value`, `suffix`, `default` (a footnote under the number).

## Countdown

```vue
<script setup lang="ts">
const deadline = Date.now() + 7 * 3600_000
</script>

<template>
  <wx-countdown title="Start to grab" :value="deadline" @finish="onFinish" />
  <wx-countdown :value="'2026-10-01T00:00:00Z'" format="DD days HH:mm:ss" />
</template>
```

`value` is the moment being counted down to: a timestamp, a `Date`, or anything `Date` parses.
Moving it restarts the clock; `finish` fires once, at zero; the timer is cleared when the component
goes away.

`format` is built from `DD`, `HH`, `mm`, `ss` and `SSS`, and only the tokens present consume time —
`mm:ss` on two hours prints `120:00` rather than quietly dropping the hours. Anything else in the
pattern is copied through, so mind a literal word that contains a token.

| Prop       | Type                           | Default      | Description                      |
| ---------- | ------------------------------ | ------------ | -------------------------------- |
| `value`    | `number \| string \| Date`     | —            | The moment being counted down to |
| `format`   | `string`                       | `'HH:mm:ss'` | `DD`, `HH`, `mm`, `ss`, `SSS`    |
| `interval` | `number`                       | `1000`       | Refresh rate in milliseconds     |
| `title`    | `string`                       | —            | Caption                          |
| `prefix`   | `string`                       | —            | Before the clock                 |
| `suffix`   | `string`                       | —            | After the clock                  |
| `size`     | `'sm' \| 'md' \| 'lg'`         | `'md'`       | Size                             |
| `tone`     | `TextTone`                     | `'strong'`   | Colour                           |
| `align`    | `'start' \| 'center' \| 'end'` | —            | Alignment                        |

**Events:** `change` (milliseconds left, on every tick), `finish`.

**Slots:** the statistic's, plus `value` scoped with `{ remaining, text }`.

The formatter is exported on its own, for a countdown rendered somewhere else:

```ts
import { formatCountdown } from '@webx-ui/core'

formatCountdown(90_000, 'mm:ss') // '01:30'
```
