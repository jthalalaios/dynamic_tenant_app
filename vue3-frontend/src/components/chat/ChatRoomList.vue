<template>
  <aside class="room-list" :aria-label="t('chat.rooms')">
    <header class="room-list__head">
      <h2>{{ t('chat.rooms') }}</h2>
      <Button
        v-if="canManage"
        icon="pi pi-plus"
        rounded
        text
        size="small"
        :aria-label="t('chat.newRoom')"
        :title="t('chat.newRoom')"
        @click="emit('create')"
      />
    </header>

    <IconField class="room-list__search">
      <InputIcon class="pi pi-search" />
      <InputText v-model="search" :placeholder="t('chat.searchRooms')" size="small" fluid />
    </IconField>

    <nav class="room-list__items">
      <template v-if="loading">
        <Skeleton v-for="n in 4" :key="n" height="3.6rem" borderRadius="14px" />
      </template>
      <template v-else>
      <button
        v-for="room in filtered"
        :key="room.id"
        type="button"
        :class="['room', { 'room--active': room.id === activeId, 'room--unread': (room.unread_count ?? 0) > 0 }]"
        :aria-current="room.id === activeId ? 'page' : undefined"
        @click="emit('select', room.id)"
      >
        <span :class="['room__icon', `room__icon--${room.audience}`]"><i :class="`pi pi-${room.icon}`" aria-hidden="true"></i></span>
        <span class="room__main">
          <span class="room__top">
            <span class="room__name">{{ room.name }}</span>
            <i v-if="room.audience === 'admins'" class="pi pi-shield room__badge-icon" :title="t('chat.audience.adminsHint')" aria-hidden="true"></i>
            <i v-else-if="room.audience === 'roles'" class="pi pi-users room__badge-icon" :title="audienceLabel(room)" aria-hidden="true"></i>
            <i v-if="room.is_locked" class="pi pi-lock room__lock" :title="t('chat.lockedHint')" aria-hidden="true"></i>
            <span v-if="room.last_message" class="room__time">{{ time.stamp(room.last_message.created_at) }}</span>
          </span>
          <span class="room__bottom">
            <span class="room__preview">
              <template v-if="room.last_message">
                <strong>{{ authorLabel(room.last_message.user) }}:</strong> {{ room.last_message.body }}
              </template>
              <template v-else>{{ room.description || t('chat.empty') }}</template>
            </span>
            <span v-if="room.unread_count" class="room__badge" :aria-label="t('chat.unreadCount', { count: room.unread_count })">
              {{ room.unread_count > 99 ? '99+' : room.unread_count }}
            </span>
          </span>
        </span>
      </button>
      </template>
      <p v-if="!loading && !filtered.length" class="room-list__empty muted">{{ t('chat.noRooms') }}</p>
    </nav>

    <footer :class="['room-list__status', `room-list__status--${status}`]" role="status">
      <span class="status-dot" aria-hidden="true"></span>
      {{ t(`chat.status.${status}`) }}
    </footer>
  </aside>
</template>

<script lang="ts" setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Skeleton from 'primevue/skeleton'
import { useChatTime } from '../../composables/useChatTime'
import { useUserStore } from '../../stores'
import type { RealtimeStatus } from '../../services/realtime'
import type { ChatMember, ChatRoom } from '../../types/models'

const props = defineProps<{
  rooms: ChatRoom[]
  activeId: number | null
  canManage: boolean
  loading: boolean
  status: RealtimeStatus
}>()

const emit = defineEmits<{ select: [id: number]; create: [] }>()

const { t } = useI18n()
const time = useChatTime()
const user = useUserStore()
const search = ref('')

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase()
  if (!term) return props.rooms
  return props.rooms.filter((r) => r.name.toLowerCase().includes(term) || r.description?.toLowerCase().includes(term))
})

/** "Doctor, Receptionist" for role rooms */
function audienceLabel(room: ChatRoom): string {
  return t('chat.audience.rolesHint', { roles: (room.roles ?? []).map((r) => r.name).join(', ') })
}

function authorLabel(author: ChatMember | null): string {
  if (!author) return t('chat.formerMember')
  return author.id === user.user?.id ? t('chat.you') : author.name.split(' ')[0]
}
</script>

<style scoped>
.room-list {
  display: flex;
  flex-direction: column;
  min-height: 0;
  background: color-mix(in srgb, var(--surface-elevated) 70%, var(--surface));
  border-right: 1px solid var(--border);
}
.room-list__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1rem 0.5rem;
}
.room-list__head h2 {
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: var(--muted);
}
.room-list__search {
  margin: 0 0.85rem 0.6rem;
}
.room-list__items {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: grid;
  align-content: start;
  gap: 0.2rem;
  padding: 0.2rem 0.55rem 0.8rem;
}
.room-list__empty {
  padding: 1rem 0.6rem;
  font-size: 0.9rem;
}
.room {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  width: 100%;
  padding: 0.6rem 0.65rem;
  border: 1px solid transparent;
  border-radius: 14px;
  background: transparent;
  color: var(--text);
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: background-color 140ms ease, border-color 140ms ease, transform 140ms ease;
}
.room:hover {
  background: var(--surface);
}
.room:focus-visible {
  outline: 2px solid var(--primary);
  outline-offset: 1px;
}
.room--active {
  background: var(--surface);
  border-color: color-mix(in srgb, var(--primary) 30%, var(--border));
  box-shadow: 0 6px 18px color-mix(in srgb, var(--primary) 12%, transparent);
}
.room__icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 2.4rem;
  height: 2.4rem;
  border-radius: 12px;
  background: var(--primary-soft);
  color: var(--primary);
}
.room--active .room__icon {
  background: linear-gradient(135deg, var(--primary), var(--accent));
  color: var(--primary-contrast);
}
.room__main {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 0.15rem;
}
.room__top,
.room__bottom {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  min-width: 0;
}
.room__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 600;
}
.room--unread .room__name {
  font-weight: 800;
}
.room__badge-icon {
  font-size: 0.72rem;
  color: var(--primary);
}
/* Restricted rooms get a quieter tile so the open ones read first */
.room__icon--admins,
.room__icon--roles {
  background: var(--accent-soft);
  color: color-mix(in srgb, var(--accent) 75%, var(--text));
}
.room__lock {
  font-size: 0.72rem;
  color: var(--muted);
}
.room__time {
  margin-left: auto;
  flex: none;
  font-size: 0.72rem;
  color: var(--muted);
}
.room__preview {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.82rem;
  color: var(--muted);
}
.room__preview strong {
  font-weight: 600;
}
.room--unread .room__preview {
  color: var(--text);
}
.room__badge {
  flex: none;
  min-width: 1.3rem;
  height: 1.3rem;
  padding: 0 0.4rem;
  border-radius: 999px;
  background: var(--primary);
  color: var(--primary-contrast);
  font-size: 0.7rem;
  font-weight: 700;
  line-height: 1.3rem;
  text-align: center;
}
.room-list__status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.7rem 1rem;
  border-top: 1px solid var(--border);
  font-size: 0.78rem;
  color: var(--muted);
}
.status-dot {
  width: 0.55rem;
  height: 0.55rem;
  border-radius: 50%;
  background: #94a3b8;
}
.room-list__status--connected .status-dot {
  background: #22c55e;
  box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.55);
  animation: live-pulse 2.4s ease-out infinite;
}
.room-list__status--connecting .status-dot,
.room-list__status--reconnecting .status-dot {
  background: #f59e0b;
}
@keyframes live-pulse {
  0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.55); }
  70% { box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
  100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}
</style>
