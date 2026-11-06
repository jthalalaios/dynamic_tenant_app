export type TenantType = 'doctor' | 'marketplace'

export interface Tenant {
  id: number
  name: string
  slug: string
  type: TenantType
  timezone: string
  locale: string
}

export interface SessionUser {
  id: number
  name: string
  email: string
  timezone: string | null
  locale: string | null
  last_login_at: string | null
  email_verified_at: string | null
  /** True while app features are locked until the email is verified */
  must_verify_email: boolean
}

export interface AccessSnapshot {
  is_admin: boolean
  roles: string[]
  permissions: string[]
}

/** Bootstrap payload from GET /auth/me (and embedded in login/register responses) */
export interface Session {
  user: SessionUser
  tenant: Tenant
  access: AccessSnapshot
  /** Effective preferences as resolved by the API */
  preferences: { timezone: string; locale: string }
}

export interface TokenResponse {
  token_type: 'Bearer'
  access_token: string
  /** Seconds until the token expires */
  expires_in: number
  session: Session
}

export interface Role {
  id: number
  name: string
  slug: string
  description: string | null
  is_admin: boolean
  is_system: boolean
  permissions?: string[]
  users_count?: number
}

export interface TeamMember {
  id: number
  name: string
  email: string
  timezone: string | null
  locale: string | null
  is_active: boolean
  last_login_at: string | null
  created_at: string
  roles?: Role[]
}

export interface Permission {
  id: number
  name: string
  group: string
  scope: string
  description: string | null
}

export interface PermissionGroup {
  group: string
  permissions: Permission[]
}

export interface Patient {
  id: number
  first_name: string
  last_name: string
  full_name: string
  email: string | null
  phone: string | null
  date_of_birth: string | null
  gender: 'female' | 'male' | 'other' | null
  notes: string | null
  created_at: string
}

export type AppointmentStatus = 'scheduled' | 'checked_in' | 'in_progress' | 'completed' | 'cancelled' | 'no_show'
export type AppointmentReason = 'consultation' | 'follow_up' | 'new_patient' | 'exam'

export interface UserRef {
  id: number
  name: string
}

export interface Appointment {
  id: number
  /** ISO-8601 in the viewer's timezone */
  scheduled_at: string
  duration_minutes: number
  reason: AppointmentReason
  status: AppointmentStatus
  allowed_transitions: AppointmentStatus[]
  checked_in_at: string | null
  completed_at: string | null
  notes: string | null
  patient?: Patient
  doctor?: UserRef | null
  checked_in_by?: UserRef | null
}

export interface Presence {
  online: boolean
  started_at: string | null
  last_heartbeat_at: string | null
  timeout_minutes: number
}

export interface DoctorDashboard {
  stats: {
    scheduled: number
    waiting: number
    checked_in: number
    completed: number
    patients: number
    staff_online: number
  }
  queue: Appointment[]
}

export interface ReportSummary {
  days: number
  total: number
  by_status: { label: AppointmentStatus; total: number }[]
  by_reason: { label: AppointmentReason; total: number }[]
}

export interface StoreProduct {
  id: number
  sku: string
  name: string
  description: string | null
  category: string | null
  price_cents: number
  currency: string
  stock: number
  is_active: boolean
  created_at: string
}

export type OrderStatus = 'pending' | 'paid' | 'shipped' | 'delivered' | 'cancelled'

export interface OrderItem {
  id: number
  product_id: number | null
  product_name: string
  unit_price_cents: number
  quantity: number
  line_total_cents: number
}

export interface Order {
  id: number
  number: string
  customer_name: string
  customer_email: string | null
  status: OrderStatus
  allowed_transitions: OrderStatus[]
  total_cents: number
  currency: string
  items_count?: number
  items?: OrderItem[]
  placed_by?: UserRef | null
  created_at: string
}

export interface MarketplaceDashboard {
  stats: {
    products: number
    active_products: number
    units_in_stock: number
    low_stock: number
    orders_today: number
    pending_orders: number
    revenue_cents: number
  }
  low_stock: StoreProduct[]
  recent_orders: Order[]
}

export interface TopProduct {
  name: string
  units: number
  revenue_cents: number
}

/** Public card of a chat participant (messages, mutes, members, presence) */
export interface ChatMember {
  id: number
  name: string
  is_admin: boolean
}

/** Who may enter a room: everyone, administrators only, or members of the listed roles */
export type ChatAudience = 'everyone' | 'admins' | 'roles'

export interface ChatRoleRef {
  id: number
  name: string
  slug: string
}

/** A workspace role offered when choosing a room's audience */
export interface ChatRoleOption extends ChatRoleRef {
  is_admin: boolean
  /** Non-admins may only open rooms to roles they hold */
  assignable: boolean
}

export interface ChatMessage {
  id: number
  room_id: number
  body: string
  /** null once the author's account was removed */
  user: ChatMember | null
  created_at: string
}

export interface ChatRoom {
  id: number
  name: string
  slug: string
  description: string | null
  /** PrimeIcons name without the `pi-` prefix */
  icon: string
  /** The workspace's Global room: always open to everyone, cannot be deleted */
  is_default: boolean
  /** Locked: only administrators may post */
  is_locked: boolean
  audience: ChatAudience
  /** Allowed roles when the audience is "roles" */
  roles?: ChatRoleRef[]
  /** Viewer specific, only on the room list */
  unread_count?: number
  last_message?: ChatMessage | null
  created_at: string
}

export interface ChatMute {
  id: number
  user: ChatMember | null
  /** null = muted in every room */
  room_id: number | null
  reason: string | null
  /** null = until someone lifts it */
  expires_at: string | null
  muted_by: ChatMember | null
  created_at: string
}
