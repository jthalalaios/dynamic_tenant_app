<template>
  <Dialog
    :visible="visible"
    modal
    :header="room ? t('chat.editRoom') : t('chat.newRoom')"
    :style="{ width: 'min(32rem, 94vw)' }"
    @update:visible="emit('update:visible', $event)"
  >
    <form class="room-form" novalidate @submit.prevent="submit">
      <div class="field">
        <label for="room_name">{{ t('chat.roomName') }}</label>
        <InputText id="room_name" v-model="form.name" maxlength="60" :invalid="!!errors.first('name')" autofocus fluid />
        <small v-if="errors.first('name')" class="field-error">{{ errors.first('name') }}</small>
      </div>

      <div class="field">
        <label for="room_description">{{ t('chat.roomDescription') }}</label>
        <InputText id="room_description" v-model="form.description" maxlength="240" :invalid="!!errors.first('description')" fluid />
        <small v-if="errors.first('description')" class="field-error">{{ errors.first('description') }}</small>
      </div>

      <fieldset class="field">
        <legend>{{ t('chat.roomIcon') }}</legend>
        <div class="icons" role="radiogroup" :aria-label="t('chat.roomIcon')">
          <button
            v-for="icon in CHAT_ROOM_ICONS"
            :key="icon"
            type="button"
            role="radio"
            :aria-checked="form.icon === icon"
            :aria-label="icon"
            :class="['icon', { 'icon--active': form.icon === icon }]"
            @click="form.icon = icon"
          >
            <i :class="`pi pi-${icon}`" aria-hidden="true"></i>
          </button>
        </div>
      </fieldset>

      <fieldset class="field">
        <legend>{{ t('chat.audience.title') }}</legend>
        <p v-if="room?.is_default" class="muted audience-note"><i class="pi pi-globe" aria-hidden="true"></i> {{ t('chat.audience.defaultFixed') }}</p>
        <template v-else>
          <SelectButton v-model="form.audience" :options="audienceOptions" optionLabel="label" optionValue="value" optionDisabled="disabled" :allowEmpty="false" />
          <small class="muted">{{ audienceHint }}</small>
          <MultiSelect
            v-if="form.audience === 'roles'"
            v-model="form.role_ids"
            :options="roleOptions"
            optionLabel="name"
            optionValue="id"
            optionDisabled="disabled"
            display="chip"
            :placeholder="t('chat.audience.pickRoles')"
            :invalid="!!errors.first('role_ids')"
            fluid
          />
          <small v-if="errors.first('role_ids')" class="field-error">{{ errors.first('role_ids') }}</small>
        </template>
      </fieldset>

      <div v-if="access.isAdmin" class="lock-field">
        <ToggleSwitch v-model="form.is_locked" inputId="room_locked" />
        <label for="room_locked">
          <strong>{{ t('chat.roomLockedField') }}</strong>
          <small class="muted">{{ t('chat.roomLockedFieldHint') }}</small>
        </label>
      </div>

      <footer class="room-form__actions">
        <Button
          v-if="room && !room.is_default"
          :label="t('chat.deleteRoom')"
          icon="pi pi-trash"
          severity="danger"
          text
          type="button"
          @click="emit('delete', room)"
        />
        <span class="spacer"></span>
        <Button :label="t('common.cancel')" text type="button" @click="emit('update:visible', false)" />
        <Button :label="t('common.save')" icon="pi pi-check" type="submit" :loading="saving" />
      </footer>
    </form>
  </Dialog>
</template>

<script lang="ts" setup>
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import SelectButton from 'primevue/selectbutton'
import ToggleSwitch from 'primevue/toggleswitch'
import { CHAT_ROOM_ICONS, type ChatRoomPayload } from '../../api'
import { useFormErrors } from '../../composables/useFormErrors'
import { useNotify } from '../../composables/useNotify'
import { useAccessStore, useChatStore } from '../../stores'
import type { ChatAudience, ChatRoom } from '../../types/models'

const props = defineProps<{ visible: boolean; room: ChatRoom | null }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: [room: ChatRoom]
  delete: [room: ChatRoom]
}>()

const { t } = useI18n()
const chat = useChatStore()
const access = useAccessStore()
const notify = useNotify()
const errors = useFormErrors()
const saving = ref(false)
const form = reactive({ name: '', description: '', icon: 'comments', is_locked: false, audience: 'everyone' as ChatAudience, role_ids: [] as number[] })

// Administrators can pick any audience; other room managers only everyone or their own roles
const audienceOptions = computed(() => [
  { value: 'everyone', label: t('chat.audience.everyone'), disabled: false },
  { value: 'admins', label: t('chat.audience.admins'), disabled: !access.isAdmin },
  { value: 'roles', label: t('chat.audience.roles'), disabled: false }
])
// Administrators enter every room anyway, so admin roles are not offered
const roleOptions = computed(() => chat.roles.filter((r) => !r.is_admin).map((r) => ({ ...r, disabled: !r.assignable })))
const audienceHint = computed(() => t(`chat.audience.${form.audience}Hint`, { roles: roleOptions.value.filter((r) => form.role_ids.includes(r.id)).map((r) => r.name).join(', ') || '…' }))

watch(
  () => props.visible,
  (open) => {
    if (!open) return
    errors.clear()
    Object.assign(form, {
      name: props.room?.name ?? '',
      description: props.room?.description ?? '',
      icon: props.room?.icon ?? 'comments',
      is_locked: props.room?.is_locked ?? false,
      audience: props.room?.audience ?? 'everyone',
      role_ids: (props.room?.roles ?? []).map((r) => r.id)
    })
  }
)

async function submit(): Promise<void> {
  saving.value = true
  errors.clear()
  const payload: ChatRoomPayload = { name: form.name.trim(), description: form.description.trim() || null, icon: form.icon }
  // Only administrators may (un)lock; others never send the flag
  if (access.isAdmin) payload.is_locked = form.is_locked
  // The Global room is always open to everyone: its audience is never sent
  if (!props.room?.is_default) {
    payload.audience = form.audience
    payload.role_ids = form.audience === 'roles' ? form.role_ids : []
  }

  try {
    const saved = props.room ? await chat.updateRoom(props.room.id, payload) : await chat.createRoom(payload)
    notify.success(t('chat.roomSaved'))
    emit('saved', saved)
    emit('update:visible', false)
  } catch (err) {
    if (!errors.capture(err)) notify.error(err)
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.room-form {
  display: grid;
  gap: 1rem;
}
.field {
  display: grid;
  gap: 0.4rem;
  margin: 0;
  padding: 0;
  border: none;
}
.field label,
.field legend {
  font-size: 0.9rem;
  font-weight: 600;
}
.field legend {
  margin-bottom: 0.4rem;
}
.field .field-error {
  margin-top: 0;
}
.icons {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(2.8rem, 1fr));
  gap: 0.45rem;
}
.icon {
  display: grid;
  place-items: center;
  aspect-ratio: 1;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--surface);
  color: var(--muted);
  cursor: pointer;
  transition: all 140ms ease;
}
.icon:hover {
  color: var(--primary);
  border-color: color-mix(in srgb, var(--primary) 40%, var(--border));
}
.icon--active {
  background: linear-gradient(135deg, var(--primary), var(--accent));
  border-color: transparent;
  color: var(--primary-contrast);
  box-shadow: 0 6px 16px color-mix(in srgb, var(--primary) 28%, transparent);
}
.audience-note {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.88rem;
}
.lock-field {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.8rem 0.9rem;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: var(--surface-elevated);
}
.lock-field label {
  display: grid;
  gap: 0.15rem;
  font-size: 0.9rem;
  cursor: pointer;
}
.room-form__actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.spacer {
  flex: 1;
}
</style>
