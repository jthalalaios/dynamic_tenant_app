<script lang="ts" setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Carousel from 'primevue/carousel'
import BaseCard from '../components/shared/cards/BaseCard.vue'
import StatCard from '../components/shared/cards/StatCard.vue'
import FeatureCard from '../components/shared/cards/FeatureCard.vue'
import VisitorCard from '../themes/doctor/VisitorCard.vue'
import ProductCard from '../themes/marketplace/ProductCard.vue'
import { getSampleVisitors } from '../themes/doctor/visitors'
import { getSampleProducts } from '../themes/marketplace/products'
import { getDomainForTheme } from '../config/appDomains'
import { useThemeStore } from '../stores'
import type { Product, Visitor } from '../types/app'

const router = useRouter()
const themeStore = useThemeStore()
const toast = useToast()
const { t } = useI18n()

const domain = computed(() => getDomainForTheme(themeStore.currentThemeName))
const isDoctor = computed(() => domain.value.id === 'doctor')

const content = computed(() => {
  const key = domain.value.id
  return {
    experience: t(`home.${key}.experience`),
    heroTitle: t(`home.${key}.heroTitle`),
    heroSubtitle: t(`home.${key}.heroSubtitle`),
    ctaLabel: t(`home.${key}.ctaLabel`),
    carouselTitle: t(`home.${key}.carouselTitle`),
    metrics: domain.value.metrics.map((m) => ({
      ...m,
      label: t(`home.${key}.metrics.${m.key}.title`),
      value: t(`home.${key}.metrics.${m.key}.value`),
      caption: t(`home.${key}.metrics.${m.key}.caption`)
    })),
    features: domain.value.features.map((f) => ({
      ...f,
      title: t(`home.${key}.features.${f.key}.title`),
      body: t(`home.${key}.features.${f.key}.body`)
    }))
  }
})

const visitors = ref<Visitor[]>(getSampleVisitors())
const products = ref<Product[]>(getSampleProducts())

// One card beside the hero text on desktop; two once the hero stacks full-width; one on phones
const carouselResponsive = [
  { breakpoint: '1024px', numVisible: 2, numScroll: 1 },
  { breakpoint: '640px', numVisible: 1, numScroll: 1 }
]

function checkIn(visitor: Visitor) {
  const target = visitors.value.find((v) => v.id === visitor.id)
  if (target) target.status = 'checkedIn'
}

function showDetails(visitor: Visitor) {
  toast.add({ severity: 'info', summary: visitor.name, detail: `${visitor.time} • ${t(`doctor.reasons.${visitor.reason}`)}`, life: 3000 })
}

function addToCart(product: Product) {
  toast.add({ severity: 'success', summary: t('cards.addToCart'), detail: t('cards.addedToCart', { name: product.name }), life: 3000 })
}

function goDashboard() { router.push(domain.value.dashboardRoute) }
</script>

<template>
  <main class="page home">
    <BaseCard class="hero fade-in" padding="lg" accent>
      <div class="hero__inner">
        <div class="hero__left">
          <p class="eyebrow">{{ content.experience }}</p>
          <h1 class="hero__title">{{ content.heroTitle }}</h1>
          <p class="hero__sub">{{ content.heroSubtitle }}</p>
          <div class="hero__cta">
            <Button :label="content.ctaLabel" :icon="domain.ctaIcon" size="large" @click="goDashboard" />
          </div>
        </div>
        <div class="hero__right">
          <p class="hero__carousel-title">{{ content.carouselTitle }}</p>
          <Carousel
            v-if="isDoctor"
            :key="'visitors'"
            :value="visitors"
            :numVisible="1"
            :numScroll="1"
            :responsiveOptions="carouselResponsive"
            class="hero__carousel"
          >
            <template #item="{ data }">
              <div class="hero__slide">
                <VisitorCard :visitor="data" @check-in="checkIn" @details="showDetails" />
              </div>
            </template>
          </Carousel>
          <Carousel
            v-else
            :key="'products'"
            :value="products"
            :numVisible="1"
            :numScroll="1"
            :responsiveOptions="carouselResponsive"
            class="hero__carousel"
          >
            <template #item="{ data }">
              <div class="hero__slide">
                <ProductCard :product="data" @add="addToCart" />
              </div>
            </template>
          </Carousel>
        </div>
      </div>
    </BaseCard>

    <section class="fade-up">
      <div class="section-head">
        <h2>{{ t('home.sections.glance') }}</h2>
      </div>
      <div class="card-grid card-grid--stats">
        <StatCard
          v-for="metric in content.metrics"
          :key="metric.key"
          :label="metric.label"
          :value="metric.value"
          :caption="metric.caption"
          :icon="metric.icon"
          :trend="metric.trend"
          :trendDirection="metric.trendDirection"
        />
      </div>
    </section>

    <section class="fade-up">
      <div class="section-head">
        <h2>{{ t('home.sections.features') }}</h2>
      </div>
      <div class="card-grid card-grid--stats">
        <FeatureCard
          v-for="feature in content.features"
          :key="feature.key"
          :title="feature.title"
          :body="feature.body"
          :icon="feature.icon"
        />
      </div>
    </section>
  </main>
</template>

<style scoped>
.hero {
  background: var(--hero-glow, none), linear-gradient(135deg, var(--surface) 0%, var(--surface-elevated) 100%);
}
.hero__inner {
  display: flex;
  gap: 2rem;
  align-items: center;
}
.hero__left,
.hero__right {
  flex: 1;
  min-width: 0;
}
.hero__left .eyebrow {
  margin-bottom: 0.5rem;
}
.hero__title {
  font-size: clamp(1.7rem, 3vw, 2.5rem);
  line-height: 1.15;
  letter-spacing: -0.02em;
  margin: 0 0 0.75rem;
}
.hero__sub {
  color: var(--muted);
  line-height: 1.65;
}
.hero__cta {
  margin-top: 1.4rem;
}
.hero__carousel-title {
  color: var(--muted);
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  margin: 0 0 0.5rem 0.5rem;
}
/* Let carousel items shrink to their slot instead of overflowing it */
.hero__carousel :deep(.p-carousel-item) {
  min-width: 0;
}
/* Room for the card hover lift/shadow inside the carousel viewport */
.hero__slide {
  height: 100%;
  padding: 0.5rem 0.4rem 0.9rem;
}

/* Animations */
.fade-in { animation: fadeIn .6s ease both }
.fade-up { animation: slideUp .6s ease both }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }
@keyframes slideUp { from{opacity:0; transform:translateY(8px)} to{opacity:1; transform:none} }

/* Responsive adjustments */
@media (max-width: 1024px) {
  .hero__inner { flex-direction: column; align-items: stretch; }
}
</style>
