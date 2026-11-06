<template>
  <main class="page">
    <PageHeader :eyebrow="t('nav.patients')" :title="t('doctor.patients.title')" :subtitle="t('doctor.patients.subtitle')">
      <template #actions>
        <Button v-if="$can('patients.create')" :label="t('doctor.patients.new')" icon="pi pi-user-plus" @click="openCreate" />
      </template>
    </PageHeader>

    <BaseCard>
      <div class="table-toolbar">
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="list.filters.search" :placeholder="t('doctor.patients.search')" @input="debouncedSearch" />
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
        <Column field="full_name" :header="t('auth.name')" />
        <Column :header="t('doctor.patients.dateOfBirth')">
          <template #body="{ data }">{{ data.date_of_birth ?? '—' }}</template>
        </Column>
        <Column field="phone" :header="t('doctor.patients.phone')" />
        <Column field="email" :header="t('auth.email')" />
        <Column :header="t('common.actions')" headerClass="row-actions">
          <template #body="{ data }">
            <div class="row-actions">
              <Button v-if="$can('patients.update')" icon="pi pi-pencil" text rounded size="small" :aria-label="t('common.edit')" @click="openEdit(data)" />
              <Button v-if="$can('patients.delete')" icon="pi pi-trash" text rounded size="small" severity="danger" :aria-label="t('common.delete')" @click="confirmDelete(data)" />
            </div>
          </template>
        </Column>
      </DataTable>
    </BaseCard>

    <Dialog v-model:visible="dialogOpen" modal :header="editing ? t('doctor.patients.edit') : t('doctor.patients.new')" :style="{ width: 'min(640px, 95vw)' }">
      <form id="patient-form" class="dialog-form" @submit.prevent="save" novalidate>
        <div class="field">
          <label for="first_name">{{ t('doctor.patients.firstName') }}</label>
          <InputText id="first_name" v-model="form.first_name" :invalid="!!errors.first('first_name')" fluid />
          <small v-if="errors.first('first_name')" class="field-error">{{ errors.first('first_name') }}</small>
        </div>
        <div class="field">
          <label for="last_name">{{ t('doctor.patients.lastName') }}</label>
          <InputText id="last_name" v-model="form.last_name" :invalid="!!errors.first('last_name')" fluid />
          <small v-if="errors.first('last_name')" class="field-error">{{ errors.first('last_name') }}</small>
        </div>
        <div class="field">
          <label for="p_email">{{ t('auth.email') }}</label>
          <InputText id="p_email" v-model="form.email" type="email" :invalid="!!errors.first('email')" fluid />
          <small v-if="errors.first('email')" class="field-error">{{ errors.first('email') }}</small>
        </div>
        <div class="field">
          <label for="phone">{{ t('doctor.patients.phone') }}</label>
          <InputText id="phone" v-model="form.phone" type="tel" fluid />
        </div>
        <div class="field">
          <label for="dob">{{ t('doctor.patients.dateOfBirth') }}</label>
          <DatePicker inputId="dob" v-model="form.dob" dateFormat="yy-mm-dd" :maxDate="new Date()" showIcon :invalid="!!errors.first('date_of_birth')" fluid />
          <small v-if="errors.first('date_of_birth')" class="field-error">{{ errors.first('date_of_birth') }}</small>
        </div>
        <div class="field">
          <label for="gender">{{ t('doctor.patients.gender') }}</label>
          <Select inputId="gender" v-model="form.gender" :options="genderOptions" optionLabel="label" optionValue="value" showClear fluid />
        </div>
        <div class="field field--full">
          <label for="p_notes">{{ t('common.notes') }}</label>
          <Textarea id="p_notes" v-model="form.notes" rows="3" autoResize fluid />
        </div>
      </form>
      <template #footer>
        <Button :label="t('common.cancel')" text @click="dialogOpen = false" />
        <Button :label="t('common.save')" type="submit" form="patient-form" :loading="saving" />
      </template>
    </Dialog>
  </main>
</template>

<script lang="ts" setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import BaseCard from '../../components/shared/cards/BaseCard.vue'
import PageHeader from '../../components/shared/PageHeader.vue'
import { patientsApi } from '../../api'
import { usePaginatedList } from '../../composables/usePaginatedList'
import { useNotify } from '../../composables/useNotify'
import { useFormErrors } from '../../composables/useFormErrors'
import { dateToIsoDay, wallTimeToDate } from '../../helpers/timezone'
import type { Patient } from '../../types/models'

const { t } = useI18n()
const confirm = useConfirm()
const notify = useNotify()
const errors = useFormErrors()
const list = usePaginatedList(patientsApi.list, { search: '' })

let searchTimer: ReturnType<typeof setTimeout> | undefined
function debouncedSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => void list.search(), 300)
}
onUnmounted(() => clearTimeout(searchTimer))

const genderOptions = computed(() => (['female', 'male', 'other'] as const).map((g) => ({ value: g, label: t(`doctor.patients.genders.${g}`) })))

const dialogOpen = ref(false)
const saving = ref(false)
const editing = ref<Patient | null>(null)
const emptyForm = () => ({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  dob: null as Date | null,
  gender: null as Patient['gender'],
  notes: ''
})
const form = reactive(emptyForm())

function openCreate() {
  editing.value = null
  errors.clear()
  Object.assign(form, emptyForm())
  dialogOpen.value = true
}

function openEdit(patient: Patient) {
  editing.value = patient
  errors.clear()
  Object.assign(form, {
    first_name: patient.first_name,
    last_name: patient.last_name,
    email: patient.email ?? '',
    phone: patient.phone ?? '',
    dob: patient.date_of_birth ? wallTimeToDate(`${patient.date_of_birth}T00:00`) : null,
    gender: patient.gender,
    notes: patient.notes ?? ''
  })
  dialogOpen.value = true
}

async function save() {
  saving.value = true
  errors.clear()
  const payload = {
    first_name: form.first_name,
    last_name: form.last_name,
    email: form.email || null,
    phone: form.phone || null,
    date_of_birth: form.dob ? dateToIsoDay(form.dob) : null,
    gender: form.gender,
    notes: form.notes || null
  }
  try {
    if (editing.value) await patientsApi.update(editing.value.id, payload)
    else await patientsApi.create(payload)
    notify.success(t('common.saved'))
    dialogOpen.value = false
    await list.load()
  } catch (err) {
    if (!errors.capture(err)) notify.error(err)
  } finally {
    saving.value = false
  }
}

function confirmDelete(patient: Patient) {
  confirm.require({
    header: t('common.confirmDeleteTitle'),
    message: t('doctor.patients.confirmDelete', { name: patient.full_name }),
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: t('common.cancel'), text: true },
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    accept: async () => {
      try {
        await patientsApi.remove(patient.id)
        notify.success(t('common.deleted'))
        await list.load()
      } catch (err) {
        notify.error(err)
      }
    }
  })
}

onMounted(list.load)
</script>
