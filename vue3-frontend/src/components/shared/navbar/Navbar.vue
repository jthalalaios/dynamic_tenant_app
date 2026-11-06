<script lang="ts" setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import Tag from 'primevue/tag'
import LanguageSelect from '../LanguageSelect.vue'
import ThemeSwitcher from '../ThemeSwitcher.vue'
import PresenceToggle from '../../../themes/doctor/PresenceToggle.vue'
import { buildNavigation } from '../../../config/navigation'
import { useAccessStore, useAuthStore, useChatStore, usePreferencesStore, useThemeStore, useUserStore } from '../../../stores'
import { useClickOutside } from '../../../composables/useClickOutside'

defineProps({ forceFixed: { type: Boolean, default: false } })

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const auth = useAuthStore()
const user = useUserStore()
const access = useAccessStore()
const prefs = usePreferencesStore()
const themeStore = useThemeStore()
const chat = useChatStore()

const navOpen = ref(false)
const scrolled = ref(false)
const adminOpen = ref(false)
const accountOpen = ref(false)
const authOpen = ref(false)
const adminRef = ref<HTMLElement | null>(null)
const accountRef = ref<HTMLElement | null>(null)
const authRef = ref<HTMLElement | null>(null)

// Different menus per tenant type, and per role: entries are filtered by permission
const navigation = computed(() =>
  auth.isAuthenticated && user.tenantType && !user.mustVerifyEmail
    ? buildNavigation(user.tenantType, access.can)
    : { main: [], admin: null }
)
const brandLabel = computed(() =>
  user.tenant?.name ?? (themeStore.currentThemeName === 'marketplace' ? t('common.market') : t('common.clinic'))
)
const showPresence = computed(() => auth.isAuthenticated && user.isDoctorTenant && access.can('presence.toggle'))
const isAdminRouteActive = computed(() => route.path.startsWith('/admin'))

useClickOutside(adminRef, () => (adminOpen.value = false))
useClickOutside(accountRef, () => (accountOpen.value = false))
useClickOutside(authRef, () => (authOpen.value = false))

// Close every menu after navigating
watch(() => route.fullPath, () => {
  navOpen.value = false
  adminOpen.value = false
  accountOpen.value = false
  authOpen.value = false
})

async function handleLogout() {
  accountOpen.value = false
  await auth.logout()
  await router.push({ name: 'signin' })
}

function onScroll() { scrolled.value = window.scrollY > 60 }
onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }))
onUnmounted(() => window.removeEventListener('scroll', onScroll))
</script>

<template>
  <header :class="['header-section', { 'fixed-header': scrolled || forceFixed }]">
    <div class="container-inner">
      <div class="brand-area">
        <RouterLink to="/" class="brand">{{ brandLabel }}</RouterLink>
      </div>

      <button class="burger" @click="navOpen = !navOpen" :aria-label="t('common.menu')" :aria-expanded="navOpen">
        <span></span><span></span><span></span>
      </button>

      <nav class="nav" :class="{ open: navOpen }" :aria-label="t('common.menu')">
        <ul class="nav-list">
          <li v-if="!auth.isAuthenticated" class="nav-item">
            <RouterLink to="/">{{ t('common.home') }}</RouterLink>
          </li>
          <li v-for="item in navigation.main" :key="item.key" class="nav-item">
            <RouterLink :to="item.to" class="nav-link" exact-active-class="is-active">
              <i :class="item.icon" aria-hidden="true"></i>{{ t(item.label) }}
              <span v-if="item.key === 'chat' && chat.totalUnread" class="nav-badge" :aria-label="t('chat.unreadCount', { count: chat.totalUnread })">
                {{ chat.totalUnread > 99 ? '99+' : chat.totalUnread }}
              </span>
            </RouterLink>
          </li>
          <li v-if="navigation.admin" class="nav-item has-sub" ref="adminRef">
            <button
              :class="['nav-link', { 'is-active': isAdminRouteActive }]"
              aria-haspopup="true"
              :aria-expanded="adminOpen"
              @click="adminOpen = !adminOpen"
            >
              <i :class="navigation.admin.icon" aria-hidden="true"></i>{{ t(navigation.admin.label) }}
              <i class="pi pi-angle-down nav-caret" aria-hidden="true"></i>
            </button>
            <transition name="fade">
              <ul v-if="adminOpen" class="sub">
                <li v-for="s in navigation.admin.items" :key="s.key">
                  <RouterLink :to="s.to" active-class="is-active"><i :class="s.icon" aria-hidden="true"></i>{{ t(s.label) }}</RouterLink>
                </li>
              </ul>
            </transition>
          </li>
        </ul>
      </nav>

      <div class="actions">
        <PresenceToggle v-if="showPresence" compact />
        <ThemeSwitcher />
        <LanguageSelect />

        <div v-if="auth.isAuthenticated" class="account-menu" ref="accountRef">
          <button class="icon-btn account-btn" @click="accountOpen = !accountOpen" :aria-label="t('common.accountMenu')" :aria-expanded="accountOpen">
            <span class="avatar" aria-hidden="true">{{ user.userInitials }}</span>
          </button>

          <transition name="fade">
            <div v-if="accountOpen" class="account-dropdown" @click.stop>
              <div class="account-summary">
                <strong>{{ user.displayName }}</strong>
                <span class="muted">{{ user.user?.email }}</span>
                <div class="role-tags">
                  <Tag v-if="access.isAdmin" :value="t('roles.admin')" severity="contrast" rounded />
                  <Tag v-for="role in access.roles.filter((r) => r !== 'admin')" :key="role" :value="role" severity="secondary" rounded />
                </div>
                <span class="muted small"><i class="pi pi-clock" aria-hidden="true"></i> {{ prefs.timezone }}</span>
              </div>
              <ul>
                <li><RouterLink to="/settings">{{ t('common.settings') }}</RouterLink></li>
                <li><button class="dropdown-action" @click="handleLogout">{{ t('common.logout') }}</button></li>
              </ul>
            </div>
          </transition>
        </div>

        <div v-else class="auth-person" ref="authRef">
          <button class="person-btn" @click="authOpen = !authOpen" aria-haspopup="true" :aria-expanded="authOpen" :aria-label="t('common.accountMenu')">
            <i class="pi pi-user" style="font-size:1.15rem"></i>
          </button>

          <transition name="fade">
            <div v-if="authOpen" class="auth-popover" @click.stop>
              <ul>
                <li><RouterLink to="/register">{{ t('common.signUp') }}</RouterLink></li>
                <li><RouterLink to="/signin">{{ t('common.signIn') }}</RouterLink></li>
              </ul>
            </div>
          </transition>
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
.header-section {
  position: sticky;
  top: 0;
  z-index: 60;
  background: linear-gradient(90deg, var(--surface) 0%, var(--surface-elevated) 100%);
  border-bottom: 1px solid var(--border);
  box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04);
  transition: background 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}
.container-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  gap: 1rem;
}
.brand-area {
  min-width: 120px;
}
.brand {
  font-weight: 700;
  color: var(--primary);
  letter-spacing: 0.02em;
  text-decoration: none;
}
.burger {
  display: none;
  background: none;
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 0.4rem;
  color: var(--text);
}
.burger span {
  display: block;
  width: 18px;
  height: 2px;
  background: currentColor;
  margin: 3px 0;
}
.nav {
  display: flex;
}
.nav-list {
  display: flex;
  align-items: center;
  gap: 1rem;
  list-style: none;
  margin: 0;
  padding: 0;
}
.nav-list a,
.nav-list button {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  color: var(--text);
  text-decoration: none;
  background: none;
  border: none;
  cursor: pointer;
  font: inherit;
  transition: color 180ms ease;
}
.nav-list .pi {
  font-size: 0.9rem;
}
.nav-list a:hover,
.nav-list button:hover,
.nav-list .is-active {
  color: var(--primary);
}
.nav-list .is-active {
  font-weight: 600;
}
.nav-caret {
  font-size: 0.75rem !important;
}
/* Unread live chat messages */
.nav-badge {
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.35rem;
  border-radius: 999px;
  background: var(--primary);
  color: var(--primary-contrast);
  font-size: 0.7rem;
  font-weight: 700;
  line-height: 1.25rem;
  text-align: center;
  box-shadow: 0 0 0 2px var(--surface);
}
.has-sub {
  position: relative;
}
.sub {
  position: absolute;
  left: 0;
  top: calc(100% + 10px);
  list-style: none;
  margin: 0;
  padding: 0.4rem;
  min-width: 180px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
  z-index: 20;
}
.sub a {
  display: flex;
  width: 100%;
  padding: 0.45rem 0.6rem;
  border-radius: 8px;
}
.sub a:hover {
  background: var(--surface-elevated);
}
.actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  flex-wrap: wrap;
  justify-content: flex-end;
}
.account-menu {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}
.icon-btn,
.person-btn {
  color: var(--text);
  background: var(--surface-elevated);
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 0.45rem 0.6rem;
  cursor: pointer;
}
.account-btn {
  padding: 0.2rem;
}
.avatar {
  display: inline-grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--primary-contrast);
  background: linear-gradient(135deg, var(--primary), var(--accent));
}
@media (max-width: 768px) {
  .container-inner {
    align-items: flex-start;
    flex-wrap: wrap;
  }
  .burger { display: block; margin-left: auto; }
  .nav {
    display: none;
    width: 100%;
    order: 3;
  }
  .nav.open {
    display: block;
    background: var(--surface);
    padding: 0.85rem 0 0.25rem;
    border-top: 1px solid var(--border);
    margin-top: 0.25rem;
  }
  .nav-list {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.7rem;
  }
  .sub {
    position: static;
    box-shadow: none;
    margin-top: 0.4rem;
  }
  .actions {
    width: 100%;
    justify-content: flex-start;
    margin-top: 0.25rem;
  }
}

.auth-person,
.account-menu {
  position: relative;
}
.auth-popover,
.account-dropdown {
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  background: var(--surface);
  color: var(--text);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 0.5rem;
  min-width: 160px;
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
  z-index: 20;
}
.account-dropdown {
  min-width: 240px;
}
.account-summary {
  display: grid;
  gap: 0.3rem;
  padding: 0.4rem 0.5rem 0.7rem;
  border-bottom: 1px solid var(--border);
  margin-bottom: 0.35rem;
}
.role-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
}
.muted {
  color: var(--muted);
  font-size: 0.88rem;
}
.small {
  font-size: 0.8rem;
}
.auth-popover ul,
.account-dropdown ul { list-style: none; margin: 0; padding: 0; }
.auth-popover li,
.account-dropdown li { padding: 0.25rem 0; }
.auth-popover li a,
.account-dropdown li a,
.dropdown-action {
  color: var(--text);
  text-decoration: none;
  display: block;
  padding: 0.4rem 0.5rem;
  border-radius: 8px;
  width: 100%;
  text-align: left;
  background: transparent;
  border: none;
  cursor: pointer;
  font: inherit;
}
.auth-popover li a:hover,
.account-dropdown li a:hover,
.dropdown-action:hover {
  background: var(--surface-elevated);
  color: var(--primary);
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 180ms ease, transform 180ms ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
