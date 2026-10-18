<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAdmin, useErrorText, useTranslate } from '@webx-ui/module-admin'
import {
  toast,
  useModal,
  WxAlert,
  WxButton,
  WxCheckbox,
  WxDialog,
  WxFormItem,
  WxInput,
  WxLoading,
  WxSpace,
  WxText,
} from '@webx-ui/core'
import { createAdminsApi } from './admins'
import { useAuthMessages } from './i18n'
import type { PermissionRow, Role, RoleInput } from './types'

/**
 * A role: its name, its code, and the permissions it opens, as a matrix — one row per module in
 * the order of the menu, a box per permission, and a box on the row that ticks the whole module
 * (WEBX_UI_PERMISSIONS.md §11).
 *
 * The server's rules are drawn here so they do not have to be learned from a refusal: a
 * permission the editor does not hold is shown and cannot be ticked (rule 2), and a role the
 * editor holds opens read-only (rule 5). They are only drawn — the server asks again.
 */
const props = withDefaults(
  defineProps<{
    /** Absent creates a role; present edits it. */
    role?: Role | null
    /** Whoever may not edit roles reads them. */
    readOnly?: boolean
  }>(),
  { role: null, readOnly: false },
)

const { resolve, dismiss, open } = useModal<Role>()

const context = useAdmin()
const api = createAdminsApi(context)
useAuthMessages()

const t = useTranslate('webx-auth')
/* Not the server's `message`: the panel says how a request failed in its own words (§13.3). */
const message = useErrorText()

const rows = ref<PermissionRow[] | null>(null)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const name = ref(props.role?.name ?? '')
const slug = ref(props.role?.slug ?? '')
const ticked = ref<Set<string>>(new Set(props.role?.permissions ?? []))

const editing = computed(() => props.role !== null)
const locked = computed(() => props.readOnly || props.role?.held === true)

const title = computed(() => {
  if (!editing.value) return t('roles.new')

  return locked.value ? t('roles.view') : t('roles.edit')
})

/*
 * What the role holds that no installed module declares — left by a module taken off the site.
 * Shown so it can be cleared, and kept on save if it is not.
 */
const unknown = computed(() => {
  if (rows.value === null) return []

  const known = new Set(rows.value.flatMap((row) => row.permissions.map((cell) => cell.name)))

  return [...ticked.value].filter((permission) => !known.has(permission)).sort()
})

void api.permissions().then((all) => (rows.value = all))

/* A box the editor may not tick stays as it is; one already ticked may still be cleared. */
function blocked(name: string, held: boolean): boolean {
  return locked.value || (!held && !ticked.value.has(name))
}

function set(name: string, on: boolean): void {
  const next = new Set(ticked.value)

  if (on) next.add(name)
  else next.delete(name)

  ticked.value = next
}

/** The boxes «All» moves: those the editor may tick, and those already ticked. */
function movable(row: PermissionRow): string[] {
  return row.permissions.filter((cell) => !blocked(cell.name, cell.held)).map((cell) => cell.name)
}

function rowState(row: PermissionRow): { checked: boolean; indeterminate: boolean } {
  const on = row.permissions.filter((cell) => ticked.value.has(cell.name)).length

  return {
    checked: on > 0 && on === row.permissions.length,
    indeterminate: on > 0 && on < row.permissions.length,
  }
}

function setRow(row: PermissionRow, on: boolean): void {
  const next = new Set(ticked.value)

  for (const name of movable(row)) {
    if (on) next.add(name)
    else next.delete(name)
  }

  ticked.value = next
}

function errorOf(field: string): string | undefined {
  return errors.value[field]?.[0]
}

/* The first problem with a permission, which the server reports by index. */
const permissionsError = computed(() => {
  const key = Object.keys(errors.value).find((one) => one.startsWith('permissions'))

  return key ? errors.value[key]?.[0] : undefined
})

async function save(): Promise<void> {
  saving.value = true
  errors.value = {}

  const input: RoleInput = {
    name: name.value,
    slug: slug.value.trim() === '' ? null : slug.value.trim(),
    permissions: [...ticked.value],
  }

  try {
    const saved = props.role
      ? await api.updateRole(props.role.id, input)
      : await api.createRole(input)

    toast.success(t(editing.value ? 'roles.updated' : 'roles.created'))
    resolve(saved)
  } catch (error) {
    const body = (error as { body?: { errors?: Record<string, string[]> } }).body

    if (body?.errors) {
      errors.value = body.errors
    } else {
      // Rules 2 and 5 arrive as a 403 with a message and no field.
      toast.danger(message(error))
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <wx-dialog v-model:open="open" :title="title" :width="800">
    <div class="wx-role-form">
      <wx-alert v-if="role?.held" type="info" :description="t('roles.held')" />

      <div class="wx-role-form__names">
        <wx-form-item :label="t('roles.name')" :error="errorOf('name')">
          <wx-input v-model="name" name="name" :disabled="locked" />
        </wx-form-item>

        <wx-form-item
          :label="t('roles.slug')"
          :error="errorOf('slug')"
          :help="locked ? undefined : t('roles.slug-hint')"
        >
          <wx-input v-model="slug" name="slug" autocomplete="off" :disabled="locked" />
        </wx-form-item>
      </div>

      <wx-text weight="semibold">{{ t('roles.permissions') }}</wx-text>
      <wx-text v-if="permissionsError" size="sm" tone="danger">{{ permissionsError }}</wx-text>

      <wx-loading v-if="rows === null" />

      <div v-else class="wx-role-form__matrix">
        <section v-for="row in rows" :key="row.module" class="wx-role-form__row">
          <div class="wx-role-form__module">
            <wx-checkbox
              :model-value="rowState(row).checked"
              :indeterminate="rowState(row).indeterminate"
              :disabled="movable(row).length === 0"
              :aria-label="`${t('roles.all')}: ${row.title}`"
              @update:model-value="(on: boolean) => setRow(row, on)"
            >
              <wx-text weight="semibold">{{ row.title }}</wx-text>
            </wx-checkbox>
          </div>

          <div class="wx-role-form__cells">
            <wx-checkbox
              v-for="cell in row.permissions"
              :key="cell.name"
              class="wx-role-form__cell"
              :model-value="ticked.has(cell.name)"
              :disabled="blocked(cell.name, cell.held)"
              :title="cell.held ? cell.name : `${cell.name} — ${t('roles.not-yours')}`"
              @update:model-value="(on: boolean) => set(cell.name, on)"
            >
              <span class="wx-role-form__label">
                <span>{{ cell.label }}</span>
                <span class="wx-role-form__code">{{ cell.name }}</span>
              </span>
            </wx-checkbox>
          </div>
        </section>

        <section v-if="unknown.length > 0" class="wx-role-form__row">
          <div class="wx-role-form__module">
            <wx-text weight="semibold">{{ t('roles.unknown') }}</wx-text>
          </div>

          <div class="wx-role-form__cells">
            <wx-checkbox
              v-for="permission in unknown"
              :key="permission"
              class="wx-role-form__cell"
              :model-value="ticked.has(permission)"
              :disabled="locked"
              @update:model-value="(on: boolean) => set(permission, on)"
            >
              <span class="wx-role-form__code">{{ permission }}</span>
            </wx-checkbox>
          </div>
        </section>
      </div>
    </div>

    <template #footer>
      <wx-space size="sm">
        <wx-button variant="outline" @click="dismiss()">
          {{ locked ? t('roles.close') : t('roles.cancel') }}
        </wx-button>
        <wx-button v-if="!locked" type="primary" :loading="saving" @click="save">
          {{ t('roles.save') }}
        </wx-button>
      </wx-space>
    </template>
  </wx-dialog>
</template>

<style>
/* The container is the wrapper: the dialog's width decides the layout, not the window's. */
.wx-role-form {
  container-type: inline-size;
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  min-width: 0;
}

.wx-role-form__names {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: var(--wx-space-12);
}

.wx-role-form__matrix {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--wx-border-default);
  border-radius: var(--wx-radius-md);
}

/* The module on the left, its permissions beside it: a row reads as «this section: these». */
.wx-role-form__row {
  display: grid;
  grid-template-columns: minmax(0, 200px) minmax(0, 1fr);
  gap: var(--wx-space-12);
  padding: var(--wx-space-12) var(--wx-space-16);
}

.wx-role-form__row + .wx-role-form__row {
  border-top: 1px solid var(--wx-border-muted);
}

.wx-role-form__cells {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-8) var(--wx-space-24);
  min-width: 0;
}

.wx-role-form__label {
  display: inline-flex;
  flex-direction: column;
  min-width: 0;
}

/* The name a developer and an agent know it by, under the words a person reads. */
.wx-role-form__code {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
  color: var(--wx-text-muted);
  overflow-wrap: anywhere;
}

@container (max-width: 560px) {
  .wx-role-form__names,
  .wx-role-form__row {
    grid-template-columns: minmax(0, 1fr);
  }

  .wx-role-form__row {
    gap: var(--wx-space-8);
    padding: var(--wx-space-12);
  }
}
</style>
