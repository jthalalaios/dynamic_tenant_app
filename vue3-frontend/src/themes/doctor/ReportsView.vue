<template>
  <main class="page">
    <PageHeader :eyebrow="t('nav.reports')" :title="t('doctor.reports.title')" :subtitle="t('doctor.reports.subtitle', { days })">
      <template #actions>
        <SelectButton v-model="days" :options="[7, 30, 90]" :allowEmpty="false" @change="load" />
      </template>
    </PageHeader>

    <section class="card-grid card-grid--stats">
      <StatCard :label="t('doctor.reports.total')" :value="summary?.total ?? '—'" :caption="t('doctor.reports.totalCaption', { days })" icon="pi pi-calendar" />
    </section>

    <section class="card-grid">
      <BaseCard>
        <template #header><strong>{{ t('doctor.reports.byStatus') }}</strong></template>
        <DataTable :value="summary?.by_status ?? []" :loading="loading" size="small">
          <template #empty>{{ t('common.noResults') }}</template>
          <Column :header="t('common.status')">
            <template #body="{ data }"><StatusTag kind="appointment" :status="data.label" /></template>
          </Column>
          <Column field="total" :header="t('doctor.reports.count')" />
          <Column :header="t('doctor.reports.share')">
            <template #body="{ data }">{{ share(data.total) }}</template>
          </Column>
        </DataTable>
      </BaseCard>
      <BaseCard>
        <template #header><strong>{{ t('doctor.reports.byReason') }}</strong></template>
        <DataTable :value="summary?.by_reason ?? []" :loading="loading" size="small">
          <template #empty>{{ t('common.noResults') }}</template>
          <Column :header="t('doctor.appointments.reason')">
            <template #body="{ data }">{{ t(`doctor.reasons.${snakeToCamel(data.label)}`) }}</template>
          </Column>
          <Column field="total" :header="t('doctor.reports.count')" />
          <Column :header="t('doctor.reports.share')">
            <template #body="{ data }">{{ share(data.total) }}</template>
          </Column>
        </DataTable>
      </BaseCard>
    </section>
  </main>
</template>

<script lang="ts" setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import SelectButton from 'primevue/selectbutton'
import BaseCard from '../../components/shared/cards/BaseCard.vue'
import StatCard from '../../components/shared/cards/StatCard.vue'
import PageHeader from '../../components/shared/PageHeader.vue'
import StatusTag from '../../components/shared/StatusTag.vue'
import { doctorApi } from '../../api'
import { useNotify } from '../../composables/useNotify'
import { usePreferencesStore } from '../../stores'
import { snakeToCamel } from '../../helpers/text'
import type { ReportSummary } from '../../types/models'

const { t } = useI18n()
const notify = useNotify()
const prefs = usePreferencesStore()
const days = ref(30)
const summary = ref<ReportSummary | null>(null)
const loading = ref(false)

const share = (count: number) =>
  summary.value?.total ? new Intl.NumberFormat(prefs.intl, { style: 'percent', maximumFractionDigits: 0 }).format(count / summary.value.total) : '—'

async function load() {
  loading.value = true
  try {
    summary.value = await doctorApi.reportSummary(days.value)
  } catch (err) {
    notify.error(err)
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
