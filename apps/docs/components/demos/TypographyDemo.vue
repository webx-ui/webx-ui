<script setup lang="ts">
import { WxHeading, WxLink, WxProse, WxText } from '@webx-ui/core'

const article = `
  <h2>Publishing a page</h2>
  <p>
    The editor stores <strong>HTML</strong>, and <code>WxProse</code> is what gives that HTML the
    typography of the design system — headings, lists, tables and images included.
  </p>
  <ul>
    <li>Every colour and size comes from a token.</li>
    <li>A pasted table scrolls inside its own box.</li>
    <li>Images never break out of the column.</li>
  </ul>
  <blockquote>A quote keeps its rule on the left and its muted colour.</blockquote>
  <table>
    <caption>Sections and their editors</caption>
    <thead><tr><th>Section</th><th>Editor</th><th>Updated</th></tr></thead>
    <tbody>
      <tr><td>News</td><td>Maria</td><td>12.03.2026</td></tr>
      <tr><td>Team</td><td>Alexey</td><td>28.02.2026</td></tr>
    </tbody>
  </table>
`
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <div>
      <span class="wx-demo__label">Headings</span>
      <div class="wx-demo__stack">
        <wx-heading :level="1">Page title</wx-heading>
        <wx-heading :level="2">Section</wx-heading>
        <wx-heading :level="3">Subsection</wx-heading>
        <wx-heading :level="2" size="md" tone="muted">A level 2 that has to look small</wx-heading>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Text</span>
      <div class="wx-demo__stack">
        <wx-text as="p">Body text, the default size and weight.</wx-text>
        <wx-text as="p" size="sm" tone="muted">A hint under a field, muted and small.</wx-text>
        <wx-text as="p" weight="semibold" tone="danger">Something went wrong.</wx-text>
        <wx-text mono>ORD-2026-000431</wx-text>
        <wx-text as="p" :truncate="2" style="max-width: 320px">
          Clamped to two lines: it is a long established fact that a reader will be distracted by
          the readable content of a page when looking at its layout, and this sentence keeps going
          until it proves the point.
        </wx-text>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Links</span>
      <div class="wx-demo__row">
        <wx-link href="#typography">Default</wx-link>
        <wx-link href="#typography" type="danger">Danger</wx-link>
        <wx-link href="#typography" type="muted" underline="never">Muted, no underline</wx-link>
        <wx-link href="https://vuejs.org" external>Vue docs</wx-link>
        <wx-link href="#typography" disabled>Disabled</wx-link>
      </div>
    </div>

    <div>
      <span class="wx-demo__label">Prose — HTML from the editor</span>
      <wx-prose :html="article" />
    </div>
  </div>
</template>
