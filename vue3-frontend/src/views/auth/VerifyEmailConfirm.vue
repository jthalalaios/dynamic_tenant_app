<template>
  <AuthCard :eyebrow="t('auth.oneMoreStep')" :title="title" :subtitle="subtitle">
    <div class="stack">
      <ProgressSpinner v-if="state === 'pending'" class="spinner" strokeWidth="4" :aria-label="t('auth.verifyingEmailTitle')" />

      <template v-else-if="state === 'done'">
        <Button :label="auth.isAuthenticated ? t('auth.continueToApp') : t('auth.signInTitle')" icon="pi pi-arrow-right" iconPos="right" @click="proceed" />
      </template>

      <template v-else>
        <Message severity="warn" :closable="false">{{ errorMessage }}</Message>
        <Button v-if="auth.isAuthenticated" :label="t('auth.requestNewLink')" @click="router.replace({ name: 'verify-email' })" />
        <RouterLink v-else to="/signin" class="link">{{ t('auth.backToSignIn') }}</RouterLink>
      </template>
    </div>
  </AuthCard>
</template>

<script lang="ts" setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import AuthCard from '../../components/shared/AuthCard.vue'
import { ApiError, authApi } from '../../api'
import { useAuthStore } from '../../stores'
import { broadcastAuthSignal } from '../../helpers/authSignals'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const state = ref<'pending' | 'done' | 'failed'>('pending')
const errorMessage = ref('')

const title = computed(() =>
  state.value === 'done' ? t('auth.emailVerifiedTitle') : state.value === 'failed' ? t('auth.verificationFailedTitle') : t('auth.verifyingEmailTitle')
)
const subtitle = computed(() =>
  state.value === 'done' ? t('auth.emailVerifiedBody') : state.value === 'failed' ? t('auth.verificationFailedBody') : t('auth.verifyingEmailBody')
)

const param = (value: unknown) => (typeof value === 'string' ? value : '')

onMounted(async () => {
  const id = param(route.params.id)
  const hash = param(route.params.hash)
  const expires = param(route.query.expires)
  const signature = param(route.query.signature)

  if (!id || !hash || !expires || !signature) {
    errorMessage.value = t('auth.verificationLinkIncomplete')
    state.value = 'failed'
    return
  }

  try {
    await authApi.verifyEmail({ id, hash, expires, signature })
    // Unlock any other tab still showing "verify your email"
    broadcastAuthSignal({ type: 'email-verified', userId: Number(id) })
    // Unlock the app right away when the link was opened in the signed-in browser
    if (auth.isAuthenticated) await auth.refreshSession().catch(() => {})
    state.value = 'done'
  } catch (err) {
    errorMessage.value = err instanceof ApiError && err.message ? err.message : t('auth.verificationLinkIncomplete')
    state.value = 'failed'
  }
})

function proceed() {
  // The guard sends signed-in users to their landing page
  void router.replace(auth.isAuthenticated ? '/' : { name: 'signin' })
}
</script>

<style scoped>
.stack {
  display: grid;
  gap: 0.8rem;
}
.spinner {
  width: 48px;
  height: 48px;
  margin: 0.5rem auto;
}
.link {
  color: var(--primary);
  text-decoration: none;
}
.link:hover {
  text-decoration: underline;
}
</style>
