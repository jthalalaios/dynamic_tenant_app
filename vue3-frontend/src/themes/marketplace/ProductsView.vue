<template>
  <main class="page">
    <PageHeader :eyebrow="t('nav.products')" :title="t('marketplace.products.title')" :subtitle="t('marketplace.products.subtitle')">
      <template #actions>
        <Button v-if="$can('products.create')" :label="t('marketplace.dashboard.newProduct')" icon="pi pi-plus" @click="openCreate" />
      </template>
    </PageHeader>

    <BaseCard>
      <div class="table-toolbar">
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="list.filters.search" :placeholder="t('marketplace.products.search')" @input="debouncedSearch" />
        </IconField>
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
        <Column field="sku" header="SKU" />
        <Column field="name" :header="t('marketplace.products.name')" />
        <Column :header="t('marketplace.products.category')">
          <template #body="{ data }">{{ data.category ?? '—' }}</template>
        </Column>
        <Column :header="t('marketplace.products.price')">
          <template #body="{ data }">{{ fmt.money(data.price_cents, data.currency) }}</template>
        </Column>
        <Column :header="t('marketplace.products.stock')">
          <template #body="{ data }">
            <Tag :value="String(data.stock)" :severity="data.stock === 0 ? 'danger' : data.stock < 10 ? 'warn' : 'success'" rounded />
          </template>
        </Column>
        <Column :header="t('common.status')">
          <template #body="{ data }">
            <Tag :value="data.is_active ? t('common.active') : t('common.inactive')" :severity="data.is_active ? 'success' : 'secondary'" rounded />
          </template>
        </Column>
        <Column :header="t('common.actions')" headerClass="row-actions">
          <template #body="{ data }">
            <div class="row-actions">
              <Button v-if="$can('products.update')" icon="pi pi-pencil" text rounded size="small" :aria-label="t('common.edit')" @click="openEdit(data)" />
              <Button v-if="$can('products.delete')" icon="pi pi-trash" text rounded size="small" severity="danger" :aria-label="t('common.delete')" @click="confirmDelete(data)" />
            </div>
          </template>
        </Column>
      </DataTable>
    </BaseCard>

    <Dialog v-model:visible="dialogOpen" modal :header="editing ? t('marketplace.products.edit') : t('marketplace.dashboard.newProduct')" :style="{ width: 'min(640px, 95vw)' }">
      <form id="product-form" class="dialog-form" @submit.prevent="save" novalidate>
        <div class="field">
          <label for="sku">SKU</label>
          <InputText id="sku" v-model="form.sku" :invalid="!!errors.first('sku')" fluid />
          <small v-if="errors.first('sku')" class="field-error">{{ errors.first('sku') }}</small>
        </div>
        <div class="field">
          <label for="name">{{ t('marketplace.products.name') }}</label>
          <InputText id="name" v-model="form.name" :invalid="!!errors.first('name')" fluid />
          <small v-if="errors.first('name')" class="field-error">{{ errors.first('name') }}</small>
        </div>
        <div class="field">
          <label for="price">{{ t('marketplace.products.price') }}</label>
          <InputNumber inputId="price" v-model="form.price" mode="currency" :currency="form.currency" :locale="prefs.intl" :min="0" :invalid="!!errors.first('price_cents')" fluid />
          <small v-if="errors.first('price_cents')" class="field-error">{{ errors.first('price_cents') }}</small>
        </div>
        <div class="field">
          <label for="currency">{{ t('marketplace.products.currency') }}</label>
          <Select inputId="currency" v-model="form.currency" :options="['EUR', 'USD', 'GBP']" fluid />
        </div>
        <div class="field">
          <label for="stock">{{ t('marketplace.products.stock') }}</label>
          <InputNumber inputId="stock" v-model="form.stock" :min="0" showButtons fluid />
        </div>
        <div class="field">
          <label for="category">{{ t('marketplace.products.category') }}</label>
          <InputText id="category" v-model="form.category" fluid />
        </div>
        <div class="field field--full">
          <label for="description">{{ t('marketplace.products.description') }}</label>
          <Textarea id="description" v-model="form.description" rows="3" autoResize fluid />
        </div>
        <div class="field field--full toggle-field">
          <ToggleSwitch inputId="is_active" v-model="form.is_active" />
          <label for="is_active">{{ t('common.active') }}</label>
        </div>
      </form>
      <template #footer>
        <Button :label="t('common.cancel')" text @click="dialogOpen = false" />
        <Button :label="t('common.save')" type="submit" form="product-form" :loading="saving" />
      </template>
    </Dialog>
  </main>
</template>

<script lang="ts" setup>
import { onMounted, onUnmounted, reactive, ref } from 'vue'
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
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'
import BaseCard from '../../components/shared/cards/BaseCard.vue'
import PageHeader from '../../components/shared/PageHeader.vue'
import { productsApi } from '../../api'
import { usePreferencesStore } from '../../stores'
import { usePaginatedList } from '../../composables/usePaginatedList'
import { useNotify } from '../../composables/useNotify'
import { useFormErrors } from '../../composables/useFormErrors'
import { useFormatters } from '../../composables/useFormatters'
import { toCents } from '../../helpers/money'
import type { StoreProduct } from '../../types/models'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const notify = useNotify()
const errors = useFormErrors()
const fmt = useFormatters()
const prefs = usePreferencesStore()
const list = usePaginatedList(productsApi.list, { search: '' })

let searchTimer: ReturnType<typeof setTimeout> | undefined
function debouncedSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => void list.search(), 300)
}
onUnmounted(() => clearTimeout(searchTimer))

const dialogOpen = ref(false)
const saving = ref(false)
const editing = ref<StoreProduct | null>(null)
const emptyForm = () => ({ sku: '', name: '', price: 0 as number | null, currency: 'EUR', stock: 0 as number | null, category: '', description: '', is_active: true })
const form = reactive(emptyForm())

function openCreate() {
  editing.value = null
  errors.clear()
  Object.assign(form, emptyForm())
  dialogOpen.value = true
}

function openEdit(product: StoreProduct) {
  editing.value = product
  errors.clear()
  Object.assign(form, {
    sku: product.sku,
    name: product.name,
    price: product.price_cents / 100,
    currency: product.currency,
    stock: product.stock,
    category: product.category ?? '',
    description: product.description ?? '',
    is_active: product.is_active
  })
  dialogOpen.value = true
}

async function save() {
  saving.value = true
  errors.clear()
  const payload = {
    sku: form.sku,
    name: form.name,
    price_cents: toCents(form.price),
    currency: form.currency,
    stock: form.stock ?? 0,
    category: form.category || null,
    description: form.description || null,
    is_active: form.is_active
  }
  try {
    if (editing.value) await productsApi.update(editing.value.id, payload)
    else await productsApi.create(payload)
    notify.success(t('common.saved'))
    dialogOpen.value = false
    await list.load()
  } catch (err) {
    if (!errors.capture(err)) notify.error(err)
  } finally {
    saving.value = false
  }
}

function confirmDelete(product: StoreProduct) {
  confirm.require({
    header: t('common.confirmDeleteTitle'),
    message: t('marketplace.products.confirmDelete', { name: product.name }),
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: t('common.cancel'), text: true },
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    accept: async () => {
      try {
        await productsApi.remove(product.id)
        notify.success(t('common.deleted'))
        await list.load()
      } catch (err) {
        notify.error(err)
      }
    }
  })
}

onMounted(async () => {
  await list.load()
  if (route.query.new === '1') {
    openCreate()
    void router.replace({ query: {} })
  }
})
</script>

<style scoped>
.toggle-field {
  display: flex !important;
  align-items: center;
  gap: 0.6rem;
}
</style>
