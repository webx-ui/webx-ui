<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  toast,
  WxButton,
  WxDialog,
  WxFormItem,
  WxInput,
  WxRadio,
  WxRadioGroup,
  WxSpace,
  WxTextarea,
  type LocalizedValue,
} from '@webx-ui/core'
import { useAdmin, useErrorText } from '@webx-ui/module-admin'
import { createPaymentsApi } from './api'
import GroupPictogram from './GroupPictogram.vue'
import { usePaymentsWords } from './i18n'
import type { MethodGroup, Pictogram } from './types'

/**
 * A group's words and pictogram (groups spec §7): edits over the config, so an empty title goes
 * back to the config's word. There is nothing here to create or remove a group with (decision 12).
 *
 * `WxRadioGroup` and not `WxIconPicker`: the picker offers the set of the core, not the site's.
 */
const props = defineProps<{
  group: MethodGroup | null
  pictograms: Pictogram[]
}>()

const emit = defineEmits<{ saved: [] }>()

const open = defineModel<boolean>('open', { default: false })

const api = createPaymentsApi(useAdmin())
const t = usePaymentsWords()
const message = useErrorText()

const titles = ref<LocalizedValue>({})
const descriptions = ref<LocalizedValue>({})
const pictogram = ref<string | null>(null)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

watch(
  () => props.group,
  (group) => {
    titles.value = { ...group?.titles }
    descriptions.value = { ...group?.descriptions }
    pictogram.value = group?.pictogram ?? null
    errors.value = {}
  },
  { immediate: true },
)

const titlesHelp = computed(() =>
  t('groups.titles-help', { title: props.group?.config_title ?? '' }),
)

async function save(): Promise<void> {
  if (props.group === null || saving.value) return

  saving.value = true
  errors.value = {}

  try {
    await api.saveGroup(props.group.key, {
      titles: titles.value,
      descriptions: descriptions.value,
      pictogram: pictogram.value,
    })
    toast.success(t('common.saved'))
    open.value = false
    emit('saved')
  } catch (error) {
    const fields = (error as { errors?: Record<string, string[]> }).errors

    if (fields && Object.keys(fields).length > 0) errors.value = fields
    else toast.danger(message(error))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="t('groups.edit')" :width="520">
    <div class="wx-payments-group-form">
      <wx-form-item :label="t('groups.titles')" :help="titlesHelp" :error="errors.titles?.[0]">
        <wx-input v-model="titles" localized :maxlength="120" data-test="group-titles" />
      </wx-form-item>

      <wx-form-item
        :label="t('groups.descriptions')"
        :help="t('groups.descriptions-help')"
        :error="errors.descriptions?.[0]"
      >
        <wx-textarea v-model="descriptions" localized :rows="2" :maxlength="500" />
      </wx-form-item>

      <wx-form-item :label="t('groups.pictogram')" :error="errors.pictogram?.[0]">
        <wx-radio-group v-model="pictogram" class="wx-payments-group-form__pictograms">
          <wx-radio
            v-for="one in pictograms"
            :key="one.name"
            :value="one.name"
            :aria-label="t(`groups.pictograms.${one.name}`)"
            :data-test="`pictogram-${one.name}`"
          >
            <span
              class="wx-payments-group-form__pictogram"
              :title="t(`groups.pictograms.${one.name}`)"
            >
              <group-pictogram :svg="one.svg" />
            </span>
          </wx-radio>
        </wx-radio-group>
      </wx-form-item>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="open = false">{{ t('common.cancel') }}</wx-button>
        <wx-button type="primary" :loading="saving" data-test="group-save" @click="save">
          {{ t('common.save') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style scoped>
.wx-payments-group-form {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
}

.wx-payments-group-form__pictograms {
  display: flex;
  flex-flow: row wrap;
  gap: var(--wx-space-12);
}

.wx-payments-group-form__pictogram {
  display: inline-flex;
}
</style>
