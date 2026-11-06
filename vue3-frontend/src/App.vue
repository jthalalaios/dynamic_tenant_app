<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import Navbar from './components/shared/navbar/Navbar.vue'
import Footer from './components/shared/Footer.vue'
import { splitTextAnimations } from './utils/InitAnimations'
import { useAccessStore, useAuthStore, useChatStore, usePresenceStore, useUserStore } from './stores'

const route = useRoute()
const auth = useAuthStore()
const user = useUserStore()
const access = useAccessStore()
const presence = usePresenceStore()
const chat = useChatStore()

const isHome = computed(() => route.name === 'home')

// Clinic staff who can go online get their presence restored (and its heartbeat) after sign-in or reload
const tracksPresence = computed(() => auth.isAuthenticated && user.isDoctorTenant && access.can('presence.toggle'))
watch(
  tracksPresence,
  (enabled) => {
    if (enabled) presence.refresh().catch(() => {})
    else presence.reset()
  },
  { immediate: true }
)

// Live chat runs app-wide (navbar unread badge, live events) for members who may use it,
// and is torn down on sign-out or when the permission is revoked
const usesChat = computed(() => auth.isAuthenticated && !user.mustVerifyEmail && access.can('chat.view'))
watch(
  usesChat,
  (enabled) => {
    if (enabled) chat.start().catch(() => {})
    else chat.stop()
  },
  { immediate: true }
)

onMounted(() => splitTextAnimations())
</script>

<template>
  <Navbar :forceFixed="isHome" />
  <router-view />
  <Footer />
  <Toast position="top-right" />
  <ConfirmDialog />
</template>
