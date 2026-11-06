<template>
  <AuthCard :eyebrow="t('auth.resetAccess')" :title="t('auth.resetPasswordTitle')" :subtitle="t('auth.resetPasswordSubtitle')">
    <Message v-if="!token" severity="warn" :closable="false">{{ t('auth.resetLinkInvalid') }}</Message>
    <form v-else @submit.prevent="onSubmit" class="form-grid" novalidate>
      <label for="email">{{ t('auth.email') }}</label>
      <InputText id="email" v-model="email" type="email" autocomplete="email" :invalid="!!form.first('email')" fluid />
      <small v-if="form.first('email')" class="field-error">{{ form.first('email') }}</small>

      <label for="password">{{ t('auth.newPassword') }}</label>
      <Password inputId="password" v-model="password" toggleMask autocomplete="new-password" :invalid="!!form.first('password')" fluid />
      <small v-if="form.first('password')" class="field-error">{{ form.first('password') }}</small>

      <label for="password_confirmation">{{ t('auth.confirmPassword') }}</label>
      <Password inputId="password_confirmation" v-model="passwordConfirmation" toggleMask :feedback="false" autocomplete="new-password" fluid />

      <Button :label="t('auth.resetPasswordAction')" type="submit" :loading="submitting" />
      <p class="helper-text">
        <RouterLink to="/signin" class="link">{{ t('auth.backToSignIn') }}</RouterLink>
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
import Message from 'primevue/message'
import AuthCard from '../../components/shared/AuthCard.vue'
import { authApi } from '../../api'
import { useNotify } from '../../composables/useNotify'
import { useFormErrors } from '../../composables/useFormErrors'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const notify = useNotify()
const form = useFormErrors()

// The emailed link carries ?token=…&email=…
const token = typeof route.query.token === 'string' ? route.query.token : ''
const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const password = ref('')
const passwordConfirmation = ref('')
const submitting = ref(false)

async function onSubmit() {
  submitting.value = true
  form.clear()
  try {
    const { message } = await authApi.resetPassword({
      token,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value
    })
    notify.success(message)
    await router.replace({ name: 'signin' })
  } catch (err) {
    if (!form.capture(err)) notify.error(err)
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
