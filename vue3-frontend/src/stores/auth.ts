import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi, type LoginPayload, type RegisterPayload } from '../api'
import { storage } from '../helpers/storage'
import type { Session, TokenResponse } from '../types/models'
import { useAccessStore } from './access'
import { usePreferencesStore } from './preferences'
import { usePresenceStore } from './presence'
import { useThemeStore } from './theme'
import { useUserStore } from './user'

const TOKEN_KEY = 'auth.token'
const EXPIRES_KEY = 'auth.expiresAt'

/**
 * Session lifecycle: bearer token storage, login/logout and bootstrapping the
 * user, access and preference stores from the API's session payload.
 */
export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(storage.get(TOKEN_KEY))
  const expiresAt = ref<number>(Number(storage.get(EXPIRES_KEY)) || 0)
  const ready = ref(false)
  let bootstrapping: Promise<void> | null = null

  const isExpired = () => expiresAt.value > 0 && Date.now() >= expiresAt.value
  const isAuthenticated = computed(() => !!token.value && !isExpired() && useUserStore().user !== null)

  function storeToken(response: TokenResponse): void {
    token.value = response.access_token
    expiresAt.value = Date.now() + response.expires_in * 1000
    storage.set(TOKEN_KEY, token.value)
    storage.set(EXPIRES_KEY, String(expiresAt.value))
  }

  async function applySession(session: Session): Promise<void> {
    useUserStore().setProfile(session.user, session.tenant)
    useAccessStore().setAccess(session.access)
    useThemeStore().lockTo(session.tenant.type)
    await usePreferencesStore().applyServer(session.preferences)
  }

  async function login(payload: LoginPayload): Promise<Session> {
    const response = await authApi.login(payload)
    storeToken(response)
    await applySession(response.session)
    return response.session
  }

  async function register(payload: RegisterPayload): Promise<Session> {
    const response = await authApi.register(payload)
    storeToken(response)
    await applySession(response.session)
    return response.session
  }

  /** Re-fetch the session (after role or profile changes) */
  async function refreshSession(): Promise<void> {
    await applySession(await authApi.me())
  }

  /** Restore a stored session once per page load; concurrent callers share the same promise */
  function bootstrap(): Promise<void> {
    if (ready.value) return Promise.resolve()

    bootstrapping ??= (async () => {
      try {
        if (token.value && !isExpired()) await refreshSession()
        else if (token.value) clearSession()
      } catch {
        clearSession()
      } finally {
        ready.value = true
        bootstrapping = null
      }
    })()

    return bootstrapping
  }

  async function logout(): Promise<void> {
    try {
      if (token.value) await authApi.logout()
    } catch {
      // The token may already be invalid; local sign-out still proceeds
    } finally {
      clearSession()
    }
  }

  function clearSession(): void {
    token.value = null
    expiresAt.value = 0
    storage.remove(TOKEN_KEY)
    storage.remove(EXPIRES_KEY)
    useUserStore().reset()
    useAccessStore().reset()
    usePresenceStore().reset()
    useThemeStore().unlock()
  }

  return { token, expiresAt, ready, isAuthenticated, login, register, logout, bootstrap, refreshSession, applySession, clearSession }
})
