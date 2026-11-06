/**
 * Same-browser signals between tabs of the app (BroadcastChannel; no-op where unsupported).
 * Used so the "verify your email" tab unlocks as soon as the emailed link is opened in another tab.
 */
const CHANNEL = 'app-auth'

type AuthSignal = { type: 'email-verified'; userId: number }

export function broadcastAuthSignal(signal: AuthSignal): void {
  if (typeof BroadcastChannel === 'undefined') return
  const channel = new BroadcastChannel(CHANNEL)
  channel.postMessage(signal)
  channel.close()
}

/** Returns an unsubscribe function */
export function onAuthSignal(callback: (signal: AuthSignal) => void): () => void {
  if (typeof BroadcastChannel === 'undefined') return () => {}
  const channel = new BroadcastChannel(CHANNEL)
  channel.onmessage = (event: MessageEvent<AuthSignal>) => callback(event.data)
  return () => channel.close()
}
