<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const stages = [
    { key: 'DRAFT_DEALER', title: 'Input Dealer', detail: 'Data & dokumen awal' },
    { key: 'SUBMITTED', title: 'Review Atasan', detail: 'Verifikasi Marketing' },
    { key: 'APPROVED', title: 'Disetujui & PO', detail: 'Kontrak siap cetak' },
    { key: 'SIGNED', title: 'Kontrak TTD', detail: 'Tanda tangan digital' },
    { key: 'DISBURSED', title: 'Dana Cair', detail: 'Selesai dicairkan' },
];

// REJECTED ditaruh di tahap review supaya langkah ke-2 tampil merah sebagai "Ditolak / Revisi".
const stageOrder = {
    DRAFT_DEALER: 0,
    REJECTED: 1,
    SUBMITTED: 1,
    APPROVED: 2,
    SIGNED: 3,
    DISBURSED: 4,
};

const activeIndex = computed(() => stageOrder[props.status] ?? 0);
</script>

<template>
    <div class="rounded border border-[#E2E8F0] bg-white p-4">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
            <div
                v-for="(stage, idx) in stages"
                :key="stage.key"
                class="flex items-start gap-2.5"
            >
                <span
                    class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-xs font-semibold tabular-nums"
                    :class="
                        props.status === 'REJECTED' && idx === 1
                            ? 'border-red-600 bg-red-600 text-white'
                            : idx < activeIndex || (props.status === 'DISBURSED' && idx === 4)
                                ? 'border-emerald-700 bg-emerald-700 text-white'
                                : idx === activeIndex
                                    ? 'border-[#1E3A8A] bg-[#1E3A8A] text-white'
                                    : 'border-slate-300 bg-white text-slate-500'
                    "
                >
                    {{ idx + 1 }}
                </span>

                <div class="min-w-0">
                    <p
                        class="text-xs font-semibold sm:text-sm"
                        :class="
                            props.status === 'REJECTED' && idx === 1
                                ? 'text-red-700'
                                : idx <= activeIndex
                                    ? 'text-slate-900'
                                    : 'text-slate-400'
                        "
                    >
                        {{ props.status === 'REJECTED' && idx === 1 ? 'Ditolak / Revisi' : stage.title }}
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ stage.detail }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

