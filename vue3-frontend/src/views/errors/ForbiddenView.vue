<template>
  <main class="page">
    <BaseCard padding="lg" accent class="error-card">
      <i class="pi pi-lock error-card__icon" aria-hidden="true"></i>
      <h1>{{ t('errors.forbiddenTitle') }}</h1>
      <p class="muted">{{ t('errors.forbidden') }}</p>
      <Button :label="t('errors.backHome')" icon="pi pi-arrow-left" @click="router.push('/')" />
    </BaseCard>
  </main>
</template>

<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'
import BaseCard from '../../components/shared/cards/BaseCard.vue'

const { t } = useI18n()
const router = useRouter()
</script>

<style scoped>
.error-card :deep(.base-card__body) {
  display: grid;
  justify-items: center;
  gap: 0.75rem;
  text-align: center;
}
.error-card__icon {
  font-size: 2.5rem;
  color: var(--primary);
}
</style>
