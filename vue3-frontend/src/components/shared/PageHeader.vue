<template>
  <BaseCard class="dashboard-header" accent padding="lg">
    <div class="dashboard-header__inner">
      <div>
        <p v-if="eyebrow" class="eyebrow">{{ eyebrow }}</p>
        <h1 class="dashboard-header__title">{{ title }}</h1>
        <p v-if="subtitle" class="dashboard-header__subtitle">{{ subtitle }}</p>
      </div>
      <div v-if="$slots.actions" class="page-header__actions">
        <slot name="actions" />
      </div>
    </div>
  </BaseCard>
</template>

<script lang="ts" setup>
import BaseCard from './cards/BaseCard.vue'

defineProps<{
  title: string
  eyebrow?: string
  subtitle?: string
}>()
</script>

<style scoped>
.page-header__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.6rem;
}
</style>
