<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import SignaturePad from '../components/SignaturePad.vue';
import StatusBadge from '../components/StatusBadge.vue';
import api, { parseApiError } from '../services/api.js';
import { exceedsUploadLimit } from '../utils/files.js';
import { formatDateTime, formatRupiah } from '../utils/formatters.js';

const route = useRoute();

const loading = ref(true);
const submitting = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
/** @type {import('vue').Ref<any>} */
const contract = ref(null);

const signMode = ref('canvas');
const agreed = ref(false);
/** @type {import('vue').Ref<File | null>} */
const uploadedFile = ref(null);
/** @type {import('vue').Ref<InstanceType<typeof SignaturePad> | null>} */
const signaturePadRef = ref(null);

async function fetchContract() {
    loading.value = true;
    errorMessage.value = '';

    try {
        const res = await api.get(`/public/contracts/${route.params.token}`);
        contract.value = res.data?.data ?? null;
    } catch (err) {
        errorMessage.value = parseApiError(err, 'Tautan kontrak tidak ditemukan atau sudah tidak berlaku.').message;
    } finally {
        loading.value = false;
    }
}

/**
 * @param {Event} event
 */
function handleFileSelect(event) {
    const input = /** @type {HTMLInputElement} */ (event.target);
    const file = input.files?.[0] ?? null;
    errorMessage.value = '';

    if (exceedsUploadLimit(file)) {
        errorMessage.value = 'Ukuran file maksimal 2 MB.';
        input.value = '';
        uploadedFile.value = null;
        return;
    }

    uploadedFile.value = file;
}

async function submitSignature() {
    errorMessage.value = '';
    successMessage.value = '';

    if (!agreed.value) {
        errorMessage.value = 'Harap centang kotak persetujuan perjanjian kredit terlebih dahulu.';
        return;
    }

    const fd = new FormData();
    fd.append('agreed', '1');

    if (signMode.value === 'canvas') {
        const blob = await signaturePadRef.value?.exportPngBlob();
        if (!blob) {
            errorMessage.value = 'Silakan goreskan tanda tangan Anda pada kotak kanvas.';
            return;
        }
        fd.append('signature_image', blob, 'tanda-tangan-konsumen.png');
    } else {
        if (!uploadedFile.value) {
            errorMessage.value = 'Silakan pilih file bukti tanda tangan (JPG, PNG, atau PDF).';
            return;
        }
        fd.append('kontrak_ttd', uploadedFile.value);
    }

    submitting.value = true;
    try {
        const res = await api.post(`/public/contracts/${route.params.token}/sign`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        contract.value = res.data?.data ?? contract.value;
        successMessage.value = res.data?.message ?? 'Tanda tangan digital Anda berhasil disimpan.';
    } catch (err) {
        const parsed = parseApiError(err, 'Gagal menyimpan tanda tangan digital.');
        errorMessage.value = Object.values(parsed.fieldErrors)[0] || parsed.message;
    } finally {
        submitting.value = false;
    }
}

onMounted(() => {
    fetchContract();
});
</script>

<template>
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8">
        <div class="mx-auto max-w-[740px] space-y-5">
            <header class="flex items-center justify-between gap-3 rounded border border-[#E2E8F0] bg-white p-5">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded bg-[#1E3A8A] text-xs font-bold text-white">
                        JKL
                    </span>
                    <div>
                        <h1 class="text-base font-semibold text-slate-900">
                            Konfirmasi &amp; Tanda Tangan Kontrak Digital
                        </h1>
                        <p class="text-xs text-slate-500">
                            PT. JKL Multifinance Indonesia
                        </p>
                    </div>
                </div>

                <StatusBadge
                    v-if="contract"
                    :status="contract.status"
                    :label="contract.status_label"
                />
            </header>

            <div v-if="loading" class="rounded border border-[#E2E8F0] bg-white p-8 text-center text-sm text-slate-500">
                Memuat ringkasan kontrak...
            </div>

            <div
                v-else-if="!contract"
                class="rounded border border-red-200 bg-red-50 p-5 text-sm text-red-700"
            >
                {{ errorMessage || 'Tautan kontrak tidak ditemukan.' }}
            </div>

            <template v-else>
                <div
                    v-if="successMessage"
                    class="rounded border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
                    role="status"
                >
                    {{ successMessage }}
                </div>

                <div
                    v-if="errorMessage"
                    class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-700"
                    role="alert"
                >
                    {{ errorMessage }}
                </div>

                <section class="rounded border border-[#E2E8F0] bg-white p-5">
                    <h2 class="border-b border-[#E2E8F0] pb-2.5 text-sm font-semibold text-slate-900">
                        Ringkasan Perjanjian Pembiayaan
                    </h2>
                    <dl class="mt-3 divide-y divide-slate-100 text-sm">
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">Nomor Pengajuan</dt>
                            <dd class="font-semibold text-slate-900 tabular-nums">{{ contract.application_number }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">Nama Konsumen</dt>
                            <dd class="font-medium text-slate-900">{{ contract.customer_name }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">NIK (Tersamar)</dt>
                            <dd class="text-slate-900 tabular-nums">{{ contract.customer_nik_masked }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">Kendaraan &amp; Dealer</dt>
                            <dd class="text-right text-slate-900">
                                {{ contract.vehicle_brand }} {{ contract.vehicle_model }} {{ contract.vehicle_type }} ({{ contract.dealer_name }})
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">Harga OTR / DP</dt>
                            <dd class="text-slate-900 tabular-nums">
                                {{ formatRupiah(contract.vehicle_price) }} / {{ formatRupiah(contract.down_payment) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">Asuransi &amp; Tenor</dt>
                            <dd class="text-slate-900 tabular-nums">
                                {{ contract.insurance_type }} &bull; {{ contract.tenor_months }} Bulan
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt class="font-medium text-slate-700">Angsuran per Bulan</dt>
                            <dd class="text-base font-bold text-[#1E3A8A] tabular-nums">
                                {{ formatRupiah(contract.monthly_installment) }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    v-if="contract.status === 'APPROVED'"
                    class="rounded border border-[#E2E8F0] bg-white p-5 space-y-4"
                >
                    <h2 class="text-sm font-semibold text-slate-900">
                        Pembubuhan Tanda Tangan Digital
                    </h2>

                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded border px-3 py-1.5 text-xs font-medium"
                            :class="signMode === 'canvas' ? 'border-[#1E3A8A] bg-[#1E3A8A] text-white' : 'border-slate-300 bg-white text-slate-700'"
                            @click="signMode = 'canvas'"
                        >
                            Gambar Tanda Tangan di Layar
                        </button>
                        <button
                            type="button"
                            class="rounded border px-3 py-1.5 text-xs font-medium"
                            :class="signMode === 'upload' ? 'border-[#1E3A8A] bg-[#1E3A8A] text-white' : 'border-slate-300 bg-white text-slate-700'"
                            @click="signMode = 'upload'"
                        >
                            Unggah File Bukti TTD
                        </button>
                    </div>

                    <div v-if="signMode === 'canvas'">
                        <SignaturePad ref="signaturePadRef" />
                    </div>

                    <div v-else>
                        <label for="public_kontrak_ttd" class="block text-xs font-medium text-slate-700">
                            Unggah Foto Tanda Tangan atau Scan Kontrak TTD (JPG, PNG, PDF — Maks. 2 MB)
                        </label>
                        <input
                            id="public_kontrak_ttd"
                            type="file"
                            accept=".jpg,.jpeg,.png,.pdf"
                            class="mt-2 block w-full text-xs text-slate-600 file:mr-3 file:rounded file:border file:border-slate-300 file:bg-slate-50 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700"
                            @change="handleFileSelect"
                        >
                    </div>

                    <label class="flex items-start gap-2.5 text-xs leading-relaxed text-slate-700">
                        <input
                            v-model="agreed"
                            type="checkbox"
                            class="mt-0.5 rounded border-slate-300 text-[#1E3A8A]"
                        >
                        <span>
                            Saya menyatakan bahwa seluruh data diri, rincian kendaraan, jumlah DP, jangka waktu kredit,
                            dan besaran angsuran bulanan di atas telah sesuai dan saya menyetujui perjanjian pembiayaan ini.
                        </span>
                    </label>

                    <button
                        type="button"
                        :disabled="submitting"
                        class="w-full rounded bg-[#1E3A8A] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#1E40AF] disabled:opacity-60"
                        @click="submitSignature"
                    >
                        {{ submitting ? 'Menyimpan Tanda Tangan...' : 'Konfirmasi & Tandatangani Kontrak' }}
                    </button>
                </section>

                <section
                    v-else
                    class="rounded border border-[#E2E8F0] bg-white p-5 space-y-3"
                >
                    <p class="text-sm font-semibold text-slate-900">
                        Kontrak Telah Ditandatangani
                    </p>
                    <p class="text-xs text-slate-600">
                        Tanda tangan digital tercatat pada {{ formatDateTime(contract.signed_at) }}.
                    </p>
                    <img
                        v-if="contract.signature_url"
                        :src="contract.signature_url"
                        alt="Tanda tangan digital konsumen"
                        class="h-24 rounded border border-slate-200 bg-[#F8FAFC] p-2 object-contain"
                    >
                </section>
            </template>
        </div>
    </div>
</template>

