<template>
  <main class="page chat-page">
    <header class="chat-hero">
      <div>
        <p class="eyebrow">{{ user.tenant?.name }}</p>
        <h1>{{ t('chat.title') }}</h1>
        <p class="muted">{{ t('chat.subtitle') }}</p>
      </div>
    </header>

    <div v-if="failed" class="chat-shell chat-shell--error">
      <i class="pi pi-exclamation-circle" aria-hidden="true"></i>
      <p>{{ t('chat.loadFailed') }}</p>
      <Button :label="t('chat.retry')" icon="pi pi-refresh" :loading="retrying" @click="retry" />
    </div>

    <div
      v-else
      :class="['chat-shell', `chat-shell--${mobilePane}`, { 'chat-shell--members': showMembers }]"
    >
      <ChatRoomList
        class="chat-shell__rooms"
        :rooms="chat.rooms"
        :activeId="chat.activeRoomId"
        :canManage="access.can('chat.manage')"
        :loading="!chat.ready"
        :status="chat.status"
        @select="selectRoom"
        @create="openRoomDialog(null)"
      />

      <section v-if="room" class="chat-shell__main" :aria-label="room.name">
        <header class="conversation-head">
          <Button icon="pi pi-arrow-left" text rounded class="conversation-head__back" :aria-label="t('chat.backToRooms')" @click="mobilePane = 'rooms'" />
          <span class="conversation-head__icon"><i :class="`pi pi-${room.icon}`" aria-hidden="true"></i></span>
          <div class="conversation-head__title">
            <h2>
              {{ room.name }}
              <span v-if="room.is_locked" class="locked-tag"><i class="pi pi-lock" aria-hidden="true"></i>{{ t('chat.locked') }}</span>
              <span v-if="room.audience === 'admins'" class="audience-tag" :title="t('chat.audience.adminsHint')"><i class="pi pi-shield" aria-hidden="true"></i>{{ t('chat.audience.admins') }}</span>
              <span v-else-if="room.audience === 'roles'" class="audience-tag" :title="t('chat.audience.rolesHint', { roles: roleNames })"><i class="pi pi-users" aria-hidden="true"></i>{{ roleNames }}</span>
            </h2>
            <p class="muted">{{ room.description || t('chat.beginning', { room: room.name }) }}</p>
          </div>

          <div class="conversation-head__actions">
            <button
              v-if="onlineHere.length"
              type="button"
              class="presence-stack"
              :title="onlineHere.map((m) => m.name).join(', ')"
              @click="showMembers = true"
            >
              <ChatAvatar v-for="m in onlineHere.slice(0, 3)" :key="m.id" :member="m" size="sm" />
              <span>{{ t('chat.onlineCount', { count: onlineHere.length }) }}</span>
            </button>
            <Button
              v-if="access.isAdmin"
              :icon="room.is_locked ? 'pi pi-lock-open' : 'pi pi-lock'"
              text
              rounded
              :severity="room.is_locked ? 'warn' : 'secondary'"
              :aria-label="room.is_locked ? t('chat.unlockRoom') : t('chat.lockRoom')"
              :title="room.is_locked ? t('chat.unlockRoom') : t('chat.lockRoom')"
              :loading="locking"
              @click="toggleLock"
            />
            <Button
              v-if="access.can('chat.manage')"
              icon="pi pi-pencil"
              text
              rounded
              severity="secondary"
              :aria-label="t('chat.editRoom')"
              :title="t('chat.editRoom')"
              @click="openRoomDialog(room)"
            />
            <Button
              icon="pi pi-users"
              text
              rounded
              :severity="showMembers ? undefined : 'secondary'"
              :aria-label="showMembers ? t('chat.hideMembers') : t('chat.showMembers')"
              :aria-pressed="showMembers"
              :title="showMembers ? t('chat.hideMembers') : t('chat.showMembers')"
              @click="showMembers = !showMembers"
            />
          </div>
        </header>

        <ChatMessageList
          :room="room"
          :messages="chat.messages[room.id] ?? []"
          :hasMore="chat.hasMore[room.id] ?? false"
          :loading="chat.loadingHistory"
          :typingNames="chat.typingNames(room.id)"
          @loadOlder="loadOlder"
          @delete="confirmDelete"
          @mute="(member) => openMute(member, 'room')"
        />

        <ChatComposer :room="room" />
      </section>

      <section v-else class="chat-shell__main chat-shell__placeholder">
        <Skeleton v-if="!chat.ready" width="60%" height="1.4rem" />
        <p v-else class="muted"><i class="pi pi-comments" aria-hidden="true"></i> {{ t('chat.noRooms') }}</p>
      </section>

      <ChatMemberPanel
        v-if="room && showMembers"
        class="chat-shell__members"
        :room="room"
        @close="showMembers = false"
        @mute="openMute"
        @unmute="unmute"
      />
    </div>

    <ChatMuteDialog
      v-model:visible="muteDialog.open"
      :member="muteDialog.member"
      :room="room"
      :initialScope="muteDialog.scope"
      :busy="muteDialog.busy"
      @confirm="mute"
    />

    <ChatRoomDialog v-model:visible="roomDialog.open" :room="roomDialog.room" @saved="(r) => selectRoom(r.id)" @delete="confirmDeleteRoom" />
  </main>
</template>

<script lang="ts" setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'
import ChatAvatar from '../../components/chat/ChatAvatar.vue'
import ChatComposer from '../../components/chat/ChatComposer.vue'
import ChatMemberPanel from '../../components/chat/ChatMemberPanel.vue'
import ChatMessageList from '../../components/chat/ChatMessageList.vue'
import ChatMuteDialog from '../../components/chat/ChatMuteDialog.vue'
import ChatRoomDialog from '../../components/chat/ChatRoomDialog.vue'
import ChatRoomList from '../../components/chat/ChatRoomList.vue'
import type { ChatMutePayload } from '../../api'
import { useNotify } from '../../composables/useNotify'
import { useAccessStore, useChatStore, useUserStore } from '../../stores'
import type { ChatMember, ChatMessage, ChatMute, ChatRoom } from '../../types/models'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const notify = useNotify()
const chat = useChatStore()
const access = useAccessStore()
const user = useUserStore()

const failed = ref(false)
const retrying = ref(false)
const locking = ref(false)
const showMembers = ref(window.matchMedia('(min-width: 1180px)').matches)
/** Phones show one pane at a time */
const mobilePane = ref<'rooms' | 'conversation'>('conversation')

const muteDialog = reactive({ open: false, member: null as ChatMember | null, scope: 'room' as 'room' | 'everywhere', busy: false })
const roomDialog = reactive({ open: false, room: null as ChatRoom | null })

const room = computed(() => chat.activeRoom)
const onlineHere = computed(() => (room.value ? chat.online[room.value.id] ?? [] : []))
const roleNames = computed(() => (room.value?.roles ?? []).map((r) => r.name).join(', '))

/** The room in the URL (?room=3), else the default room */
const requestedRoomId = computed(() => {
  const id = Number(route.query.room)
  return Number.isInteger(id) && id > 0 ? id : null
})

async function ensureStarted(): Promise<void> {
  try {
    await chat.start()
    failed.value = false
  } catch {
    failed.value = !chat.ready
  }
}

async function retry(): Promise<void> {
  retrying.value = true
  await ensureStarted()
  retrying.value = false
}

function selectRoom(id: number): void {
  mobilePane.value = 'conversation'
  if (requestedRoomId.value !== id) void router.replace({ query: { ...route.query, room: String(id) } })
}

// Open whatever the URL asks for once rooms are known; fall back to the default room
watch(
  [() => chat.ready, requestedRoomId, () => chat.rooms.map((r) => r.id).join(',')],
  async ([ready]) => {
    if (!ready) return
    const target = chat.rooms.find((r) => r.id === requestedRoomId.value) ?? chat.defaultRoom
    if (!target) return
    if (target.id !== requestedRoomId.value) {
      void router.replace({ query: { ...route.query, room: String(target.id) } })
      return
    }
    try {
      await chat.openRoom(target.id)
    } catch (err) {
      notify.error(err)
    }
  },
  { immediate: true }
)

async function loadOlder(): Promise<void> {
  if (!room.value) return
  try {
    await chat.loadOlder(room.value.id)
  } catch (err) {
    notify.error(err)
  }
}

function confirmDelete(message: ChatMessage): void {
  confirm.require({
    header: t('chat.deleteMessage'),
    message: t('chat.confirmDeleteMessage'),
    icon: 'pi pi-trash',
    rejectProps: { label: t('common.cancel'), text: true },
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    accept: async () => {
      try {
        await chat.deleteMessage(message)
        notify.success(t('chat.messageDeleted'))
      } catch (err) {
        notify.error(err)
      }
    }
  })
}

function openMute(member: ChatMember, scope: 'room' | 'everywhere'): void {
  Object.assign(muteDialog, { open: true, member, scope, busy: false })
}

async function mute(payload: ChatMutePayload): Promise<void> {
  muteDialog.busy = true
  try {
    const created = await chat.mute(payload)
    notify.success(t('chat.mutedToast', { name: created.user?.name ?? '' }))
    muteDialog.open = false
  } catch (err) {
    notify.error(err)
  } finally {
    muteDialog.busy = false
  }
}

async function unmute(target: ChatMute): Promise<void> {
  try {
    await chat.unmute(target)
    notify.success(t('chat.unmutedToast', { name: target.user?.name ?? '' }))
  } catch (err) {
    notify.error(err)
  }
}

async function toggleLock(): Promise<void> {
  if (!room.value) return
  locking.value = true
  try {
    const updated = await chat.updateRoom(room.value.id, { is_locked: !room.value.is_locked })
    notify.success(updated.is_locked ? t('chat.roomLocked') : t('chat.roomUnlocked'))
  } catch (err) {
    notify.error(err)
  } finally {
    locking.value = false
  }
}

function openRoomDialog(target: ChatRoom | null): void {
  Object.assign(roomDialog, { open: true, room: target })
}

function confirmDeleteRoom(target: ChatRoom): void {
  confirm.require({
    header: t('chat.deleteRoom'),
    message: t('chat.confirmDeleteRoom', { name: target.name }),
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: t('common.cancel'), text: true },
    acceptProps: { label: t('common.delete'), severity: 'danger' },
    accept: async () => {
      try {
        await chat.deleteRoom(target)
        roomDialog.open = false
        notify.success(t('chat.roomDeleted'))
      } catch (err) {
        notify.error(err)
      }
    }
  })
}

onMounted(() => {
  if (!chat.ready) void ensureStarted()
})

// Leaving the page leaves the room's presence channel ("online here" means "has it open")
onBeforeUnmount(() => chat.closeRoom())
</script>

<style scoped>
.chat-page {
  padding-bottom: 1.5rem;
}
.chat-hero h1 {
  margin: 0.3rem 0 0.25rem;
  font-size: clamp(1.4rem, 2.4vw, 1.8rem);
}
.chat-shell {
  display: grid;
  grid-template-columns: minmax(240px, 290px) minmax(0, 1fr);
  height: clamp(520px, calc(100vh - 240px), 920px);
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: var(--card-radius);
  background: var(--hero-glow, none), var(--surface);
  box-shadow: var(--card-shadow);
}
.chat-shell--members {
  grid-template-columns: minmax(240px, 290px) minmax(0, 1fr) minmax(220px, 270px);
}
.chat-shell--error {
  display: grid;
  place-content: center;
  justify-items: center;
  gap: 0.8rem;
  grid-template-columns: none;
  color: var(--muted);
}
.chat-shell--error .pi {
  font-size: 2rem;
  color: #dc2626;
}
.chat-shell__main {
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
}
.chat-shell__placeholder {
  align-items: center;
  justify-content: center;
}

.conversation-head {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.85rem 1rem;
  border-bottom: 1px solid var(--border);
  background: color-mix(in srgb, var(--surface) 88%, transparent);
  backdrop-filter: blur(6px);
}
.conversation-head__back {
  display: none;
}
.conversation-head__icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 2.6rem;
  height: 2.6rem;
  border-radius: 14px;
  background: linear-gradient(135deg, var(--primary), var(--accent));
  color: var(--primary-contrast);
  box-shadow: 0 8px 20px color-mix(in srgb, var(--primary) 25%, transparent);
}
.conversation-head__title {
  flex: 1;
  min-width: 0;
}
.conversation-head__title h2 {
  flex-wrap: wrap;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1.05rem;
}
.conversation-head__title p {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.84rem;
}
.locked-tag {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.1rem 0.5rem;
  border-radius: 999px;
  background: #fef3c7;
  color: #92400e;
  font-size: 0.7rem;
  font-weight: 700;
}
.audience-tag {
  flex: none;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.1rem 0.5rem;
  border-radius: 999px;
  background: var(--accent-soft);
  color: color-mix(in srgb, var(--accent) 70%, var(--text));
  font-size: 0.7rem;
  font-weight: 700;
}
.audience-tag .pi,
.locked-tag .pi {
  font-size: 0.65rem;
}
.conversation-head__actions {
  display: flex;
  align-items: center;
  gap: 0.15rem;
}
.presence-stack {
  display: inline-flex;
  align-items: center;
  margin-right: 0.4rem;
  padding: 0.2rem 0.6rem 0.2rem 0.25rem;
  border: 1px solid var(--border);
  border-radius: 999px;
  background: var(--surface);
  color: var(--muted);
  font: inherit;
  font-size: 0.78rem;
  cursor: pointer;
}
.presence-stack :deep(.chat-avatar) {
  margin-right: -0.45rem;
  box-shadow: 0 0 0 2px var(--surface);
}
.presence-stack span:last-child {
  margin-left: 0.75rem;
}

/* Tablets: the member list floats over the conversation */
@media (max-width: 1100px) {
  .chat-shell--members {
    grid-template-columns: minmax(220px, 260px) minmax(0, 1fr);
    position: relative;
  }
  .chat-shell__members {
    position: absolute;
    inset: 0 0 0 auto;
    width: min(280px, 85%);
    z-index: 2;
    box-shadow: -18px 0 40px rgba(15, 23, 42, 0.14);
  }
}

/* Phones: one pane at a time */
@media (max-width: 760px) {
  .chat-shell,
  .chat-shell--members {
    grid-template-columns: minmax(0, 1fr);
    height: calc(100vh - 170px);
    min-height: 460px;
  }
  .chat-shell--conversation .chat-shell__rooms,
  .chat-shell--rooms .chat-shell__main {
    display: none;
  }
  .chat-shell--rooms .chat-shell__members {
    display: none;
  }
  .conversation-head__back {
    display: inline-flex;
  }
  .presence-stack span:last-child,
  .chat-hero .muted {
    display: none;
  }
}
</style>
