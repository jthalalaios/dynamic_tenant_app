import { updatePrimaryPalette } from '@primeuix/themes'
import { palette } from '@primeuix/styled'
import type { TenantType } from '../types/models'

export type Theme = {
  name: TenantType
  label: string
  cssVars: Record<string, string>
  bodyClass?: string
}

export const doctorTheme: Theme = {
  name: 'doctor',
  label: 'Doctor',
  cssVars: {
    '--bg': '#f6fbff',
    '--text': '#0f1724',
    '--primary': '#0f66f0',
    '--primary-contrast': '#ffffff',
    '--surface': '#ffffff',
    '--surface-elevated': '#f8fbff',
    '--border': '#e6eefb',
    '--accent': '#06b6d4',
    '--muted': '#64748b'
  },
  bodyClass: 'theme-doctor'
}

export const marketplaceTheme: Theme = {
  name: 'marketplace',
  label: 'Marketplace',
  cssVars: {
    '--bg': '#fffaf4',
    '--text': '#1f2937',
    '--primary': '#ef4444',
    '--primary-contrast': '#ffffff',
    '--surface': '#ffffff',
    '--surface-elevated': '#fff4eb',
    '--border': '#fde7d8',
    '--accent': '#f59e0b',
    '--muted': '#7c2d12'
  },
  bodyClass: 'theme-marketplace'
}

/** Every theme the app ships; which ones are offered is decided by the theme store */
export const availableThemes: Theme[] = [doctorTheme, marketplaceTheme]

export function applyTheme(themeName: TenantType) {
  const theme = availableThemes.find((t) => t.name === themeName) || doctorTheme
  const root = document.documentElement
  Object.entries(theme.cssVars).forEach(([k, v]) => root.style.setProperty(k, v))

  // Keep PrimeVue components (buttons, tags, focus rings...) in sync with the active theme
  const primaryScale = palette(theme.cssVars['--primary'])
  if (typeof primaryScale !== 'string') updatePrimaryPalette(primaryScale)

  // Manage body class for theme-specific selectors
  document.body.classList.remove(...availableThemes.map((t) => t.bodyClass || ''))
  if (theme.bodyClass) document.body.classList.add(theme.bodyClass)

  applyFavicon(theme.cssVars['--primary'], theme.cssVars['--accent'])
}

/** Same mark as public/favicon.svg, drawn in the theme's colours */
function faviconSvg(primary: string, accent: string): string {
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="${primary}"/><stop offset="1" stop-color="${accent}"/></linearGradient></defs><rect width="64" height="64" rx="16" fill="url(#g)"/><path d="M18 20h28a4 4 0 0 1 4 4v14a4 4 0 0 1-4 4H30l-8 7v-7h-4a4 4 0 0 1-4-4V24a4 4 0 0 1 4-4z" fill="#fff"/><circle cx="24" cy="31" r="2.6" fill="${primary}"/><circle cx="32" cy="31" r="2.6" fill="${primary}"/><circle cx="40" cy="31" r="2.6" fill="${primary}"/></svg>`
}

function applyFavicon(primary: string, accent: string): void {
  let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
  if (!link) {
    link = document.createElement('link')
    link.rel = 'icon'
    document.head.appendChild(link)
  }
  link.type = 'image/svg+xml'
  link.href = `data:image/svg+xml,${encodeURIComponent(faviconSvg(primary, accent))}`
}
