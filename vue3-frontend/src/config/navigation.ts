import type { PermissionCheck } from '../stores/access'
import type { TenantType } from '../types/models'

export interface NavLink {
  key: string
  /** i18n key */
  label: string
  icon: string
  to: string
  /** Shown when the user holds any of these permissions */
  permission?: PermissionCheck
}

export interface NavSection {
  key: string
  label: string
  icon: string
  items: NavLink[]
}

/** Live chat: same page for every tenant type, styled by the active theme. Last, so it never becomes the landing page */
const CHAT_LINK: NavLink = { key: 'chat', label: 'nav.chat', icon: 'pi pi-comments', to: '/chat', permission: 'chat.view' }

/** Day-to-day menu per tenant type; each entry mirrors its route's permission */
const MAIN_MENU: Record<TenantType, NavLink[]> = {
  doctor: [
    { key: 'dashboard', label: 'nav.dashboard', icon: 'pi pi-home', to: '/clinic', permission: 'appointments.view' },
    { key: 'appointments', label: 'nav.appointments', icon: 'pi pi-calendar', to: '/clinic/appointments', permission: 'appointments.view' },
    { key: 'patients', label: 'nav.patients', icon: 'pi pi-id-card', to: '/clinic/patients', permission: 'patients.view' },
    { key: 'reports', label: 'nav.reports', icon: 'pi pi-chart-bar', to: '/clinic/reports', permission: 'reports.view' },
    CHAT_LINK
  ],
  marketplace: [
    { key: 'dashboard', label: 'nav.dashboard', icon: 'pi pi-home', to: '/market', permission: ['products.view', 'orders.view'] },
    { key: 'products', label: 'nav.products', icon: 'pi pi-box', to: '/market/products', permission: 'products.view' },
    { key: 'orders', label: 'nav.orders', icon: 'pi pi-shopping-cart', to: '/market/orders', permission: 'orders.view' },
    { key: 'marketing', label: 'nav.marketing', icon: 'pi pi-megaphone', to: '/market/insights', permission: 'marketing.view' },
    CHAT_LINK
  ]
}

/** Administration menu, shared by both tenant types */
const ADMIN_SECTION: NavSection = {
  key: 'admin',
  label: 'nav.administration',
  icon: 'pi pi-shield',
  items: [
    { key: 'users', label: 'nav.users', icon: 'pi pi-users', to: '/admin/users', permission: 'users.view' },
    { key: 'roles', label: 'nav.roles', icon: 'pi pi-lock', to: '/admin/roles', permission: 'roles.view' }
  ]
}

export interface Navigation {
  main: NavLink[]
  admin: NavSection | null
}

/**
 * Menu for a user: admins (who hold every permission) see the administration
 * section; everyone else sees only the entries their roles allow.
 */
export function buildNavigation(type: TenantType, can: (check?: PermissionCheck) => boolean): Navigation {
  const main = MAIN_MENU[type].filter((item) => can(item.permission))
  const adminItems = ADMIN_SECTION.items.filter((item) => can(item.permission))

  return { main, admin: adminItems.length ? { ...ADMIN_SECTION, items: adminItems } : null }
}

/** Landing route after sign-in: the first menu entry the user may open */
export function homePathFor(type: TenantType, can: (check?: PermissionCheck) => boolean): string {
  return buildNavigation(type, can).main[0]?.to ?? '/settings'
}
