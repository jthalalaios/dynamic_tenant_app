<template>
  <section class="auth-shell">
    <BaseCard class="auth-card" padding="lg" accent>
      <div class="auth-card__header">
        <p class="eyebrow">{{ eyebrow }}</p>
        <h2>{{ title }}</h2>
        <p class="auth-card__subtitle">{{ subtitle }}</p>
      </div>
      <slot />
    </BaseCard>
  </section>
</template>

<script lang="ts" setup>
import BaseCard from './cards/BaseCard.vue'

defineProps<{
  eyebrow: string
  title: string
  subtitle: string
}>()
</script>

<style scoped>
.auth-shell {
  min-height: calc(100vh - 120px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 1rem;
}
.auth-card {
  width: min(100%, 460px);
  height: auto;
  background: var(--hero-glow, none), linear-gradient(135deg, var(--surface) 0%, var(--surface-elevated) 100%);
  border-radius: 24px;
}
.auth-card__header {
  margin-bottom: 1.25rem;
}
.eyebrow {
  margin-bottom: 0.25rem;
}
.auth-card h2 {
  margin: 0 0 0.35rem;
  color: var(--text);
}
.auth-card__subtitle {
  color: var(--muted);
  line-height: 1.5;
}
@media (max-width: 640px) {
  .auth-card {
    border-radius: 18px;
  }
}
</style>
