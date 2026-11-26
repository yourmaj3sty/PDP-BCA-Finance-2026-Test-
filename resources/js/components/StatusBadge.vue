<script setup>
import { computed } from 'vue';
import { statusBadgeClass } from '../utils/formatters.js';

const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, required: true },
});

const badgeClass = computed(() => statusBadgeClass(props.status));
</script>

<template>
    <span
        class="inline-flex items-center rounded border px-2.5 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="badgeClass"
    >
        {{ props.label }}
    </span>
</template>

