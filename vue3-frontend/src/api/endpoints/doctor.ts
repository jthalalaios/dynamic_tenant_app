import { http, resource } from '../http'
import type { Resource } from '../../types/api'
import type {
  Appointment,
  AppointmentReason,
  AppointmentStatus,
  DoctorDashboard,
  Patient,
  Presence,
  ReportSummary
} from '../../types/models'

export type PatientPayload = Partial<Omit<Patient, 'id' | 'full_name' | 'created_at'>>

export interface AppointmentPayload {
  patient_id: number
  doctor_id?: number | null
  /** Local wall time `YYYY-MM-DD HH:mm:ss`, interpreted in the user's timezone by the API */
  scheduled_at: string
  duration_minutes?: number
  reason: AppointmentReason
  notes?: string | null
}

export const patientsApi = resource<Patient, PatientPayload>('/doctor/patients')

export const appointmentsApi = {
  ...resource<Appointment, AppointmentPayload>('/doctor/appointments'),
  checkIn: (id: number) => http.post<Resource<Appointment>>(`/doctor/appointments/${id}/check-in`).then((r) => r.data),
  setStatus: (id: number, status: AppointmentStatus) =>
    http.patch<Resource<Appointment>>(`/doctor/appointments/${id}/status`, { status }).then((r) => r.data)
}

export const presenceApi = {
  show: () => http.get<Resource<Presence>>('/doctor/presence').then((r) => r.data),
  online: () => http.post<Resource<Presence>>('/doctor/presence/online').then((r) => r.data),
  heartbeat: () => http.post<Resource<Presence>>('/doctor/presence/heartbeat').then((r) => r.data),
  offline: () => http.post<Resource<Presence>>('/doctor/presence/offline').then((r) => r.data)
}

export const doctorApi = {
  dashboard: () => http.get<Resource<DoctorDashboard>>('/doctor/dashboard').then((r) => r.data),
  reportSummary: (days = 30) => http.get<Resource<ReportSummary>>('/doctor/reports/summary', { days }).then((r) => r.data)
}
