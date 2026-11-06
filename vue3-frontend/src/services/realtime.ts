import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { ref } from 'vue'
import { httpClient } from '../api/http'

/**
 * Laravel Reverb connection (Pusher protocol) shared by the whole app.
 *
 * - Channel authorization goes through the app's own HTTP client, so it carries the
 *   Bearer token, locale and X-App-Theme like every other API call.
 * - Without VITE_REVERB_APP_KEY the app runs without live updates (`unavailable`).
 */
export type RealtimeStatus = 'idle' | 'connecting' | 'connected' | 'reconnecting' | 'unavailable'

type ReverbEcho = Echo<'reverb'>

const env = import.meta.env
let echo: ReverbEcho | null = null

/** Reactive connection state, for "Live" / "Reconnecting…" indicators */
export const realtimeStatus = ref<RealtimeStatus>('idle')

const listeners = new Set<() => void>()

/** Called every time the socket (re)connects, so callers can resync what they missed */
export function onRealtimeConnected(callback: () => void): () => void {
  listeners.add(callback)
  return () => listeners.delete(callback)
}

export function isRealtimeConfigured(): boolean {
  return !!env.VITE_REVERB_APP_KEY
}

export function connectRealtime(): ReverbEcho | null {
  if (echo) return echo
  if (!isRealtimeConfigured()) {
    realtimeStatus.value = 'unavailable'
    return null
  }

  const scheme = (env.VITE_REVERB_SCHEME || 'http').toLowerCase()
  const port = Number(env.VITE_REVERB_PORT) || (scheme === 'https' ? 443 : 80)

  echo = new Echo({
    broadcaster: 'reverb',
    key: env.VITE_REVERB_APP_KEY as string,
    wsHost: env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    Pusher,
    // X-Socket-ID is added by our own HTTP client (see main.ts), not by Echo's global axios hook
    withoutInterceptors: true,
    channelAuthorization: {
      customHandler: ({ socketId, channelName }, callback) => {
        httpClient
          .post('/broadcasting/auth', { socket_id: socketId, channel_name: channelName })
          .then((response) => callback(null, response.data))
          .catch((error: unknown) => callback(error instanceof Error ? error : new Error(String(error)), null))
      }
    }
  })

  realtimeStatus.value = 'connecting'
  echo.connector.pusher.connection.bind('state_change', ({ current }: { previous: string; current: string }) => {
    if (current === 'connected') {
      realtimeStatus.value = 'connected'
      listeners.forEach((callback) => callback())
    } else if (current === 'connecting') {
      realtimeStatus.value = realtimeStatus.value === 'connecting' ? 'connecting' : 'reconnecting'
    } else if (current === 'unavailable' || current === 'disconnected' || current === 'failed') {
      realtimeStatus.value = 'reconnecting'
    }
  })

  return echo
}

export function realtime(): ReverbEcho | null {
  return echo
}

export function realtimeSocketId(): string | null {
  return echo?.socketId() ?? null
}

/** Close the socket (sign-out, lost permission); the next connectRealtime() starts fresh */
export function disconnectRealtime(): void {
  if (!echo) return
  echo.leaveAllChannels()
  echo.disconnect()
  echo = null
  realtimeStatus.value = 'idle'
}
