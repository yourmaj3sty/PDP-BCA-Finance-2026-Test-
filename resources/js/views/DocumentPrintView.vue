<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import api, { parseApiError } from '../services/api.js';
import { formatDate, formatDateTime, formatRupiah } from '../utils/formatters.js';

/** @typedef {import('../types/models.js').CreditApplication} CreditApplication */

const route = useRoute();

const kind = computed(() => (route.params.kind === 'po' ? 'po' : 'contract'));
const loading = ref(true);
const errorMessage = ref('');
const documentNumber = ref('');
const issuedAt = ref('');
/** @type {import('vue').Ref<Record<string, string>>} */
const company = ref({});
/** @type {import('vue').Ref<CreditApplication | null>} */
const application = ref(null);

async function fetchDocument() {
    loading.value = true;
    errorMessage.value = '';

    try {
        const endpoint = kind.value === 'po'
            ? `/applications/${route.params.id}/po`
            : `/applications/${route.params.id}/contract`;

        const res = await api.get(endpoint);
        const payload = res.data?.data;
        documentNumber.value = payload.document_number;
        issuedAt.value = payload.issued_at;
        company.value = payload.company ?? {};
        application.value = payload.application ?? null;
    } catch (err) {
        errorMessage.value = parseApiError(err, 'Dokumen belum dapat ditampilkan.').message;
    } finally {
        loading.value = false;
    }
}

function printDocument() {
    window.print();
}

onMounted(() => {
    fetchDocument();
});
</script>

<template>
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-6">
        <div class="no-print mx-auto mb-4 flex max-w-[820px] flex-wrap items-center justify-between gap-3">
            <RouterLink
                :to="`/applications/${route.params.id}`"
                class="rounded border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                &larr; Kembali ke Detail Pengajuan
            </RouterLink>

            <button
                type="button"
                class="rounded bg-[#1E3A8A] px-4 py-2 text-sm font-medium text-white hover:bg-[#1E40AF]"
                @click="printDocument"
            >
                Cetak / Simpan PDF
            </button>
        </div>

        <div v-if="loading" class="mx-auto max-w-[820px] rounded border border-[#E2E8F0] bg-white p-10 text-center text-sm text-slate-500">
            Menyiapkan dokumen cetak...
        </div>

        <div v-else-if="errorMessage" class="mx-auto max-w-[820px] rounded border border-red-200 bg-red-50 p-6 text-sm text-red-700">
            {{ errorMessage }}
        </div>

        <article
            v-else-if="application"
            class="print-sheet mx-auto max-w-[820px] rounded border border-[#E2E8F0] bg-white p-8 text-slate-900 sm:p-10"
        >
            <!-- Kop Surat Resmi PT. JKL -->
            <header class="border-b-2 border-[#1E3A8A] pb-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-lg font-bold tracking-tight text-[#1E3A8A]">
                            {{ company.name }}
                        </p>
                        <p class="text-xs font-medium text-slate-700">
                            {{ company.division }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ company.address }} &bull; Telp. {{ company.phone }}
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="text-sm font-bold text-slate-900">
                            {{ kind === 'po' ? 'PURCHASE ORDER (PO)' : 'PERJANJIAN PEMBIAYAAN KREDIT' }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-600 tabular-nums">
                            No. Dokumen: {{ documentNumber }}
                        </p>
                        <p class="text-xs text-slate-500">
                            Tanggal Terbit: {{ formatDate(issuedAt) }}
                        </p>
                    </div>
                </div>
            </header>

            <!-- Isi Dokumen Purchase Order (PO) -->
            <template v-if="kind === 'po'">
                <section class="mt-6 text-sm leading-relaxed">
                    <p class="font-medium">Kepada Yth.</p>
                    <p class="font-semibold text-slate-900">{{ application.dealer_name }}</p>
                    <p class="mt-3 text-slate-700">
                        Dengan hormat, berdasarkan hasil verifikasi dan persetujuan kredit nomor
                        <span class="font-semibold tabular-nums">{{ application.application_number }}</span>,
                        bersama ini kami menerbitkan Purchase Order (PO) pemesanan unit kendaraan dengan rincian sebagai berikut:
                    </p>
                </section>

                <section class="mt-5">
                    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                        Rincian Unit &amp; Pemesan
                    </h2>
                    <table class="w-full border-collapse border border-slate-300 text-sm">
                        <tbody>
                            <tr class="border-b border-slate-300">
                                <td class="w-48 bg-slate-50 px-3 py-2 font-medium text-slate-700">Nama Konsumen</td>
                                <td class="px-3 py-2">{{ application.customer_name }} (NIK: {{ application.customer_nik }})</td>
                            </tr>
                            <tr class="border-b border-slate-300">
                                <td class="bg-slate-50 px-3 py-2 font-medium text-slate-700">Merk / Model / Tipe</td>
                                <td class="px-3 py-2">{{ application.vehicle_brand }} {{ application.vehicle_model }} &mdash; {{ application.vehicle_type }}</td>
                            </tr>
                            <tr class="border-b border-slate-300">
                                <td class="bg-slate-50 px-3 py-2 font-medium text-slate-700">Warna Unit</td>
                                <td class="px-3 py-2">{{ application.vehicle_color }}</td>
                            </tr>
                            <tr class="border-b border-slate-300">
                                <td class="bg-slate-50 px-3 py-2 font-medium text-slate-700">Harga OTR Unit</td>
                                <td class="px-3 py-2 font-medium tabular-nums">{{ formatRupiah(application.vehicle_price) }}</td>
                            </tr>
                            <tr class="border-b border-slate-300">
                                <td class="bg-slate-50 px-3 py-2 font-medium text-slate-700">Uang Muka (DP) Konsumen</td>
                                <td class="px-3 py-2 tabular-nums">{{ formatRupiah(application.down_payment) }}</td>
                            </tr>
                            <tr>
                                <td class="bg-slate-50 px-3 py-2 font-semibold text-slate-900">Nilai Pelunasan ke Dealer</td>
                                <td class="px-3 py-2 font-bold text-[#1E3A8A] tabular-nums">
                                    {{ formatRupiah(application.loan_breakdown?.principal_vehicle ?? ((application.vehicle_price || 0) - (application.down_payment || 0))) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="mt-5 rounded border border-slate-200 bg-slate-50 p-4 text-xs leading-relaxed text-slate-700">
                    <p class="font-semibold text-slate-900">Ketentuan Penyerahan Unit &amp; Penagihan:</p>
                    <ol class="mt-1.5 list-decimal space-y-1 pl-4">
                        <li>Penyerahan unit kendaraan dilakukan setelah konsumen menandatangani dokumen kontrak resmi PT. JKL.</li>
                        <li>Pencairan dana pelunasan ke dealer diproses setelah berkas serah terima dan kontrak tertandatangani diverifikasi oleh Admin Backoffice.</li>
                        <li>PO ini berlaku sebagai dokumen komitmen pembiayaan resmi tanpa perlu pengetikan ulang oleh dealer.</li>
                    </ol>
                </section>

                <footer class="mt-10 grid grid-cols-2 gap-8 text-center text-xs">
                    <div>
                        <p class="text-slate-600">Diterima oleh Dealer,</p>
                        <div class="h-20" />
                        <p class="font-semibold text-slate-900">{{ application.dealer_name }}</p>
                    </div>
                    <div>
                        <p class="text-slate-600">Disetujui atas nama PT. JKL,</p>
                        <div class="flex h-20 items-center justify-center">
                            <span class="rounded border border-emerald-600 px-3 py-1 text-[11px] font-semibold text-emerald-700">
                                TEROTORISASI SISTEM
                            </span>
                        </div>
                        <p class="font-semibold text-slate-900">{{ application.approver?.name || 'Atasan Marketing' }}</p>
                        <p class="text-slate-500">No. Ref: {{ application.application_number }}</p>
                    </div>
                </footer>
            </template>

            <!-- Isi Dokumen Kontrak Pembiayaan -->
            <template v-else>
                <section class="mt-6 text-sm leading-relaxed text-slate-700">
                    <p>
                        Perjanjian Pembiayaan Kredit Kendaraan Bermotor ini dibuat secara terintegrasi untuk pengajuan nomor
                        <span class="font-semibold text-slate-900 tabular-nums">{{ application.application_number }}</span>
                        antara <span class="font-semibold text-slate-900">{{ company.name }}</span> (selaku Kreditur) dan pihak Konsumen (selaku Debitur):
                    </p>
                </section>

                <section class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <table class="w-full border-collapse border border-slate-300 text-xs">
                        <thead>
                            <tr class="border-b border-slate-300 bg-slate-50">
                                <th colspan="2" class="px-3 py-2 text-left font-semibold text-slate-800">
                                    Data Debitur (Konsumen)
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-slate-200">
                                <td class="w-32 px-3 py-1.5 text-slate-500">Nama Lengkap</td>
                                <td class="px-3 py-1.5 font-medium">{{ application.customer_name }}</td>
                            </tr>
                            <tr class="border-b border-slate-200">
                                <td class="px-3 py-1.5 text-slate-500">NIK</td>
                                <td class="px-3 py-1.5 tabular-nums">{{ application.customer_nik }}</td>
                            </tr>
                            <tr class="border-b border-slate-200">
                                <td class="px-3 py-1.5 text-slate-500">Tanggal Lahir</td>
                                <td class="px-3 py-1.5">{{ formatDate(application.customer_dob) }}</td>
                            </tr>
                            <tr class="border-b border-slate-200">
                                <td class="px-3 py-1.5 text-slate-500">Status Nikah</td>
                                <td class="px-3 py-1.5">{{ application.marital_status }}</td>
                            </tr>
                            <tr v-if="application.marital_status === 'Menikah'">
                                <td class="px-3 py-1.5 text-slate-500">Pasangan</td>
                                <td class="px-3 py-1.5">{{ application.spouse_name }} ({{ application.spouse_nik }})</td>
                            </tr>
                        </tbody>
                    </table>

                    <table class="w-full border-collapse border border-slate-300 text-xs">
                        <thead>
                            <tr class="border-b border-slate-300 bg-slate-50">
                                <th colspan="2" class="px-3 py-2 text-left font-semibold text-slate-800">
                                    Objek Jaminan &amp; Skema Kredit
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-slate-200">
                                <td class="w-36 px-3 py-1.5 text-slate-500">Unit Kendaraan</td>
                                <td class="px-3 py-1.5 font-medium">
                                    {{ application.vehicle_brand }} {{ application.vehicle_model }} {{ application.vehicle_type }}
                                </td>
                            </tr>
                            <tr class="border-b border-slate-200">
                                <td class="px-3 py-1.5 text-slate-500">Warna / Dealer</td>
                                <td class="px-3 py-1.5">{{ application.vehicle_color }} / {{ application.dealer_name }}</td>
                            </tr>
                            <tr class="border-b border-slate-200">
                                <td class="px-3 py-1.5 text-slate-500">Harga OTR</td>
                                <td class="px-3 py-1.5 tabular-nums">{{ formatRupiah(application.vehicle_price) }}</td>
                            </tr>
                            <tr class="border-b border-slate-200">
                                <td class="px-3 py-1.5 text-slate-500">DP / Asuransi</td>
                                <td class="px-3 py-1.5 tabular-nums">
                                    {{ formatRupiah(application.down_payment) }} / {{ application.insurance_type }} ({{ formatRupiah(application.insurance_premium) }})
                                </td>
                            </tr>
                            <tr>
                                <td class="px-3 py-1.5 font-semibold text-slate-800">Angsuran / Tenor</td>
                                <td class="px-3 py-1.5 font-bold text-[#1E3A8A] tabular-nums">
                                    {{ formatRupiah(application.monthly_installment) }} &times; {{ application.tenor_months }} bulan
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section v-if="application.loan_breakdown" class="mt-5">
                    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                        Ringkasan Perhitungan Pembiayaan
                    </h2>
                    <table class="w-full border-collapse border border-slate-300 text-xs">
                        <tbody>
                            <tr class="border-b border-slate-200">
                                <td class="px-3 py-1.5 text-slate-600">Pokok Hutang Kendaraan (OTR &minus; DP)</td>
                                <td class="px-3 py-1.5 text-right font-medium tabular-nums">
                                    {{ formatRupiah(application.loan_breakdown.principal_vehicle) }}
                                </td>
                            </tr>
                            <tr class="border-b border-slate-200">
                                <td class="px-3 py-1.5 text-slate-600">
                                    Premi Asuransi {{ application.insurance_type }} ({{ application.loan_breakdown.insurance_rate_percent }}% / tahun)
                                </td>
                                <td class="px-3 py-1.5 text-right font-medium tabular-nums">
                                    {{ formatRupiah(application.loan_breakdown.insurance_premium) }}
                                </td>
                            </tr>
                            <tr class="border-b border-slate-200">
                                <td class="px-3 py-1.5 text-slate-600">
                                    Total Bunga Flat ({{ application.loan_breakdown.annual_interest_rate }}% / tahun)
                                </td>
                                <td class="px-3 py-1.5 text-right font-medium tabular-nums">
                                    {{ formatRupiah(application.loan_breakdown.total_interest) }}
                                </td>
                            </tr>
                            <tr class="bg-slate-50">
                                <td class="px-3 py-2 font-semibold text-slate-900">Total Kewajiban Kredit</td>
                                <td class="px-3 py-2 text-right font-bold text-[#1E3A8A] tabular-nums">
                                    {{ formatRupiah(application.loan_breakdown.total_loan) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="mt-5 text-xs leading-relaxed text-slate-600">
                    <p class="font-semibold text-slate-800">Klausul Pokok Perjanjian:</p>
                    <ol class="mt-1 list-decimal space-y-1 pl-4">
                        <li>Debitur berkewajiban membayar angsuran bulanan sebesar {{ formatRupiah(application.monthly_installment) }} secara tepat waktu selama {{ application.tenor_months }} bulan.</li>
                        <li>Objek kendaraan menjadi jaminan pembiayaan hingga seluruh kewajiban kredit dinyatakan lunas oleh Kreditur.</li>
                        <li>Tanda tangan digital pada dokumen ini sah sebagai persetujuan mengikat antara Debitur dan PT. JKL Multifinance Indonesia.</li>
                    </ol>
                </section>

                <footer class="mt-8 grid grid-cols-2 gap-8 text-center text-xs">
                    <div>
                        <p class="text-slate-600">Debitur / Konsumen,</p>
                        <div class="my-2 flex h-24 items-center justify-center">
                            <img
                                v-if="application.signature_url"
                                :src="application.signature_url"
                                alt="Tanda tangan konsumen"
                                class="max-h-20 object-contain"
                            >
                            <span v-else class="text-slate-400 italic">(Menunggu tanda tangan)</span>
                        </div>
                        <p class="font-semibold text-slate-900">{{ application.customer_name }}</p>
                        <p v-if="application.signed_at" class="text-[11px] text-slate-500">
                            Ditandatangani: {{ formatDateTime(application.signed_at) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-slate-600">Kreditur — PT. JKL Multifinance,</p>
                        <div class="my-2 flex h-24 items-center justify-center">
                            <span class="rounded border border-emerald-600 px-3 py-1 text-[11px] font-semibold text-emerald-700">
                                DISETUJUI SECARA DIGITAL
                            </span>
                        </div>
                        <p class="font-semibold text-slate-900">{{ application.approver?.name || 'Atasan Marketing' }}</p>
                        <p class="text-[11px] text-slate-500">No. Kontrak: {{ documentNumber }}</p>
                    </div>
                </footer>
            </template>
        </article>
    </div>
</template>
