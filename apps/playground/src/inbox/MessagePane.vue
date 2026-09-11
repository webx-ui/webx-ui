<script setup lang="ts">
import { computed } from 'vue'
import { forms, type Submission } from './data'

const props = defineProps<{
  item: Submission
  /** Narrow shells open the pane over the list, so it needs a way back. */
  showBack?: boolean
}>()

defineEmits<{ back: []; progress: []; done: [] }>()

const form = computed(() => forms.find((f) => f.id === props.item.form) ?? forms[0])

const status = computed(() => {
  if (props.item.status === 'done') return { label: 'Оброблено', type: 'success' as const }
  if (props.item.status === 'progress') return { label: 'В роботі', type: 'primary' as const }
  return { label: 'Новий', type: 'warning' as const }
})
</script>

<template>
  <div class="pane">
    <wx-header class="pane__bar" :bordered="true">
      <wx-action v-if="showBack" icon="arrow-left" title="До списку" @click="$emit('back')" />
      <strong class="pane__title">{{ form.label }}</strong>
      <wx-badge :type="status.type" size="sm">{{ status.label }}</wx-badge>
      <span class="pane__ref" :title="item.at">{{ item.ref }}</span>

      <template #end>
        <wx-button
          size="sm"
          :variant="item.status === 'progress' ? 'solid' : 'outline'"
          :type="item.status === 'progress' ? 'primary' : 'default'"
          @click="$emit('progress')"
        >
          <template #icon><wx-icon name="clock" /></template>
          В роботу
        </wx-button>
        <wx-button
          size="sm"
          :variant="item.status === 'done' ? 'solid' : 'outline'"
          :type="item.status === 'done' ? 'success' : 'default'"
          @click="$emit('done')"
        >
          <template #icon><wx-icon name="check" /></template>
          Оброблено
        </wx-button>

        <wx-dropdown align="end">
          <template #trigger>
            <wx-action type="more" title="Ще" />
          </template>
          <wx-dropdown-item icon="user">Призначити менеджера</wx-dropdown-item>
          <wx-dropdown-item icon="download">Експортувати</wx-dropdown-item>
          <hr />
          <wx-dropdown-item icon="trash" tone="danger">У кошик</wx-dropdown-item>
        </wx-dropdown>
      </template>
    </wx-header>

    <wx-scrollbar class="pane__body">
      <div class="pane__stack">
        <wx-card padding="md" bordered :shadow="'never'">
          <div class="sender">
            <span class="sender__avatar">{{ item.initials }}</span>
            <div class="sender__who">
              <div class="sender__name">{{ item.name }}</div>
              <div class="sender__contacts">
                <wx-link :href="`tel:${item.phone.replace(/\s/g, '')}`" type="primary">
                  <template #icon><wx-icon name="phone" /></template>
                  {{ item.phone }}
                </wx-link>
                <wx-link :href="`mailto:${item.email}`" type="muted">
                  <template #icon><wx-icon name="mail" /></template>
                  {{ item.email }}
                </wx-link>
              </div>
            </div>
            <wx-space size="sm" class="sender__actions">
              <wx-button type="primary" size="sm" :href="`tel:${item.phone.replace(/\s/g, '')}`">
                <template #icon><wx-icon name="phone" /></template>
                Подзвонити
              </wx-button>
              <wx-button variant="outline" size="sm">Створити замовлення</wx-button>
            </wx-space>
          </div>
        </wx-card>

        <!--
          The fields come from the form, not from this screen: a new form on the site
          adds rows here without a line of admin code.
        -->
        <wx-card padding="md" bordered :shadow="'never'">
          <dl class="fields">
            <template v-for="field in item.fields" :key="field.label">
              <dt class="fields__label">{{ field.label }}</dt>
              <dd class="fields__value" :class="{ 'fields__value--link': field.link }">
                {{ field.value }}
              </dd>
            </template>
          </dl>
        </wx-card>

        <wx-card padding="md" bordered :shadow="'never'">
          <div class="meta__title">Звідки</div>
          <wx-row :gutter="24" gutter-y="10">
            <wx-col v-for="fact in item.meta" :key="fact.label" :span="24" :sm="12">
              <div class="meta__row">
                <span class="meta__label">{{ fact.label }}</span>
                <span
                  class="meta__value"
                  :class="{ 'meta__value--link': fact.link, 'meta__value--mono': fact.mono }"
                >
                  {{ fact.value }}
                </span>
              </div>
            </wx-col>
          </wx-row>
        </wx-card>

        <wx-card v-if="item.log" padding="sm" bordered :shadow="'never'">
          <div class="log">
            <span class="log__who">{{ item.log.who }}</span>
            <span class="log__text">{{ item.log.text }}</span>
          </div>
        </wx-card>

        <wx-card padding="sm" bordered :shadow="'never'">
          <wx-space size="sm" align="center">
            <wx-input placeholder="Нотатка для команди…" size="sm" class="note" />
            <wx-action icon="arrow-right" title="Додати нотатку" />
          </wx-space>
        </wx-card>
      </div>
    </wx-scrollbar>
  </div>
</template>

<style scoped>
.pane {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  background: var(--wx-bg-body);
}

.pane__bar {
  flex: 0 0 auto;
}

.pane__title {
  font-size: var(--wx-font-size-md);
  font-weight: var(--wx-font-weight-semibold);
  white-space: nowrap;
}

.pane__ref {
  color: var(--wx-text-placeholder);
  font-size: var(--wx-font-size-sm);
  white-space: nowrap;
}

.pane__body {
  flex: 1 1 auto;
  min-height: 0;
}

.pane__stack {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
  padding: var(--wx-space-16);
}

.sender {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--wx-space-12);
}

.sender__avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  flex: 0 0 44px;
  border-radius: var(--wx-radius-full);
  background: var(--wx-color-primary);
  color: var(--wx-color-primary-contrast);
  font-size: var(--wx-font-size-md);
  font-weight: var(--wx-font-weight-semibold);
}

.sender__who {
  flex: 1 1 200px;
  min-width: 0;
}

.sender__name {
  font-size: var(--wx-font-size-lg);
  font-weight: var(--wx-font-weight-semibold);
}

.sender__contacts {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wx-space-14);
  margin-top: var(--wx-space-4);
  font-size: var(--wx-font-size-sm);
}

.sender__actions {
  flex: 0 0 auto;
}

.fields {
  display: grid;
  grid-template-columns: 150px minmax(0, 1fr);
  gap: var(--wx-space-12) var(--wx-space-16);
  margin: 0;
}

.fields__label {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.fields__value {
  margin: 0;
  font-size: var(--wx-font-size-sm);
  line-height: var(--wx-font-line-height-normal);
}

.fields__value--link {
  color: var(--wx-text-link);
}

.meta__title {
  margin-bottom: var(--wx-space-12);
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-xs);
  font-weight: var(--wx-font-weight-medium);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.meta__row {
  display: flex;
  gap: var(--wx-space-10);
  font-size: var(--wx-font-size-sm);
}

.meta__label {
  width: 90px;
  flex: 0 0 90px;
  color: var(--wx-text-muted);
}

.meta__value {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.meta__value--link {
  color: var(--wx-text-link);
}

.meta__value--mono {
  font-family: var(--wx-font-family-mono);
  font-size: var(--wx-font-size-xs);
}

.log {
  display: flex;
  align-items: center;
  gap: var(--wx-space-10);
}

.log__who {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  flex: 0 0 24px;
  border-radius: var(--wx-radius-full);
  background: var(--wx-color-primary-soft);
  color: var(--wx-color-primary);
  font-size: 10px;
  font-weight: var(--wx-font-weight-semibold);
}

.log__text {
  color: var(--wx-text-muted);
  font-size: var(--wx-font-size-sm);
}

.note {
  flex: 1 1 auto;
}
</style>
