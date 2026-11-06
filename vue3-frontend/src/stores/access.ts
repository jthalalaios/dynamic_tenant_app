import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { AccessSnapshot } from '../types/models'

export type PermissionCheck = string | readonly string[]

/**
 * The current user's roles and effective permissions, as resolved by the API.
 * The API stays the authority; this store only drives what the UI shows.
 */
export const useAccessStore = defineStore('access', () => {
  const isAdmin = ref(false)
  const roles = ref<string[]>([])
  const permissionList = ref<string[]>([])

  const permissions = computed(() => new Set(permissionList.value))

  function setAccess(snapshot: AccessSnapshot): void {
    isAdmin.value = snapshot.is_admin
    roles.value = [...snapshot.roles]
    permissionList.value = [...snapshot.permissions]
  }

  function reset(): void {
    isAdmin.value = false
    roles.value = []
    permissionList.value = []
  }

  /** True if the user holds at least one of the permissions (or the single one given) */
  function can(check: PermissionCheck | undefined): boolean {
    if (check === undefined) return true
    const list = typeof check === 'string' ? [check] : check
    return list.length === 0 || list.some((p) => permissions.value.has(p))
  }

  function canAll(check: readonly string[]): boolean {
    return check.every((p) => permissions.value.has(p))
  }

  function hasRole(role: string | readonly string[]): boolean {
    const list = typeof role === 'string' ? [role] : role
    return list.some((r) => roles.value.includes(r))
  }

  return { isAdmin, roles, permissions: permissionList, setAccess, reset, can, canAll, hasRole }
})
