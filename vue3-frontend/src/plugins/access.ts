import type { App } from 'vue'
import { useAccessStore, type PermissionCheck } from '../stores/access'

/**
 * Exposes `$can()` / `$hasRole()` to every template. They read the Pinia
 * store during render, so templates update when permissions change.
 */
export const accessPlugin = {
  install(app: App) {
    app.config.globalProperties.$can = (check: PermissionCheck) => useAccessStore().can(check)
    app.config.globalProperties.$hasRole = (role: string | readonly string[]) => useAccessStore().hasRole(role)
  }
}

declare module 'vue' {
  interface ComponentCustomProperties {
    $can: (check: PermissionCheck) => boolean
    $hasRole: (role: string | readonly string[]) => boolean
  }
}
