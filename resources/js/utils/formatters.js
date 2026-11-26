/**
 * Contoh: 250000000 -> "Rp 250.000.000". Nilai kosong/tidak valid jadi "-".
 *
 * @param {number | string | null | undefined} amount
 * @returns {string}
 */
export function formatRupiah(amount) {
    if (amount === null || amount === undefined || amount === '') {
        return '-';
    }

    const numeric = Number(amount);
    if (!Number.isFinite(numeric)) {
        return '-';
    }

    return `Rp ${Math.round(numeric).toLocaleString('id-ID')}`;
}

const dateFormat = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
const dateTimeFormat = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

/**
 * @param {string | null | undefined} value
 * @param {Intl.DateTimeFormat} formatter
 */
function formatWith(value, formatter) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : formatter.format(date);
}

/**
 * Contoh: "14 Mei 1992".
 *
 * @param {string | null | undefined} value
 */
export function formatDate(value) {
    return formatWith(value, dateFormat);
}

/**
 * Contoh: "14 Mei 2026, 09.30" (zona waktu browser).
 *
 * @param {string | null | undefined} value
 */
export function formatDateTime(value) {
    return formatWith(value, dateTimeFormat);
}

const STATUS_BADGE_CLASSES = {
    DRAFT_DEALER: 'bg-slate-100 text-slate-700 border-slate-300',
    SUBMITTED: 'bg-amber-100 text-amber-800 border-amber-300',
    REJECTED: 'bg-red-100 text-red-800 border-red-300',
    APPROVED: 'bg-emerald-100 text-emerald-800 border-emerald-300',
    SIGNED: 'bg-sky-100 text-sky-800 border-sky-300',
    DISBURSED: 'bg-emerald-100 text-emerald-900 border-emerald-400',
};

/**
 * @param {string} status
 */
export function statusBadgeClass(status) {
    return STATUS_BADGE_CLASSES[status] ?? STATUS_BADGE_CLASSES.DRAFT_DEALER;
}

// Kode aksi dari application_logs.action (lihat CreditApplicationService).
const ACTION_LABELS = {
    CREATE_DRAFT: 'Input Pengajuan Awal',
    UPDATE_DATA: 'Pembaruan Data & Dokumen',
    SUBMIT: 'Kirim Pengajuan ke Atasan',
    RESUBMIT: 'Kirim Ulang Setelah Revisi',
    APPROVE: 'Persetujuan Pengajuan',
    REJECT: 'Penolakan Pengajuan',
    SEND_PO: 'Pengiriman PO ke Dealer',
    SIGN_CONTRACT: 'Tanda Tangan Kontrak Digital',
    DISBURSE: 'Pencairan Dana ke Dealer',
};

/**
 * @param {string} action
 */
export function actionLabel(action) {
    return ACTION_LABELS[action] ?? action;
}

