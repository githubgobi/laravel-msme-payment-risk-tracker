<template>
    <Link
        :href="item.href"
        :class="[
            'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors group relative',
            isActive
                ? 'bg-indigo-600 text-white'
                : 'text-gray-400 hover:text-white hover:bg-gray-800'
        ]"
    >
        <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />

        <span v-if="!collapsed" class="truncate">{{ item.label }}</span>

        <!-- Tooltip when collapsed -->
        <span
            v-if="collapsed"
            class="absolute left-full ml-2 px-2 py-1 bg-gray-800 text-white text-xs rounded whitespace-nowrap
                   opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50"
        >
            {{ item.label }}
        </span>
    </Link>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    item: { type: Object, required: true },
    collapsed: { type: Boolean, default: false },
});

const page = usePage();
const isActive = computed(() => page.url.startsWith(props.item.href));
</script>
