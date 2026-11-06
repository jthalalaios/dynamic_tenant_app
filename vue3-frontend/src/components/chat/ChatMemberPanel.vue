<template>
  <aside class="members" :aria-label="t('chat.members')">
    <header class="members__head">
      <h2>{{ t('chat.members') }}</h2>
      <Button icon="pi pi-times" text rounded size="small" class="members__close" :aria-label="t('chat.hideMembers')" @click="emit('close')" />
    </header>

    <div class="members__scroll">
      <section v-for="group in groups" :key="group.key" class="members__group">
        <h3>{{ group.label }} — {{ group.members.length }}</h3>
        <ul>
          <li v-for="member in group.members" :key="member.id" :class="['member', { 'member--away': group.key === 'offline' }]">
            <ChatAvatar :member="member" size="sm" :online="presenceKnown ? group.key === 'online' : undefined" />
            <span class="member__name">
              {{ member.name }}
              <small v-if="member.id === me" class="muted">({{ t('chat.you') }})</small>
            </span>
            <i v-if="member.is_admin" class="pi pi-shield member__admin" :title="t('chat.admin')" aria-hidden="true"></i>
            <i
              v-if="chat.muteOf(member.id, room.id)"
              class="pi pi-volume-off member__muted"
              :title="muteLabel(chat.muteOf(member.id, room.id)!)"
              aria-hidden="true"
            ></i>
            <Button
              v-if="chat.canModerate(member)"
              icon="pi pi-ellipsis-v"
              text
              rounded
              size="small"
              class="member__menu"
              :aria-label="t('chat.memberActions', { name: member.name })"
              aria-haspopup="true"
              @click="openMenu($event, member)"
            />
          </li>
        </ul>
      </section>

      <section v-if="isModerator" class="members__group members__mod">
        <h3><i class="pi pi-volume-off" aria-hidden="true"></i> {{ t('chat.mutedMembers') }} — {{ chat.liveMutes.length }}</h3>
        <p v-if="!chat.liveMutes.length" class="muted small">{{ t('chat.noMutes') }}</p>
        <ul v-else>
          <li v-for="mute in chat.liveMutes" :key="mute.id" class="mute">
            <ChatAvatar :member="mute.user" size="sm" />
            <span class="mute__body">
              <strong>{{ mute.user?.name ?? t('chat.formerMember') }}</strong>
              <small class="muted">{{ scopeLabel(mute) }} · {{ muteLabel(mute) }}</small>
              <small v-if="mute.reason" class="mute__reason">“{{ mute.reason }}”</small>
            </span>
            <Button
              v-if="chat.canModerate(mute.user)"
              icon="pi pi-volume-up"
              text
              rounded
              size="small"
              :title="t('chat.unmute')"
              :aria-label="`${t('chat.unmute')}: ${mute.user?.name ?? ''}`"
              @click="emit('unmute', mute)"
            />
          </li>
        </ul>
      </section>
    </div>

    <Menu ref="menu" :model="menuItems" popup />
  </aside>
</template>

<script lang="ts" setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import type { MenuItem } from 'primevue/menuitem'
import ChatAvatar from './ChatAvatar.vue'
import { useChatTime } from '../../composables/useChatTime'
import { useAccessStore, useChatStore, useUserStore } from '../../stores'
import type { ChatMember, ChatMute, ChatRoom } from '../../types/models'

const props = defineProps<{ room: ChatRoom }>()

const emit = defineEmits<{
  close: []
  mute: [member: ChatMember, scope: 'room' | 'everywhere']
  unmute: [mute: ChatMute]
}>()

const { t } = useI18n()
const chat = useChatStore()
const access = useAccessStore()
const user = useUserStore()
const time = useChatTime()

const menu = ref<InstanceType<typeof Menu> | null>(null)
const menuItems = ref<MenuItem[]>([])

const me = computed(() => user.user?.id ?? null)

const isModerator = computed(() => access.isAdmin || access.can('chat.mute'))
/** Without a live connection we cannot tell who is online, so everyone is listed together */
const presenceKnown = computed(() => chat.status === 'connected')

const groups = computed(() => {
  // Only the members who may enter this room (its audience)
  const all = [...chat.membersFor(props.room.id)].sort((a, b) => Number(b.is_admin) - Number(a.is_admin) || a.name.localeCompare(b.name))
  if (!presenceKnown.value) return [{ key: 'all', label: t('chat.members'), members: all }]

  const onlineIds = new Set((chat.online[props.room.id] ?? []).map((m) => m.id))
  const online = all.filter((m) => onlineIds.has(m.id))
  const offline = all.filter((m) => !onlineIds.has(m.id))
  return [
    { key: 'online', label: t('chat.online'), members: online },
    { key: 'offline', label: t('chat.offline'), members: offline }
  ].filter((g) => g.members.length)
})

function muteLabel(mute: ChatMute): string {
  return mute.expires_at ? t('chat.mutedUntil', { time: time.full(mute.expires_at) }) : t('chat.mutedForever')
}

function scopeLabel(mute: ChatMute): string {
  if (mute.room_id === null) return t('chat.everywhere')
  const room = chat.rooms.find((r) => r.id === mute.room_id)
  return room ? `#${room.name}` : '—'
}

function openMenu(event: Event, member: ChatMember): void {
  const covering = chat.liveMutes.filter((m) => m.user?.id === member.id && (m.room_id === null || m.room_id === props.room.id))
  menuItems.value = [
    { label: member.name, items: [
      { label: t('chat.muteInRoom'), icon: 'pi pi-volume-off', command: () => emit('mute', member, 'room') },
      { label: t('chat.muteEverywhere'), icon: 'pi pi-ban', command: () => emit('mute', member, 'everywhere') },
      ...covering.map((m) => ({
        label: `${t('chat.unmute')} (${scopeLabel(m)})`,
        icon: 'pi pi-volume-up',
        command: () => emit('unmute', m)
      }))
    ] }
  ]
  menu.value?.toggle(event)
}
</script>

<style scoped>
.members {
  display: flex;
  flex-direction: column;
  min-height: 0;
  background: color-mix(in srgb, var(--surface-elevated) 70%, var(--surface));
  border-left: 1px solid var(--border);
}
.members__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 0.75rem 0.5rem 1rem;
}
.members__head h2 {
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: var(--muted);
}
.members__scroll {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 0 0.6rem 1rem;
}
.members__group h3 {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin: 0.9rem 0.4rem 0.4rem;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--muted);
}
.members__group ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.1rem;
}
.member,
.mute {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  min-height: 2.6rem;
  padding: 0.3rem 0.4rem;
  border-radius: 12px;
}
.member:hover,
.mute:hover {
  background: var(--surface);
}
.member--away {
  opacity: 0.62;
}
.member__name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.9rem;
  font-weight: 500;
}
.member__admin {
  color: var(--primary);
  font-size: 0.8rem;
}
.member__muted {
  color: #b45309;
  font-size: 0.8rem;
}
.member__menu {
  opacity: 0;
}
.member:hover .member__menu,
.member__menu:focus-visible {
  opacity: 1;
}
.members__mod {
  margin-top: 0.75rem;
  padding-top: 0.25rem;
  border-top: 1px dashed var(--border);
}
.mute__body {
  flex: 1;
  min-width: 0;
  display: grid;
  font-size: 0.85rem;
}
.mute__body small {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.mute__reason {
  font-style: italic;
  color: var(--muted);
}
.small {
  padding: 0 0.4rem;
  font-size: 0.85rem;
}
@media (hover: none) {
  .member__menu {
    opacity: 1;
  }
}
</style>
