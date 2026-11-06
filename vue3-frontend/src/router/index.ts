import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import Home from '../views/Home.vue'
import { installGuards } from './guards'

// Home is eager (landing page); every other route is code-split and loaded on demand
const routes: RouteRecordRaw[] = [
  { path: '/', name: 'home', component: Home },

  { path: '/signin', name: 'signin', component: () => import('../views/auth/Login.vue'), meta: { guestOnly: true, title: 'auth.signInTitle' } },
  { path: '/register', name: 'register', component: () => import('../views/auth/Register.vue'), meta: { guestOnly: true, title: 'auth.signUpTitle' } },
  { path: '/forgot', name: 'forgot', component: () => import('../views/auth/ForgotPassword.vue'), meta: { guestOnly: true, title: 'auth.forgotPasswordTitle' } },
  { path: '/reset-password', name: 'reset-password', component: () => import('../views/auth/ResetPassword.vue'), meta: { guestOnly: true, title: 'auth.resetPasswordTitle' } },
  // Notice + resend for signed-in users who still have to confirm their email
  { path: '/verify-email', name: 'verify-email', component: () => import('../views/auth/VerifyEmail.vue'), meta: { requiresAuth: true, allowUnverified: true, title: 'auth.verifyEmailTitle' } },
  // Target of the emailed link; works signed in or not (the link's signature is the proof)
  { path: '/verify-email/:id/:hash', name: 'verify-email.confirm', component: () => import('../views/auth/VerifyEmailConfirm.vue'), meta: { title: 'auth.verifyingEmailTitle' } },

  {
    path: '/clinic',
    meta: { requiresAuth: true, tenant: 'doctor' },
    children: [
      { path: '', name: 'clinic.dashboard', component: () => import('../themes/doctor/DoctorDashboard.vue'), meta: { permission: 'appointments.view', title: 'nav.dashboard' } },
      { path: 'appointments', name: 'clinic.appointments', component: () => import('../themes/doctor/AppointmentsView.vue'), meta: { permission: 'appointments.view', title: 'nav.appointments' } },
      { path: 'patients', name: 'clinic.patients', component: () => import('../themes/doctor/PatientsView.vue'), meta: { permission: 'patients.view', title: 'nav.patients' } },
      { path: 'reports', name: 'clinic.reports', component: () => import('../themes/doctor/ReportsView.vue'), meta: { permission: 'reports.view', title: 'nav.reports' } }
    ]
  },

  {
    path: '/market',
    meta: { requiresAuth: true, tenant: 'marketplace' },
    children: [
      { path: '', name: 'market.dashboard', component: () => import('../themes/marketplace/MarketplaceDashboard.vue'), meta: { permission: ['products.view', 'orders.view'], title: 'nav.dashboard' } },
      { path: 'products', name: 'market.products', component: () => import('../themes/marketplace/ProductsView.vue'), meta: { permission: 'products.view', title: 'nav.products' } },
      { path: 'orders', name: 'market.orders', component: () => import('../themes/marketplace/OrdersView.vue'), meta: { permission: 'orders.view', title: 'nav.orders' } },
      { path: 'insights', name: 'market.insights', component: () => import('../themes/marketplace/InsightsView.vue'), meta: { permission: 'marketing.view', title: 'nav.marketing' } }
    ]
  },

  {
    path: '/admin',
    meta: { requiresAuth: true },
    children: [
      { path: 'users', name: 'admin.users', component: () => import('../views/admin/UsersView.vue'), meta: { permission: 'users.view', title: 'nav.users' } },
      { path: 'roles', name: 'admin.roles', component: () => import('../views/admin/RolesView.vue'), meta: { permission: 'roles.view', title: 'nav.roles' } }
    ]
  },

  // Live chat, shared by every tenant theme; the open room lives in the query (?room=3)
  { path: '/chat', name: 'chat', component: () => import('../views/chat/ChatView.vue'), meta: { requiresAuth: true, permission: 'chat.view', title: 'nav.chat' } },

  { path: '/dashboard', name: 'dashboard', redirect: '/', meta: { requiresAuth: true } },
  { path: '/settings', name: 'settings', component: () => import('../views/SettingsView.vue'), meta: { requiresAuth: true, allowUnverified: true, title: 'common.settings' } },
  { path: '/forbidden', name: 'forbidden', component: () => import('../views/errors/ForbiddenView.vue'), meta: { title: 'errors.forbiddenTitle' } },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('../views/errors/NotFoundView.vue'), meta: { title: 'errors.notFoundTitle' } }
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: (_to, _from, saved) => saved ?? { top: 0 }
})

installGuards(router)

export default router
