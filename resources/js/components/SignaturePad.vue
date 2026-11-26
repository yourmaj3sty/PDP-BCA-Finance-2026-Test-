<script setup>
import { onMounted, ref } from 'vue';

const emit = defineEmits(['change']);

/** @type {import('vue').Ref<HTMLCanvasElement | null>} */
const canvasRef = ref(null);
const isDrawing = ref(false);
const hasStroke = ref(false);

function setupCanvas() {
    const canvas = canvasRef.value;
    if (!canvas) {
        return;
    }

    // Ukuran buffer dikali devicePixelRatio supaya goresan tidak buram di layar HiDPI.
    const rect = canvas.getBoundingClientRect();
    const ratio = window.devicePixelRatio || 1;
    const width = Math.max(rect.width || 480, 280);
    const height = 170;

    canvas.width = width * ratio;
    canvas.height = height * ratio;

    const ctx = canvas.getContext('2d');
    if (!ctx) {
        return;
    }

    ctx.scale(ratio, ratio);
    ctx.fillStyle = '#FFFFFF';
    ctx.fillRect(0, 0, width, height);
    ctx.strokeStyle = '#0F172A';
    ctx.lineWidth = 2.2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
}

onMounted(() => {
    setupCanvas();
});

/**
 * @param {PointerEvent} event
 * @returns {{ x: number, y: number }}
 */
function getPoint(event) {
    const canvas = canvasRef.value;
    const rect = canvas.getBoundingClientRect();
    return {
        x: event.clientX - rect.left,
        y: event.clientY - rect.top,
    };
}

/**
 * @param {PointerEvent} event
 */
function startDraw(event) {
    const canvas = canvasRef.value;
    const ctx = canvas?.getContext('2d');
    if (!canvas || !ctx) {
        return;
    }

    canvas.setPointerCapture(event.pointerId);
    const { x, y } = getPoint(event);
    isDrawing.value = true;
    ctx.beginPath();
    ctx.moveTo(x, y);
}

/**
 * @param {PointerEvent} event
 */
function draw(event) {
    if (!isDrawing.value) {
        return;
    }

    const ctx = canvasRef.value?.getContext('2d');
    if (!ctx) {
        return;
    }

    const { x, y } = getPoint(event);
    ctx.lineTo(x, y);
    ctx.stroke();

    if (!hasStroke.value) {
        hasStroke.value = true;
        emit('change', true);
    }
}

function endDraw() {
    isDrawing.value = false;
}

function clear() {
    hasStroke.value = false;
    emit('change', false);
    setupCanvas();
}

/**
 * PNG dari isi kanvas, atau null kalau belum ada goresan.
 *
 * @returns {Promise<Blob | null>}
 */
function exportPngBlob() {
    if (!hasStroke.value || !canvasRef.value) {
        return Promise.resolve(null);
    }

    return new Promise((resolve) => {
        canvasRef.value.toBlob((blob) => resolve(blob), 'image/png');
    });
}

defineExpose({
    clear,
    exportPngBlob,
    hasStroke,
});
</script>

<template>
    <div>
        <div class="overflow-hidden rounded border border-slate-300 bg-white">
            <canvas
                ref="canvasRef"
                class="block h-[170px] w-full touch-none cursor-crosshair"
                @pointerdown="startDraw"
                @pointermove="draw"
                @pointerup="endDraw"
                @pointercancel="endDraw"
            />
        </div>
        <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
            <span>Goreskan tanda tangan di dalam kotak di atas.</span>
            <button
                type="button"
                class="font-medium text-[#1E3A8A] hover:underline"
                @click="clear"
            >
                Bersihkan Kanvas
            </button>
        </div>
    </div>
</template>

