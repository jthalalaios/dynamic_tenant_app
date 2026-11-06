import type { Router } from 'vue-router'
import i18n from '../i18n'
import { homePathFor } from '../config/navigation'
import { useAccessStore, useAuthStore, useUserStore } from '../stores'

/**
 * Global navigation guards. Route meta is merged parent → child, so a child
 * inherits `requiresAuth` / `tenant` from its group.
 */
export function installGuards(router: Router): void {
  router.beforeEach(async (to) => {
    const auth = useAuthStore()
    await auth.bootstrap()

    const user = useUserStore()
    const access = useAccessStore()
    const signedIn = auth.isAuthenticated
    const landing = () => {
      if (user.mustVerifyEmail) return { name: 'verify-email' }
      return user.tenantType ? homePathFor(user.tenantType, access.can) : '/settings'
    }

    // Signed-in users skip the marketing landing page and the guest-only screens
    if (signedIn && (to.name === 'home' || to.name === 'dashboard' || to.meta.guestOnly)) {
      return landing()
    }

    if (to.meta.requiresAuth && !signedIn) {
      return { name: 'signin', query: { redirect: to.fullPath } }
    }

    // App features stay locked until the email is verified (the API enforces the same)
    if (signedIn && user.mustVerifyEmail && to.meta.requiresAuth && !to.meta.allowUnverified) {
      return { name: 'verify-email' }
    }

    if (signedIn && to.name === 'verify-email' && !user.mustVerifyEmail) {
      return landing()
    }

    if (to.meta.tenant && to.meta.tenant !== user.tenantType) {
      return { name: 'forbidden' }
    }

    if (to.meta.permission !== undefined && !access.can(to.meta.permission)) {
      return { name: 'forbidden' }
    }

    return true
  })

  router.afterEach((to) => {
    const appName = i18n.global.t('common.appName')
    document.title = to.meta.title ? `${i18n.global.t(to.meta.title)} · ${appName}` : appName
  })
}
