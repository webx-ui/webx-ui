---
'@webx-ui/core': minor
---

`WxCodeEditor` has `language-completions`: the suggestions each language ships with, switched on
without a list of your own. HTML — tags, attributes and their values, the closing tag written on
`>`; JavaScript, alone or in a `<script>` — keywords, snippets (`if`, `for`, `function`, `try`…),
the names the code declares and the globals of the page after a dot (`document.`, `console.`,
`localStorage.`); CSS, alone or in a `<style>` — properties and values. An open `<script` or
`<style` at the very end of the document is still HTML's: it offers `src`, `async`, `defer` and is
closed on `>`. `Esc` closes the list; Enter without one is a line break.
