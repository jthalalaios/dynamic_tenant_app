<template>
  <AuthCard :eyebrow="t('auth.oneMoreStep')" :title="t('auth.verifyEmailTitle')" :subtitle="t('auth.verifyEmailSubtitle')">
    <div class="stack">
      <p class="sent-to">
        <i class="pi pi-envelope" aria-hidden="true"></i>
        <i18n-t keypath="auth.verificationSentTo" tag="span" scope="global">
          <template #email><strong>{{ user.user?.email }}</strong></template>
        </i18n-t>
      </p>

      <Button :label="t('auth.iVerified')" icon="pi pi-check" :loading="checking" @click="checkAgain" />
      <Button
        :label="cooldown > 0 ? t('auth.resendIn', { seconds: cooldown }) : t('auth.resendVerification')"
        severity="secondary"
        outlined
        :loading="resending"
        :disabled="cooldown > 0"
        @click="resend"
      />

      <p class="helper-text">
        {{ t('auth.wrongAccount') }}
        <a href="#" class="link" @click.prevent="signOut">{{ t('common.logout') }}</a>
      </p>
    </div>
  </AuthCard>
</template>

<script lang="ts" setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'
import AuthCard from '../../components/shared/AuthCard.vue'
import { authApi } from '../../api'
import { useAuthStore, useUserStore } from '../../stores'
import { useNotify } from '../../composables/useNotify'
import { onAuthSignal } from '../../helpers/authSignals'

const { t } = useI18n()
const auth = useAuthStore()
const user = useUserStore()
const router = useRouter()
const notify = useNotify()

const checking = ref(false)
const resending = ref(false)
const cooldown = ref(0)
let timer: ReturnType<typeof setInterval> | undefined

function startCooldown(seconds = 30) {
  cooldown.value = seconds
  clearInterval(timer)
  timer = setInterval(() => {
    cooldown.value -= 1
    if (cooldown.value <= 0) clearInterval(timer)
  }, 1000)
}

/** The link may have been opened on another device: re-read the session */
async function checkAgain() {
  checking.value = true
  try {
    await auth.refreshSession()
    if (user.mustVerifyEmail) notify.info(t('auth.stillUnverified'))
    else await router.replace('/')
  } catch (err) {
    notify.error(err)
  } finally {
    checking.value = false
  }
}

/** Interval of the background re-check while this tab is visible */
const POLL_MS = 15_000
let poller: ReturnType<typeof setInterval> | undefined
let offSignal: () => void = () => {}
let silentCheckRunning = false

/**
 * Quiet re-check (no toasts): runs when another tab confirms the link, when this
 * tab regains focus, and every few seconds, so the page unlocks by itself.
 */
async function silentCheck() {
  if (silentCheckRunning || checking.value || document.visibilityState !== 'visible') return
  silentCheckRunning = true
  try {
    await auth.refreshSession()
    if (!user.mustVerifyEmail) await router.replace('/')
  } catch {
    // Offline or signed out: the global handlers deal with it; try again later
  } finally {
    silentCheckRunning = false
  }
}

function onVisible() {
  if (document.visibilityState === 'visible') void silentCheck()
}

onMounted(() => {
  offSignal = onAuthSignal((signal) => {
    if (signal.type === 'email-verified' && signal.userId === user.user?.id) void silentCheck()
  })
  document.addEventListener('visibilitychange', onVisible)
  window.addEventListener('focus', onVisible)
  poller = setInterval(() => void silentCheck(), POLL_MS)
})

async function resend() {
  resending.value = true
  try {
    const { message, verified } = await authApi.resendVerification()
    if (verified) {
      await auth.refreshSession()
      await router.replace('/')
      return
    }
    notify.success(message)
    startCooldown()
  } catch (err) {
    notify.error(err)
  } finally {
    resending.value = false
  }
}

async function signOut() {
  await auth.logout()
  await router.replace({ name: 'signin' })
}

onBeforeUnmount(() => {
  clearInterval(timer)
  clearInterval(poller)
  offSignal()
  document.removeEventListener('visibilitychange', onVisible)
  window.removeEventListener('focus', onVisible)
})
</script>

<style scoped>
.stack {
  display: grid;
  gap: 0.8rem;
}
.sent-to {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  margin: 0 0 0.25rem;
  color: var(--text);
  line-height: 1.5;
  overflow-wrap: anywhere;
}
.sent-to .pi {
  color: var(--primary);
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
