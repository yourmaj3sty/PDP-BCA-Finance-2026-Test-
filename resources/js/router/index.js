import { createRouter, createWebHistory } from 'vue-router';
import { useAuth } from '../stores/auth.js';

import LoginView from '../views/LoginView.vue';
import DashboardView from '../views/DashboardView.vue';
import ApplicationFormView from '../views/ApplicationFormView.vue';
import ApplicationDetailView from '../views/ApplicationDetailView.vue';
import DocumentPrintView from '../views/DocumentPrintView.vue';
import PublicSignView from '../views/PublicSignView.vue';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: LoginView,
        meta: { guestOnly: true },
    },
    {
        path: '/sign/:token',
        name: 'public-sign',
        component: PublicSignView,
        meta: { public: true },
    },
    {
        path: '/',
        name: 'dashboard',
        component: DashboardView,
        meta: { requiresAuth: true },
    },
    {
        path: '/applications/new',
        name: 'application-create',
        component: ApplicationFormView,
        meta: { requiresAuth: true },
    },
    {
        path: '/applications/:id/edit',
        name: 'application-edit',
        component: ApplicationFormView,
        meta: { requiresAuth: true },
    },
    {
        path: '/applications/:id',
        name: 'application-detail',
        component: ApplicationDetailView,
        meta: { requiresAuth: true },
    },
    {
        path: '/applications/:id/print/:kind(contract|po)',
        name: 'document-print',
        component: DocumentPrintView,
        meta: { requiresAuth: true },
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuth();

    if (!auth.hydrated.value && auth.token.value) {
        await auth.fetchMe();
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated.value) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guestOnly && auth.isAuthenticated.value) {
        return { name: 'dashboard' };
    }

    return true;
});

export default router;

