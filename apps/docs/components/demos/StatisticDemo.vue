<script setup lang="ts">
import { ref } from 'vue'
import { WxButton, WxCard, WxCountdown, WxIcon, WxStatistic } from '@webx-ui/core'

const deadline = ref(Date.now() + 7 * 3600_000 - 13_000)
const monthEnd = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 1).getTime()
const finished = ref(false)

function reset() {
  finished.value = false
  deadline.value = Date.now() + 7 * 3600_000
}
</script>

<template>
  <div class="wx-demo wx-demo--stack">
    <wx-card padding="lg">
      <div class="stat-row">
        <wx-statistic title="Daily active users" :value="268500" locale="en-US" />
        <wx-statistic title="Ratio of men to women" value="138/100" />
        <wx-statistic title="Revenue" :value="172000" :precision="2" prefix="₴" locale="en-US" />
        <wx-statistic title="Feedback" :value="562" tone="success">
          <template #suffix><wx-icon name="mail" /></template>
          +12% since last week
        </wx-statistic>
      </div>
    </wx-card>

    <div>
      <span class="wx-demo__label">Countdown</span>
      <wx-card padding="lg">
        <div class="stat-row">
          <wx-countdown title="Start to grab" :value="deadline" @finish="finished = true" />
          <wx-countdown title="Remaining VIP time" :value="deadline" format="HH:mm:ss" size="lg" />
          <wx-countdown
            title="Still to go until next month"
            :value="monthEnd"
            format="DD days HH:mm:ss"
          >
            <template #title>
              <span style="display: inline-flex; align-items: center; gap: 6px">
                <wx-icon name="calendar" /> Still to go until next month
              </span>
            </template>
            {{ new Date(monthEnd).toISOString().slice(0, 10) }}
          </wx-countdown>
        </div>

        <template #footer>
          <div class="wx-demo__row">
            <wx-button type="primary" size="sm" @click="reset">Reset</wx-button>
            <span v-if="finished" style="color: var(--wx-color-success-active); font-size: 14px">
              The first countdown fired <code>finish</code>.
            </span>
          </div>
        </template>
      </wx-card>
    </div>
  </div>
</template>

<style scoped>
.stat-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 24px;
}
</style>
