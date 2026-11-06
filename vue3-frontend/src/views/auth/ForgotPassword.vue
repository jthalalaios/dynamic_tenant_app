<template>
  <AuthCard :eyebrow="t('auth.resetAccess')" :title="t('auth.forgotPasswordTitle')" :subtitle="t('auth.forgotPasswordSubtitle')">
    <form @submit.prevent="onSubmit" class="form-grid" novalidate>
      <label for="email">{{ t('auth.email') }}</label>
      <InputText id="email" v-model="email" type="email" autocomplete="email" :placeholder="t('auth.emailPlaceholder')" :invalid="!!form.first('email')" fluid />
      <small v-if="form.first('email')" class="field-error">{{ form.first('email') }}</small>
      <Button :label="t('auth.sendResetLink')" type="submit" :loading="submitting" />
      <p class="helper-text">
        <RouterLink to="/signin" class="link">{{ t('auth.backToSignIn') }}</RouterLink>
      </p>
    </form>
  </AuthCard>
</template>

<script lang="ts" setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import AuthCard from '../../components/shared/AuthCard.vue'
import { authApi } from '../../api'
import { useNotify } from '../../composables/useNotify'
import { useFormErrors } from '../../composables/useFormErrors'

const email = ref('')
const submitting = ref(false)
const notify = useNotify()
const form = useFormErrors()
const { t } = useI18n()

async function onSubmit() {
  submitting.value = true
  form.clear()
  try {
    await authApi.forgotPassword(email.value)
    notify.success(t('auth.genericResetSuccess'))
  } catch (err) {
    if (!form.capture(err)) notify.error(err, t('auth.requestFailed'))
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
