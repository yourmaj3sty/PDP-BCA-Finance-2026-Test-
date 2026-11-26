<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { parseApiError } from '../services/api.js';
import { useAuth } from '../stores/auth.js';

const route = useRoute();
const router = useRouter();
const auth = useAuth();

const loginInput = ref('');
const password = ref('');
const submitting = ref(false);
const errorMessage = ref('');
/** @type {import('vue').Ref<Record<string, string>>} */
const fieldErrors = ref({});

const demoAccounts = [
    { role: 'Sales Dealer', email: 'sales@jkl.co.id', name: 'Rizal Pratama', task: 'Input pengajuan awal & dokumen SPK/KTP' },
    { role: 'Marketing', email: 'marketing@jkl.co.id', name: 'Siti Rahmawati', task: 'Lengkapi data kredit, unggah KK, kirim approval' },
    { role: 'Atasan Marketing', email: 'atasan@jkl.co.id', name: 'Hendra Wijaya', task: 'Review pengajuan, Approve / Reject' },
    { role: 'Admin Backoffice', email: 'backoffice@jkl.co.id', name: 'Dewi Kusuma', task: 'Cetak Kontrak & PO, verifikasi TTD, cairkan dana' },
];

/**
 * @param {string} email
 */
function fillDemo(email) {
    loginInput.value = email;
    password.value = 'password123';
    errorMessage.value = '';
    fieldErrors.value = {};
}

async function handleSubmit() {
    submitting.value = true;
    errorMessage.value = '';
    fieldErrors.value = {};

    try {
        await auth.login(loginInput.value, password.value);
        const redirectTarget = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/')
            ? route.query.redirect
            : '/';
        await router.push(redirectTarget);
    } catch (err) {
        const parsed = parseApiError(err, 'Gagal masuk. Periksa kembali akun dan password Anda.');
        errorMessage.value = parsed.message;
        fieldErrors.value = parsed.fieldErrors;
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen flex-col justify-center bg-[#F8FAFC] px-4 py-10">
        <div class="mx-auto w-full max-w-[440px]">
            <div class="mb-6 flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded bg-[#1E3A8A] text-sm font-bold text-white">
                    JKL
                </span>
                <div>
                    <h1 class="text-lg font-semibold text-slate-900">
                        PT. JKL Multifinance
                    </h1>
                    <p class="text-sm text-slate-600">
                        Sistem Pengajuan Kredit Kendaraan Bermotor
                    </p>
                </div>
            </div>

            <div class="rounded border border-[#E2E8F0] bg-white p-6">
                <h2 class="text-base font-semibold text-slate-900">
                    Masuk ke Portal Internal
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Gunakan akun staf sesuai peran untuk memproses pengajuan kredit.
                </p>

                <div
                    v-if="errorMessage"
                    class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700"
                    role="alert"
                >
                    {{ errorMessage }}
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="handleSubmit">
                    <div>
                        <label for="login" class="block text-sm font-medium text-slate-700">
                            Email atau Username
                        </label>
                        <input
                            id="login"
                            v-model="loginInput"
                            type="text"
                            autocomplete="username"
                            required
                            placeholder="contoh: sales@jkl.co.id"
                            class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#1E3A8A] focus:outline-none"
                        >
                        <p v-if="fieldErrors.login" class="mt-1 text-xs text-red-600">
                            {{ fieldErrors.login }}
                        </p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700">
                            Password
                        </label>
                        <input
                            id="password"
                            v-model="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            placeholder="Masukkan password"
                            class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#1E3A8A] focus:outline-none"
                        >
                        <p v-if="fieldErrors.password" class="mt-1 text-xs text-red-600">
                            {{ fieldErrors.password }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="submitting"
                        class="w-full rounded bg-[#1E3A8A] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#1E40AF] disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ submitting ? 'Memproses...' : 'Masuk' }}
                    </button>
                </form>
            </div>

            <div class="mt-5 rounded border border-[#E2E8F0] bg-white p-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-semibold text-slate-700">
                        Akun Uji Coba per Peran
                    </h3>
                    <span class="text-xs text-slate-500">
                        Password: <code class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-800">password123</code>
                    </span>
                </div>

                <div class="mt-3 divide-y divide-slate-100">
                    <div
                        v-for="account in demoAccounts"
                        :key="account.email"
                        class="flex items-center justify-between gap-2 py-2"
                    >
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-900">
                                {{ account.role }} — {{ account.email }}
                            </p>
                            <p class="truncate text-xs text-slate-500">
                                {{ account.task }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded border border-[#E2E8F0] bg-slate-50 px-2.5 py-1 text-xs font-medium text-[#1E3A8A] hover:bg-slate-100"
                            @click="fillDemo(account.email)"
                        >
                            Pilih
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

