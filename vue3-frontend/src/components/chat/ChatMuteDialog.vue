<template>
  <Dialog
    :visible="visible"
    modal
    :header="member ? t('chat.muteTitle', { name: member.name }) : ''"
    :style="{ width: 'min(30rem, 94vw)' }"
    @update:visible="emit('update:visible', $event)"
  >
    <form v-if="member" class="mute-form" @submit.prevent="submit">
      <div class="mute-form__who">
        <ChatAvatar :member="member" size="lg" />
        <p class="muted">{{ t('chat.muteHint') }}</p>
      </div>

      <fieldset>
        <legend>{{ t('chat.muteScope') }}</legend>
        <SelectButton v-model="scope" :options="scopeOptions" optionLabel="label" optionValue="value" :allowEmpty="false" />
      </fieldset>

      <fieldset>
        <legend>{{ t('chat.muteDuration') }}</legend>
        <SelectButton v-model="duration" :options="durationOptions" optionLabel="label" optionValue="value" :allowEmpty="false" class="durations" />
      </fieldset>

      <div class="field">
        <label for="mute_reason">{{ t('chat.muteReason') }}</label>
        <InputText id="mute_reason" v-model="reason" :placeholder="t('chat.muteReasonPlaceholder')" maxlength="240" fluid />
      </div>

      <footer class="mute-form__actions">
        <Button :label="t('common.cancel')" text type="button" @click="emit('update:visible', false)" />
        <Button :label="t('chat.mute')" icon="pi pi-volume-off" severity="warn" type="submit" :loading="busy" />
      </footer>
    </form>
  </Dialog>
</template>

<script lang="ts" setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import SelectButton from 'primevue/selectbutton'
import ChatAvatar from './ChatAvatar.vue'
import { CHAT_MUTE_DURATIONS, type ChatMutePayload } from '../../api'
import type { ChatMember, ChatRoom } from '../../types/models'

/** SelectButton needs a non-null value; 0 stands for "until lifted" */
const FOREVER = 0

const props = defineProps<{
  visible: boolean
  member: ChatMember | null
  room: ChatRoom | null
  initialScope: 'room' | 'everywhere'
  busy: boolean
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  confirm: [payload: ChatMutePayload]
}>()

const { t } = useI18n()

const scope = ref<'room' | 'everywhere'>('room')
const duration = ref<number>(60)
const reason = ref('')

// Fresh form every time the dialog opens
watch(
  () => props.visible,
  (open) => {
    if (!open) return
    scope.value = props.room ? props.initialScope : 'everywhere'
    duration.value = 60
    reason.value = ''
  }
)

const scopeOptions = computed(() => [
  ...(props.room ? [{ label: t('chat.scopeRoom', { room: props.room.name }), value: 'room' }] : []),
  { label: t('chat.scopeEverywhere'), value: 'everywhere' }
])

const durationOptions = computed(() => [
  ...CHAT_MUTE_DURATIONS.map((minutes) => ({ label: t(`chat.durations.${minutes}`), value: minutes })),
  { label: t('chat.durations.forever'), value: FOREVER }
])

function submit(): void {
  if (!props.member) return
  emit('confirm', {
    user_id: props.member.id,
    room_id: scope.value === 'room' && props.room ? props.room.id : null,
    duration_minutes: duration.value === FOREVER ? null : duration.value,
    reason: reason.value.trim() || null
  })
}
</script>

<style scoped>
.mute-form {
  display: grid;
  gap: 1.1rem;
}
.mute-form__who {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  font-size: 0.9rem;
  line-height: 1.45;
}
fieldset {
  display: grid;
  gap: 0.45rem;
  margin: 0;
  padding: 0;
  border: none;
}
legend,
.field label {
  margin-bottom: 0.45rem;
  font-size: 0.9rem;
  font-weight: 600;
}
.field {
  display: grid;
}
.durations {
  flex-wrap: wrap;
}
.mute-form__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>
