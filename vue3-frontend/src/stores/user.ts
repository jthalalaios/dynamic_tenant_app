import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { SessionUser, Tenant, TenantType } from '../types/models'
import { initials } from '../helpers/text'

/**
 * The signed-in user's profile and their tenant (clinic or shop).
 */
export const useUserStore = defineStore('user', () => {
  const user = ref<SessionUser | null>(null)
  const tenant = ref<Tenant | null>(null)

  const tenantType = computed<TenantType | null>(() => tenant.value?.type ?? null)
  const isDoctorTenant = computed(() => tenantType.value === 'doctor')
  const isMarketplaceTenant = computed(() => tenantType.value === 'marketplace')
  const displayName = computed(() => user.value?.name ?? '')
  const userInitials = computed(() => initials(displayName.value))
  const mustVerifyEmail = computed(() => user.value?.must_verify_email === true)

  function setProfile(nextUser: SessionUser, nextTenant: Tenant): void {
    user.value = nextUser
    tenant.value = nextTenant
  }

  function reset(): void {
    user.value = null
    tenant.value = null
  }

  return { user, tenant, tenantType, isDoctorTenant, isMarketplaceTenant, displayName, userInitials, mustVerifyEmail, setProfile, reset }
})
