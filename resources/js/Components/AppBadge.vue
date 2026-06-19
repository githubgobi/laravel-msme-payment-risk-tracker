<template>
    <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium', styles]">
        <span v-if="dot" class="w-1.5 h-1.5 rounded-full flex-shrink-0" :class="dotColor"></span>
        <slot />
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    color: { type: String, default: 'gray' },
    dot:   { type: Boolean, default: false },
});

const colorMap = {
    green:  { badge: 'bg-green-100 text-green-700',   dot: 'bg-green-500' },
    red:    { badge: 'bg-red-100 text-red-700',       dot: 'bg-red-500' },
    amber:  { badge: 'bg-amber-100 text-amber-700',   dot: 'bg-amber-500' },
    blue:   { badge: 'bg-blue-100 text-blue-700',     dot: 'bg-blue-500' },
    indigo: { badge: 'bg-indigo-100 text-indigo-700', dot: 'bg-indigo-500' },
    gray:   { badge: 'bg-gray-100 text-gray-600',     dot: 'bg-gray-400' },
};

const resolved = computed(() => colorMap[props.color] ?? colorMap.gray);
const styles   = computed(() => resolved.value.badge);
const dotColor = computed(() => resolved.value.dot);
</script>
