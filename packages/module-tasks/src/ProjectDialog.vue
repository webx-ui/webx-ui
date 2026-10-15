<script setup lang="ts">
import { reactive, ref, useId } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxSpace,
  WxSwitch,
  WxTextarea,
} from '@webx-ui/core'
import { createTasksApi } from './api'
import { useTasksMessages } from './i18n'
import type { Project, ProjectInput } from './types'

/**
 * A project, new or changed: where agents work on it and the rules they are handed. Answers the
 * project as the server saved it; a refused field stays open with the reason under it.
 */
defineOptions({ name: 'WxTasksProjectDialog' })

const props = withDefaults(defineProps<{ project?: Project | null }>(), { project: null })

const { open, resolve, dismiss } = useModal<Project>()

const context = useAdmin()
const api = createTasksApi(context)
useTasksMessages()

const t = useTranslate('webx-tasks')
const message = useErrorText()

const formId = useId()
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const values = reactive({
  slug: props.project?.slug ?? '',
  title: props.project?.title ?? '',
  workdir: props.project?.workdir ?? '',
  repo_url: props.project?.repo_url ?? '',
  default_branch: props.project?.default_branch ?? 'main',
  rules: props.project?.rules ?? '',
  archived: props.project?.archived ?? false,
})

async function submit(): Promise<void> {
  if (saving.value) return

  saving.value = true
  errors.value = {}

  const input: ProjectInput = {
    ...values,
    workdir: values.workdir || null,
    repo_url: values.repo_url || null,
    default_branch: values.default_branch || null,
    rules: values.rules || null,
  }

  try {
    resolve(
      props.project
        ? await api.saveProject(props.project.id, input)
        : await api.createProject(input),
    )
    toast.success(t('panel.project-saved'))
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) errors.value = body.errors
    else toast.danger(message(error))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog
    v-model:open="open"
    :title="props.project ? t('panel.project-edit') : t('panel.project-new')"
    :width="560"
  >
    <form :id="formId" class="wx-tasks-project" @submit.prevent="submit">
      <wx-form-item :label="t('panel.project-title')" :error="errors.title" required>
        <wx-input v-model="values.title" :maxlength="255" />
      </wx-form-item>

      <wx-form-item
        :label="t('panel.project-slug')"
        :help="t('panel.project-slug-help')"
        :error="errors.slug"
        required
      >
        <wx-input v-model="values.slug" :maxlength="64" />
      </wx-form-item>

      <wx-form-item
        :label="t('panel.project-workdir')"
        :help="t('panel.project-workdir-help')"
        :error="errors.workdir"
      >
        <wx-input v-model="values.workdir" />
      </wx-form-item>

      <wx-form-item :label="t('panel.project-repo')" :error="errors.repo_url">
        <wx-input v-model="values.repo_url" />
      </wx-form-item>

      <wx-form-item :label="t('panel.project-branch')" :error="errors.default_branch">
        <wx-input v-model="values.default_branch" />
      </wx-form-item>

      <wx-form-item
        :label="t('panel.project-rules')"
        :help="t('panel.project-rules-help')"
        :error="errors.rules"
      >
        <wx-textarea v-model="values.rules" :rows="5" />
      </wx-form-item>

      <wx-form-item v-if="props.project" :help="t('panel.project-archived-help')">
        <wx-switch v-model="values.archived" :label="t('panel.project-archived')" />
      </wx-form-item>
    </form>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">{{ t('panel.keep') }}</wx-button>
        <wx-button type="primary" native-type="submit" :form="formId" :loading="saving">
          {{ t('panel.save') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-tasks-project {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-4);
}
</style>
