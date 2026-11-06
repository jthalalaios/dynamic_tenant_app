<template>
  <main class="page">
    <PageHeader :eyebrow="t('common.settings')" :title="t('settings.title')" :subtitle="t('settings.subtitle')" />

    <section class="card-grid settings-grid">
      <BaseCard padding="lg">
        <template #header><strong>{{ t('settings.profile') }}</strong></template>
        <form class="dialog-form" @submit.prevent="saveProfile" novalidate>
          <div class="field field--full">
            <label for="s_name">{{ t('auth.name') }}</label>
            <InputText id="s_name" v-model="name" :invalid="!!profileErrors.first('name')" fluid />
            <small v-if="profileErrors.first('name')" class="field-error">{{ profileErrors.first('name') }}</small>
          </div>
          <div class="field field--full">
            <label>{{ t('auth.email') }}</label>
            <InputText :modelValue="user.user?.email" disabled fluid />
          </div>
          <div class="field field--full">
            <span class="muted">{{ t('settings.organization') }}: <strong>{{ user.tenant?.name }}</strong> · {{ user.tenant ? t(`themes.${user.tenant.type}`) : '' }}</span>
          </div>
          <div class="field field--full">
            <Button :label="t('common.save')" type="submit" :loading="savingProfile" />
          </div>
        </form>
      </BaseCard>

      <BaseCard padding="lg">
        <template #header><strong>{{ t('settings.preferences') }}</strong></template>
        <div class="dialog-form">
          <div class="field field--full">
            <label>{{ t('common.language') }}</label>
            <SelectButton :modelValue="prefs.locale" :options="localeOptions" optionLabel="label" optionValue="value" :allowEmpty="false" @update:modelValue="changeLocale" />
          </div>
          <div class="field field--full">
            <label>{{ t('common.timezone') }}</label>
            <TimezoneSelector />
            <small class="muted">{{ t('settings.timezoneHint', { now: fmt.dateTime(new Date().toISOString()) }) }}</small>
          </div>
        </div>
      </BaseCard>

      <BaseCard padding="lg">
        <template #header><strong>{{ t('settings.security') }}</strong></template>
        <form class="dialog-form" @submit.prevent="changePassword" novalidate>
          <div class="field field--full">
            <label for="s_current">{{ t('settings.currentPassword') }}</label>
            <Password inputId="s_current" v-model="currentPassword" :feedback="false" toggleMask autocomplete="current-password" :invalid="!!passwordErrors.first('current_password')" fluid />
            <small v-if="passwordErrors.first('current_password')" class="field-error">{{ passwordErrors.first('current_password') }}</small>
          </div>
          <div class="field field--full">
            <label for="s_new">{{ t('auth.newPassword') }}</label>
            <Password inputId="s_new" v-model="newPassword" toggleMask autocomplete="new-password" :invalid="!!passwordErrors.first('password')" fluid />
            <small v-if="passwordErrors.first('password')" class="field-error">{{ passwordErrors.first('password') }}</small>
          </div>
          <div class="field field--full">
            <label for="s_confirm">{{ t('auth.confirmPassword') }}</label>
            <Password inputId="s_confirm" v-model="confirmPassword" :feedback="false" toggleMask autocomplete="new-password" fluid />
          </div>
          <div class="field field--full">
            <Button :label="t('settings.changePassword')" type="submit" severity="secondary" :loading="savingPassword" />
          </div>
        </form>
      </BaseCard>
    </section>
  </main>
</template>

<script lang="ts" setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import SelectButton from 'primevue/selectbutton'
import BaseCard from '../components/shared/cards/BaseCard.vue'
import PageHeader from '../components/shared/PageHeader.vue'
import TimezoneSelector from '../components/shared/TimezoneSelector.vue'
import { authApi } from '../api'
import { useAuthStore, usePreferencesStore, useUserStore } from '../stores'
import { SUPPORTED_LOCALES } from '../helpers/language'
import { useNotify } from '../composables/useNotify'
import { useFormErrors } from '../composables/useFormErrors'
import { useFormatters } from '../composables/useFormatters'
import { usePreferenceActions } from '../composables/usePreferenceActions'

const { t } = useI18n()
const auth = useAuthStore()
const user = useUserStore()
const prefs = usePreferencesStore()
const notify = useNotify()
const fmt = useFormatters()
const { changeLocale } = usePreferenceActions()
const profileErrors = useFormErrors()
const passwordErrors = useFormErrors()

const localeOptions = computed(() => SUPPORTED_LOCALES.map((l) => ({ value: l.code, label: `${l.flag} ${l.label}` })))

const name = ref(user.user?.name ?? '')
const savingProfile = ref(false)

async function saveProfile() {
  savingProfile.value = true
  profileErrors.clear()
  try {
    await auth.applySession(await authApi.updateProfile({ name: name.value }))
    notify.success(t('common.saved'))
  } catch (err) {
    if (!profileErrors.capture(err)) notify.error(err)
  } finally {
    savingProfile.value = false
  }
}

const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const savingPassword = ref(false)

async function changePassword() {
  savingPassword.value = true
  passwordErrors.clear()
  try {
    await authApi.updateProfile({
      current_password: currentPassword.value,
      password: newPassword.value,
      password_confirmation: confirmPassword.value
    })
    currentPassword.value = newPassword.value = confirmPassword.value = ''
    notify.success(t('settings.passwordChanged'))
  } catch (err) {
    if (!passwordErrors.capture(err)) notify.error(err)
  } finally {
    savingPassword.value = false
  }
}
</script>

<style scoped>
.settings-grid {
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr));
  align-items: start;
}
</style>
