<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import {
  confirm,
  mustacheCompletions,
  mustacheProblems,
  useLocales,
  WxAlert,
  WxButton,
  WxCodeEditor,
  WxText,
  WxTooltip,
  type CodeEditorExposed,
  type LocalizedValue,
  type MustacheVariable,
  toast,
} from '@webx-ui/core'
import { useTranslate } from '@webx-ui/module-admin'
import { useChannel } from './context'
import { blankText, sampleOf, withSample } from './defaults'
import { useNotificationsMessages } from './i18n'
import { isHtmlLetter } from './render'
import { snippetOf, variablesOf } from './variables'

/**
 * `wx-notifications-template`: the body of a channel's message (§7) in every language of the site.
 *
 * `WxCodeEditor` with Mustache painted over HTML for Telegram and over text for a letter; `{{`
 * opens the event's variables, the fields of the list being looped over and the end of the section
 * that is open. Under it the same variables as buttons — the editor is the fast way, the buttons
 * are the way to find out what there is — and what is wrong with the template as it stands, by the
 * same rules the save refuses it with.
 *
 * The same field is the subject of a letter, one line (`single-line`, `part: subject`, no
 * `chips` under it): `{{` opens the same list there, and a variable clicked under the template
 * goes into whichever of the two the caret was in last.
 *
 * Under the template, when the event has one for this kind of channel, "Insert the default
 * template" puts the event's own message in — asking first if something is written already.
 *
 * The event and the kind of channel come from the form around it (`provideChannel`); without one
 * the field still edits a template and offers Mustache's syntax alone.
 */
defineOptions({ name: 'WxNotificationsTemplateField' })

const props = withDefaults(
  defineProps<{
    localized?: boolean
    disabled?: boolean
    minHeight?: string
    /** One line, for the subject. */
    singleLine?: boolean
    /** What of the message this is: the variables under the template write into the last one. */
    part?: string
    /** The variables and the default template under the field. */
    chips?: boolean
  }>(),
  {
    localized: false,
    disabled: undefined,
    minHeight: '200px',
    singleLine: false,
    part: 'template',
    chips: true,
  },
)

const model = defineModel<string | LocalizedValue | null>({ default: '' })

useNotificationsMessages()

const t = useTranslate('webx-notifications')
const channel = useChannel()
const locales = useLocales()
const editor = ref<CodeEditorExposed | null>(null)

const variables = computed<MustacheVariable[]>(() => variablesOf(channel.event.value))

const completions = computed(() =>
  mustacheCompletions(variables.value, {
    variable: t('panel.hint-variable'),
    list: t('panel.hint-list'),
    field: t('panel.hint-field'),
    section: t('panel.hint-section'),
    inverted: t('panel.hint-inverted'),
    close: t('panel.hint-close'),
    comment: t('panel.hint-comment'),
    example: t('panel.hint-example'),
  }),
)

/** The language on screen: what the problems below are about. */
const text = computed(() => {
  const value = model.value

  if (value === null || value === undefined) return ''
  if (typeof value === 'string') return value

  const active = locales.active.value || locales.list.value[0]?.code || ''

  return value[active] ?? ''
})

/* Telegram's HTML, a letter's HTML when it starts with a tag, else text. */
const language = computed(() => {
  if (props.singleLine) return 'mustache'

  const provider = channel.provider.value?.key ?? ''

  return provider === 'telegram' || isHtmlLetter(provider, text.value)
    ? 'mustache-html'
    : 'mustache'
})

/*
 * Names are judged only on a channel of an event: one for any event may say anything, and the
 * server only warns about it on save.
 */
const problems = computed(() =>
  mustacheProblems(text.value, channel.event.value === null ? undefined : variables.value),
)

const said = computed(() => {
  const found = problems.value

  return [
    ...found.unclosed.map((name) => t('panel.problem-unclosed', { name })),
    ...found.stray.map((name) => t('panel.problem-stray', { name })),
    ...found.refused.map((tag) => t('panel.problem-refused', { tag })),
    ...(found.broken ? [t('panel.problem-broken')] : []),
    ...(found.unknown.length > 0
      ? [
          t('panel.problem-unknown', {
            names: found.unknown.map((name) => `{{${name}}}`).join(', '),
          }),
        ]
      : []),
  ]
})

function example(variable: MustacheVariable): string {
  if ((variable.fields?.length ?? 0) > 0) {
    return t('panel.variable-list', {
      fields: (variable.fields ?? []).map((field) => field.name).join(', '),
    })
  }

  const value = variable.example

  return typeof value === 'string' || typeof value === 'number' ? String(value) : ''
}

/** Into this editor where the caret is, and the caret after it. */
function insert(snippet: string): void {
  const view = editor.value?.view

  if (!view) return

  const range = view.state.selection.main

  view.dispatch({
    changes: { from: range.from, to: range.to, insert: snippet },
    selection: { anchor: range.from + snippet.length },
    scrollIntoView: true,
  })
  view.focus()
}

onMounted(() => channel.inserters.set(props.part, insert))
onBeforeUnmount(() => {
  if (channel.inserters.get(props.part) === insert) channel.inserters.delete(props.part)
})

const sample = computed(() => sampleOf(channel.event.value, channel.provider.value?.key))

/** The event's default template in place of the message; asked first if it would replace text. */
async function insertDefault(): Promise<void> {
  const found = sample.value

  if (found === null) return

  const values = channel.values.value
  const subject = channel.provider.value?.subject ?? false
  const written = !blankText(values.template) || (subject && !blankText(values.subject))

  if (written) {
    const agreed = await confirm({
      title: t('panel.default-title'),
      message: t('panel.default-text'),
      confirmText: t('panel.default-replace'),
      cancelText: t('panel.cancel'),
    })

    if (!agreed) return
  }

  channel.values.value = withSample(channel.values.value, found, subject)
  toast.info(t('panel.default-inserted'))
}
</script>

<template>
  <div class="wx-notifications-template">
    <wx-code-editor
      ref="editor"
      v-model="model"
      :language="language"
      :completions="completions"
      :localized="props.localized"
      :disabled="props.disabled"
      :line-numbers="false"
      :line-wrapping="!props.singleLine"
      :single-line="props.singleLine"
      :min-height="props.singleLine ? undefined : props.minHeight"
      @focus="channel.focused.value = props.part"
    />

    <wx-alert v-for="line in said" :key="line" type="warning" variant="soft" :description="line" />

    <div v-if="props.chips" class="wx-notifications-template__variables">
      <wx-text size="sm" weight="medium">{{ t('panel.variables') }}</wx-text>

      <wx-text v-if="variables.length === 0" size="sm" tone="muted">
        {{ t('panel.variables-none') }}
      </wx-text>

      <template v-else>
        <wx-text size="sm" tone="muted">{{ t('panel.variables-help') }}</wx-text>

        <div class="wx-notifications-template__chips">
          <wx-tooltip
            v-for="variable in variables"
            :key="variable.name"
            :content="[variable.label, example(variable)].filter(Boolean).join(' — ')"
          >
            <wx-button
              size="sm"
              variant="outline"
              class="wx-notifications-template__chip"
              :disabled="props.disabled"
              @click="channel.insert(snippetOf(variable))"
            >
              <span class="wx-notifications-template__name">
                {{ (variable.fields?.length ?? 0) > 0 ? `#${variable.name}` : variable.name }}
              </span>
            </wx-button>
          </wx-tooltip>
        </div>
      </template>

      <div v-if="sample" class="wx-notifications-template__default">
        <wx-button
          size="sm"
          variant="text"
          icon="file-text"
          :disabled="props.disabled"
          @click="insertDefault"
        >
          {{ t('panel.default-insert') }}
        </wx-button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wx-notifications-template {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-8);
  min-width: 0;
}

.wx-notifications-template__variables {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}

.wx-notifications-template__chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-6);
  margin-top: var(--wx-space-4);
}

.wx-notifications-template__default {
  margin-top: var(--wx-space-4);
}

.wx-notifications-template__name {
  font-family: var(--wx-font-family-mono);
}
</style>
