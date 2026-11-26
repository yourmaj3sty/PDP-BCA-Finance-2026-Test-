<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api, { parseApiError } from '../services/api.js';
import { useAuth } from '../stores/auth.js';
import { formatRupiah } from '../utils/formatters.js';
import { exceedsUploadLimit } from '../utils/files.js';
import { calculateLoan, INSURANCE_OPTIONS, TENOR_OPTIONS } from '../utils/loanCalculator.js';

/** @typedef {import('../types/models.js').ApplicationDocument} ApplicationDocument */

const route = useRoute();
const router = useRouter();
const auth = useAuth();

const isEditMode = computed(() => Boolean(route.params.id));
const isMarketing = computed(() => auth.hasRole('marketing'));

const loading = ref(false);
const saving = ref(false);
const errorBanner = ref('');
/** @type {import('vue').Ref<Record<string, string>>} */
const fieldErrors = ref({});
/** @type {import('vue').Ref<ApplicationDocument[]>} */
const existingDocs = ref([]);
const existingNumber = ref('');

const form = reactive({
    customer_name: '',
    customer_nik: '',
    customer_dob: '',
    marital_status: 'Belum Menikah',
    spouse_name: '',
    spouse_nik: '',
    dealer_name: '',
    vehicle_brand: '',
    vehicle_model: '',
    vehicle_type: '',
    vehicle_color: '',
    vehicle_price: '',
    insurance_type: 'All Risk',
    down_payment: '',
    tenor_months: '36',
});

/** @type {Record<string, File | null>} */
const selectedFiles = reactive({
    ktp: null,
    spk: null,
    bukti_bayar: null,
    kk: null,
});

const documentFields = [
    { key: 'ktp', label: 'KTP Konsumen', requiredOnCreate: true, requiredOnSubmit: true },
    { key: 'spk', label: 'Surat Pemesanan Kendaraan (SPK)', requiredOnCreate: true, requiredOnSubmit: true },
    { key: 'bukti_bayar', label: 'Bukti Bayar Tanda Jadi', requiredOnCreate: true, requiredOnSubmit: true },
    { key: 'kk', label: 'Kartu Keluarga (KK)', requiredOnCreate: false, requiredOnSubmit: true },
];

const showSpouseFields = computed(() => form.marital_status === 'Menikah');

watch(
    () => form.marital_status,
    (next) => {
        if (next !== 'Menikah') {
            form.spouse_name = '';
            form.spouse_nik = '';
            delete fieldErrors.value.spouse_name;
            delete fieldErrors.value.spouse_nik;
        }
    },
);

const simulation = computed(() => calculateLoan({
    vehiclePrice: Number(form.vehicle_price),
    downPayment: Number(form.down_payment),
    insuranceType: form.insurance_type,
    tenorMonths: Number(form.tenor_months),
}));

/**
 * @param {string} docType
 * @returns {ApplicationDocument | undefined}
 */
function findExistingDoc(docType) {
    return existingDocs.value.find((doc) => doc.document_type === docType);
}

/**
 * @param {string} key
 * @param {Event} event
 */
function handleFileChange(key, event) {
    const input = /** @type {HTMLInputElement} */ (event.target);
    const file = input.files?.[0] ?? null;

    delete fieldErrors.value[key];

    if (exceedsUploadLimit(file)) {
        fieldErrors.value[key] = 'Ukuran file melebihi batas maksimal 2 MB.';
        input.value = '';
        selectedFiles[key] = null;
        return;
    }

    selectedFiles[key] = file;
}

/**
 * Cek cepat di browser sebelum request. Aturan final tetap di Form Request backend.
 *
 * @param {boolean} requireSubmissionComplete
 * @returns {boolean}
 */
function validateClient(requireSubmissionComplete) {
    /** @type {Record<string, string>} */
    const errors = {};

    if (!form.customer_name.trim()) errors.customer_name = 'Nama lengkap konsumen wajib diisi.';
    if (!/^\d{16}$/.test(form.customer_nik)) errors.customer_nik = 'NIK konsumen harus terdiri dari tepat 16 digit angka.';
    if (!form.customer_dob) errors.customer_dob = 'Tanggal lahir wajib diisi.';

    if (showSpouseFields.value) {
        if (!form.spouse_name.trim()) errors.spouse_name = 'Nama pasangan wajib diisi jika status Menikah.';
        if (!/^\d{16}$/.test(form.spouse_nik)) errors.spouse_nik = 'NIK pasangan harus terdiri dari tepat 16 digit angka.';
    }

    if (!form.dealer_name.trim()) errors.dealer_name = 'Nama dealer wajib diisi.';
    if (!form.vehicle_brand.trim()) errors.vehicle_brand = 'Merk kendaraan wajib diisi.';
    if (!form.vehicle_model.trim()) errors.vehicle_model = 'Model kendaraan wajib diisi.';
    if (!form.vehicle_type.trim()) errors.vehicle_type = 'Tipe kendaraan wajib diisi.';
    if (!form.vehicle_color.trim()) errors.vehicle_color = 'Warna kendaraan wajib diisi.';

    const otr = Number(form.vehicle_price);
    const dp = form.down_payment === '' ? null : Number(form.down_payment);
    if (!Number.isFinite(otr) || otr < 1_000_000) {
        errors.vehicle_price = 'Harga kendaraan minimal Rp 1.000.000.';
    }

    if (requireSubmissionComplete || isMarketing.value || dp !== null) {
        if (dp === null || !Number.isFinite(dp) || dp < 0) {
            errors.down_payment = 'Down payment (DP) wajib diisi dengan angka valid.';
        } else if (Number.isFinite(otr) && dp >= otr) {
            errors.down_payment = 'Down payment (DP) harus lebih kecil dari harga kendaraan (OTR).';
        }
    }

    if (!isEditMode.value) {
        for (const docField of documentFields) {
            if (docField.requiredOnCreate && !selectedFiles[docField.key]) {
                errors[docField.key] = `Dokumen ${docField.label} wajib diunggah.`;
            }
        }
    }

    if (requireSubmissionComplete) {
        for (const docField of documentFields) {
            if (docField.requiredOnSubmit && !selectedFiles[docField.key] && !findExistingDoc(docField.key)) {
                errors[docField.key] = `Dokumen ${docField.label} wajib dilengkapi sebelum dikirim.`;
            }
        }
    }

    fieldErrors.value = errors;
    return Object.keys(errors).length === 0;
}

/**
 * @returns {FormData}
 */
function buildFormData() {
    const fd = new FormData();

    // Upload file butuh multipart, dan PHP tidak membaca multipart pada request PUT,
    // jadi edit dikirim sebagai POST dengan method spoofing Laravel.
    if (isEditMode.value) {
        fd.append('_method', 'PUT');
    }

    fd.append('customer_name', form.customer_name.trim());
    fd.append('customer_nik', form.customer_nik.trim());
    fd.append('customer_dob', form.customer_dob);
    fd.append('marital_status', form.marital_status);

    if (showSpouseFields.value) {
        fd.append('spouse_name', form.spouse_name.trim());
        fd.append('spouse_nik', form.spouse_nik.trim());
    }

    fd.append('dealer_name', form.dealer_name.trim());
    fd.append('vehicle_brand', form.vehicle_brand.trim());
    fd.append('vehicle_model', form.vehicle_model.trim());
    fd.append('vehicle_type', form.vehicle_type.trim());
    fd.append('vehicle_color', form.vehicle_color.trim());
    fd.append('vehicle_price', String(Math.round(Number(form.vehicle_price))));

    // Sales Dealer boleh mengosongkan data pinjaman; Marketing wajib mengisinya.
    if (form.down_payment !== '' || isMarketing.value) {
        fd.append('insurance_type', form.insurance_type);
        fd.append('down_payment', String(Math.round(Number(form.down_payment || 0))));
        fd.append('tenor_months', String(form.tenor_months));
    }

    for (const docField of documentFields) {
        const file = selectedFiles[docField.key];
        if (file) {
            fd.append(docField.key, file);
        }
    }

    return fd;
}

/**
 * @param {boolean} submitAfterSave
 */
async function handleSave(submitAfterSave = false) {
    errorBanner.value = '';
    if (!validateClient(submitAfterSave)) {
        errorBanner.value = 'Periksa kembali kolom yang masih belum valid.';
        return;
    }

    saving.value = true;
    try {
        const payload = buildFormData();
        let savedId = route.params.id;

        if (isEditMode.value) {
            await api.post(`/applications/${savedId}`, payload, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
        } else {
            const createRes = await api.post('/applications', payload, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            savedId = createRes.data?.data?.id;
        }

        if (submitAfterSave && savedId) {
            await api.post(`/applications/${savedId}/submit`);
        }

        await router.push(`/applications/${savedId}`);
    } catch (err) {
        const parsed = parseApiError(err, 'Gagal menyimpan data pengajuan.');
        errorBanner.value = parsed.message;
        fieldErrors.value = { ...fieldErrors.value, ...parsed.fieldErrors };
    } finally {
        saving.value = false;
    }
}

async function loadExisting() {
    if (!isEditMode.value) {
        return;
    }

    loading.value = true;
    try {
        const res = await api.get(`/applications/${route.params.id}`);
        const app = res.data?.data;
        if (!app) {
            return;
        }

        existingNumber.value = app.application_number;
        form.customer_name = app.customer_name ?? '';
        form.customer_nik = app.customer_nik ?? '';
        form.customer_dob = app.customer_dob ?? '';
        form.marital_status = app.marital_status ?? 'Belum Menikah';
        form.spouse_name = app.spouse_name ?? '';
        form.spouse_nik = app.spouse_nik ?? '';
        form.dealer_name = app.dealer_name ?? '';
        form.vehicle_brand = app.vehicle_brand ?? '';
        form.vehicle_model = app.vehicle_model ?? '';
        form.vehicle_type = app.vehicle_type ?? '';
        form.vehicle_color = app.vehicle_color ?? '';
        form.vehicle_price = app.vehicle_price ? String(app.vehicle_price) : '';
        form.insurance_type = app.insurance_type ?? 'All Risk';
        form.down_payment = app.down_payment !== null ? String(app.down_payment) : '';
        form.tenor_months = app.tenor_months ? String(app.tenor_months) : '36';
        existingDocs.value = app.documents ?? [];
    } catch (err) {
        errorBanner.value = parseApiError(err, 'Gagal memuat data pengajuan.').message;
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadExisting();
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <RouterLink
                    :to="isEditMode ? `/applications/${route.params.id}` : '/'"
                    class="text-xs font-medium text-[#1E3A8A] hover:underline"
                >
                    &larr; {{ isEditMode ? 'Kembali ke Detail Pengajuan' : 'Kembali ke Dashboard' }}
                </RouterLink>
                <h1 class="mt-1 text-xl font-semibold text-slate-900">
                    {{ isEditMode ? `Lengkapi / Revisi Pengajuan ${existingNumber}` : 'Input Pengajuan Kredit Baru' }}
                </h1>
                <p class="mt-0.5 text-sm text-slate-600">
                    {{ isEditMode
                        ? 'Perbarui data konsumen, skema kredit, dan kelengkapan Kartu Keluarga sebelum dikirim ke Atasan Marketing.'
                        : 'Input data awal deal pembelian kendaraan dan unggah dokumen KTP, SPK, serta Bukti Bayar Tanda Jadi.' }}
                </p>
            </div>
        </div>

        <div
            v-if="errorBanner"
            class="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            role="alert"
        >
            {{ errorBanner }}
        </div>

        <div v-if="loading" class="rounded border border-[#E2E8F0] bg-white p-8 text-center text-sm text-slate-500">
            Memuat formulir pengajuan...
        </div>

        <form v-else class="space-y-6" @submit.prevent="handleSave(false)">
            <section class="rounded border border-[#E2E8F0] bg-white p-5">
                <h2 class="border-b border-[#E2E8F0] pb-3 text-base font-semibold text-slate-900">
                    1. Data Konsumen &amp; Pasangan
                </h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="customer_name" class="block text-sm font-medium text-slate-700">
                            Nama Lengkap Konsumen <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="customer_name"
                            v-model="form.customer_name"
                            type="text"
                            placeholder="Sesuai KTP"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-[#1E3A8A] focus:outline-none"
                        >
                        <p v-if="fieldErrors.customer_name" class="mt-1 text-xs text-red-600">
                            {{ fieldErrors.customer_name }}
                        </p>
                    </div>

                    <div>
                        <label for="customer_nik" class="block text-sm font-medium text-slate-700">
                            NIK (16 Digit) <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="customer_nik"
                            v-model="form.customer_nik"
                            type="text"
                            inputmode="numeric"
                            maxlength="16"
                            placeholder="3174xxxxxxxxxxxx"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm tabular-nums focus:border-[#1E3A8A] focus:outline-none"
                        >
                        <p v-if="fieldErrors.customer_nik" class="mt-1 text-xs text-red-600">
                            {{ fieldErrors.customer_nik }}
                        </p>
                    </div>

                    <div>
                        <label for="customer_dob" class="block text-sm font-medium text-slate-700">
                            Tanggal Lahir <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="customer_dob"
                            v-model="form.customer_dob"
                            type="date"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-[#1E3A8A] focus:outline-none"
                        >
                        <p v-if="fieldErrors.customer_dob" class="mt-1 text-xs text-red-600">
                            {{ fieldErrors.customer_dob }}
                        </p>
                    </div>

                    <div>
                        <label for="marital_status" class="block text-sm font-medium text-slate-700">
                            Status Perkawinan <span class="text-red-600">*</span>
                        </label>
                        <select
                            id="marital_status"
                            v-model="form.marital_status"
                            class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#1E3A8A] focus:outline-none"
                        >
                            <option value="Belum Menikah">Belum Menikah</option>
                            <option value="Menikah">Menikah</option>
                            <option value="Cerai">Cerai</option>
                        </select>
                        <p v-if="fieldErrors.marital_status" class="mt-1 text-xs text-red-600">
                            {{ fieldErrors.marital_status }}
                        </p>
                    </div>

                    <template v-if="showSpouseFields">
                        <div>
                            <label for="spouse_name" class="block text-sm font-medium text-slate-700">
                                Nama Lengkap Pasangan <span class="text-red-600">*</span>
                            </label>
                            <input
                                id="spouse_name"
                                v-model="form.spouse_name"
                                type="text"
                                placeholder="Sesuai KTP / Kartu Keluarga"
                                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-[#1E3A8A] focus:outline-none"
                            >
                            <p v-if="fieldErrors.spouse_name" class="mt-1 text-xs text-red-600">
                                {{ fieldErrors.spouse_name }}
                            </p>
                        </div>

                        <div>
                            <label for="spouse_nik" class="block text-sm font-medium text-slate-700">
                                NIK Pasangan (16 Digit) <span class="text-red-600">*</span>
                            </label>
                            <input
                                id="spouse_nik"
                                v-model="form.spouse_nik"
                                type="text"
                                inputmode="numeric"
                                maxlength="16"
                                placeholder="3174xxxxxxxxxxxx"
                                class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm tabular-nums focus:border-[#1E3A8A] focus:outline-none"
                            >
                            <p v-if="fieldErrors.spouse_nik" class="mt-1 text-xs text-red-600">
                                {{ fieldErrors.spouse_nik }}
                            </p>
                        </div>
                    </template>
                </div>
            </section>

            <section class="rounded border border-[#E2E8F0] bg-white p-5">
                <h2 class="border-b border-[#E2E8F0] pb-3 text-base font-semibold text-slate-900">
                    2. Data Kendaraan &amp; Dealer
                </h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="dealer_name" class="block text-sm font-medium text-slate-700">
                            Nama Dealer <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="dealer_name"
                            v-model="form.dealer_name"
                            type="text"
                            placeholder="Contoh: Dealer Nusantara Motor Jakarta"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-[#1E3A8A] focus:outline-none"
                        >
                        <p v-if="fieldErrors.dealer_name" class="mt-1 text-xs text-red-600">
                            {{ fieldErrors.dealer_name }}
                        </p>
                    </div>

                    <div>
                        <label for="vehicle_brand" class="block text-sm font-medium text-slate-700">
                            Merk Kendaraan <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="vehicle_brand"
                            v-model="form.vehicle_brand"
                            type="text"
                            placeholder="Contoh: Toyota / Honda / Yamaha"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-[#1E3A8A] focus:outline-none"
                        >
                        <p v-if="fieldErrors.vehicle_brand" class="mt-1 text-xs text-red-600">
                            {{ fieldErrors.vehicle_brand }}
                        </p>
                    </div>

                    <div>
                        <label for="vehicle_model" class="block text-sm font-medium text-slate-700">
                            Model Kendaraan <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="vehicle_model"
                            v-model="form.vehicle_model"
                            type="text"
                            placeholder="Contoh: Avanza / BR-V / NMAX"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-[#1E3A8A] focus:outline-none"
                        >
                        <p v-if="fieldErrors.vehicle_model" class="mt-1 text-xs text-red-600">
                            {{ fieldErrors.vehicle_model }}
                        </p>
                    </div>

                    <div>
                        <label for="vehicle_type" class="block text-sm font-medium text-slate-700">
                            Tipe / Varian <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="vehicle_type"
                            v-model="form.vehicle_type"
                            type="text"
                            placeholder="Contoh: 1.5 G CVT / Prestige"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-[#1E3A8A] focus:outline-none"
                        >
                        <p v-if="fieldErrors.vehicle_type" class="mt-1 text-xs text-red-600">
                            {{ fieldErrors.vehicle_type }}
                        </p>
                    </div>

                    <div>
                        <label for="vehicle_color" class="block text-sm font-medium text-slate-700">
                            Warna Kendaraan <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="vehicle_color"
                            v-model="form.vehicle_color"
                            type="text"
                            placeholder="Contoh: Putih Mutiara"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-[#1E3A8A] focus:outline-none"
                        >
                        <p v-if="fieldErrors.vehicle_color" class="mt-1 text-xs text-red-600">
                            {{ fieldErrors.vehicle_color }}
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="vehicle_price" class="block text-sm font-medium text-slate-700">
                            Harga Kendaraan / OTR (Rp) <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="vehicle_price"
                            v-model="form.vehicle_price"
                            type="number"
                            min="1000000"
                            step="100000"
                            placeholder="250000000"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm tabular-nums focus:border-[#1E3A8A] focus:outline-none"
                        >
                        <p class="mt-1 text-xs text-slate-500 tabular-nums">
                            Terbaca: {{ form.vehicle_price ? formatRupiah(form.vehicle_price) : '-' }}
                        </p>
                        <p v-if="fieldErrors.vehicle_price" class="mt-1 text-xs text-red-600">
                            {{ fieldErrors.vehicle_price }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="rounded border border-[#E2E8F0] bg-white p-5">
                <h2 class="border-b border-[#E2E8F0] pb-3 text-base font-semibold text-slate-900">
                    3. Data Pinjaman &amp; Simulasi Angsuran
                </h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="insurance_type" class="block text-sm font-medium text-slate-700">
                            Jenis Asuransi
                        </label>
                        <select
                            id="insurance_type"
                            v-model="form.insurance_type"
                            class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#1E3A8A] focus:outline-none"
                        >
                            <option
                                v-for="(info, name) in INSURANCE_OPTIONS"
                                :key="name"
                                :value="name"
                            >
                                {{ name }} ({{ info.percentLabel }})
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ INSURANCE_OPTIONS[form.insurance_type]?.description }}
                        </p>
                        <p v-if="fieldErrors.insurance_type" class="mt-1 text-xs text-red-600">
                            {{ fieldErrors.insurance_type }}
                        </p>
                    </div>

                    <div>
                        <label for="down_payment" class="block text-sm font-medium text-slate-700">
                            Down Payment / DP (Rp)
                        </label>
                        <input
                            id="down_payment"
                            v-model="form.down_payment"
                            type="number"
                            min="0"
                            step="100000"
                            placeholder="50000000"
                            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm tabular-nums focus:border-[#1E3A8A] focus:outline-none"
                        >
                        <p class="mt-1 text-xs text-slate-500 tabular-nums">
                            Terbaca: {{ form.down_payment !== '' ? formatRupiah(form.down_payment) : '-' }}
                        </p>
                        <p v-if="fieldErrors.down_payment" class="mt-1 text-xs text-red-600">
                            {{ fieldErrors.down_payment }}
                        </p>
                    </div>

                    <div>
                        <label for="tenor_months" class="block text-sm font-medium text-slate-700">
                            Lama Kredit (Tenor)
                        </label>
                        <select
                            id="tenor_months"
                            v-model="form.tenor_months"
                            class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#1E3A8A] focus:outline-none"
                        >
                            <option
                                v-for="tenor in TENOR_OPTIONS"
                                :key="tenor"
                                :value="String(tenor)"
                            >
                                {{ tenor }} Bulan ({{ tenor / 12 }} Tahun)
                            </option>
                        </select>
                        <p v-if="fieldErrors.tenor_months" class="mt-1 text-xs text-red-600">
                            {{ fieldErrors.tenor_months }}
                        </p>
                    </div>
                </div>

                <div class="mt-5 rounded border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                    <div v-if="simulation" class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                        <div>
                            <p class="text-xs text-slate-500">Pokok Hutang Unit</p>
                            <p class="mt-0.5 font-medium text-slate-900 tabular-nums">
                                {{ formatRupiah(simulation.principal_vehicle) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Premi Asuransi ({{ simulation.insurance_rate_percent }}%/thn)</p>
                            <p class="mt-0.5 font-medium text-slate-900 tabular-nums">
                                {{ formatRupiah(simulation.insurance_premium) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Total Pokok + Bunga ({{ simulation.annual_interest_rate }}% flat/thn)</p>
                            <p class="mt-0.5 font-medium text-slate-900 tabular-nums">
                                {{ formatRupiah(simulation.total_loan) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Estimasi Angsuran / Bulan</p>
                            <p class="mt-0.5 text-base font-semibold text-[#1E3A8A] tabular-nums">
                                {{ formatRupiah(simulation.monthly_installment) }}
                            </p>
                        </div>
                    </div>
                    <p v-else class="text-xs text-slate-500">
                        Isi Harga Kendaraan (OTR), Down Payment (&lt; OTR), Jenis Asuransi, dan Tenor untuk melihat estimasi angsuran bulanan otomatis.
                    </p>
                </div>
            </section>

            <section class="rounded border border-[#E2E8F0] bg-white p-5">
                <h2 class="border-b border-[#E2E8F0] pb-3 text-base font-semibold text-slate-900">
                    4. Unggah Dokumen Digital (JPG, PNG, PDF — Maks. 2 MB per file)
                </h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div
                        v-for="doc in documentFields"
                        :key="doc.key"
                        class="rounded border border-[#E2E8F0] p-3.5"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <label :for="`doc_${doc.key}`" class="block text-sm font-medium text-slate-800">
                                {{ doc.label }}
                                <span
                                    v-if="(!isEditMode && doc.requiredOnCreate) || (isEditMode && doc.requiredOnSubmit)"
                                    class="text-red-600"
                                >*</span>
                            </label>
                            <a
                                v-if="findExistingDoc(doc.key)"
                                :href="findExistingDoc(doc.key).file_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-xs font-medium text-[#1E3A8A] hover:underline"
                            >
                                Lihat File Terunggah
                            </a>
                        </div>

                        <p v-if="findExistingDoc(doc.key)" class="mt-1 truncate text-xs text-emerald-700">
                            File aktif: {{ findExistingDoc(doc.key).original_name }}
                        </p>

                        <input
                            :id="`doc_${doc.key}`"
                            type="file"
                            accept=".jpg,.jpeg,.png,.pdf"
                            class="mt-2 block w-full text-xs text-slate-600 file:mr-3 file:rounded file:border file:border-slate-300 file:bg-slate-50 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-100"
                            @change="handleFileChange(doc.key, $event)"
                        >

                        <p v-if="fieldErrors[doc.key]" class="mt-1.5 text-xs text-red-600">
                            {{ fieldErrors[doc.key] }}
                        </p>
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <RouterLink
                    :to="isEditMode ? `/applications/${route.params.id}` : '/'"
                    class="rounded border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Batal
                </RouterLink>

                <button
                    type="submit"
                    :disabled="saving"
                    class="rounded border border-[#1E3A8A] bg-white px-4 py-2 text-sm font-medium text-[#1E3A8A] hover:bg-slate-50 disabled:opacity-60"
                    :class="!isEditMode || !isMarketing ? '!bg-[#1E3A8A] !text-white hover:!bg-[#1E40AF]' : ''"
                >
                    {{ saving ? 'Menyimpan...' : (isEditMode ? 'Simpan Perubahan' : 'Simpan sebagai Draft Dealer') }}
                </button>

                <button
                    v-if="isEditMode && isMarketing"
                    type="button"
                    :disabled="saving"
                    class="rounded bg-[#1E3A8A] px-4 py-2 text-sm font-medium text-white hover:bg-[#1E40AF] disabled:opacity-60"
                    @click="handleSave(true)"
                >
                    {{ saving ? 'Memproses...' : 'Simpan & Kirim ke Atasan Marketing' }}
                </button>
            </div>
        </form>
    </div>
</template>

