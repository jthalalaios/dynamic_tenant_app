<template>
  <AuthCard :eyebrow="t('auth.createAccount')" :title="t('auth.signUpTitle')" :subtitle="t('auth.signUpSubtitle')">
    <form @submit.prevent="onSubmit" class="form-grid" novalidate>
      <!-- Standalone apps (and single-theme backends) only offer their own workspace -->
      <template v-if="themeStore.canSwitch">
        <span class="field-label">{{ t('auth.workspaceType') }}</span>
        <SelectButton v-model="type" :options="typeOptions" optionLabel="label" optionValue="value" :allowEmpty="false" :aria-label="t('auth.workspaceType')" />
      </template>

      <!-- Everyone joins the theme's single workspace, as a member an admin can promote -->
      <p class="join-hint" role="note">
        <i :class="type === 'doctor' ? 'pi pi-heart' : 'pi pi-shopping-bag'" aria-hidden="true"></i>
        <span>{{ t('auth.joinWorkspace', { workspace: workspaceName }) }}</span>
      </p>

      <label for="name">{{ t('auth.name') }}</label>
      <InputText id="name" v-model="name" autocomplete="name" :placeholder="t('auth.fullName')" :invalid="!!form.first('name')" fluid />
      <small v-if="form.first('name')" class="field-error">{{ form.first('name') }}</small>

      <label for="email">{{ t('auth.email') }}</label>
      <InputText id="email" v-model="email" type="email" autocomplete="email" :placeholder="t('auth.emailPlaceholder')" :invalid="!!form.first('email')" fluid />
      <small v-if="form.first('email')" class="field-error">{{ form.first('email') }}</small>

      <label for="password">{{ t('auth.password') }}</label>
      <Password inputId="password" v-model="password" toggleMask autocomplete="new-password" :placeholder="t('auth.choosePassword')" :invalid="!!form.first('password')" fluid />
      <small v-if="form.first('password')" class="field-error">{{ form.first('password') }}</small>

      <Button :label="t('auth.createAccount')" type="submit" :loading="submitting" />

      <p class="helper-text">
        {{ t('auth.alreadyHaveAccount') }}
        <RouterLink to="/signin" class="link">{{ t('auth.signInLink') }}</RouterLink>
      </p>
    </form>
  </AuthCard>
</template>

<script lang="ts" setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import SelectButton from 'primevue/selectbutton'
import AuthCard from '../../components/shared/AuthCard.vue'
import { appApi } from '../../api'
import { useAccessStore, useAuthStore, usePreferencesStore, useThemeStore } from '../../stores'
import { homePathFor } from '../../config/navigation'
import { useNotify } from '../../composables/useNotify'
import { useFormErrors } from '../../composables/useFormErrors'
import type { TenantType } from '../../types/models'

const { t } = useI18n()
const auth = useAuthStore()
const access = useAccessStore()
const prefs = usePreferencesStore()
const themeStore = useThemeStore()
const router = useRouter()
const notify = useNotify()
const form = useFormErrors()

// Default to the theme the guest is previewing (always the fixed one in a standalone app)
const type = ref<TenantType>(themeStore.currentThemeName)
/** Workspace names from the API; the theme label until they arrive */
const workspaces = ref<Partial<Record<TenantType, string>>>({})
const workspaceName = computed(() => workspaces.value[type.value] ?? t(`themes.${type.value}`))
onMounted(() => {
  appApi.config().then((config) => (workspaces.value = config.workspaces ?? {})).catch(() => {})
})
const name = ref('')
const email = ref('')
const password = ref('')
const submitting = ref(false)

const typeOptions = computed(() => themeStore.available.map((theme) => ({ value: theme.name, label: t(`themes.${theme.name}`) })))

// Preview the workspace being created; follow the theme if it changes (e.g. API narrowed the offer)
watch(type, (next) => themeStore.setTheme(next))
watch(() => themeStore.currentThemeName, (next) => (type.value = next))

async function onSubmit() {
  submitting.value = true
  form.clear()
  try {
    const session = await auth.register({
      type: type.value,
      name: name.value,
      email: email.value,
      password: password.value,
      timezone: prefs.timezone,
      locale: prefs.locale
    })
    await router.replace(homePathFor(session.tenant.type, access.can))
  } catch (err) {
    if (!form.capture(err)) notify.error(err, t('auth.registrationFailed'))
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.join-hint {
  display: flex;
  align-items: flex-start;
  gap: 0.6rem;
  margin: 0;
  padding: 0.75rem 0.9rem;
  border: 1px solid color-mix(in srgb, var(--primary) 22%, var(--border));
  border-radius: 12px;
  background: var(--primary-soft);
  color: var(--text);
  font-size: 0.9rem;
  line-height: 1.45;
}
.join-hint .pi {
  margin-top: 0.15rem;
  color: var(--primary);
}
.form-grid {
  display: grid;
  gap: 0.8rem;
}
.form-grid label,
.field-label {
  display: block;
  font-weight: 600;
  color: var(--text);
}
.helper-text {
  margin: 0.15rem 0 0;
  color: var(--muted);
  font-size: 0.95rem;
}
.link {
  color: var(--primary);
  text-decoration: none;
}
.link:hover {
  text-decoration: underline;
}
</style>
