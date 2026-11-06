<template>
  <BaseCard class="feature-card" interactive accent>
    <span class="feature-card__icon" aria-hidden="true"><i :class="icon"></i></span>
    <h3 class="feature-card__title">{{ title }}</h3>
    <p class="feature-card__body">{{ body }}</p>
  </BaseCard>
</template>

<script lang="ts" setup>
import BaseCard from './BaseCard.vue'

defineProps<{
  title: string
  body: string
  icon: string
}>()
</script>

<style scoped>
.feature-card__icon {
  display: inline-grid;
  place-items: center;
  width: 3rem;
  height: 3rem;
  border-radius: 14px;
  font-size: 1.2rem;
  color: var(--primary-contrast);
  background: linear-gradient(135deg, var(--primary), var(--accent));
  box-shadow: 0 8px 18px color-mix(in srgb, var(--primary) 28%, transparent);
  margin-bottom: 1rem;
}
.feature-card__title {
  font-size: 1.05rem;
  margin-bottom: 0.4rem;
}
.feature-card__body {
  color: var(--muted);
  line-height: 1.6;
}
</style>
