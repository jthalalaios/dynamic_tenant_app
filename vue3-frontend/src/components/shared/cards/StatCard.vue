<template>
  <BaseCard class="stat-card" interactive>
    <div class="stat-card__top">
      <span class="stat-card__label">{{ label }}</span>
      <span class="stat-card__icon" aria-hidden="true"><i :class="icon"></i></span>
    </div>
    <div class="stat-card__value">{{ value }}</div>
    <div class="stat-card__bottom">
      <span v-if="trend" :class="['stat-card__trend', `stat-card__trend--${trendDirection}`]">
        <i :class="trendDirection === 'down' ? 'pi pi-arrow-down-right' : 'pi pi-arrow-up-right'" aria-hidden="true"></i>
        {{ trend }}
      </span>
      <span class="stat-card__caption">{{ caption }}</span>
    </div>
  </BaseCard>
</template>

<script lang="ts" setup>
import BaseCard from './BaseCard.vue'

withDefaults(defineProps<{
  label: string
  value: string | number
  caption?: string
  icon?: string
  trend?: string
  trendDirection?: 'up' | 'down'
}>(), {
  caption: '',
  icon: 'pi pi-chart-bar',
  trend: '',
  trendDirection: 'up'
})
</script>

<style scoped>
.stat-card__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}
.stat-card__label {
  color: var(--muted);
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}
.stat-card__icon {
  display: inline-grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 12px;
  color: var(--primary);
  background: var(--primary-soft);
}
.stat-card__value {
  font-size: 2rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  margin: 0.6rem 0 0.4rem;
}
.stat-card__bottom {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}
.stat-card__caption {
  color: var(--muted);
  font-size: 0.9rem;
}
.stat-card__trend {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 700;
}
.stat-card__trend .pi {
  font-size: 0.7rem;
}
.stat-card__trend--up {
  color: #15803d;
  background: rgba(22, 163, 74, 0.12);
}
.stat-card__trend--down {
  color: #b91c1c;
  background: rgba(220, 38, 38, 0.12);
}
</style>
