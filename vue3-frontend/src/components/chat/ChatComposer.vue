<template>
  <div v-if="blocked" :class="['composer-blocked', `composer-blocked--${blocked.kind}`]" role="status">
    <i :class="blocked.icon" aria-hidden="true"></i>
    <span>{{ blocked.text }}</span>
  </div>

  <form v-else class="composer" @submit.prevent="submit">
    <Textarea
      ref="input"
      v-model="body"
      class="composer__input"
      rows="1"
      autoResize
      :maxlength="MAX_LENGTH"
      :placeholder="t('chat.placeholder', { room: room.name })"
      :aria-label="t('chat.placeholder', { room: room.name })"
      @keydown="onKeydown"
      @input="chat.notifyTyping()"
    />
    <div class="composer__side">
      <small v-if="body.length > MAX_LENGTH - 200" :class="['composer__count', { 'composer__count--full': body.length >= MAX_LENGTH }]">
        {{ t('chat.characters', { count: body.length, max: MAX_LENGTH }) }}
      </small>
      <Button
        type="submit"
        icon="pi pi-send"
        rounded
        :loading="sending"
        :disabled="!canSend"
        :aria-label="t('chat.send')"
        :title="`${t('chat.send')} · ${t('chat.sendHint')}`"
      />
    </div>
  </form>
</template>

<script lang="ts" setup>
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import { ApiError } from '../../api'
import { useChatTime } from '../../composables/useChatTime'
import { useNotify } from '../../composables/useNotify'
import { useAccessStore, useChatStore } from '../../stores'
import type { ChatRoom } from '../../types/models'

/** Mirrors the API's `max:2000` rule */
const MAX_LENGTH = 2000

const props = defineProps<{ room: ChatRoom }>()

const { t } = useI18n()
const chat = useChatStore()
const access = useAccessStore()
const notify = useNotify()
const time = useChatTime()

const body = ref('')
const sending = ref(false)
const input = ref<{ $el: HTMLTextAreaElement } | null>(null)

/** Drafts survive switching rooms (in memory, per room) */
const drafts = new Map<number, string>()
watch(
  () => props.room.id,
  (next, previous) => {
    if (previous !== undefined) drafts.set(previous, body.value)
    body.value = drafts.get(next) ?? ''
    void nextTick(focus)
  }
)

const canSend = computed(() => body.value.trim().length > 0 && body.value.length <= MAX_LENGTH && !sending.value)

const blocked = computed(() => {
  const mute = chat.myMute(props.room.id)
  if (mute) {
    return {
      kind: 'muted',
      icon: 'pi pi-volume-off',
      text: mute.expires_at ? t('chat.mutedYouUntil', { time: time.full(mute.expires_at) }) : t('chat.mutedYou')
    }
  }
  if (props.room.is_locked && !access.isAdmin) {
    return { kind: 'locked', icon: 'pi pi-lock', text: t('chat.lockedHint') }
  }
  return null
})

function focus(): void {
  input.value?.$el?.focus()
}

function onKeydown(event: KeyboardEvent): void {
  // Enter sends, Shift+Enter adds a line; never interrupt an IME composition
  if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
    event.preventDefault()
    void submit()
  }
}

async function submit(): Promise<void> {
  if (!canSend.value) return
  const text = body.value.trim()
  sending.value = true
  try {
    await chat.send(text)
    body.value = ''
    drafts.delete(props.room.id)
  } catch (err) {
    // The server knows best: a mute or lock may have just been applied
    if (err instanceof ApiError && (err.code === 'chat_muted' || err.code === 'chat_room_locked')) {
      void chat.refreshRooms().catch(() => {})
    }
    notify.error(err)
  } finally {
    sending.value = false
    void nextTick(focus)
  }
}

defineExpose({ focus })
</script>

<style scoped>
.composer {
  display: flex;
  align-items: flex-end;
  gap: 0.6rem;
  margin: 0 1rem 1rem;
  padding: 0.45rem 0.45rem 0.45rem 0.9rem;
  border: 1px solid var(--border);
  border-radius: 20px;
  background: var(--surface);
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
  transition: border-color 140ms ease, box-shadow 140ms ease;
}
.composer:focus-within {
  border-color: color-mix(in srgb, var(--primary) 55%, var(--border));
  box-shadow: 0 0 0 4px var(--primary-soft), 0 8px 24px rgba(15, 23, 42, 0.06);
}
.composer__input {
  flex: 1;
  max-height: 10rem;
  overflow-y: auto !important;
  padding: 0.5rem 0;
  border: none !important;
  background: transparent !important;
  box-shadow: none !important;
  resize: none;
  font: inherit;
  line-height: 1.45;
}
.composer__side {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.composer__count {
  color: var(--muted);
  font-size: 0.72rem;
  white-space: nowrap;
}
.composer__count--full {
  color: #dc2626;
  font-weight: 700;
}
.composer-blocked {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin: 0 1rem 1rem;
  padding: 0.85rem 1rem;
  border-radius: 16px;
  font-size: 0.9rem;
  font-weight: 500;
}
.composer-blocked--muted {
  border: 1px solid #fcd34d;
  background: #fffbeb;
  color: #92400e;
}
.composer-blocked--locked {
  border: 1px solid var(--border);
  background: var(--surface-elevated);
  color: var(--muted);
}
</style>
