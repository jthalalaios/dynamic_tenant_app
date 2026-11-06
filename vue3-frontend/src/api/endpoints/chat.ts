import { http } from '../http'
import type { Resource } from '../../types/api'
import type { ChatAudience, ChatMember, ChatMessage, ChatMute, ChatRoleOption, ChatRoom } from '../../types/models'

export interface ChatRoomPayload {
  name: string
  description?: string | null
  icon?: string
  /** Administrators only */
  is_locked?: boolean
  audience?: ChatAudience
  /** Required when the audience is "roles" */
  role_ids?: number[]
}

export interface ChatMutePayload {
  user_id: number
  /** null = every room */
  room_id?: number | null
  /** One of CHAT_MUTE_DURATIONS; null = until lifted */
  duration_minutes?: number | null
  reason?: string | null
}

/** Mirrors ChatMute::DURATIONS on the API (15m, 1h, 24h, 7d) */
export const CHAT_MUTE_DURATIONS = [15, 60, 1440, 10080] as const

/** Mirrors ChatRoom::ICONS on the API */
export const CHAT_ROOM_ICONS = [
  'comments', 'hashtag', 'megaphone', 'users', 'heart', 'briefcase',
  'bolt', 'star', 'calendar', 'shopping-cart', 'flag', 'globe', 'shield'
] as const

export const chatApi = {
  /** Rooms the viewer may enter; meta.roles is filled for room managers only */
  rooms: () => http.get<{ data: ChatRoom[]; meta: { mutes: ChatMute[]; roles: ChatRoleOption[] } }>('/chat/rooms'),
  createRoom: (payload: ChatRoomPayload) => http.post<Resource<ChatRoom>>('/chat/rooms', payload).then((r) => r.data),
  updateRoom: (id: number, payload: Partial<ChatRoomPayload>) =>
    http.patch<Resource<ChatRoom>>(`/chat/rooms/${id}`, payload).then((r) => r.data),
  deleteRoom: (id: number) => http.delete(`/chat/rooms/${id}`),
  markRead: (id: number, messageId?: number) => http.post(`/chat/rooms/${id}/read`, messageId ? { message_id: messageId } : {}),

  /** Oldest first; `before` pages backwards */
  messages: (roomId: number, before?: number) =>
    http.get<{ data: ChatMessage[]; meta: { has_more: boolean } }>(`/chat/rooms/${roomId}/messages`, { before }),
  send: (roomId: number, body: string) =>
    http.post<Resource<ChatMessage>>(`/chat/rooms/${roomId}/messages`, { body }).then((r) => r.data),
  deleteMessage: (id: number) => http.delete(`/chat/messages/${id}`),

  /** Members who can chat; with a room, only those who may enter it */
  members: (roomId?: number) => http.get<{ data: ChatMember[] }>('/chat/members', { room: roomId }).then((r) => r.data),

  mutes: () => http.get<{ data: ChatMute[] }>('/chat/mutes').then((r) => r.data),
  mute: (payload: ChatMutePayload) => http.post<Resource<ChatMute>>('/chat/mutes', payload).then((r) => r.data),
  unmute: (id: number) => http.delete(`/chat/mutes/${id}`)
}
