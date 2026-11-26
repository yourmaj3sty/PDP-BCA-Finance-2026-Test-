<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuth } from '../stores/auth.js';

const route = useRoute();
const router = useRouter();
const auth = useAuth();

const canCreate = computed(() => auth.hasRole('sales_dealer'));

async function handleLogout() {
    await auth.logout();
    await router.push({ name: 'login' });
}
</script>

<template>
    <header class="no-print border-b border-[#E2E8F0] bg-white">
        <div class="mx-auto flex max-w-[1100px] flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
            <div class="flex flex-wrap items-center gap-6">
                <RouterLink
                    to="/"
                    class="flex items-center gap-2.5 text-slate-900 no-underline"
                >
                    <span class="flex h-8 w-8 items-center justify-center rounded bg-[#1E3A8A] text-xs font-bold tracking-tight text-white">
                        JKL
                    </span>
                    <span class="text-sm font-semibold tracking-tight text-slate-900 sm:text-base">
                        Sistem Kredit Kendaraan
                    </span>
                </RouterLink>

                <nav class="flex items-center gap-1" aria-label="Navigasi utama">
                    <RouterLink
                        to="/"
                        class="rounded px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="route.name === 'dashboard'
                            ? 'bg-slate-100 text-[#1E3A8A]'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                    >
                        Dashboard
                    </RouterLink>

                    <RouterLink
                        v-if="canCreate"
                        to="/applications/new"
                        class="rounded px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="route.name === 'application-create'
                            ? 'bg-slate-100 text-[#1E3A8A]'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                    >
                        Buat Pengajuan
                    </RouterLink>
                </nav>
            </div>

            <div class="flex items-center gap-3">
                <div v-if="auth.user.value" class="text-right">
                    <p class="text-sm font-medium leading-tight text-slate-900">
                        {{ auth.user.value.name }}
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ auth.user.value.role_label }}
                    </p>
                </div>

                <button
                    type="button"
                    class="rounded border border-[#E2E8F0] bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    @click="handleLogout"
                >
                    Keluar
                </button>
            </div>
        </div>
    </header>
</template>

