<template>
  <main class="page">
    <PageHeader :eyebrow="t('marketplace.dashboard.eyebrow')" :title="t('marketplace.dashboard.title')" :subtitle="t('marketplace.dashboard.subtitle')">
      <template #actions>
        <Button v-if="$can('orders.create')" :label="t('marketplace.orders.new')" icon="pi pi-shopping-cart" outlined @click="router.push({ name: 'market.orders', query: { new: '1' } })" />
        <Button v-if="$can('products.create')" :label="t('marketplace.dashboard.newProduct')" icon="pi pi-plus" @click="router.push({ name: 'market.products', query: { new: '1' } })" />
      </template>
    </PageHeader>

    <section class="card-grid card-grid--stats" :aria-label="t('home.sections.glance')">
      <template v-if="dashboard">
        <StatCard v-for="stat in stats" :key="stat.label" v-bind="stat" />
      </template>
      <template v-else>
        <Skeleton v-for="n in 4" :key="n" height="9rem" borderRadius="18px" />
      </template>
    </section>

    <section class="card-grid two-col">
      <BaseCard v-if="$can('products.view')">
        <template #header><strong>{{ t('marketplace.dashboard.lowStock') }}</strong></template>
        <DataTable :value="dashboard?.low_stock ?? []" size="small">
          <template #empty>{{ t('marketplace.dashboard.allStocked') }}</template>
          <Column field="name" :header="t('marketplace.products.name')" />
          <Column field="sku" header="SKU" />
          <Column :header="t('marketplace.products.stock')">
            <template #body="{ data }"><Tag :value="String(data.stock)" :severity="data.stock === 0 ? 'danger' : 'warn'" rounded /></template>
          </Column>
        </DataTable>
      </BaseCard>
      <BaseCard v-if="$can('orders.view')">
        <template #header><strong>{{ t('marketplace.dashboard.recentOrders') }}</strong></template>
        <DataTable :value="dashboard?.recent_orders ?? []" size="small">
          <template #empty>{{ t('common.noResults') }}</template>
          <Column field="number" :header="t('marketplace.orders.number')" />
          <Column field="customer_name" :header="t('marketplace.orders.customer')" />
          <Column :header="t('marketplace.orders.total')">
            <template #body="{ data }">{{ fmt.money(data.total_cents, data.currency) }}</template>
          </Column>
          <Column :header="t('common.status')">
            <template #body="{ data }"><StatusTag kind="order" :status="data.status" /></template>
          </Column>
        </DataTable>
      </BaseCard>
    </section>
  </main>
</template>

<script lang="ts" setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Skeleton from 'primevue/skeleton'
import Tag from 'primevue/tag'
import BaseCard from '../../components/shared/cards/BaseCard.vue'
import StatCard from '../../components/shared/cards/StatCard.vue'
import PageHeader from '../../components/shared/PageHeader.vue'
import StatusTag from '../../components/shared/StatusTag.vue'
import { marketplaceApi } from '../../api'
import { useNotify } from '../../composables/useNotify'
import { useFormatters } from '../../composables/useFormatters'
import type { MarketplaceDashboard } from '../../types/models'

const { t } = useI18n()
const router = useRouter()
const notify = useNotify()
const fmt = useFormatters()
const dashboard = ref<MarketplaceDashboard | null>(null)

const stats = computed(() => {
  const s = dashboard.value?.stats
  if (!s) return []
  return [
    { label: t('marketplace.dashboard.stats.products'), value: s.products, caption: t('marketplace.dashboard.stats.productsCaption', { count: s.active_products }), icon: 'pi pi-box' },
    { label: t('marketplace.dashboard.stats.units'), value: fmt.number(s.units_in_stock), caption: t('marketplace.dashboard.stats.lowStockCaption', { count: s.low_stock }), icon: 'pi pi-warehouse' },
    { label: t('marketplace.dashboard.stats.ordersToday'), value: s.orders_today, caption: t('marketplace.dashboard.stats.pendingCaption', { count: s.pending_orders }), icon: 'pi pi-shopping-cart' },
    // Revenue is reported in the shop's default currency
    { label: t('marketplace.dashboard.stats.revenue'), value: fmt.money(s.revenue_cents, 'EUR'), caption: t('marketplace.dashboard.stats.revenueCaption'), icon: 'pi pi-wallet' }
  ]
})

onMounted(async () => {
  try {
    dashboard.value = await marketplaceApi.dashboard()
  } catch (err) {
    notify.error(err)
  }
})
</script>

<style scoped>
.two-col {
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 420px), 1fr));
}
</style>
