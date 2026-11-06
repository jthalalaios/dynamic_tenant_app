<template>
  <span :class="['chat-avatar', `chat-avatar--${size}`, { 'chat-avatar--ghost': !member }]" :style="palette" aria-hidden="true">
    <i v-if="!member" class="pi pi-user"></i>
    <template v-else>{{ letters }}</template>
    <span v-if="online !== undefined" :class="['chat-avatar__dot', { 'chat-avatar__dot--on': online }]"></span>
  </span>
</template>

<script lang="ts" setup>
import { computed } from 'vue'
import { initials } from '../../helpers/text'
import type { ChatMember } from '../../types/models'

const props = withDefaults(
  defineProps<{
    member: Pick<ChatMember, 'id' | 'name'> | null
    size?: 'sm' | 'md' | 'lg'
    /** Shows a presence dot when set */
    online?: boolean
  }>(),
  { size: 'md', online: undefined }
)

const letters = computed(() => initials(props.member?.name ?? ''))

// A stable, distinct hue per member, softened to sit well on both themes
const palette = computed(() => {
  if (!props.member) return {}
  const hue = (props.member.id * 137) % 360
  return {
    '--avatar-bg': `hsl(${hue} 72% 90%)`,
    '--avatar-fg': `hsl(${hue} 52% 30%)`
  }
})
</script>

<style scoped>
.chat-avatar {
  position: relative;
  flex: none;
  display: inline-grid;
  place-items: center;
  border-radius: 50%;
  background: var(--avatar-bg, var(--surface-elevated));
  color: var(--avatar-fg, var(--muted));
  font-weight: 700;
  letter-spacing: 0.02em;
  user-select: none;
}
.chat-avatar--sm {
  width: 1.9rem;
  height: 1.9rem;
  font-size: 0.68rem;
}
.chat-avatar--md {
  width: 2.4rem;
  height: 2.4rem;
  font-size: 0.8rem;
}
.chat-avatar--lg {
  width: 3rem;
  height: 3rem;
  font-size: 0.95rem;
}
.chat-avatar--ghost {
  border: 1px dashed var(--border);
}
.chat-avatar__dot {
  position: absolute;
  right: -1px;
  bottom: -1px;
  width: 32%;
  height: 32%;
  min-width: 0.55rem;
  min-height: 0.55rem;
  border-radius: 50%;
  background: #cbd5e1;
  box-shadow: 0 0 0 2px var(--surface);
}
.chat-avatar__dot--on {
  background: #22c55e;
}
</style>
