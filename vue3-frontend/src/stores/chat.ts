import { defineStore } from 'pinia'
import { computed, reactive, ref } from 'vue'
import { chatApi, type ChatMutePayload, type ChatRoomPayload } from '../api'
import { connectRealtime, disconnectRealtime, onRealtimeConnected, realtime, realtimeStatus } from '../services/realtime'
import type { ChatMember, ChatMessage, ChatMute, ChatRoleOption, ChatRoom } from '../types/models'
import { useAccessStore } from './access'
import { useUserStore } from './user'

/** How long a "typing" whisper stays visible without a new one */
const TYPING_TTL_MS = 4_000
/** Minimum gap between our own "typing" whispers */
const TYPING_THROTTLE_MS = 2_500
/** Resync interval while the websocket is down or not configured */
const POLL_MS = 10_000

interface TypingEntry {
  name: string
  timer: ReturnType<typeof setTimeout>
}

type MuteEvent = { action: 'muted' | 'unmuted'; mute: ChatMute }
type DeletedEvent = { id: number; room_id: number; deleted_by: number }

/** Merge two id-sorted message lists, newest wins on duplicates */
function mergeMessages(current: ChatMessage[], incoming: ChatMessage[]): ChatMessage[] {
  const byId = new Map<number, ChatMessage>()
  for (const m of current) byId.set(m.id, m)
  for (const m of incoming) byId.set(m.id, m)
  return [...byId.values()].sort((a, b) => a.id - b.id)
}

/**
 * Live chat state: the rooms the member may enter (with unread counts), message
 * history per room, active mutes, room presence and typing indicators.
 *
 * Realtime channels (authorised by the API):
 * - `private-chat.feed.{room}`: one per room we may enter, carrying its messages.
 *   Restricted rooms (admins / roles) are never subscribed by others.
 * - `private-chat.tenant.{id}`: workspace signals; a room change only says "rooms
 *   changed", and we re-fetch the list the API filters for us.
 * - `presence-chat.room.{room}`: who has the open room on screen, plus "typing".
 *
 * The API is the authority: moderation rules mirrored here only decide which
 * buttons to show.
 */
export const useChatStore = defineStore('chat', () => {
  const user = useUserStore()
  const access = useAccessStore()

  const rooms = ref<ChatRoom[]>([])
  const mutes = ref<ChatMute[]>([])
  /** Workspace roles to pick a room audience from (room managers only) */
  const roles = ref<ChatRoleOption[]>([])
  /** Members who may enter each room (loaded when the room is opened) */
  const membersByRoom = reactive<Record<number, ChatMember[]>>({})
  const messages = reactive<Record<number, ChatMessage[]>>({})
  const hasMore = reactive<Record<number, boolean>>({})
  const online = reactive<Record<number, ChatMember[]>>({})
  const typing = reactive<Record<number, Record<number, TypingEntry>>>({})
  const activeRoomId = ref<number | null>(null)
  const started = ref(false)
  const ready = ref(false)
  const loadingHistory = ref(false)
  /** Ticks so mute countdowns and expiries update on screen */
  const now = ref(Date.now())

  let tenantChannel: string | null = null
  let presenceChannel: string | null = null
  /** Room ids whose message feed we are subscribed to */
  const feeds = new Set<number>()
  let clock: ReturnType<typeof setInterval> | null = null
  let poller: ReturnType<typeof setInterval> | null = null
  let offConnected: (() => void) | null = null
  let connectedOnce = false
  let starting: Promise<void> | null = null
  let lastTypingSent = 0
  let readTimer: ReturnType<typeof setTimeout> | null = null
  let roomsRefreshTimer: ReturnType<typeof setTimeout> | null = null
  const lastReadSent: Record<number, number> = {}

  const me = computed(() => user.user?.id ?? null)
  const status = computed(() => realtimeStatus.value)
  const activeRoom = computed(() => rooms.value.find((r) => r.id === activeRoomId.value) ?? null)
  const defaultRoom = computed(() => rooms.value.find((r) => r.is_default) ?? rooms.value[0] ?? null)
  const totalUnread = computed(() => rooms.value.reduce((sum, r) => sum + (r.unread_count ?? 0), 0))
  const liveMutes = computed(() => mutes.value.filter(isLive))

  // ---------------------------------------------------------------- moderation

  function isLive(mute: ChatMute): boolean {
    return mute.expires_at === null || Date.parse(mute.expires_at) > now.value
  }

  /** The mute silencing a member in a room: permanent beats timed, then the one ending last */
  function muteOf(userId: number, roomId: number | null): ChatMute | null {
    const covering = liveMutes.value.filter((m) => m.user?.id === userId && (m.room_id === null || m.room_id === roomId))
    if (!covering.length) return null
    return covering.reduce((best, m) => {
      if (best.expires_at === null) return best
      if (m.expires_at === null) return m
      return Date.parse(m.expires_at) > Date.parse(best.expires_at) ? m : best
    })
  }

  function myMute(roomId: number | null): ChatMute | null {
    return me.value === null ? null : muteOf(me.value, roomId)
  }

  /** Admins moderate everyone; chat.mute holders everyone but admins; never yourself */
  function canModerate(target: ChatMember | null | undefined): boolean {
    if (!target || target.id === me.value) return false
    if (access.isAdmin) return true
    return access.can('chat.mute') && !target.is_admin
  }

  function canDelete(message: ChatMessage): boolean {
    if (message.user && message.user.id === me.value) return true
    if (!message.user) return access.isAdmin || access.can('chat.mute')
    return canModerate(message.user)
  }

  function canPost(room: ChatRoom | null): boolean {
    if (!room) return false
    if (room.is_locked && !access.isAdmin) return false
    return myMute(room.id) === null
  }

  // ------------------------------------------------------------------ lifecycle

  /** Idempotent; concurrent callers share one boot and all see its outcome */
  function start(): Promise<void> {
    if (started.value) return starting ?? Promise.resolve()
    started.value = true
    const boot = run()
    starting = boot
    boot.then(
      () => (starting = null),
      () => (starting = null)
    )
    return boot
  }

  async function run(): Promise<void> {
    clock = setInterval(() => (now.value = Date.now()), 15_000)
    document.addEventListener('visibilitychange', onVisibilityChange)

    try {
      await refreshRooms()
    } catch (err) {
      // Roll back so the next start() (e.g. the chat page retrying) begins clean
      stop()
      throw err
    }
    if (!started.value) return
    ready.value = true

    subscribeTenant()
    syncFeeds()
    offConnected = onRealtimeConnected(() => {
      // The first connect follows the initial load; later ones are reconnects that may have missed events
      if (connectedOnce) void resync()
      connectedOnce = true
    })
    poller = setInterval(() => {
      if (realtimeStatus.value !== 'connected') void resync()
    }, POLL_MS)
  }

  function stop(): void {
    if (!started.value) return
    started.value = false
    if (clock) clearInterval(clock)
    if (poller) clearInterval(poller)
    if (readTimer) clearTimeout(readTimer)
    if (roomsRefreshTimer) clearTimeout(roomsRefreshTimer)
    clock = poller = readTimer = roomsRefreshTimer = null
    offConnected?.()
    offConnected = null
    connectedOnce = false
    document.removeEventListener('visibilitychange', onVisibilityChange)
    disconnectRealtime()
    tenantChannel = presenceChannel = null
    feeds.clear()
    reset()
  }

  function reset(): void {
    rooms.value = []
    mutes.value = []
    roles.value = []
    activeRoomId.value = null
    ready.value = false
    for (const record of [membersByRoom, messages, hasMore, online, lastReadSent]) {
      for (const key of Object.keys(record)) delete (record as Record<string, unknown>)[key]
    }
    for (const key of Object.keys(typing)) clearTyping(Number(key))
  }

  /** Catch up after a reconnect or while polling */
  async function resync(): Promise<void> {
    try {
      await refreshRooms()
      if (activeRoomId.value !== null) await loadLatest(activeRoomId.value)
    } catch {
      // Offline: the next tick or reconnect tries again
    }
  }

  function onVisibilityChange(): void {
    if (document.visibilityState === 'visible') scheduleRead()
  }

  // ---------------------------------------------------------------------- data

  /** The rooms we may enter (the API filters by audience), then follow exactly their feeds */
  async function refreshRooms(): Promise<void> {
    const response = await chatApi.rooms()
    rooms.value = response.data
    mutes.value = response.meta.mutes
    roles.value = response.meta.roles ?? []

    // A room taken away from us (deleted, audience narrowed): forget it
    const visible = new Set(rooms.value.map((r) => r.id))
    for (const key of Object.keys(messages)) {
      if (!visible.has(Number(key))) delete messages[Number(key)]
    }
    if (activeRoomId.value !== null && !visible.has(activeRoomId.value)) closeRoom()
    if (started.value) syncFeeds()
  }

  /** Debounced: several signals in a row cost one request */
  function refreshRoomsSoon(): void {
    if (roomsRefreshTimer) clearTimeout(roomsRefreshTimer)
    roomsRefreshTimer = setTimeout(() => void refreshRooms().catch(() => {}), 400)
  }

  async function loadMembers(roomId: number): Promise<void> {
    try {
      membersByRoom[roomId] = await chatApi.members(roomId)
    } catch {
      membersByRoom[roomId] = []
    }
  }

  function membersFor(roomId: number): ChatMember[] {
    return membersByRoom[roomId] ?? []
  }

  /** Newest page; replaces the list when it no longer overlaps (we missed more than a page) */
  async function loadLatest(roomId: number): Promise<void> {
    const response = await chatApi.messages(roomId)
    const current = messages[roomId]
    const overlaps = current?.length && response.data.length && response.data[0].id <= current[current.length - 1].id

    if (!current || !overlaps) {
      messages[roomId] = response.data
      hasMore[roomId] = response.meta.has_more
    } else {
      messages[roomId] = mergeMessages(current, response.data)
    }
  }

  async function loadOlder(roomId: number): Promise<void> {
    const current = messages[roomId]
    if (loadingHistory.value || !current?.length || !hasMore[roomId]) return
    loadingHistory.value = true
    try {
      const response = await chatApi.messages(roomId, current[0].id)
      messages[roomId] = mergeMessages(messages[roomId] ?? [], response.data)
      hasMore[roomId] = response.meta.has_more
    } finally {
      loadingHistory.value = false
    }
  }

  async function openRoom(roomId: number): Promise<void> {
    if (activeRoomId.value === roomId && messages[roomId]) return
    leavePresence()
    activeRoomId.value = roomId
    joinPresence(roomId)
    void loadMembers(roomId)

    if (!messages[roomId]) {
      loadingHistory.value = true
      try {
        await loadLatest(roomId)
      } finally {
        loadingHistory.value = false
      }
    }
    scheduleRead(0)
  }

  function closeRoom(): void {
    leavePresence()
    activeRoomId.value = null
  }

  /** Mark the open room read up to its newest message (only when the tab is visible) */
  function scheduleRead(delay = 400): void {
    if (readTimer) clearTimeout(readTimer)
    readTimer = setTimeout(() => {
      const room = activeRoom.value
      const list = room ? messages[room.id] : undefined
      if (!room || !list?.length || document.visibilityState !== 'visible') return

      const lastId = list[list.length - 1].id
      room.unread_count = 0
      if ((lastReadSent[room.id] ?? 0) >= lastId) return
      lastReadSent[room.id] = lastId
      void chatApi.markRead(room.id, lastId).catch(() => delete lastReadSent[room.id])
    }, delay)
  }

  // ------------------------------------------------------------------- actions

  async function send(body: string): Promise<void> {
    const room = activeRoom.value
    if (!room) return
    const message = await chatApi.send(room.id, body)
    lastTypingSent = 0
    receive(message)
  }

  async function deleteMessage(message: ChatMessage): Promise<void> {
    await chatApi.deleteMessage(message.id)
    removeMessage(message.room_id, message.id)
  }

  async function mute(payload: ChatMutePayload): Promise<ChatMute> {
    const created = await chatApi.mute(payload)
    applyMuteChange({ action: 'muted', mute: created })
    return created
  }

  async function unmute(target: ChatMute): Promise<void> {
    await chatApi.unmute(target.id)
    applyMuteChange({ action: 'unmuted', mute: target })
  }

  /** Room changes re-fetch the list: the API decides which rooms we may see */
  async function createRoom(payload: ChatRoomPayload): Promise<ChatRoom> {
    const room = await chatApi.createRoom(payload)
    await refreshRooms()
    return room
  }

  async function updateRoom(id: number, payload: Partial<ChatRoomPayload>): Promise<ChatRoom> {
    const room = await chatApi.updateRoom(id, payload)
    await refreshRooms()
    // The audience may have changed who belongs here
    if (membersByRoom[id]) void loadMembers(id)
    return room
  }

  async function deleteRoom(room: ChatRoom): Promise<void> {
    await chatApi.deleteRoom(room.id)
    await refreshRooms()
  }

  /** Tell the room we are typing (throttled; whispers never touch the API) */
  function notifyTyping(): void {
    const echo = realtime()
    if (!echo || !presenceChannel || me.value === null) return
    if (Date.now() - lastTypingSent < TYPING_THROTTLE_MS) return
    lastTypingSent = Date.now()
    echo.join(presenceChannel).whisper('typing', { id: me.value, name: user.displayName })
  }

  function typingNames(roomId: number): string[] {
    return Object.values(typing[roomId] ?? {}).map((entry) => entry.name)
  }

  // -------------------------------------------------------------- live events

  function subscribeTenant(): void {
    const echo = connectRealtime()
    const tenantId = user.tenant?.id
    if (!echo || !tenantId) return

    tenantChannel = `chat.tenant.${tenantId}`
    echo
      .private(tenantChannel)
      .listen('.room.changed', () => refreshRoomsSoon())
      .listen('.mute.changed', (event: MuteEvent) => applyMuteChange(event))
  }

  /** Subscribe to the feed of every room we may enter, and leave the others */
  function syncFeeds(): void {
    const echo = realtime()
    if (!echo) return
    const wanted = new Set(rooms.value.map((r) => r.id))

    for (const id of [...feeds]) {
      if (!wanted.has(id)) {
        echo.leaveChannel(`private-chat.feed.${id}`)
        feeds.delete(id)
      }
    }
    for (const id of wanted) {
      if (feeds.has(id)) continue
      echo
        .private(`chat.feed.${id}`)
        .listen('.message.sent', (message: ChatMessage) => receive(message))
        .listen('.message.deleted', (event: DeletedEvent) => removeMessage(event.room_id, event.id))
      feeds.add(id)
    }
  }

  function joinPresence(roomId: number): void {
    const echo = realtime()
    if (!echo) return
    presenceChannel = `chat.room.${roomId}`
    online[roomId] = []

    echo
      .join(presenceChannel)
      .here((list: ChatMember[]) => (online[roomId] = list))
      .joining((member: ChatMember) => {
        if (!online[roomId]?.some((m) => m.id === member.id)) online[roomId] = [...(online[roomId] ?? []), member]
      })
      .leaving((member: ChatMember) => {
        online[roomId] = (online[roomId] ?? []).filter((m) => m.id !== member.id)
        stopTyping(roomId, member.id)
      })
      .listenForWhisper('typing', (event: { id: number; name: string }) => startTyping(roomId, event))
  }

  function leavePresence(): void {
    // Only the presence channel: the room's message feed stays subscribed for unread counts
    if (presenceChannel) realtime()?.leaveChannel(`presence-${presenceChannel}`)
    if (activeRoomId.value !== null) {
      delete online[activeRoomId.value]
      clearTyping(activeRoomId.value)
    }
    presenceChannel = null
  }

  function receive(message: ChatMessage): void {
    const list = messages[message.room_id]
    // Rooms never opened load their history on first open; no need to keep a partial list
    if (list) messages[message.room_id] = mergeMessages(list, [message])

    const room = rooms.value.find((r) => r.id === message.room_id)
    if (room) {
      if (!room.last_message || room.last_message.id <= message.id) room.last_message = message
      const own = message.user?.id === me.value
      if (!own) {
        if (room.id === activeRoomId.value && document.visibilityState === 'visible') scheduleRead()
        else room.unread_count = (room.unread_count ?? 0) + 1
      }
    }
    if (message.user) stopTyping(message.room_id, message.user.id)
  }

  function removeMessage(roomId: number, id: number): void {
    const list = messages[roomId]
    if (list) messages[roomId] = list.filter((m) => m.id !== id)

    const room = rooms.value.find((r) => r.id === roomId)
    if (room?.last_message?.id === id) {
      const remaining = messages[roomId]
      room.last_message = remaining?.length ? remaining[remaining.length - 1] : null
    }
    refreshRoomsSoon()
  }

  function applyMuteChange({ action, mute: changed }: MuteEvent): void {
    // Re-muting replaces the previous terms server side (same user + scope)
    const rest = mutes.value.filter(
      (m) => m.id !== changed.id && !(m.user?.id === changed.user?.id && m.room_id === changed.room_id)
    )
    mutes.value = action === 'muted' ? [...rest, changed] : rest
  }

  function startTyping(roomId: number, event: { id: number; name: string }): void {
    if (event.id === me.value) return
    const entries = (typing[roomId] ??= {})
    if (entries[event.id]) clearTimeout(entries[event.id].timer)
    entries[event.id] = { name: event.name, timer: setTimeout(() => stopTyping(roomId, event.id), TYPING_TTL_MS) }
  }

  function stopTyping(roomId: number, userId: number): void {
    const entry = typing[roomId]?.[userId]
    if (!entry) return
    clearTimeout(entry.timer)
    delete typing[roomId][userId]
  }

  function clearTyping(roomId: number): void {
    for (const entry of Object.values(typing[roomId] ?? {})) clearTimeout(entry.timer)
    delete typing[roomId]
  }

  return {
    rooms,
    mutes,
    liveMutes,
    roles,
    messages,
    hasMore,
    online,
    activeRoomId,
    activeRoom,
    defaultRoom,
    totalUnread,
    started,
    ready,
    loadingHistory,
    now,
    status,
    start,
    stop,
    refreshRooms,
    loadMembers,
    membersFor,
    loadOlder,
    openRoom,
    closeRoom,
    scheduleRead,
    send,
    deleteMessage,
    mute,
    unmute,
    createRoom,
    updateRoom,
    deleteRoom,
    notifyTyping,
    typingNames,
    muteOf,
    myMute,
    canModerate,
    canDelete,
    canPost
  }
})
