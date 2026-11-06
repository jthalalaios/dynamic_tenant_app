<template>
  <main class="page">
    <PageHeader :eyebrow="t('nav.marketing')" :title="t('marketplace.insights.title')" :subtitle="t('marketplace.insights.subtitle')" />

    <BaseCard>
      <template #header><strong>{{ t('marketplace.insights.topProducts') }}</strong></template>
      <DataTable :value="rows" :loading="loading" size="small">
        <template #empty>{{ t('common.noResults') }}</template>
        <Column :header="'#'">
          <template #body="{ index }">{{ index + 1 }}</template>
        </Column>
        <Column field="name" :header="t('marketplace.products.name')" />
        <Column :header="t('marketplace.insights.unitsSold')">
          <template #body="{ data }">{{ fmt.number(data.units) }}</template>
        </Column>
        <Column :header="t('marketplace.insights.revenue')">
          <template #body="{ data }">{{ fmt.money(data.revenue_cents, 'EUR') }}</template>
        </Column>
      </DataTable>
    </BaseCard>
  </main>
</template>

<script lang="ts" setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import BaseCard from '../../components/shared/cards/BaseCard.vue'
import PageHeader from '../../components/shared/PageHeader.vue'
import { marketplaceApi } from '../../api'
import { useNotify } from '../../composables/useNotify'
import { useFormatters } from '../../composables/useFormatters'
import type { TopProduct } from '../../types/models'

const { t } = useI18n()
const notify = useNotify()
const fmt = useFormatters()
const rows = ref<TopProduct[]>([])
const loading = ref(true)

onMounted(async () => {
  try {
    rows.value = await marketplaceApi.topProducts()
  } catch (err) {
    notify.error(err)
  } finally {
    loading.value = false
  }
})
</script>
