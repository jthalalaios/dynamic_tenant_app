import 'vue-router'
import type { PermissionCheck } from '../stores/access'
import type { TenantType } from '../types/models'

declare module 'vue-router' {
  interface RouteMeta {
    /** Requires a signed-in user */
    requiresAuth?: boolean
    /** Reachable by signed-in users before they verify their email */
    allowUnverified?: boolean
    /** Only for guests (sign-in, register...) */
    guestOnly?: boolean
    /** Restricts the route to one tenant type */
    tenant?: TenantType
    /** Any of these permissions grants access */
    permission?: PermissionCheck
    /** i18n key for the document title */
    title?: string
  }
}
