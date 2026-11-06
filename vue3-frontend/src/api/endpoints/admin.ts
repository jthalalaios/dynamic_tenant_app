import { http, resource } from '../http'
import type { PermissionGroup, Role, TeamMember } from '../../types/models'

export interface TeamMemberPayload {
  name: string
  email: string
  password?: string | null
  timezone?: string | null
  locale?: string | null
  is_active?: boolean
  role_ids: number[]
}

export interface RolePayload {
  name: string
  description?: string | null
  is_admin?: boolean
  permissions: string[]
}

export const usersApi = resource<TeamMember, TeamMemberPayload>('/admin/users')

export const rolesApi = {
  ...resource<Role, RolePayload>('/admin/roles'),
  /** Roles are few per tenant, so the endpoint is not paginated */
  all: () => http.get<{ data: Role[] }>('/admin/roles').then((r) => r.data)
}

export const permissionsApi = {
  grouped: () => http.get<{ data: PermissionGroup[] }>('/admin/permissions').then((r) => r.data)
}
