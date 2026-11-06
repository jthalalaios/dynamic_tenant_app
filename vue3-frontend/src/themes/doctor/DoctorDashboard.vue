<template>
  <main class="page">
    <PageHeader :eyebrow="t('doctor.dashboard.eyebrow')" :title="t('doctor.dashboard.title')" :subtitle="t('doctor.dashboard.subtitle')">
      <template #actions>
        <PresenceToggle v-if="$can('presence.toggle')" />
        <Button v-if="$can('appointments.create')" :label="t('doctor.dashboard.newVisit')" icon="pi pi-plus" @click="router.push({ name: 'clinic.appointments', query: { new: '1' } })" />
      </template>
    </PageHeader>

    <Message v-if="canCheckIn && !presence.online" severity="warn" :closable="false" icon="pi pi-wifi">
      {{ t('presence.offlineBanner') }}
    </Message>

    <section class="card-grid card-grid--stats" :aria-label="t('home.sections.glance')">
      <template v-if="dashboard">
        <StatCard v-for="stat in stats" :key="stat.label" v-bind="stat" />
      </template>
      <template v-else>
        <Skeleton v-for="n in 4" :key="n" height="9rem" borderRadius="18px" />
      </template>
    </section>

    <section>
      <div class="section-head">
        <h2>{{ t('doctor.dashboard.queue') }}</h2>
        <p>{{ t('doctor.dashboard.queueSubtitle') }}</p>
      </div>
      <div v-if="queue.length" class="card-grid">
        <VisitorCard
          v-for="v in queue"
          :key="v.id"
          :visitor="v"
          :checkInDisabled="!canCheckIn || !presence.online"
          :checkInHint="canCheckIn ? t('presence.offlineBanner') : t('errors.forbidden')"
          :loading="checkingIn === v.id"
          @check-in="checkIn"
          @details="showDetails"
        />
      </div>
      <BaseCard v-else-if="dashboard" padding="lg">
        <p class="muted">{{ t('doctor.dashboard.emptyQueue') }}</p>
      </BaseCard>
    </section>
  </main>
</template>

<script lang="ts" setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import BaseCard from '../../components/shared/cards/BaseCard.vue'
import StatCard from '../../components/shared/cards/StatCard.vue'
import PageHeader from '../../components/shared/PageHeader.vue'
import VisitorCard from './VisitorCard.vue'
import PresenceToggle from './PresenceToggle.vue'
import { ApiError, appointmentsApi, doctorApi } from '../../api'
import { useAccessStore, usePresenceStore } from '../../stores'
import { useNotify } from '../../composables/useNotify'
import { useFormatters } from '../../composables/useFormatters'
import { snakeToCamel } from '../../helpers/text'
import type { Visitor } from '../../types/app'
import type { Appointment, DoctorDashboard } from '../../types/models'

const { t } = useI18n()
const router = useRouter()
const notify = useNotify()
const fmt = useFormatters()
const access = useAccessStore()
const presence = usePresenceStore()

const dashboard = ref<DoctorDashboard | null>(null)
const checkingIn = ref<number | null>(null)
const canCheckIn = computed(() => access.can('appointments.check-in'))

const ACTIVE_STATUSES: Appointment['status'][] = ['scheduled', 'checked_in', 'in_progress']

/** Today's active appointments as visitor cards */
const queue = computed<Visitor[]>(() =>
  (dashboard.value?.queue ?? [])
    .filter((a) => ACTIVE_STATUSES.includes(a.status))
    .map((a) => ({
      id: a.id,
      name: a.patient?.full_name ?? '—',
      time: fmt.time(a.scheduled_at),
      reason: snakeToCamel(a.reason),
      status: a.status === 'scheduled' ? 'waiting' : 'checkedIn'
    }))
)

const stats = computed(() => {
  const s = dashboard.value?.stats
  if (!s) return []
  return [
    { label: t('doctor.dashboard.stats.total'), value: s.scheduled, caption: t('doctor.dashboard.stats.totalCaption'), icon: 'pi pi-calendar' },
    { label: t('doctor.dashboard.stats.waiting'), value: s.waiting, caption: t('doctor.dashboard.stats.waitingCaption'), icon: 'pi pi-hourglass' },
    { label: t('doctor.dashboard.stats.checkedIn'), value: s.checked_in, caption: t('doctor.dashboard.stats.checkedInCaption'), icon: 'pi pi-check-circle' },
    { label: t('doctor.dashboard.stats.staffOnline'), value: s.staff_online, caption: t('doctor.dashboard.stats.staffOnlineCaption'), icon: 'pi pi-wifi' }
  ]
})

async function load() {
  try {
    dashboard.value = await doctorApi.dashboard()
  } catch (err) {
    notify.error(err)
  }
}

async function checkIn(visitor: Visitor) {
  checkingIn.value = visitor.id
  try {
    await appointmentsApi.checkIn(visitor.id)
    notify.success(t('doctor.dashboard.checkedInToast', { name: visitor.name }), t('cards.checkedIn'))
    await load()
  } catch (err) {
    // The server is the authority on presence: resync if it says we're offline
    if (err instanceof ApiError && err.code === 'user_offline') await presence.refresh().catch(() => {})
    notify.error(err)
  } finally {
    checkingIn.value = null
  }
}

function showDetails(visitor: Visitor) {
  notify.info(`${visitor.time} • ${t(`doctor.reasons.${visitor.reason}`)}`, visitor.name)
}

onMounted(load)
</script>
