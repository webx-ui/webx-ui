<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  mustacheCompletions,
  mustacheProblems,
  WxAlert,
  WxCodeEditor,
  type MustacheVariable,
} from '@webx-ui/core'

/*
 * A notification template: type `{{` for the variables, `{{#` for a section, and inside
 * `{{#items}}` the fields of a line come first. `{{/` closes the section open nearest.
 */
const variables: MustacheVariable[] = [
  { name: 'form', label: 'The name of the form', example: 'Contact us' },
  { name: 'name', label: 'Who wrote', example: 'Alex Doe' },
  { name: 'note', label: 'What they added, if anything', example: 'Call after six' },
  {
    name: 'items',
    label: 'The fields of the submission',
    fields: [
      { name: 'label', label: 'The label of the field' },
      { name: 'value', label: 'What was typed into it' },
    ],
  },
]

/* The subject is one line: the same suggestions, and Enter never breaks it. */
const subject = ref('New submission: {{form}}')

const template = ref(`<b>{{form}}</b> from {{name}}
{{#items}}
{{label}}: {{value}}
{{/items}}
{{#note}}<i>{{note}}</i>{{/note}}`)

const completions = mustacheCompletions(variables)
const problems = computed(() => mustacheProblems(template.value, variables))

const warning = computed(() => {
  const found = problems.value
  const lines = [
    ...found.unclosed.map((name) => `{{#${name}}} is not closed.`),
    ...found.stray.map((name) => `{{/${name}}} closes nothing.`),
    ...found.unknown.map((name) => `{{${name}}} is not a variable here.`),
    ...found.refused.map((tag) => `${tag} is not allowed.`),
  ]

  return lines.join(' ')
})
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <wx-code-editor
      v-model="subject"
      language="mustache"
      :completions="completions"
      :line-numbers="false"
      single-line
      aria-label="Subject"
    />
    <wx-code-editor
      v-model="template"
      language="mustache-html"
      :completions="completions"
      :line-numbers="false"
      line-wrapping
      min-height="140px"
      aria-label="Template"
    />
    <wx-alert v-if="warning" type="warning" :description="warning" />
  </div>
</template>
