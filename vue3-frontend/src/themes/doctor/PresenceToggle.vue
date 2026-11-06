<template>
  <button
    type="button"
    :class="['presence-toggle', { 'presence-toggle--online': presence.online, 'presence-toggle--compact': compact }]"
    :disabled="presence.busy"
    :aria-pressed="presence.online"
    :title="presence.online ? t('presence.goOffline') : t('presence.goOnline')"
    @click="toggle"
  >
    <span class="presence-toggle__dot" aria-hidden="true"></span>
    <span class="presence-toggle__label">{{ presence.online ? t('presence.online') : t('presence.offline') }}</span>
    <span v-if="!compact && presence.online && presence.since" class="presence-toggle__since">
      {{ t('presence.since', { time: fmt.time(presence.since) }) }}
    </span>
  </button>
</template>

<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { usePresenceStore } from '../../stores'
import { useNotify } from '../../composables/useNotify'
import { useFormatters } from '../../composables/useFormatters'

withDefaults(defineProps<{ compact?: boolean }>(), { compact: false })

const { t } = useI18n()
const presence = usePresenceStore()
const notify = useNotify()
const fmt = useFormatters()

async function toggle() {
  try {
    if (presence.online) {
      await presence.goOffline()
      notify.info(t('presence.wentOffline'))
    } else {
      await presence.goOnline()
      notify.success(t('presence.wentOnline'))
    }
  } catch (err) {
    notify.error(err)
  }
}
</script>

<style scoped>
.presence-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.45rem 0.8rem;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: var(--surface-elevated);
  color: var(--muted);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease;
}
.presence-toggle:disabled {
  opacity: 0.6;
  cursor: progress;
}
.presence-toggle__dot {
  width: 0.6rem;
  height: 0.6rem;
  border-radius: 50%;
  background: #94a3b8;
}
.presence-toggle--online {
  color: #15803d;
  border-color: rgba(22, 163, 74, 0.35);
  background: rgba(22, 163, 74, 0.1);
}
.presence-toggle--online .presence-toggle__dot {
  background: #16a34a;
  box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.5);
  animation: pulse 2s infinite;
}
.presence-toggle__since {
  font-weight: 500;
  font-size: 0.85rem;
}
.presence-toggle--compact {
  padding: 0.4rem 0.65rem;
}
@keyframes pulse {
  70% { box-shadow: 0 0 0 6px rgba(22, 163, 74, 0); }
  100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); }
}
</style>
