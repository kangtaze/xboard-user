import { createRouter, createWebHistory } from 'vue-router'
import { getToken } from '@/api/client'

const router = createRouter({
  history: createWebHistory(),
  scrollBehavior(_to, _from, savedPosition) {
    if (savedPosition) return savedPosition
    return { top: 0 }
  },
  routes: [
    // Auth (public)
    { path: '/auth/login', name: 'login', component: () => import('@/views/auth/LoginPage.vue') },
    { path: '/auth/register', name: 'register', component: () => import('@/views/auth/RegisterPage.vue') },
    { path: '/auth/forgot', name: 'forgot', component: () => import('@/views/auth/ForgotPassword.vue') },

    // Dashboard (authenticated)
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('@/components/layout/DashboardLayout.vue'),
      children: [
        { path: '', name: 'dashboard-home', component: () => import('@/views/dashboard/Dashboard.vue') },
        { path: 'nodes', name: 'nodes', component: () => import('@/views/nodes/NodeList.vue') },
        { path: 'plans', name: 'plans', component: () => import('@/views/commerce/PlanPurchase.vue') },
        { path: 'orders', name: 'orders', component: () => import('@/views/commerce/Orders.vue') },
        { path: 'tickets', name: 'tickets', component: () => import('@/views/interaction/Tickets.vue') },
        { path: 'ticket/:id', name: 'ticket-detail', component: () => import('@/views/interaction/TicketDetail.vue') },
        { path: 'invite', name: 'invite', component: () => import('@/views/interaction/Invite.vue') },
        { path: 'giftcard', name: 'giftcard', component: () => import('@/views/interaction/GiftCard.vue') },
        { path: 'settings', name: 'settings', component: () => import('@/views/account/Settings.vue') },
      ],
    },
    { path: '/', redirect: '/auth/login' },
    { path: '/:pathMatch(.*)*', redirect: '/auth/login' },
  ],
})

router.beforeEach((to) => {
  const token = getToken()
  const requiresAuth = to.path.startsWith('/dashboard')
  if (requiresAuth && !token) return { name: 'login', query: { redirect: to.fullPath } }
  if (token && ['login', 'register', 'forgot'].includes(to.name as string)) return { name: 'dashboard-home', query: { ...to.query } }
})

export default router
