<script setup lang="ts">
import { ref } from 'vue'
import {
  WxButton,
  WxCheckbox,
  WxForm,
  WxFormItem,
  WxInput,
  WxInputNumber,
  WxSwitch,
  WxTextarea,
  type ValidationErrors,
} from '@webx-ui/core'

const form = ref({ title: '', slug: '', weight: 0, summary: '', published: false, agreed: false })

// Exactly the payload a Laravel 422 puts in `errors`.
const errors = ref<ValidationErrors>({})
const saving = ref(false)

function save() {
  saving.value = true
  setTimeout(() => {
    errors.value = form.value.title
      ? {}
      : {
          title: ['The title field is required.'],
          slug: ['The slug has already been taken.'],
        }
    saving.value = false
  }, 500)
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <wx-form :errors="errors" @submit="save">
      <wx-form-item label="Title" name="title" required help="Shown in the browser tab">
        <wx-input v-model="form.title" placeholder="About us" clearable />
      </wx-form-item>

      <wx-form-item label="URL" name="slug" required>
        <wx-input v-model="form.slug" placeholder="about-us">
          <template #prefix>/</template>
        </wx-input>
      </wx-form-item>

      <wx-form-item label="Sort weight" name="weight">
        <wx-input-number v-model="form.weight" :min="0" :max="100" :step="5" />
      </wx-form-item>

      <wx-form-item label="Summary" name="summary">
        <wx-textarea v-model="form.summary" autosize :maxlength="200" show-count />
      </wx-form-item>

      <wx-form-item>
        <wx-switch v-model="form.published" label="Published" />
      </wx-form-item>

      <wx-form-item>
        <wx-checkbox v-model="form.agreed" label="I have read the guidelines" />
      </wx-form-item>

      <div style="display: flex; gap: 8px; justify-content: flex-end">
        <wx-button variant="text" @click="errors = {}">Reset errors</wx-button>
        <wx-button type="success" native-type="submit" :loading="saving">Save</wx-button>
      </div>
    </wx-form>

    <p style="margin: 0; color: var(--wx-text-muted); font-size: 14px">
      Submitting with an empty title returns errors from the “server” — watch the messages and the
      field states appear together.
    </p>
  </div>
</template>
