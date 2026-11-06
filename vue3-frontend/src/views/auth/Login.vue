<template>
  <AuthCard :eyebrow="t('auth.welcomeBack')" :title="t('auth.signInTitle')" :subtitle="t('auth.signInSubtitle')">
    <form @submit.prevent="onSubmit" class="form-grid" novalidate>
      <label for="email">{{ t('auth.email') }}</label>
      <InputText id="email" v-model="email" type="email" autocomplete="email" :placeholder="t('auth.emailPlaceholder')" :invalid="!!form.first('email')" fluid />
      <small v-if="form.first('email')" class="field-error">{{ form.first('email') }}</small>

      <label for="password">{{ t('auth.password') }}</label>
      <Password inputId="password" v-model="password" toggleMask :feedback="false" autocomplete="current-password" :placeholder="t('auth.passwordPlaceholder')" :invalid="!!form.first('password')" fluid />
      <small v-if="form.first('password')" class="field-error">{{ form.first('password') }}</small>

      <div class="actions-row">
        <Button :label="t('auth.signInTitle')" type="submit" :loading="submitting" />
        <RouterLink to="/forgot" class="forgot-link">{{ t('auth.forgotPassword') }}</RouterLink>
      </div>

      <p class="helper-text">
        {{ t('auth.newHere') }}
        <RouterLink to="/register" class="link">{{ t('auth.createAccount') }}</RouterLink>
      </p>
    </form>
  </AuthCard>
</template>

<script lang="ts" setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import AuthCard from '../../components/shared/AuthCard.vue'
import { useAccessStore, useAuthStore } from '../../stores'
import { homePathFor } from '../../config/navigation'
import { useNotify } from '../../composables/useNotify'
import { useFormErrors } from '../../composables/useFormErrors'

const email = ref('')
const password = ref('')
const submitting = ref(false)
const auth = useAuthStore()
const access = useAccessStore()
const route = useRoute()
const router = useRouter()
const notify = useNotify()
const form = useFormErrors()
const { t } = useI18n()

/** Only follow same-app relative redirects (no open redirect via ?redirect=https://evil) */
function safeRedirect(value: unknown): string | null {
  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') ? value : null
}

async function onSubmit() {
  submitting.value = true
  form.clear()
  try {
    const session = await auth.login({ email: email.value, password: password.value })
    await router.replace(safeRedirect(route.query.redirect) ?? homePathFor(session.tenant.type, access.can))
  } catch (err) {
    if (!form.capture(err)) notify.error(err, t('auth.loginFailed'))
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.form-grid {
  display: grid;
  gap: 0.8rem;
}
.form-grid label {
  display: block;
  font-weight: 600;
  color: var(--text);
}
.actions-row {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-top: 0.35rem;
}
.forgot-link,
.link {
  color: var(--primary);
  text-decoration: none;
}
.forgot-link:hover,
.link:hover {
  text-decoration: underline;
}
.helper-text {
  margin: 0.25rem 0 0;
  color: var(--muted);
  font-size: 0.95rem;
}
@media (max-width: 640px) {
  .actions-row {
    flex-direction: column;
    align-items: stretch;
  }
  .forgot-link {
    margin-left: 0;
  }
}
</style>
