import type { AppDomainConfig } from '../types/app'

export const appDomains: Record<string, AppDomainConfig> = {
  doctor: {
    id: 'doctor',
    label: 'Doctor App',
    description: 'Clinical operations and patient journey management',
    themeName: 'doctor',
    heroTitle: 'Manage visitors, patients, and appointments in one calm workspace.',
    heroSubtitle: 'A modern clinic experience with dynamic cards, live metrics, animated sections, and reusable domain-aware components.',
    ctaLabel: 'Open Dashboard',
    ctaIcon: 'pi pi-chart-line',
    metrics: [
      { key: 'visitors', icon: 'pi pi-users', trend: '+12%', trendDirection: 'up' },
      { key: 'patients', icon: 'pi pi-heart', trend: '+4%', trendDirection: 'up' },
      { key: 'reports', icon: 'pi pi-file', trend: '-2', trendDirection: 'down' }
    ],
    features: [
      { key: 'appointments', icon: 'pi pi-calendar' },
      { key: 'patients', icon: 'pi pi-id-card' },
      { key: 'reports', icon: 'pi pi-chart-bar' }
    ],
    sliderItems: [
      { id: 1, title: 'Morning rounds', subtitle: '09:00 · Consultation', accent: 'pi pi-calendar' },
      { id: 2, title: 'Follow-up care', subtitle: '09:30 · Review', accent: 'pi pi-heart' },
      { id: 3, title: 'New arrivals', subtitle: '10:00 · Admission', accent: 'pi pi-users' }
    ],
    dashboardRoute: '/clinic'
  },
  marketplace: {
    id: 'marketplace',
    label: 'Marketplace App',
    description: 'Commerce, promotions, and customer engagement',
    themeName: 'marketplace',
    heroTitle: 'Launch products and grow revenue with a polished storefront experience.',
    heroSubtitle: 'A flexible marketplace UI with promo cards, commerce metrics, and animated conversion blocks.',
    ctaLabel: 'Explore Catalog',
    ctaIcon: 'pi pi-shopping-bag',
    metrics: [
      { key: 'revenue', icon: 'pi pi-wallet', trend: '+18%', trendDirection: 'up' },
      { key: 'orders', icon: 'pi pi-shopping-cart', trend: '+7%', trendDirection: 'up' },
      { key: 'customers', icon: 'pi pi-users', trend: '+3%', trendDirection: 'up' }
    ],
    features: [
      { key: 'products', icon: 'pi pi-box' },
      { key: 'orders', icon: 'pi pi-truck' },
      { key: 'marketing', icon: 'pi pi-megaphone' }
    ],
    sliderItems: [
      { id: 1, title: 'Hot deals', subtitle: 'Flash sale · 50% off', accent: 'pi pi-tags' },
      { id: 2, title: 'Best sellers', subtitle: 'Top rated products', accent: 'pi pi-star' },
      { id: 3, title: 'New arrivals', subtitle: 'Fresh inventory', accent: 'pi pi-shopping-cart' }
    ],
    dashboardRoute: '/market'
  }
}

export function getDefaultDomain(): AppDomainConfig {
  return appDomains.doctor
}

export function getDomainById(id: string): AppDomainConfig {
  return appDomains[id] || getDefaultDomain()
}

export function getDomainForTheme(themeName: string): AppDomainConfig {
  if (themeName === 'marketplace') return appDomains.marketplace
  return getDefaultDomain()
}
