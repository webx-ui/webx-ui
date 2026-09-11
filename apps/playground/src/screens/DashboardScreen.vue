<script setup lang="ts">
/**
 * An ordinary padded screen — the shape most admin routes have. It is here to prove
 * the plain case still behaves: `WxMain` keeps its padding, the grid reflows against
 * the column it is in rather than the window, and the page scrolls inside the shell.
 */
const stats = [
  { title: 'Замовлення', value: 1248, tone: 'default' as const, delta: '+12%' },
  { title: 'Дохід', value: 86420, prefix: '€', tone: 'success' as const, delta: '+4,1%' },
  { title: 'Нові звернення', value: 37, tone: 'default' as const, delta: '+9' },
  { title: 'Повернення', value: 2.4, suffix: '%', tone: 'danger' as const, delta: '−0,3' },
]

const lorem =
  'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer posuere erat a ante ' +
  'venenatis dapibus posuere velit aliquet. Nullam quis risus eget urna mollis ornare vel eu leo.'

const cards = [
  { title: 'Curabitur blandit', tag: 'Draft' },
  { title: 'Vestibulum id ligula', tag: 'Live' },
  { title: 'Donec sed odio dui', tag: 'Live' },
  { title: 'Maecenas faucibus', tag: 'Draft' },
  { title: 'Aenean lacinia', tag: 'Archive' },
  { title: 'Nullam quis risus', tag: 'Live' },
]

const tones = {
  Draft: 'warning',
  Live: 'success',
  Archive: 'default',
} as const

const activity = [
  { who: 'Ганна Р.', what: 'оновила сторінку Lorem ipsum', when: '12 хв тому' },
  { who: 'Система', what: 'імпортувала 34 записи', when: 'годину тому' },
  { who: 'Олег М.', what: 'закрив звернення #1041', when: 'сьогодні, 09:14' },
  { who: 'Тарас К.', what: 'додав 5 зображень', when: 'вчора' },
]
</script>

<template>
  <div class="screen">
    <header class="screen__head">
      <div>
        <wx-heading :level="1" size="lg">Огляд</wx-heading>
        <wx-text size="sm" tone="muted">Lorem ipsum dolor sit amet, consectetur adipiscing</wx-text>
      </div>
      <div class="screen__head-actions">
        <wx-action icon="refresh" title="Оновити" />
        <wx-button type="primary" size="sm">
          <template #icon><wx-icon name="plus" /></template>
          Створити
        </wx-button>
      </div>
    </header>

    <wx-row :gutter="16" wrap>
      <wx-col v-for="stat in stats" :key="stat.title" :span="24" :sm="12" :lg="6">
        <wx-card padding="md" bordered shadow="never">
          <wx-statistic
            :title="stat.title"
            :value="stat.value"
            :prefix="stat.prefix"
            :suffix="stat.suffix"
            size="lg"
          />
          <wx-text size="xs" :tone="stat.tone === 'danger' ? 'danger' : 'success'">
            {{ stat.delta }} за місяць
          </wx-text>
        </wx-card>
      </wx-col>
    </wx-row>

    <wx-row :gutter="16" wrap>
      <wx-col :span="24" :lg="16">
        <wx-card title="Lorem ipsum" padding="md" bordered shadow="never">
          <wx-prose>
            <p>{{ lorem }}</p>
            <p>
              Sed posuere consectetur est at lobortis. Cum sociis natoque penatibus et magnis dis
              parturient montes, nascetur ridiculus mus. Donec ullamcorper nulla non metus auctor
              fringilla. Vivamus sagittis lacus vel augue laoreet rutrum faucibus dolor auctor.
            </p>
            <ul>
              <li>Nulla vitae elit libero, a pharetra augue</li>
              <li>Donec id elit non mi porta gravida at eget metus</li>
              <li>Etiam porta sem malesuada magna mollis euismod</li>
            </ul>
          </wx-prose>
        </wx-card>
      </wx-col>

      <wx-col :span="24" :lg="8">
        <wx-card title="Остання активність" padding="md" bordered shadow="never">
          <ul class="screen__activity">
            <li v-for="item in activity" :key="item.what">
              <wx-text size="sm"
                ><strong>{{ item.who }}</strong> {{ item.what }}</wx-text
              >
              <wx-text size="xs" tone="muted">{{ item.when }}</wx-text>
            </li>
          </ul>
        </wx-card>
      </wx-col>
    </wx-row>

    <wx-row :gutter="16" wrap>
      <wx-col v-for="card in cards" :key="card.title" :span="24" :sm="12" :lg="8">
        <wx-card padding="md" bordered shadow="hover">
          <template #header>
            <div class="screen__card-head">
              <wx-text weight="semibold">{{ card.title }}</wx-text>
              <wx-badge :type="tones[card.tag as keyof typeof tones]" size="sm">
                {{ card.tag }}
              </wx-badge>
            </div>
          </template>
          <wx-text size="sm" tone="muted">{{ lorem }}</wx-text>
          <template #footer>
            <wx-link href="#">Читати далі</wx-link>
          </template>
        </wx-card>
      </wx-col>
    </wx-row>
  </div>
</template>

<style scoped>
.screen {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-16);
}

.screen__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-12);
}

.screen__head-actions {
  display: flex;
  align-items: center;
  gap: var(--wx-space-8);
}

.screen__card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--wx-space-8);
}

.screen__activity {
  display: flex;
  flex-direction: column;
  gap: var(--wx-space-12);
  margin: 0;
  padding: 0;
  list-style: none;
}

.screen__activity li {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
</style>
