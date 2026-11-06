<template>
  <BaseCard class="visitor-card" padding="none" interactive>
    <div class="visitor-card__main">
      <Avatar :label="initials" shape="circle" size="large" class="visitor-card__avatar" />
      <div class="visitor-card__info">
        <div class="visitor-card__name">{{ visitor.name }}</div>
        <div class="visitor-card__meta">
          <i class="pi pi-clock" aria-hidden="true"></i>
          <span>{{ visitor.time }}</span>
          <span class="visitor-card__dot" aria-hidden="true">•</span>
          <span>{{ t(`doctor.reasons.${visitor.reason}`) }}</span>
        </div>
      </div>
    </div>
    <div class="visitor-card__status">
      <Tag
        :value="isCheckedIn ? t('cards.checkedIn') : t('cards.waiting')"
        :severity="isCheckedIn ? 'success' : 'warn'"
        :icon="isCheckedIn ? 'pi pi-check-circle' : 'pi pi-hourglass'"
        rounded
      />
    </div>

    <template #footer>
      <Button :label="t('cards.details')" icon="pi pi-search" size="small" text @click="emit('details', visitor)" />
      <Button
        class="visitor-card__checkin"
        :label="isCheckedIn ? t('cards.checkedIn') : t('cards.checkIn')"
        :icon="isCheckedIn ? 'pi pi-check-circle' : 'pi pi-check'"
        size="small"
        :outlined="!isCheckedIn"
        :disabled="isCheckedIn || checkInDisabled"
        :loading="loading"
        :title="!isCheckedIn && checkInDisabled ? checkInHint : undefined"
        @click="emit('checkIn', visitor)"
      />
    </template>
  </BaseCard>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import BaseCard from '../../components/shared/cards/BaseCard.vue'
import type { Visitor } from '../../types/app'
import { initials as toInitials } from '../../helpers/text'

const props = withDefaults(defineProps<{
  visitor: Visitor
  /** e.g. the user is offline or lacks the check-in permission */
  checkInDisabled?: boolean
  /** Explains why check-in is disabled (shown as a tooltip) */
  checkInHint?: string
  loading?: boolean
}>(), {
  checkInDisabled: false,
  checkInHint: '',
  loading: false
})
const emit = defineEmits<{
  details: [visitor: Visitor]
  checkIn: [visitor: Visitor]
}>()

const { t } = useI18n()
const isCheckedIn = computed(() => props.visitor.status === 'checkedIn')
const initials = computed(() => toInitials(props.visitor.name))
</script>

<style scoped>
.visitor-card__main {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 1.1rem 1.1rem 0.6rem;
}
.visitor-card__avatar {
  flex-shrink: 0;
  font-weight: 700;
  color: var(--primary-contrast);
  background: linear-gradient(135deg, var(--primary), var(--accent));
}
.visitor-card__info {
  min-width: 0;
}
.visitor-card__name {
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.visitor-card__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem;
  color: var(--muted);
  font-size: 0.88rem;
  margin-top: 0.15rem;
}
.visitor-card__meta .pi {
  font-size: 0.8rem;
}
.visitor-card__status {
  padding: 0 1.1rem 1rem;
}
.visitor-card__checkin {
  margin-left: auto;
}
</style>
