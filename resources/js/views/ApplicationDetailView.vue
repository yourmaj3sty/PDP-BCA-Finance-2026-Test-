<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import SignaturePad from '../components/SignaturePad.vue';
import StatusBadge from '../components/StatusBadge.vue';
import WorkflowStepper from '../components/WorkflowStepper.vue';
import api, { parseApiError } from '../services/api.js';
import { exceedsUploadLimit } from '../utils/files.js';
import { actionLabel, formatDate, formatDateTime, formatRupiah } from '../utils/formatters.js';

/** @typedef {import('../types/models.js').CreditApplication} CreditApplication */

const route = useRoute();

/** @type {import('vue').Ref<CreditApplication | null>} */
const application = ref(null);
const loading = ref(true);
const processing = ref(false);
const errorBanner = ref('');
const successBanner = ref('');

const submitNotes = ref('');
const reviewNotes = ref('');
const reviewFieldError = ref('');

const signMode = ref('canvas');
const signAgreed = ref(false);
/** @type {import('vue').Ref<File | null>} */
const uploadedContractFile = ref(null);
const signError = ref('');
/** @type {import('vue').Ref<InstanceType<typeof SignaturePad> | null>} */
const signaturePadRef = ref(null);

const disbursementRef = ref('');
const disbursementNotes = ref('');
const disburseFieldError = ref('');

const copiedLink = ref(false);

const consumerSignUrl = computed(() => {
    const token = application.value?.contract_sign_token;
    return token ? `${window.location.origin}/sign/${token}` : '';
});

const consumerSignPath = computed(() => {
    const token = application.value?.contract_sign_token;
    return token ? `/sign/${token}` : '';
});

async function fetchDetail() {
    loading.value = true;
    errorBanner.value = '';

    try {
        const res = await api.get(`/applications/${route.params.id}`);
        application.value = res.data?.data ?? null;
    } catch (err) {
        errorBanner.value = parseApiError(err, 'Gagal memuat rincian pengajuan.').message;
    } finally {
        loading.value = false;
    }
}

function clearActionFeedback() {
    errorBanner.value = '';
    successBanner.value = '';
    reviewFieldError.value = '';
    signError.value = '';
    disburseFieldError.value = '';
}

async function handleSubmitToManager() {
    if (!application.value) return;
    clearActionFeedback();
    processing.value = true;

    try {
        const res = await api.post(`/applications/${application.value.id}/submit`, {
            notes: submitNotes.value.trim() || null,
        });
        application.value = res.data?.data ?? application.value;
        successBanner.value = res.data?.message ?? 'Pengajuan berhasil dikirim ke Atasan Marketing.';
        submitNotes.value = '';
    } catch (err) {
        const parsed = parseApiError(err, 'Pengajuan belum memenuhi syarat kirim.');
        const firstFieldError = Object.values(parsed.fieldErrors)[0];
        errorBanner.value = firstFieldError || parsed.message;
    } finally {
        processing.value = false;
    }
}

async function handleApprove() {
    if (!application.value) return;
    clearActionFeedback();
    processing.value = true;

    try {
        const res = await api.post(`/applications/${application.value.id}/approve`, {
            notes: reviewNotes.value.trim() || null,
        });
        application.value = res.data?.data ?? application.value;
        successBanner.value = res.data?.message ?? 'Pengajuan disetujui.';
        reviewNotes.value = '';
    } catch (err) {
        errorBanner.value = parseApiError(err, 'Gagal menyetujui pengajuan.').message;
    } finally {
        processing.value = false;
    }
}

async function handleReject() {
    if (!application.value) return;
    clearActionFeedback();

    if (reviewNotes.value.trim().length < 5) {
        reviewFieldError.value = 'Catatan alasan penolakan wajib diisi minimal 5 karakter.';
        return;
    }

    processing.value = true;
    try {
        const res = await api.post(`/applications/${application.value.id}/reject`, {
            notes: reviewNotes.value.trim(),
        });
        application.value = res.data?.data ?? application.value;
        successBanner.value = res.data?.message ?? 'Pengajuan ditolak dan dikembalikan untuk revisi.';
        reviewNotes.value = '';
    } catch (err) {
        const parsed = parseApiError(err, 'Gagal menolak pengajuan.');
        reviewFieldError.value = parsed.fieldErrors.notes ?? '';
        errorBanner.value = parsed.message;
    } finally {
        processing.value = false;
    }
}

async function handleMarkPoSent() {
    if (!application.value) return;
    clearActionFeedback();
    processing.value = true;

    try {
        const res = await api.post(`/applications/${application.value.id}/send-po`);
        application.value = res.data?.data ?? application.value;
        successBanner.value = res.data?.message ?? 'Pengiriman PO ke dealer berhasil dicatat.';
    } catch (err) {
        errorBanner.value = parseApiError(err, 'Gagal mencatat pengiriman PO.').message;
    } finally {
        processing.value = false;
    }
}

/**
 * @param {Event} event
 */
function onContractFileSelect(event) {
    const input = /** @type {HTMLInputElement} */ (event.target);
    const file = input.files?.[0] ?? null;
    signError.value = '';

    if (exceedsUploadLimit(file)) {
        signError.value = 'Ukuran file bukti kontrak maksimal 2 MB.';
        input.value = '';
        uploadedContractFile.value = null;
        return;
    }

    uploadedContractFile.value = file;
}

async function handleInternalSign() {
    if (!application.value) return;
    clearActionFeedback();

    if (!signAgreed.value) {
        signError.value = 'Centang pernyataan konfirmasi tanda tangan terlebih dahulu.';
        return;
    }

    const fd = new FormData();
    fd.append('agreed', '1');

    if (signMode.value === 'canvas') {
        const blob = await signaturePadRef.value?.exportPngBlob();
        if (!blob) {
            signError.value = 'Harap goreskan tanda tangan pada kanvas terlebih dahulu.';
            return;
        }
        fd.append('signature_image', blob, 'signature.png');
    } else {
        if (!uploadedContractFile.value) {
            signError.value = 'Pilih file bukti tanda tangan kontrak (JPG/PNG/PDF).';
            return;
        }
        fd.append('kontrak_ttd', uploadedContractFile.value);
    }

    processing.value = true;
    try {
        const res = await api.post(`/applications/${application.value.id}/sign`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        application.value = res.data?.data ?? application.value;
        successBanner.value = res.data?.message ?? 'Tanda tangan kontrak berhasil disimpan.';
        signAgreed.value = false;
    } catch (err) {
        const parsed = parseApiError(err, 'Gagal menyimpan tanda tangan kontrak.');
        signError.value = Object.values(parsed.fieldErrors)[0] || parsed.message;
    } finally {
        processing.value = false;
    }
}

async function handleDisburse() {
    if (!application.value) return;
    clearActionFeedback();

    if (disbursementRef.value.trim().length < 4) {
        disburseFieldError.value = 'Nomor referensi transfer wajib diisi minimal 4 karakter.';
        return;
    }

    processing.value = true;
    try {
        const res = await api.post(`/applications/${application.value.id}/disburse`, {
            disbursement_ref: disbursementRef.value.trim(),
            notes: disbursementNotes.value.trim() || null,
        });
        application.value = res.data?.data ?? application.value;
        successBanner.value = res.data?.message ?? 'Dana berhasil dicairkan.';
        disbursementRef.value = '';
        disbursementNotes.value = '';
    } catch (err) {
        const parsed = parseApiError(err, 'Gagal mencatat pencairan dana.');
        disburseFieldError.value = parsed.fieldErrors.disbursement_ref ?? '';
        errorBanner.value = parsed.message;
    } finally {
        processing.value = false;
    }
}

async function copyConsumerLink() {
    if (!consumerSignUrl.value) return;
    try {
        await navigator.clipboard.writeText(consumerSignUrl.value);
        copiedLink.value = true;
        setTimeout(() => {
            copiedLink.value = false;
        }, 2500);
    } catch {
        copiedLink.value = false;
    }
}

onMounted(() => {
    fetchDetail();
});
</script>

<template>
    <div class="space-y-6">
        <div v-if="loading" class="rounded border border-[#E2E8F0] bg-white p-8 text-center text-sm text-slate-500">
            Memuat rincian pengajuan...
        </div>

        <div
            v-else-if="!application"
            class="rounded border border-red-200 bg-red-50 p-5 text-sm text-red-700"
        >
            {{ errorBanner || 'Data pengajuan tidak ditemukan.' }}
        </div>

        <template v-else>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <RouterLink to="/" class="text-xs font-medium text-[#1E3A8A] hover:underline">
                        &larr; Kembali ke Dashboard
                    </RouterLink>
                    <div class="mt-1 flex flex-wrap items-center gap-3">
                        <h1 class="text-xl font-semibold text-slate-900 tabular-nums">
                            {{ application.application_number }}
                        </h1>
                        <StatusBadge :status="application.status" :label="application.status_label" />
                    </div>
                    <p class="mt-0.5 text-sm text-slate-600">
                        {{ application.customer_name }} &mdash; {{ application.vehicle_brand }} {{ application.vehicle_model }} {{ application.vehicle_type }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <RouterLink
                        v-if="application.permissions?.can_update"
                        :to="`/applications/${application.id}/edit`"
                        class="rounded border border-[#1E3A8A] bg-white px-3.5 py-2 text-sm font-medium text-[#1E3A8A] hover:bg-slate-50"
                    >
                        Lengkapi / Revisi Data
                    </RouterLink>

                    <template v-if="application.permissions?.can_view_documents">
                        <RouterLink
                            :to="`/applications/${application.id}/print/contract`"
                            target="_blank"
                            class="rounded border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Cetak Dokumen Kontrak
                        </RouterLink>
                        <RouterLink
                            :to="`/applications/${application.id}/print/po`"
                            target="_blank"
                            class="rounded border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Cetak Dokumen PO
                        </RouterLink>
                    </template>
                </div>
            </div>

            <WorkflowStepper :status="application.status" />

            <div
                v-if="successBanner"
                class="rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                role="status"
            >
                {{ successBanner }}
            </div>

            <div
                v-if="errorBanner"
                class="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                role="alert"
            >
                {{ errorBanner }}
            </div>

            <div
                v-if="application.status === 'REJECTED' && application.rejection_note"
                class="rounded border border-red-200 bg-red-50 px-4 py-3"
            >
                <p class="text-xs font-semibold text-red-800">Catatan Penolakan Atasan Marketing</p>
                <p class="mt-1 text-sm text-red-700">{{ application.rejection_note }}</p>
            </div>

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <section class="rounded border border-[#E2E8F0] bg-white p-5">
                    <h2 class="border-b border-[#E2E8F0] pb-2.5 text-sm font-semibold text-slate-900">
                        Data Konsumen &amp; Pasangan
                    </h2>
                    <dl class="mt-3 divide-y divide-slate-100 text-sm">
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">Nama Lengkap</dt>
                            <dd class="text-right font-medium text-slate-900">{{ application.customer_name }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">NIK</dt>
                            <dd class="text-right font-medium text-slate-900 tabular-nums">{{ application.customer_nik }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">Tanggal Lahir</dt>
                            <dd class="text-right text-slate-900">{{ formatDate(application.customer_dob) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">Status Perkawinan</dt>
                            <dd class="text-right text-slate-900">{{ application.marital_status }}</dd>
                        </div>
                        <template v-if="application.marital_status === 'Menikah'">
                            <div class="flex justify-between gap-4 py-2">
                                <dt class="text-slate-500">Nama Pasangan</dt>
                                <dd class="text-right font-medium text-slate-900">{{ application.spouse_name || '-' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 py-2">
                                <dt class="text-slate-500">NIK Pasangan</dt>
                                <dd class="text-right text-slate-900 tabular-nums">{{ application.spouse_nik || '-' }}</dd>
                            </div>
                        </template>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">Sales Penginput</dt>
                            <dd class="text-right text-slate-900">{{ application.creator?.name || '-' }}</dd>
                        </div>
                        <div v-if="application.approver" class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">Disetujui Oleh</dt>
                            <dd class="text-right text-slate-900">{{ application.approver.name }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded border border-[#E2E8F0] bg-white p-5">
                    <h2 class="border-b border-[#E2E8F0] pb-2.5 text-sm font-semibold text-slate-900">
                        Kendaraan &amp; Simulasi Pembiayaan
                    </h2>
                    <dl class="mt-3 divide-y divide-slate-100 text-sm">
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">Dealer</dt>
                            <dd class="text-right font-medium text-slate-900">{{ application.dealer_name }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">Unit Kendaraan</dt>
                            <dd class="text-right text-slate-900">
                                {{ application.vehicle_brand }} {{ application.vehicle_model }} {{ application.vehicle_type }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">Warna</dt>
                            <dd class="text-right text-slate-900">{{ application.vehicle_color }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">Harga OTR</dt>
                            <dd class="text-right font-medium text-slate-900 tabular-nums">{{ formatRupiah(application.vehicle_price) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">Down Payment (DP)</dt>
                            <dd class="text-right text-slate-900 tabular-nums">{{ formatRupiah(application.down_payment) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">Jenis &amp; Premi Asuransi</dt>
                            <dd class="text-right text-slate-900 tabular-nums">
                                <span v-if="application.insurance_type">
                                    {{ application.insurance_type }} ({{ formatRupiah(application.insurance_premium) }})
                                </span>
                                <span v-else>-</span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-slate-500">Tenor &amp; Bunga Flat</dt>
                            <dd class="text-right text-slate-900 tabular-nums">
                                {{ application.tenor_months ? `${application.tenor_months} Bulan (${application.interest_rate}% / tahun)` : '-' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt class="font-medium text-slate-700">Angsuran per Bulan</dt>
                            <dd class="text-right text-base font-semibold text-[#1E3A8A] tabular-nums">
                                {{ formatRupiah(application.monthly_installment) }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>

            <section class="rounded border border-[#E2E8F0] bg-white p-5">
                <h2 class="border-b border-[#E2E8F0] pb-2.5 text-sm font-semibold text-slate-900">
                    Dokumen Digital Lampiran
                </h2>

                <div v-if="!application.documents || application.documents.length === 0" class="py-4 text-sm text-slate-500">
                    Belum ada dokumen yang diunggah.
                </div>

                <div v-else class="mt-3 divide-y divide-slate-100">
                    <div
                        v-for="doc in application.documents"
                        :key="doc.id"
                        class="flex flex-wrap items-center justify-between gap-3 py-2.5 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900">
                                {{ doc.document_type_label }}
                            </p>
                            <p class="truncate text-xs text-slate-500">
                                {{ doc.original_name }} &mdash; Diunggah oleh {{ doc.uploaded_by || '-' }}
                            </p>
                        </div>

                        <a
                            :href="doc.file_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="shrink-0 rounded border border-[#E2E8F0] bg-slate-50 px-3 py-1.5 text-xs font-medium text-[#1E3A8A] hover:bg-slate-100"
                        >
                            Lihat / Unduh
                        </a>
                    </div>
                </div>
            </section>

            <!-- Panel Aksi 1: Marketing kirim ke Atasan Marketing -->
            <section
                v-if="application.permissions?.can_submit"
                class="rounded border border-[#E2E8F0] bg-white p-5"
            >
                <h2 class="text-sm font-semibold text-slate-900">
                    Kirim Pengajuan untuk Persetujuan
                </h2>
                <p class="mt-1 text-xs text-slate-600">
                    Pastikan seluruh data konsumen, pasangan (jika Menikah), simulasi pembiayaan, serta 4 dokumen (KTP, SPK, Bukti Bayar, KK) sudah lengkap.
                </p>

                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label for="submit_notes" class="block text-xs font-medium text-slate-700">
                            Catatan Tambahan untuk Atasan Marketing (Opsional)
                        </label>
                        <input
                            id="submit_notes"
                            v-model="submitNotes"
                            type="text"
                            placeholder="Contoh: Seluruh dokumen asli telah diverifikasi."
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-[#1E3A8A] focus:outline-none"
                        >
                    </div>

                    <button
                        type="button"
                        :disabled="processing"
                        class="rounded bg-[#1E3A8A] px-4 py-2 text-sm font-medium text-white hover:bg-[#1E40AF] disabled:opacity-60"
                        @click="handleSubmitToManager"
                    >
                        {{ processing ? 'Mengirim...' : 'Kirim ke Atasan Marketing' }}
                    </button>
                </div>
            </section>

            <!-- Panel Aksi 2: Atasan Marketing Approve / Reject -->
            <section
                v-if="application.permissions?.can_approve || application.permissions?.can_reject"
                class="rounded border border-[#E2E8F0] bg-white p-5"
            >
                <h2 class="text-sm font-semibold text-slate-900">
                    Keputusan Persetujuan Atasan Marketing
                </h2>
                <p class="mt-1 text-xs text-slate-600">
                    Tinjau rincian data dan seluruh dokumen lampiran di atas sebelum menyetujui atau menolak pengajuan.
                </p>

                <div class="mt-3">
                    <label for="review_notes" class="block text-xs font-medium text-slate-700">
                        Catatan Keputusan (wajib diisi jika menolak)
                    </label>
                    <textarea
                        id="review_notes"
                        v-model="reviewNotes"
                        rows="3"
                        placeholder="Tuliskan catatan persetujuan atau alasan penolakan yang perlu diperbaiki Marketing..."
                        class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-[#1E3A8A] focus:outline-none"
                    />
                    <p v-if="reviewFieldError" class="mt-1 text-xs text-red-600">
                        {{ reviewFieldError }}
                    </p>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        :disabled="processing"
                        class="rounded bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                        @click="handleApprove"
                    >
                        {{ processing ? 'Memproses...' : 'Setujui Pengajuan (Approve)' }}
                    </button>

                    <button
                        type="button"
                        :disabled="processing"
                        class="rounded border border-red-300 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-100 disabled:opacity-60"
                        @click="handleReject"
                    >
                        {{ processing ? 'Memproses...' : 'Tolak Pengajuan (Reject)' }}
                    </button>
                </div>
            </section>

            <!-- Panel Aksi 3: Kontrak, PO, & Tanda Tangan Konsumen -->
            <section
                v-if="['APPROVED', 'SIGNED', 'DISBURSED'].includes(application.status)"
                class="rounded border border-[#E2E8F0] bg-white p-5 space-y-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E2E8F0] pb-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">
                            Status Dokumen Kontrak, PO &amp; Tanda Tangan Konsumen
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-600">
                            PO ke Dealer:
                            <span class="font-medium text-slate-900">
                                {{ application.po_sent_at ? `Terkirim (${formatDateTime(application.po_sent_at)})` : 'Siap dikirim ke dealer' }}
                            </span>
                            &nbsp;|&nbsp; TTD Konsumen:
                            <span class="font-medium text-slate-900">
                                {{ application.signed_at ? `Sudah ditandatangani (${formatDateTime(application.signed_at)})` : 'Menunggu tanda tangan' }}
                            </span>
                        </p>
                    </div>

                    <button
                        v-if="application.permissions?.can_send_po && !application.po_sent_at"
                        type="button"
                        :disabled="processing"
                        class="rounded border border-[#1E3A8A] bg-white px-3.5 py-1.5 text-xs font-medium text-[#1E3A8A] hover:bg-slate-50 disabled:opacity-60"
                        @click="handleMarkPoSent"
                    >
                        Tandai PO Terkirim ke Dealer
                    </button>
                </div>

                <!-- Tautan Token Khusus Konsumen saat APPROVED -->
                <div
                    v-if="application.status === 'APPROVED' && consumerSignUrl"
                    class="rounded border border-[#E2E8F0] bg-[#F8FAFC] p-4"
                >
                    <p class="text-xs font-semibold text-slate-800">
                        Tautan Tanda Tangan Digital Konsumen (Tanpa Login)
                    </p>
                    <p class="mt-0.5 text-xs text-slate-600">
                        Kirimkan tautan ini kepada konsumen untuk meninjau ringkasan kontrak dan membubuhkan tanda tangan digital.
                    </p>
                    <div class="mt-2.5 flex flex-wrap items-center gap-2">
                        <input
                            type="text"
                            readonly
                            :value="consumerSignUrl"
                            class="min-w-[220px] flex-1 rounded border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-700"
                        >
                        <button
                            type="button"
                            class="rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100"
                            @click="copyConsumerLink"
                        >
                            {{ copiedLink ? 'Tersalin' : 'Salin Tautan' }}
                        </button>
                        <RouterLink
                            :to="consumerSignPath"
                            target="_blank"
                            class="rounded bg-[#1E3A8A] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#1E40AF]"
                        >
                            Buka Halaman TTD
                        </RouterLink>
                    </div>
                </div>

                <!-- Opsi input TTD langsung oleh staf Marketing / Backoffice saat tatap muka atau unggah bukti -->
                <div
                    v-if="application.permissions?.can_sign"
                    class="rounded border border-[#E2E8F0] p-4"
                >
                    <h3 class="text-xs font-semibold text-slate-800">
                        Catat Tanda Tangan Kontrak (Input Langsung atau Unggah Bukti TTD)
                    </h3>

                    <div class="mt-3 flex gap-2">
                        <button
                            type="button"
                            class="rounded border px-3 py-1.5 text-xs font-medium"
                            :class="signMode === 'canvas' ? 'border-[#1E3A8A] bg-[#1E3A8A] text-white' : 'border-slate-300 bg-white text-slate-700'"
                            @click="signMode = 'canvas'"
                        >
                            Kanvas Tanda Tangan
                        </button>
                        <button
                            type="button"
                            class="rounded border px-3 py-1.5 text-xs font-medium"
                            :class="signMode === 'upload' ? 'border-[#1E3A8A] bg-[#1E3A8A] text-white' : 'border-slate-300 bg-white text-slate-700'"
                            @click="signMode = 'upload'"
                        >
                            Unggah Bukti Kontrak TTD
                        </button>
                    </div>

                    <div v-if="signMode === 'canvas'" class="mt-3">
                        <SignaturePad ref="signaturePadRef" />
                    </div>

                    <div v-else class="mt-3">
                        <input
                            type="file"
                            accept=".jpg,.jpeg,.png,.pdf"
                            class="block w-full text-xs text-slate-600 file:mr-3 file:rounded file:border file:border-slate-300 file:bg-slate-50 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700"
                            @change="onContractFileSelect"
                        >
                    </div>

                    <label class="mt-3 flex items-start gap-2 text-xs text-slate-700">
                        <input
                            v-model="signAgreed"
                            type="checkbox"
                            class="mt-0.5 rounded border-slate-300 text-[#1E3A8A]"
                        >
                        <span>Konsumen telah memeriksa rincian pembiayaan dan menyetujui dokumen kontrak kredit PT. JKL.</span>
                    </label>

                    <p v-if="signError" class="mt-2 text-xs text-red-600">
                        {{ signError }}
                    </p>

                    <button
                        type="button"
                        :disabled="processing"
                        class="mt-3 rounded bg-[#1E3A8A] px-4 py-2 text-xs font-medium text-white hover:bg-[#1E40AF] disabled:opacity-60"
                        @click="handleInternalSign"
                    >
                        {{ processing ? 'Menyimpan...' : 'Konfirmasi & Simpan Tanda Tangan' }}
                    </button>
                </div>

                <!-- Pratinjau tanda tangan konsumen yang sudah masuk -->
                <div v-if="application.signature_url" class="rounded border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                    <p class="text-xs font-semibold text-slate-700">
                        Tanda Tangan Digital Konsumen Tercatat
                    </p>
                    <img
                        :src="application.signature_url"
                        alt="Tanda tangan digital konsumen"
                        class="mt-2 h-24 rounded border border-slate-200 bg-white p-2 object-contain"
                    >
                </div>
            </section>

            <!-- Panel Aksi 4: Pencairan Dana (Admin Backoffice) -->
            <section
                v-if="application.permissions?.can_disburse"
                class="rounded border border-[#E2E8F0] bg-white p-5"
            >
                <h2 class="text-sm font-semibold text-slate-900">
                    Eksekusi Pencairan Dana ke Dealer
                </h2>
                <p class="mt-1 text-xs text-slate-600">
                    Verifikasi tanda tangan kontrak konsumen dan input nomor referensi transfer untuk menyelesaikan pengajuan.
                </p>

                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label for="disbursement_ref" class="block text-xs font-medium text-slate-700">
                            Nomor Referensi Transfer / Pencairan <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="disbursement_ref"
                            v-model="disbursementRef"
                            type="text"
                            placeholder="Contoh: TRF-JKL-20261126-001"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-[#1E3A8A] focus:outline-none"
                        >
                        <p v-if="disburseFieldError" class="mt-1 text-xs text-red-600">
                            {{ disburseFieldError }}
                        </p>
                    </div>

                    <div>
                        <label for="disbursement_notes" class="block text-xs font-medium text-slate-700">
                            Catatan Pencairan (Opsional)
                        </label>
                        <input
                            id="disbursement_notes"
                            v-model="disbursementNotes"
                            type="text"
                            placeholder="Contoh: Transfer ke rekening dealer terkonfirmasi."
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-[#1E3A8A] focus:outline-none"
                        >
                    </div>
                </div>

                <button
                    type="button"
                    :disabled="processing"
                    class="mt-4 rounded bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60"
                    @click="handleDisburse"
                >
                    {{ processing ? 'Memproses...' : 'Cairkan Dana' }}
                </button>
            </section>

            <!-- Ringkasan Pencairan Selesai -->
            <section
                v-if="application.status === 'DISBURSED'"
                class="rounded border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900"
            >
                <p class="font-semibold">
                    Pencairan Dana Selesai
                </p>
                <p class="mt-1 text-xs">
                    Nomor Referensi: <span class="font-semibold tabular-nums">{{ application.disbursement_ref }}</span>
                    &nbsp;|&nbsp; Tanggal Pencairan: {{ formatDateTime(application.disbursed_at) }}
                </p>
            </section>

            <section class="rounded border border-[#E2E8F0] bg-white p-5">
                <h2 class="border-b border-[#E2E8F0] pb-2.5 text-sm font-semibold text-slate-900">
                    Riwayat Aktivitas (Audit Trail)
                </h2>

                <div class="mt-3 overflow-x-auto">
                    <table class="w-full border-collapse text-left text-xs sm:text-sm">
                        <thead>
                            <tr class="border-b border-[#E2E8F0] text-xs font-semibold text-slate-500">
                                <th class="py-2 pr-4">Waktu</th>
                                <th class="py-2 pr-4">Pengguna</th>
                                <th class="py-2 pr-4">Aktivitas</th>
                                <th class="py-2 pr-4">Perubahan Status</th>
                                <th class="py-2">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="log in application.logs" :key="log.id">
                                <td class="py-2.5 pr-4 whitespace-nowrap text-slate-600 tabular-nums">
                                    {{ formatDateTime(log.created_at) }}
                                </td>
                                <td class="py-2.5 pr-4 whitespace-nowrap">
                                    <p class="font-medium text-slate-900">{{ log.user_name }}</p>
                                    <p class="text-xs text-slate-500">{{ log.user_role_label }}</p>
                                </td>
                                <td class="py-2.5 pr-4 font-medium text-slate-800">
                                    {{ actionLabel(log.action) }}
                                </td>
                                <td class="py-2.5 pr-4 whitespace-nowrap text-slate-600">
                                    <span v-if="log.from_status_label">{{ log.from_status_label }} &rarr; </span>
                                    <span class="font-medium text-slate-900">{{ log.to_status_label }}</span>
                                </td>
                                <td class="py-2.5 text-slate-600">
                                    {{ log.notes || '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>
    </div>
</template>

