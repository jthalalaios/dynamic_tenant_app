<template>
  <main class="page">
    <PageHeader :eyebrow="t('nav.appointments')" :title="t('doctor.appointments.title')" :subtitle="t('doctor.appointments.subtitle')">
      <template #actions>
        <PresenceToggle v-if="$can('presence.toggle')" />
        <Button v-if="$can('appointments.create')" :label="t('doctor.appointments.new')" icon="pi pi-plus" @click="openCreate" />
      </template>
    </PageHeader>

    <BaseCard>
      <div class="table-toolbar">
        <div class="table-toolbar__filters">
          <DatePicker v-model="day" dateFormat="yy-mm-dd" showIcon showButtonBar :placeholder="t('doctor.appointments.anyDay')" @update:modelValue="applyFilters" />
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
        <Column :header="t('doctor.appointments.when')">
          <template #body="{ data }">{{ fmt.dateTime(data.scheduled_at) }}</template>
        </Column>
        <Column :header="t('nav.patients')">
          <template #body="{ data }">{{ data.patient?.full_name }}</template>
        </Column>
        <Column :header="t('doctor.appointments.reason')">
          <template #body="{ data }">{{ t(`doctor.reasons.${snakeToCamel(data.reason)}`) }}</template>
        </Column>
        <Column :header="t('common.status')">
          <template #body="{ data }"><StatusTag kind="appointment" :status="data.status" /></template>
        </Column>
        <Column :header="t('doctor.appointments.checkedInBy')">
          <template #body="{ data }">
            <span v-if="data.checked_in_by">{{ data.checked_in_by.name }} · {{ fmt.time(data.checked_in_at) }}</span>
            <span v-else class="muted">—</span>
          </template>
        </Column>
        <Column :header="t('common.actions')" headerClass="row-actions">
          <template #body="{ data }">
            <div class="row-actions">
              <Button
                v-if="$can('appointments.check-in') && data.allowed_transitions.includes('checked_in')"
                icon="pi pi-check"
                size="small"
                :label="t('cards.checkIn')"
                :disabled="!presence.online"
                :title="presence.online ? undefined : t('presence.offlineBanner')"
                :loading="busyId === data.id"
                @click="checkIn(data)"
              />
              <Button
                v-for="next in data.allowed_transitions.filter((s: AppointmentStatus) => s !== 'checked_in')"
                v-show="$can('appointments.update')"
                :key="next"
                size="small"
                text
                :label="t(`doctor.appointments.to.${snakeToCamel(next)}`)"
                :loading="busyId === data.id"
                @click="setStatus(data, next)"
              />
              <Button v-if="$can('appointments.update') && data.status === 'scheduled'" icon="pi pi-pencil" text rounded size="small" :aria-label="t('common.edit')" @click="openEdit(data)" />
              <Button v-if="$can('appointments.delete')" icon="pi pi-trash" text rounded size="small" severity="danger" :aria-label="t('common.delete')" @click="confirmDelete(data)" />
            </div>
          </template>
        </Column>
      </DataTable>
    </BaseCard>

    <Dialog v-model:visible="dialogOpen" modal :header="editing ? t('doctor.appointments.edit') : t('doctor.appointments.new')" :style="{ width: 'min(640px, 95vw)' }">
      <form id="appointment-form" class="dialog-form" @submit.prevent="save" novalidate>
        <div class="field field--full">
          <label for="patient">{{ t('nav.patients') }}</label>
          <AutoComplete
            inputId="patient"
            v-model="form.patient"
            :suggestions="patientSuggestions"
            optionLabel="full_name"
            forceSelection
            dropdown
            :invalid="!!errors.first('patient_id')"
            fluid
            @complete="searchPatients"
          />
          <small v-if="errors.first('patient_id')" class="field-error">{{ errors.first('patient_id') }}</small>
        </div>
        <div class="field">
          <label for="scheduled_at">{{ t('doctor.appointments.when') }} <span class="muted">({{ prefs.timezone }})</span></label>
          <DatePicker inputId="scheduled_at" v-model="form.scheduledAt" showTime hourFormat="24" :stepMinute="5" dateFormat="yy-mm-dd" :invalid="!!errors.first('scheduled_at')" fluid />
          <small v-if="errors.first('scheduled_at')" class="field-error">{{ errors.first('scheduled_at') }}</small>
        </div>
        <div class="field">
          <label for="duration">{{ t('doctor.appointments.duration') }}</label>
          <InputNumber inputId="duration" v-model="form.duration" :min="5" :max="480" :step="5" showButtons suffix=" min" fluid />
        </div>
        <div class="field field--full">
          <label for="reason">{{ t('doctor.appointments.reason') }}</label>
          <Select inputId="reason" v-model="form.reason" :options="reasonOptions" optionLabel="label" optionValue="value" :invalid="!!errors.first('reason')" fluid />
          <small v-if="errors.first('reason')" class="field-error">{{ errors.first('reason') }}</small>
        </div>
        <div class="field field--full">
          <label for="notes">{{ t('common.notes') }}</label>
          <Textarea id="notes" v-model="form.notes" rows="3" autoResize fluid />
        </div>
      </form>
      <template #footer>
        <Button :label="t('common.cancel')" text @click="dialogOpen = false" />
        <Button :label="t('common.save')" type="submit" form="appointment-form" :loading="saving" />
      </template>
    </Dialog>
  </main>
</template>

<script lang="ts" setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import AutoComplete, { type AutoCompleteCompleteEvent } from 'primevue/autocomplete'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import BaseCard from '../../components/shared/cards/BaseCard.vue'
import PageHeader from '../../components/shared/PageHeader.vue'
import StatusTag from '../../components/shared/StatusTag.vue'
import PresenceToggle from './PresenceToggle.vue'
import { ApiError, appointmentsApi, patientsApi } from '../../api'
import { usePreferencesStore, usePresenceStore } from '../../stores'
import { usePaginatedList } from '../../composables/usePaginatedList'
import { useNotify } from '../../composables/useNotify'
import { useFormErrors } from '../../composables/useFormErrors'
import { useFormatters } from '../../composables/useFormatters'
import { dateToIsoDay, dateToWallTime, todayIn, wallTimeToDate } from '../../helpers/timezone'
import { snakeToCamel } from '../../helpers/text'
import type { Appointment, AppointmentReason, AppointmentStatus, Patient } from '../../types/models'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const notify = useNotify()
const errors = useFormErrors()
const fmt = useFormatters()
const prefs = usePreferencesStore()
const presence = usePresenceStore()

const STATUSES: AppointmentStatus[] = ['scheduled', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show']
const REASONS: AppointmentReason[] = ['consultation', 'follow_up', 'new_patient', 'exam']

const statusOptions = computed(() => STATUSES.map((s) => ({ value: s, label: t(`status.appointment.${snakeToCamel(s)}`) })))
const reasonOptions = computed(() => REASONS.map((r) => ({ value: r, label: t(`doctor.reasons.${snakeToCamel(r)}`) })))

// Default to "today" in the user's timezone, not the browser's
const day = ref<Date | null>(wallTimeToDate(`${todayIn(prefs.timezone)}T00:00`))
const list = usePaginatedList(appointmentsApi.list, { date: day.value ? dateToIsoDay(day.value) : '', status: '' as AppointmentStatus | '' })

function applyFilters() {
  list.filters.date = day.value ? dateToIsoDay(day.value) : ''
  void list.search()
}

const busyId = ref<number | null>(null)

async function checkIn(appointment: Appointment) {
  busyId.value = appointment.id
  try {
    await appointmentsApi.checkIn(appointment.id)
    notify.success(t('doctor.dashboard.checkedInToast', { name: appointment.patient?.full_name ?? '' }))
    await list.load()
  } catch (err) {
    if (err instanceof ApiError && err.code === 'user_offline') await presence.refresh().catch(() => {})
    notify.error(err)
  } finally {
    busyId.value = null
  }
}

async function setStatus(appointment: Appointment, status: AppointmentStatus) {
  busyId.value = appointment.id
  try {
    await appointmentsApi.setStatus(appointment.id, status)
    await list.load()
  } catch (err) {
    notify.error(err)
  } finally {
    busyId.value = null
  }
}

function confirmDelete(appointment: Appointment) {
  confirm.require({
    header: t('common.confirmDeleteTitle'),
    message: t('doctor.appointments.confirmDelete', { name: appointment.patient?.full_name ?? '' }),
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: t('common.cancel'), text: true },
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    accept: async () => {
      try {
        await appointmentsApi.remove(appointment.id)
        notify.success(t('common.deleted'))
        await list.load()
      } catch (err) {
        notify.error(err)
      }
    }
  })
}

// --- create / edit dialog -------------------------------------------------
const dialogOpen = ref(false)
const saving = ref(false)
const editing = ref<Appointment | null>(null)
const patientSuggestions = ref<Patient[]>([])
const form = reactive({
  patient: null as Patient | null,
  scheduledAt: null as Date | null,
  duration: 30,
  reason: 'consultation' as AppointmentReason,
  notes: ''
})

async function searchPatients(event: AutoCompleteCompleteEvent) {
  try {
    patientSuggestions.value = (await patientsApi.list({ search: event.query, per_page: 20 })).data
  } catch (err) {
    notify.error(err)
  }
}

function openCreate() {
  editing.value = null
  errors.clear()
  Object.assign(form, { patient: null, scheduledAt: null, duration: 30, reason: 'consultation', notes: '' })
  dialogOpen.value = true
}

function openEdit(appointment: Appointment) {
  editing.value = appointment
  errors.clear()
  Object.assign(form, {
    patient: appointment.patient ?? null,
    scheduledAt: wallTimeToDate(appointment.scheduled_at),
    duration: appointment.duration_minutes,
    reason: appointment.reason,
    notes: appointment.notes ?? ''
  })
  dialogOpen.value = true
}

async function save() {
  saving.value = true
  errors.clear()
  const payload = {
    patient_id: form.patient?.id ?? 0,
    // Wall time; the API reads it in the user's timezone
    scheduled_at: form.scheduledAt ? dateToWallTime(form.scheduledAt) : '',
    duration_minutes: form.duration,
    reason: form.reason,
    notes: form.notes || null
  }
  try {
    if (editing.value) await appointmentsApi.update(editing.value.id, payload)
    else await appointmentsApi.create(payload)
    notify.success(t('common.saved'))
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
  // Deep link from the dashboard's "New visit" button
  if (route.query.new === '1') {
    openCreate()
    void router.replace({ query: {} })
  }
})
</script>
