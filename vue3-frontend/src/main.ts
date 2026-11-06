import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import 'primeicons/primeicons.css'
import './styles.css'
import './themes/theme-base.css'
import './themes/doctor.css'
import './themes/marketplace.css'
import App from './App.vue'
import router from './router'
import i18n from './i18n'
import { appApi, configureHttp } from './api'
import { fixedTheme } from './config/edition'
import { accessPlugin } from './plugins/access'
import { realtimeSocketId } from './services/realtime'
import { useAuthStore, usePreferencesStore, useThemeStore } from './stores'

async function bootstrap(): Promise<void> {
  const app = createApp(App)
  const pinia = createPinia()

  app.use(pinia)
  app.use(i18n)
  app.use(PrimeVue, {
    ripple: true,
    theme: {
      preset: Aura,
      options: {
        // The app ships its own light tenant themes, so never follow the OS dark mode
        darkModeSelector: '.app-dark',
        // Layered PrimeVue styles let our unlayered theme CSS override them predictably
        cssLayer: { name: 'primevue', order: 'primevue' }
      }
    }
  })
  app.use(ToastService)
  app.use(ConfirmationService)
  app.use(accessPlugin)

  const auth = useAuthStore(pinia)
  const preferences = usePreferencesStore(pinia)

  // The HTTP layer reads live state through hooks instead of importing stores
  configureHttp({
    getToken: () => auth.token,
    getLocale: () => preferences.locale,
    getTimezone: () => preferences.timezone,
    getTheme: () => fixedTheme,
    getSocketId: () => realtimeSocketId(),
    onEmailUnverified: () => {
      // The session is stale (verification state changed elsewhere); resync, then show the notice
      void auth.refreshSession().catch(() => {})
      if (router.currentRoute.value.name !== 'verify-email') void router.replace({ name: 'verify-email' })
    },
    onUnauthorized: () => {
      if (!auth.token) return
      auth.clearSession()
      const current = router.currentRoute.value
      if (current.meta.requiresAuth) {
        void router.replace({ name: 'signin', query: { redirect: current.fullPath } })
      }
    }
  })

  const themes = useThemeStore(pinia)
  themes.init()
  // Multi mode: only offer the themes the API serves (TENANCY_THEMES); never blocks boot
  appApi.config().then((config) => themes.setEnabled(config.themes)).catch(() => {})
  await preferences.setLocale(preferences.locale)

  // Installing the router runs the first navigation, whose guard restores the session
  app.use(router)
  await router.isReady()
  app.mount('#app')
}

void bootstrap()
