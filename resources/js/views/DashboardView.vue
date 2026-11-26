<script setup>
import { computed, onMounted, ref } from 'vue';
import StatusBadge from '../components/StatusBadge.vue';
import api, { parseApiError } from '../services/api.js';
import { useAuth } from '../stores/auth.js';
import { formatDate, formatRupiah } from '../utils/formatters.js';

/** @typedef {import('../types/models.js').CreditApplication} CreditApplication */
/** @typedef {import('../types/models.js').DashboardSummary} DashboardSummary */

const auth = useAuth();

/** @type {import('vue').Ref<CreditApplication[]>} */
const applications = ref([]);
/** @type {import('vue').Ref<DashboardSummary>} */
const summary = ref({
    draft_dealer: 0,
    waiting_review: 0,
    rejected: 0,
    approved: 0,
    ready_disburse: 0,
    completed: 0,
    total: 0,
});

const statusFilter = ref('ALL');
const searchTerm = ref('');
const loading = ref(false);
const errorMessage = ref('');

const canCreate = computed(() => auth.hasRole('sales_dealer'));

const summaryCards = computed(() => [
    {
        label: 'Menunggu Review',
        status: 'SUBMITTED',
        count: summary.value.waiting_review,
        accent: 'text-amber-700',
    },
    {
        label: 'Disetujui',
        status: 'APPROVED',
        count: summary.value.approved,
        accent: 'text-emerald-700',
    },
    {
        label: 'Siap Cair',
        status: 'SIGNED',
        count: summary.value.ready_disburse,
        accent: 'text-sky-700',
    },
    {
        label: 'Selesai',
        status: 'DISBURSED',
        count: summary.value.completed,
        accent: 'text-emerald-900',
    },
]);

const statusOptions = [
    { value: 'ALL', label: 'Semua Status' },
    { value: 'DRAFT_DEALER', label: 'Draft Dealer' },
    { value: 'SUBMITTED', label: 'Menunggu Review' },
    { value: 'REJECTED', label: 'Ditolak' },
    { value: 'APPROVED', label: 'Disetujui' },
    { value: 'SIGNED', label: 'Kontrak Ditandatangani' },
    { value: 'DISBURSED', label: 'Dana Dicairkan' },
];

async function fetchApplications() {
    loading.value = true;
    errorMessage.value = '';

    try {
        const params = {};
        if (statusFilter.value !== 'ALL') {
            params.status = statusFilter.value;
        }
        if (searchTerm.value.trim() !== '') {
            params.search = searchTerm.value.trim();
        }

        const response = await api.get('/applications', { params });
        applications.value = response.data?.data ?? [];
        if (response.data?.summary) {
            summary.value = response.data.summary;
        }
    } catch (err) {
        errorMessage.value = parseApiError(err, 'Gagal memuat daftar pengajuan kredit.').message;
    } finally {
        loading.value = false;
    }
}

/**
 * @param {string} status
 */
function toggleCardFilter(status) {
    statusFilter.value = statusFilter.value === status ? 'ALL' : status;
    fetchApplications();
}

function resetFilters() {
    statusFilter.value = 'ALL';
    searchTerm.value = '';
    fetchApplications();
}

onMounted(() => {
    fetchApplications();
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">
                    Daftar Pengajuan Kredit Kendaraan
                </h1>
                <p class="mt-0.5 text-sm text-slate-600">
                    Pantau status verifikasi, persetujuan, kontrak digital, hingga pencairan dana dealer.
                </p>
            </div>

            <RouterLink
                v-if="canCreate"
                to="/applications/new"
                class="inline-flex items-center rounded bg-[#1E3A8A] px-4 py-2 text-sm font-medium text-white hover:bg-[#1E40AF]"
            >
                Buat Pengajuan Baru
            </RouterLink>
        </div>

        <section class="grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="Ringkasan status pengajuan">
            <button
                v-for="card in summaryCards"
                :key="card.status"
                type="button"
                class="rounded border bg-white p-4 text-left transition-colors hover:border-slate-300"
                :class="statusFilter === card.status ? 'border-[#1E3A8A] ring-1 ring-[#1E3A8A]' : 'border-[#E2E8F0]'"
                @click="toggleCardFilter(card.status)"
            >
                <p class="text-xs font-medium text-slate-500">
                    {{ card.label }}
                </p>
                <p class="mt-1.5 text-2xl font-semibold tabular-nums" :class="card.accent">
                    {{ card.count }}
                </p>
            </button>
        </section>

        <section class="rounded border border-[#E2E8F0] bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E2E8F0] p-4">
                <form class="flex flex-1 flex-wrap items-center gap-2" @submit.prevent="fetchApplications">
                    <input
                        v-model="searchTerm"
                        type="search"
                        placeholder="Cari nomor pengajuan, nama konsumen, NIK, atau dealer..."
                        class="w-full min-w-[220px] flex-1 rounded border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#1E3A8A] focus:outline-none sm:max-w-sm"
                    >

                    <select
                        v-model="statusFilter"
                        aria-label="Filter status"
                        class="rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#1E3A8A] focus:outline-none"
                        @change="fetchApplications"
                    >
                        <option
                            v-for="opt in statusOptions"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>

                    <button
                        type="submit"
                        class="rounded border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                    >
                        Cari
                    </button>

                    <button
                        v-if="statusFilter !== 'ALL' || searchTerm !== ''"
                        type="button"
                        class="rounded px-2.5 py-2 text-sm text-slate-500 hover:text-slate-800"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </form>

                <div class="text-xs text-slate-500 tabular-nums">
                    Total arsip: {{ summary.total }} pengajuan (Draft: {{ summary.draft_dealer }}, Ditolak: {{ summary.rejected }})
                </div>
            </div>

            <div v-if="errorMessage" class="p-4 text-sm text-red-700">
                {{ errorMessage }}
            </div>

            <div v-else-if="loading" class="p-8 text-center text-sm text-slate-500">
                Memuat data pengajuan...
            </div>

            <div v-else-if="applications.length === 0" class="p-8 text-center">
                <p class="text-sm font-medium text-slate-700">
                    Tidak ada pengajuan yang sesuai filter saat ini.
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    Ubah kata kunci pencarian atau pilih status lain untuk melihat data.
                </p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b border-[#E2E8F0] bg-slate-50 text-xs font-semibold text-slate-600">
                            <th class="px-4 py-3">No. Pengajuan</th>
                            <th class="px-4 py-3">Konsumen</th>
                            <th class="px-4 py-3">Kendaraan &amp; Dealer</th>
                            <th class="px-4 py-3 text-right">Harga OTR / DP</th>
                            <th class="px-4 py-3 text-right">Angsuran</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E8F0]">
                        <tr
                            v-for="item in applications"
                            :key="item.id"
                            class="hover:bg-slate-50/70"
                        >
                            <td class="px-4 py-3 align-top">
                                <RouterLink
                                    :to="`/applications/${item.id}`"
                                    class="font-medium text-[#1E3A8A] tabular-nums hover:underline"
                                >
                                    {{ item.application_number }}
                                </RouterLink>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ formatDate(item.created_at) }}
                                </p>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <p class="font-medium text-slate-900">
                                    {{ item.customer_name }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-500 tabular-nums">
                                    NIK: {{ item.customer_nik }}
                                </p>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <p class="text-slate-900">
                                    {{ item.vehicle_brand }} {{ item.vehicle_model }} {{ item.vehicle_type }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ item.dealer_name }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-right align-top tabular-nums">
                                <p class="font-medium text-slate-900">
                                    {{ formatRupiah(item.vehicle_price) }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    DP: {{ formatRupiah(item.down_payment) }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-right align-top tabular-nums">
                                <p class="font-medium text-slate-900">
                                    {{ formatRupiah(item.monthly_installment) }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ item.tenor_months ? `${item.tenor_months} bulan` : 'Belum dipilih' }}
                                </p>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <StatusBadge :status="item.status" :label="item.status_label" />
                            </td>
                            <td class="px-4 py-3 text-right align-top">
                                <RouterLink
                                    :to="`/applications/${item.id}`"
                                    class="inline-block rounded border border-[#E2E8F0] bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    Lihat Detail
                                </RouterLink>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

