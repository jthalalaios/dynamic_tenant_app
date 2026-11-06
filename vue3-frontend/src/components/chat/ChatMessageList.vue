<template>
  <div class="message-list">
    <div ref="scroller" class="message-list__scroll" role="log" aria-live="polite" :aria-label="room.name" @scroll.passive="onScroll">
      <div class="message-list__history">
        <Button
          v-if="hasMore"
          :label="t('chat.loadOlder')"
          icon="pi pi-history"
          size="small"
          text
          rounded
          :loading="loading"
          @click="requestOlder"
        />
        <div v-else-if="!loading" class="message-list__intro">
          <span class="message-list__intro-icon"><i :class="`pi pi-${room.icon}`" aria-hidden="true"></i></span>
          <h3>{{ room.name }}</h3>
          <p class="muted">{{ room.description || t('chat.beginning', { room: room.name }) }}</p>
        </div>
      </div>

      <template v-if="loading && !messages.length">
        <div v-for="n in 5" :key="n" :class="['skeleton-row', { 'skeleton-row--own': n % 3 === 0 }]">
          <Skeleton shape="circle" size="2.4rem" />
          <Skeleton :width="`${30 + ((n * 17) % 40)}%`" height="2.6rem" borderRadius="16px" />
        </div>
      </template>

      <p v-else-if="!messages.length" class="message-list__empty muted">
        <i class="pi pi-comments" aria-hidden="true"></i> {{ t('chat.empty') }}
      </p>

      <template v-for="item in items" :key="item.key">
        <div v-if="item.kind === 'day'" class="day-divider" role="separator">
          <span>{{ item.label }}</span>
        </div>

        <article
          v-else
          :class="['message', { 'message--own': item.own, 'message--grouped': !item.head }]"
          :aria-label="`${item.message.user?.name ?? t('chat.formerMember')}, ${time.full(item.message.created_at)}`"
        >
          <div class="message__gutter">
            <ChatAvatar v-if="item.head && !item.own" :member="item.message.user" />
          </div>

          <div class="message__content">
            <header v-if="item.head" class="message__meta">
              <span class="message__author">{{ item.own ? t('chat.you') : item.message.user?.name ?? t('chat.formerMember') }}</span>
              <span v-if="item.message.user?.is_admin" class="message__admin" :title="t('chat.admin')">
                <i class="pi pi-shield" aria-hidden="true"></i>{{ t('chat.admin') }}
              </span>
              <span v-if="item.muted" class="message__muted" :title="t('chat.mutedBadge')">
                <i class="pi pi-volume-off" aria-hidden="true"></i>
              </span>
              <time class="message__time" :datetime="item.message.created_at" :title="time.full(item.message.created_at)">
                {{ time.clock(item.message.created_at) }}
              </time>
            </header>

            <div class="message__row">
              <p class="message__bubble" :title="item.head ? undefined : time.full(item.message.created_at)">{{ item.message.body }}</p>

              <div v-if="item.canDelete || item.canModerate" class="message__actions">
                <button
                  v-if="item.canModerate && item.message.user"
                  type="button"
                  class="message__action"
                  :title="t('chat.mute')"
                  :aria-label="t('chat.muteTitle', { name: item.message.user.name })"
                  @click="emit('mute', item.message.user)"
                >
                  <i class="pi pi-volume-off" aria-hidden="true"></i>
                </button>
                <button
                  v-if="item.canDelete"
                  type="button"
                  class="message__action message__action--danger"
                  :title="t('chat.deleteMessage')"
                  :aria-label="t('chat.deleteMessage')"
                  @click="emit('delete', item.message)"
                >
                  <i class="pi pi-trash" aria-hidden="true"></i>
                </button>
              </div>
            </div>
          </div>
        </article>
      </template>
    </div>

    <div class="message-list__footer">
      <transition name="pop">
        <button v-if="unseen > 0" type="button" class="jump" @click="scrollToBottom(true)">
          <i class="pi pi-arrow-down" aria-hidden="true"></i>
          {{ t('chat.newMessages') }} · {{ unseen }}
        </button>
      </transition>

      <p class="typing" aria-live="polite">
        <template v-if="typingText">
          <span class="typing__dots" aria-hidden="true"><span></span><span></span><span></span></span>
          {{ typingText }}
        </template>
      </p>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'
import ChatAvatar from './ChatAvatar.vue'
import { useChatTime } from '../../composables/useChatTime'
import { useChatStore, useUserStore } from '../../stores'
import type { ChatMember, ChatMessage, ChatRoom } from '../../types/models'

/** Consecutive messages of one author within this window share a header */
const GROUP_WINDOW_MS = 5 * 60_000
/** Within this distance from the bottom, new messages auto-scroll into view */
const STICKY_PX = 140

const props = defineProps<{
  room: ChatRoom
  messages: ChatMessage[]
  hasMore: boolean
  loading: boolean
  typingNames: string[]
}>()

const emit = defineEmits<{
  loadOlder: []
  delete: [message: ChatMessage]
  mute: [member: ChatMember]
}>()

const { t } = useI18n()
const time = useChatTime()
const chat = useChatStore()
const user = useUserStore()

const scroller = ref<HTMLElement | null>(null)
const unseen = ref(0)
let pinnedToBottom = true
let restore: { height: number; top: number } | null = null

type Item =
  | { kind: 'day'; key: string; label: string }
  | { kind: 'message'; key: string; message: ChatMessage; own: boolean; head: boolean; muted: boolean; canDelete: boolean; canModerate: boolean }

const items = computed<Item[]>(() => {
  const out: Item[] = []
  let previous: ChatMessage | null = null
  let previousDay = ''

  for (const message of props.messages) {
    const day = time.dayKey(message.created_at)
    if (day !== previousDay) {
      out.push({ kind: 'day', key: `day-${day}`, label: time.dayLabel(message.created_at) })
      previous = null
      previousDay = day
    }

    const sameAuthor = previous !== null && previous.user?.id === message.user?.id
    const close = previous !== null && Date.parse(message.created_at) - Date.parse(previous.created_at) < GROUP_WINDOW_MS
    const author = message.user

    out.push({
      kind: 'message',
      key: `m-${message.id}`,
      message,
      own: author?.id === user.user?.id,
      head: !(sameAuthor && close),
      muted: author ? chat.muteOf(author.id, props.room.id) !== null : false,
      canDelete: chat.canDelete(message),
      canModerate: chat.canModerate(author)
    })
    previous = message
  }
  return out
})

const typingText = computed(() => {
  const names = props.typingNames
  if (names.length === 0) return ''
  if (names.length === 1) return t('chat.typingOne', { name: names[0] })
  if (names.length === 2) return t('chat.typingTwo', { first: names[0], second: names[1] })
  return t('chat.typingMany')
})

function distanceFromBottom(): number {
  const el = scroller.value
  return el ? el.scrollHeight - el.scrollTop - el.clientHeight : 0
}

function scrollToBottom(smooth = false): void {
  const el = scroller.value
  if (!el) return
  el.scrollTo({ top: el.scrollHeight, behavior: smooth ? 'smooth' : 'auto' })
  unseen.value = 0
  pinnedToBottom = true
}

function requestOlder(): void {
  const el = scroller.value
  if (!el || props.loading || !props.hasMore) return
  // Keep the first visible message in place once older ones are prepended
  restore = { height: el.scrollHeight, top: el.scrollTop }
  emit('loadOlder')
}

function onScroll(): void {
  const el = scroller.value
  if (!el) return
  pinnedToBottom = distanceFromBottom() < STICKY_PX
  if (pinnedToBottom) unseen.value = 0
  if (el.scrollTop < 80) requestOlder()
}

// New room: start at the latest message
watch(
  () => props.room.id,
  () => {
    unseen.value = 0
    restore = null
    void nextTick(() => scrollToBottom())
  }
)

watch(
  () => props.messages,
  (next, previous) => {
    void nextTick(() => {
      const el = scroller.value
      if (!el) return

      const prependedOlder = previous?.length && next.length && next[0].id < previous[0].id
      if (prependedOlder && restore) {
        el.scrollTop = el.scrollHeight - restore.height + restore.top
        restore = null
        return
      }
      restore = null

      const newest = next[next.length - 1]
      const grew = !previous?.length || (newest && newest.id > previous[previous.length - 1].id)
      if (!grew) return

      const mine = newest?.user?.id === user.user?.id
      if (pinnedToBottom || mine || !previous?.length) scrollToBottom(!!previous?.length)
      else unseen.value += next.length - previous.length
    })
  }
)

onMounted(() => void nextTick(() => scrollToBottom()))
</script>

<style scoped>
.message-list {
  position: relative;
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}
.message-list__scroll {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 0.5rem 1.25rem 0.75rem;
  scroll-behavior: auto;
}
.message-list__history {
  display: grid;
  justify-items: center;
  padding: 0.75rem 0 0.25rem;
}
.message-list__intro {
  display: grid;
  justify-items: center;
  gap: 0.35rem;
  padding: 1.25rem 1rem 0.75rem;
  text-align: center;
}
.message-list__intro-icon {
  display: grid;
  place-items: center;
  width: 3.4rem;
  height: 3.4rem;
  border-radius: 18px;
  background: linear-gradient(135deg, var(--primary), var(--accent));
  color: var(--primary-contrast);
  font-size: 1.3rem;
  box-shadow: 0 12px 28px color-mix(in srgb, var(--primary) 28%, transparent);
}
.message-list__intro h3 {
  font-size: 1.1rem;
}
.message-list__intro p {
  max-width: 44ch;
  font-size: 0.9rem;
}
.message-list__empty {
  padding: 2rem 0;
  text-align: center;
}
.skeleton-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.45rem 0;
}
.skeleton-row--own {
  flex-direction: row-reverse;
}

.day-divider {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 1.1rem 0 0.6rem;
  color: var(--muted);
  font-size: 0.74rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.day-divider::before,
.day-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--border);
}

.message {
  display: flex;
  gap: 0.65rem;
  margin-top: 0.85rem;
  animation: message-in 220ms ease-out;
}
.message--grouped {
  margin-top: 0.2rem;
}
.message__gutter {
  flex: none;
  width: 2.4rem;
}
.message--own {
  flex-direction: row-reverse;
}
.message--own .message__gutter {
  width: 0;
}
.message__content {
  min-width: 0;
  max-width: min(72ch, 78%);
  display: grid;
  gap: 0.25rem;
}
.message--own .message__content {
  justify-items: end;
}
.message__meta {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  font-size: 0.8rem;
}
.message--own .message__meta {
  flex-direction: row-reverse;
}
.message__author {
  font-weight: 700;
}
.message__admin {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.05rem 0.45rem;
  border-radius: 999px;
  background: var(--primary-soft);
  color: var(--primary);
  font-size: 0.68rem;
  font-weight: 700;
}
.message__admin .pi {
  font-size: 0.65rem;
}
.message__muted {
  color: #b45309;
  font-size: 0.75rem;
}
.message__time {
  color: var(--muted);
  font-size: 0.72rem;
}
.message__row {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}
.message--own .message__row {
  flex-direction: row-reverse;
}
.message__bubble {
  padding: 0.6rem 0.85rem;
  border: 1px solid var(--border);
  border-radius: 6px 18px 18px 18px;
  background: var(--surface);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  line-height: 1.45;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.message--grouped .message__bubble {
  border-top-left-radius: 18px;
}
.message--own .message__bubble {
  border: none;
  border-radius: 18px 6px 18px 18px;
  background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 72%, var(--accent)));
  color: var(--primary-contrast);
  box-shadow: 0 6px 16px color-mix(in srgb, var(--primary) 22%, transparent);
}
.message--own.message--grouped .message__bubble {
  border-top-right-radius: 18px;
}
.message__actions {
  display: flex;
  gap: 0.15rem;
  opacity: 0;
  transition: opacity 120ms ease;
}
.message:hover .message__actions,
.message:focus-within .message__actions {
  opacity: 1;
}
.message__action {
  display: grid;
  place-items: center;
  width: 1.9rem;
  height: 1.9rem;
  border: 1px solid var(--border);
  border-radius: 50%;
  background: var(--surface);
  color: var(--muted);
  cursor: pointer;
  transition: color 120ms ease, border-color 120ms ease;
}
.message__action:hover,
.message__action:focus-visible {
  color: var(--primary);
  border-color: var(--primary);
}
.message__action--danger:hover,
.message__action--danger:focus-visible {
  color: #dc2626;
  border-color: #dc2626;
}

.message-list__footer {
  position: relative;
  padding: 0 1.25rem;
}
.jump {
  position: absolute;
  left: 50%;
  bottom: calc(100% + 0.5rem);
  transform: translateX(-50%);
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.9rem;
  border: none;
  border-radius: 999px;
  background: var(--primary);
  color: var(--primary-contrast);
  font: inherit;
  font-size: 0.82rem;
  font-weight: 600;
  box-shadow: 0 10px 24px color-mix(in srgb, var(--primary) 30%, transparent);
  cursor: pointer;
}
.typing {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 1.4rem;
  color: var(--muted);
  font-size: 0.8rem;
}
.typing__dots {
  display: inline-flex;
  gap: 0.2rem;
}
.typing__dots span {
  width: 0.35rem;
  height: 0.35rem;
  border-radius: 50%;
  background: var(--primary);
  animation: typing-bounce 1.2s infinite ease-in-out;
}
.typing__dots span:nth-child(2) {
  animation-delay: 0.15s;
}
.typing__dots span:nth-child(3) {
  animation-delay: 0.3s;
}

.pop-enter-active,
.pop-leave-active {
  transition: opacity 160ms ease, transform 160ms ease;
}
.pop-enter-from,
.pop-leave-to {
  opacity: 0;
  transform: translate(-50%, 6px);
}

@keyframes message-in {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: none; }
}
@keyframes typing-bounce {
  0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
  40% { transform: translateY(-4px); opacity: 1; }
}

@media (max-width: 640px) {
  .message-list__scroll {
    padding: 0.5rem 0.75rem;
  }
  .message__content {
    max-width: 86%;
  }
  .message__actions {
    opacity: 1;
  }
}
</style>
