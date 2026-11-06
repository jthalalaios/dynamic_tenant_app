/**
 * localStorage wrapper that never throws (private mode, blocked storage, SSR).
 * Keys are namespaced so the app never collides with other code on the origin.
 */
const PREFIX = 'app.'

export const storage = {
  get(key: string): string | null {
    try {
      return localStorage.getItem(PREFIX + key)
    } catch {
      return null
    }
  },
  set(key: string, value: string): void {
    try {
      localStorage.setItem(PREFIX + key, value)
    } catch {
      /* storage unavailable: preference simply isn't persisted */
    }
  },
  remove(key: string): void {
    try {
      localStorage.removeItem(PREFIX + key)
    } catch {
      /* ignore */
    }
  },
  getJson<T>(key: string): T | null {
    const raw = this.get(key)
    if (raw === null) return null
    try {
      return JSON.parse(raw) as T
    } catch {
      return null
    }
  },
  setJson(key: string, value: unknown): void {
    this.set(key, JSON.stringify(value))
  }
}
