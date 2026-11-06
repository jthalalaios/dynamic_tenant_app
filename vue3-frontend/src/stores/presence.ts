import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { presenceApi } from '../api'
import type { Presence } from '../types/models'

const DEFAULT_TIMEOUT_MINUTES = 5

/**
 * Online/offline state for clinic staff. While online, a heartbeat keeps the
 * server-side session alive; check-in is only allowed while it is live.
 */
export const usePresenceStore = defineStore('presence', () => {
  const state = ref<Presence | null>(null)
  const busy = ref(false)
  let timer: ReturnType<typeof setInterval> | null = null

  const online = computed(() => state.value?.online ?? false)
  const since = computed(() => state.value?.started_at ?? null)
  // Beat at half the server timeout (at most once a minute) so one missed beat never drops the session
  const heartbeatMs = computed(() => Math.min(60_000, ((state.value?.timeout_minutes ?? DEFAULT_TIMEOUT_MINUTES) * 60_000) / 2))

  function apply(next: Presence): void {
    state.value = next
    next.online ? startHeartbeat() : stopHeartbeat()
  }

  async function refresh(): Promise<void> {
    apply(await presenceApi.show())
  }

  async function goOnline(): Promise<void> {
    busy.value = true
    try {
      apply(await presenceApi.online())
    } finally {
      busy.value = false
    }
  }

  async function goOffline(): Promise<void> {
    busy.value = true
    try {
      apply(await presenceApi.offline())
    } finally {
      busy.value = false
    }
  }

  function startHeartbeat(): void {
    stopHeartbeat()
    timer = setInterval(async () => {
      try {
        apply(await presenceApi.heartbeat())
      } catch {
        // Transient network errors: keep trying; a 401 is handled globally
      }
    }, heartbeatMs.value)
  }

  function stopHeartbeat(): void {
    if (timer) clearInterval(timer)
    timer = null
  }

  /** Local cleanup on sign-out (the server session expires on its own) */
  function reset(): void {
    stopHeartbeat()
    state.value = null
  }

  return { state, busy, online, since, refresh, goOnline, goOffline, reset }
})
