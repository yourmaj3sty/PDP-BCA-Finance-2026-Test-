<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppNavbar from './components/AppNavbar.vue';
import { useAuth } from './stores/auth.js';

const route = useRoute();
const auth = useAuth();

const showNavbar = computed(() => (
    auth.isAuthenticated.value
    && route.name !== 'login'
    && route.name !== 'public-sign'
    && route.name !== 'document-print'
));
</script>

<template>
    <div class="min-h-screen bg-[#F8FAFC] text-slate-900">
        <AppNavbar v-if="showNavbar" />
        <main :class="showNavbar ? 'mx-auto max-w-[1100px] px-4 py-6 sm:px-6' : ''">
            <RouterView />
        </main>
    </div>
</template>

