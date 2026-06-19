<template>
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between">
            <div class="min-w-0">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ label }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 truncate">{{ value }}</p>
                <p v-if="sub" class="mt-1 text-xs text-gray-500">{{ sub }}</p>
            </div>
            <div :class="['flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center', iconBg]">
                <component :is="icon" class="w-5 h-5" :class="iconColor" />
            </div>
        </div>
        <div v-if="trend !== null" class="mt-3 flex items-center gap-1">
            <ArrowTrendingUpIcon v-if="trend >= 0" class="w-4 h-4 text-red-500" />
            <ArrowTrendingDownIcon v-else class="w-4 h-4 text-green-500" />
            <span :class="['text-xs font-medium', trend >= 0 ? 'text-red-600' : 'text-green-600']">
                {{ Math.abs(trend) }}% vs last month
            </span>
        </div>
    </div>
</template>

<script setup>
import { ArrowTrendingUpIcon, ArrowTrendingDownIcon } from '@heroicons/vue/24/outline';

defineProps({
    label:     { type: String, required: true },
    value:     { type: [String, Number], required: true },
    sub:       { type: String, default: null },
    icon:      { type: Object, required: true },
    iconBg:    { type: String, default: 'bg-indigo-50' },
    iconColor: { type: String, default: 'text-indigo-600' },
    trend:     { type: Number, default: null },
});
</script>
