<template>
  <main class="page">
    <PageHeader :eyebrow="t('nav.orders')" :title="t('marketplace.orders.title')" :subtitle="t('marketplace.orders.subtitle')">
      <template #actions>
        <Button v-if="$can('orders.create')" :label="t('marketplace.orders.new')" icon="pi pi-plus" @click="openCreate" />
      </template>
    </PageHeader>

    <BaseCard>
      <div class="table-toolbar">
        <div class="table-toolbar__filters">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText v-model="list.filters.search" :placeholder="t('marketplace.orders.search')" @input="debouncedSearch" />
          </IconField>
          <Select v-model="list.filters.status" :options="statusOptions" optionLabel="label" optionValue="value" showClear :placeholder="t('doctor.appointments.anyStatus')" @change="list.search()" />
        </div>
      </div>

      <DataTable
        lazy
        paginator
        :value="list.items.value"
        :totalRecords="list.total.value"
        :rows="list.rows.value"
        :first="(list.page.value - 1) * list.rows.value"
        :loading="list.loading.value"
        :rowsPerPageOptions="[15, 30, 50]"
        dataKey="id"
        stripedRows
        @page="list.onPage"
      >
        <template #empty>{{ t('common.noResults') }}</template>
        <Column field="number" :header="t('marketplace.orders.number')" />
        <Column :header="t('marketplace.orders.placedAt')">
          <template #body="{ data }">{{ fmt.dateTime(data.created_at) }}</template>
        </Column>
        <Column field="customer_name" :header="t('marketplace.orders.customer')" />
        <Column field="items_count" :header="t('marketplace.orders.items')" />
        <Column :header="t('marketplace.orders.total')">
          <template #body="{ data }">{{ fmt.money(data.total_cents, data.currency) }}</template>
        </Column>
        <Column :header="t('common.status')">
          <template #body="{ data }"><StatusTag kind="order" :status="data.status" /></template>
        </Column>
        <Column :header="t('common.actions')" headerClass="col-center" bodyClass="col-center">
          <template #body="{ data }">
            <div v-if="$can('orders.update') && data.allowed_transitions.length" class="order-actions">
              <Button
                v-for="next in data.allowed_transitions"
                :key="next"
                size="small"
                rounded
                :outlined="next === 'cancelled'"
                :severity="transitionStyle(next).severity"
                :icon="transitionStyle(next).icon"
                :label="t(`marketplace.orders.to.${next}`)"
                :loading="busyKey === `${data.id}:${next}`"
                :disabled="busyKey !== null && busyKey.startsWith(`${data.id}:`)"
                class="order-action"
                @click="next === 'cancelled' ? confirmCancel(data) : setStatus(data, next)"
              />
            </div>
            <span v-else class="muted" aria-hidden="true">—</span>
          </template>
        </Column>
      </DataTable>
    </BaseCard>

    <Dialog v-model:visible="dialogOpen" modal :header="t('marketplace.orders.new')" :style="{ width: 'min(720px, 95vw)' }">
      <form id="order-form" class="dialog-form" @submit.prevent="save" novalidate>
        <div class="field">
          <label for="customer_name">{{ t('marketplace.orders.customer') }}</label>
          <InputText id="customer_name" v-model="form.customer_name" :invalid="!!errors.first('customer_name')" fluid />
          <small v-if="errors.first('customer_name')" class="field-error">{{ errors.first('customer_name') }}</small>
        </div>
        <div class="field">
          <label for="customer_email">{{ t('auth.email') }}</label>
          <InputText id="customer_email" v-model="form.customer_email" type="email" :invalid="!!errors.first('customer_email')" fluid />
          <small v-if="errors.first('customer_email')" class="field-error">{{ errors.first('customer_email') }}</small>
        </div>

        <div class="field field--full">
          <label>{{ t('marketplace.orders.items') }}</label>
          <div v-for="(line, index) in form.items" :key="index" class="order-line">
            <Select
              v-model="line.product_id"
              :options="products"
              optionLabel="name"
              optionValue="id"
              filter
              :optionDisabled="(p: StoreProduct) => p.stock === 0"
              :placeholder="t('marketplace.orders.pickProduct')"
              class="order-line__product"
            >
              <template #option="{ option }">
                <span>{{ option.name }}</span>
                <span class="muted order-line__meta">{{ fmt.money(option.price_cents, option.currency) }} · {{ t('marketplace.orders.inStock', { count: option.stock }) }}</span>
              </template>
            </Select>
            <InputNumber v-model="line.quantity" :min="1" :max="1000" showButtons class="order-line__qty" />
            <Button icon="pi pi-times" text rounded severity="secondary" :aria-label="t('common.delete')" :disabled="form.items.length === 1" @click="form.items.splice(index, 1)" />
          </div>
          <small v-if="itemsError" class="field-error">{{ itemsError }}</small>
          <div class="order-footer">
            <Button :label="t('marketplace.orders.addLine')" icon="pi pi-plus" text size="small" @click="form.items.push({ product_id: null, quantity: 1 })" />
            <strong v-if="estimatedTotal !== null">{{ t('marketplace.orders.total') }}: {{ estimatedTotal }}</strong>
          </div>
        </div>
      </form>
      <template #footer>
        <Button :label="t('common.cancel')" text @click="dialogOpen = false" />
        <Button :label="t('marketplace.orders.place')" type="submit" form="order-form" :loading="saving" />
      </template>
    </Dialog>
  </main>
</template>

<script lang="ts" setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import BaseCard from '../../components/shared/cards/BaseCard.vue'
import PageHeader from '../../components/shared/PageHeader.vue'
import StatusTag from '../../components/shared/StatusTag.vue'
import { ordersApi, productsApi } from '../../api'
import { usePaginatedList } from '../../composables/usePaginatedList'
import { useNotify } from '../../composables/useNotify'
import { useFormErrors } from '../../composables/useFormErrors'
import { useFormatters } from '../../composables/useFormatters'
import type { Order, OrderStatus, StoreProduct } from '../../types/models'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const notify = useNotify()
const confirm = useConfirm()
const errors = useFormErrors()
const fmt = useFormatters()
const list = usePaginatedList(ordersApi.list, { search: '', status: '' as OrderStatus | '' })

const STATUSES: OrderStatus[] = ['pending', 'paid', 'shipped', 'delivered', 'cancelled']
const statusOptions = computed(() => STATUSES.map((s) => ({ value: s, label: t(`status.order.${s}`) })))

let searchTimer: ReturnType<typeof setTimeout> | undefined
function debouncedSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => void list.search(), 300)
}
onUnmounted(() => clearTimeout(searchTimer))

/** Look of each next-status action: forward steps are filled, cancelling is a quiet outline */
const TRANSITION_STYLE: Record<OrderStatus, { icon: string; severity: 'success' | 'info' | 'warn' | 'danger' | 'secondary' }> = {
  pending: { icon: 'pi pi-clock', severity: 'secondary' },
  paid: { icon: 'pi pi-wallet', severity: 'success' },
  shipped: { icon: 'pi pi-truck', severity: 'info' },
  delivered: { icon: 'pi pi-check-circle', severity: 'success' },
  cancelled: { icon: 'pi pi-times', severity: 'danger' }
}

function transitionStyle(status: OrderStatus) {
  return TRANSITION_STYLE[status]
}

/** `${orderId}:${status}` of the action in flight, so only that button spins */
const busyKey = ref<string | null>(null)

async function setStatus(order: Order, status: OrderStatus) {
  busyKey.value = `${order.id}:${status}`
  try {
    await ordersApi.setStatus(order.id, status)
    await list.load()
  } catch (err) {
    notify.error(err)
  } finally {
    busyKey.value = null
  }
}

/** Cancelling cannot be undone, so it asks first */
function confirmCancel(order: Order) {
  confirm.require({
    header: t('common.confirmDeleteTitle'),
    message: t('marketplace.orders.confirmCancel', { number: order.number }),
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: t('common.cancel'), text: true },
    acceptProps: { label: t('marketplace.orders.to.cancelled'), severity: 'danger' },
    accept: () => setStatus(order, 'cancelled')
  })
}

// --- new order dialog ------------------------------------------------------
const dialogOpen = ref(false)
const saving = ref(false)
const products = ref<StoreProduct[]>([])
const form = reactive({
  customer_name: '',
  customer_email: '',
  items: [{ product_id: null as number | null, quantity: 1 as number | null }]
})

// Validation errors for any line (items.0.product_id, ...) or the whole list
const itemsError = computed(() => Object.entries(errors.errors.value).find(([key]) => key === 'items' || key.startsWith('items.'))?.[1]?.[0])

const estimatedTotal = computed(() => {
  const lines = form.items
    .map((line) => ({ product: products.value.find((p) => p.id === line.product_id), quantity: line.quantity ?? 0 }))
    .filter((l): l is { product: StoreProduct; quantity: number } => !!l.product)
  if (!lines.length) return null
  const cents = lines.reduce((sum, l) => sum + l.product.price_cents * l.quantity, 0)
  return fmt.money(cents, lines[0].product.currency)
})

async function openCreate() {
  errors.clear()
  Object.assign(form, { customer_name: '', customer_email: '', items: [{ product_id: null, quantity: 1 }] })
  dialogOpen.value = true
  try {
    products.value = (await productsApi.list({ active: 1, per_page: 100 })).data
  } catch (err) {
    notify.error(err)
  }
}

async function save() {
  saving.value = true
  errors.clear()
  try {
    const order = await ordersApi.create({
      customer_name: form.customer_name,
      customer_email: form.customer_email || null,
      items: form.items
        .filter((line) => line.product_id !== null)
        .map((line) => ({ product_id: line.product_id as number, quantity: line.quantity ?? 1 }))
    })
    notify.success(t('marketplace.orders.placed', { number: order.number }))
    dialogOpen.value = false
    await list.load()
  } catch (err) {
    if (!errors.capture(err)) notify.error(err)
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await list.load()
  if (route.query.new === '1') {
    void openCreate()
    void router.replace({ query: {} })
  }
})
</script>

<style scoped>
.order-line {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  margin-bottom: 0.5rem;
}
.order-line__product {
  flex: 1;
  min-width: 0;
}
.order-line__qty {
  width: 9rem;
}
.order-line__meta {
  margin-left: 0.5rem;
  font-size: 0.85rem;
}
/* Actions column: centred pill buttons */
:deep(th.col-center .p-datatable-column-header-content) {
  justify-content: center;
}
:deep(td.col-center) {
  text-align: center;
}
.order-actions {
  display: inline-flex;
  flex-wrap: nowrap;
  justify-content: center;
  gap: 0.4rem;
}
.order-action {
  padding: 0.35rem 0.8rem;
  font-size: 0.82rem;
  white-space: nowrap;
  transition: transform 120ms ease, box-shadow 120ms ease;
}
.order-action:not(:disabled):hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 14px rgba(15, 23, 42, 0.12);
}
.order-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}
</style>
