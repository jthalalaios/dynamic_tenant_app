import type { TenantType } from '../types/models'

/**
 * Which theme(s) this frontend build/host serves.
 *
 * - Multi (default): guests pick a theme, sign-in locks it to the tenant type.
 * - Standalone: one fixed theme, e.g. a dedicated clinic app. Every API call
 *   then carries `X-App-Theme`, so login / register / forgot password only act
 *   on accounts of that theme.
 *
 * Resolution order:
 *   1. VITE_THEME_HOSTS  "clinic.example.com=doctor,market.example.com=marketplace"
 *      → one build serves several separated apps, chosen by hostname
 *   2. VITE_APP_THEME    "doctor" | "marketplace" → the whole build is standalone
 *   3. VITE_APP_THEMES   "doctor,marketplace" → multi mode, limited to these themes
 */
export const ALL_THEMES: readonly TenantType[] = ['doctor', 'marketplace']

export function isThemeName(value: unknown): value is TenantType {
  return typeof value === 'string' && (ALL_THEMES as readonly string[]).includes(value)
}

function parseList(raw: string | undefined): TenantType[] {
  return (raw ?? '')
    .split(',')
    .map((v) => v.trim().toLowerCase())
    .filter(isThemeName)
}

function themeForHost(raw: string | undefined, host: string): TenantType | null {
  for (const entry of (raw ?? '').split(',')) {
    const [pattern, theme] = entry.split('=').map((v) => v?.trim().toLowerCase())
    if (pattern && pattern === host.toLowerCase() && isThemeName(theme)) return theme
  }
  return null
}

const env = import.meta.env
const host = typeof window !== 'undefined' ? window.location.host : ''
const configuredHostTheme = themeForHost(env.VITE_THEME_HOSTS, host)
const configuredTheme = (env.VITE_APP_THEME ?? '').trim().toLowerCase()

/** The single theme of a standalone app, or null in multi mode */
export const fixedTheme: TenantType | null = configuredHostTheme ?? (isThemeName(configuredTheme) ? configuredTheme : null)

export const isStandalone = fixedTheme !== null

/** Themes offered before the API reports which ones it serves */
export const initialThemes: TenantType[] = fixedTheme
  ? [fixedTheme]
  : parseList(env.VITE_APP_THEMES).length
    ? parseList(env.VITE_APP_THEMES)
    : [...ALL_THEMES]
