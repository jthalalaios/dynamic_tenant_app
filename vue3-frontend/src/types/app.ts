export type AppDomainId = 'doctor' | 'marketplace'

/** Metric shown in a StatCard; label/value/caption come from `home.<domain>.metrics.<key>` */
export interface MetricItem {
  key: string
  icon: string
  trend?: string
  trendDirection?: 'up' | 'down'
}

/** Feature shown in a FeatureCard; title/body come from `home.<domain>.features.<key>` */
export interface FeatureItem {
  key: string
  icon: string
}

export interface SliderItem {
  id: number
  title: string
  subtitle: string
  accent: string
}

export interface AppDomainConfig {
  id: AppDomainId
  label: string
  description: string
  themeName: string
  heroTitle: string
  heroSubtitle: string
  ctaLabel: string
  ctaIcon: string
  metrics: MetricItem[]
  features: FeatureItem[]
  sliderItems: SliderItem[]
  /** Where the landing page CTA leads (guests are sent to sign in first) */
  dashboardRoute: string
}

export type VisitorStatus = 'waiting' | 'checkedIn'

export interface Visitor {
  id: number
  name: string
  time: string
  /** i18n key under `doctor.reasons` */
  reason: string
  status: VisitorStatus
}

export interface Product {
  id: number
  name: string
  /** i18n key under `marketplace.categories` */
  category: string
  icon: string
  price: number
  stock: number
  rating: number
}
