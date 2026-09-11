<script setup lang="ts">
import { ref, watch } from 'vue'
import {
  WxAccordion,
  WxAccordionItem,
  WxAction,
  WxButton,
  WxForm,
  WxFormItem,
  WxInput,
  WxPopover,
} from '@webx-ui/core'

interface Question {
  id: string
  title: string
  subtitle?: string
  answer: string
}

const questions = ref<Question[]>([
  {
    id: 'price',
    title: 'What is included in the price?',
    subtitle: 'Answered 12 times',
    answer: 'Hosting for the first year, the content migration and two rounds of edits.',
  },
  {
    id: 'time',
    title: 'How long does it take?',
    answer: 'Four to six weeks, counting from the day the content is handed over.',
  },
])

const open = ref('price')
const editingId = ref<string | null>(null)
const adding = ref(false)

const draft = ref({ title: '', subtitle: '', answer: '' })

/* One panel at a time: the id of the item being edited is what opens it. */
function editorOpen(id: string) {
  return editingId.value === id
}

function toggleEditor(id: string, value: boolean) {
  editingId.value = value ? id : null

  const question = questions.value.find((item) => item.id === id)
  if (value && question) {
    draft.value = {
      title: question.title,
      subtitle: question.subtitle ?? '',
      answer: question.answer,
    }
  }
}

watch(adding, (value) => {
  if (value) draft.value = { title: '', subtitle: '', answer: '' }
})

function save(id: string) {
  const question = questions.value.find((item) => item.id === id)
  if (!question) return

  question.title = draft.value.title.trim() || question.title
  question.subtitle = draft.value.subtitle.trim() || undefined
  question.answer = draft.value.answer.trim() || question.answer
  editingId.value = null
}

function add() {
  const id = `question-${Date.now().toString(36)}`
  questions.value.push({
    id,
    title: draft.value.title.trim() || 'New question',
    subtitle: draft.value.subtitle.trim() || undefined,
    answer: draft.value.answer.trim() || 'Nothing has been written yet.',
  })
  open.value = id
  adding.value = false
}

function remove(id: string) {
  questions.value = questions.value.filter((item) => item.id !== id)
  editingId.value = null
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <wx-accordion v-model="open" variant="separated">
      <wx-accordion-item
        v-for="question in questions"
        :key="question.id"
        :value="question.id"
        :title="question.title"
        :subtitle="question.subtitle"
      >
        {{ question.answer }}

        <template #extra>
          <wx-popover
            :open="editorOpen(question.id)"
            title="Question"
            :width="320"
            side="bottom"
            align="end"
            @update:open="toggleEditor(question.id, $event)"
          >
            <template #trigger>
              <wx-action type="edit" size="sm" title="Edit this question" />
            </template>

            <wx-form gap="sm">
              <wx-form-item label="Question">
                <wx-input v-model="draft.title" size="sm" />
              </wx-form-item>
              <wx-form-item label="Note" help="Shown under the question">
                <wx-input v-model="draft.subtitle" size="sm" />
              </wx-form-item>
              <wx-form-item label="Answer">
                <wx-input v-model="draft.answer" size="sm" />
              </wx-form-item>
            </wx-form>

            <template #footer="{ close }">
              <wx-button
                class="wx-demo__push"
                size="sm"
                type="danger"
                variant="text"
                :disabled="questions.length < 2"
                @click="remove(question.id)"
              >
                Delete
              </wx-button>
              <wx-button size="sm" @click="close">Cancel</wx-button>
              <wx-button size="sm" type="primary" @click="save(question.id)">Save</wx-button>
            </template>
          </wx-popover>
        </template>
      </wx-accordion-item>
    </wx-accordion>

    <div>
      <wx-popover v-model:open="adding" title="New question" :width="320" align="start">
        <template #trigger>
          <wx-button size="sm">Add a question</wx-button>
        </template>

        <wx-form gap="sm">
          <wx-form-item label="Question">
            <wx-input v-model="draft.title" size="sm" placeholder="What do you want to ask?" />
          </wx-form-item>
          <wx-form-item label="Answer">
            <wx-input v-model="draft.answer" size="sm" />
          </wx-form-item>
        </wx-form>

        <template #footer="{ close }">
          <wx-button size="sm" @click="close">Cancel</wx-button>
          <wx-button size="sm" type="primary" @click="add">Add</wx-button>
        </template>
      </wx-popover>
    </div>
  </div>
</template>

<style scoped>
.wx-demo__push {
  margin-right: auto;
}
</style>
