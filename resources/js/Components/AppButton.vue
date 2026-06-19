<template>
    <component
        :is="href ? Link : 'button'"
        :href="href"
        :type="href ? undefined : type"
        :disabled="disabled || loading"
        :class="[
            'inline-flex items-center gap-2 font-medium rounded-lg transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed',
            sizeClasses,
            variantClasses,
        ]"
    >
        <svg v-if="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <component v-if="icon && !loading" :is="icon" class="w-4 h-4" />
        <slot />
    </component>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href:     { type: String,  default: null },
    type:     { type: String,  default: 'button' },
    variant:  { type: String,  default: 'primary' },
    size:     { type: String,  default: 'md' },
    icon:     { type: Object,  default: null },
    loading:  { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const sizeClasses = computed(() => ({
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
    lg: 'px-5 py-2.5 text-base',
}[props.size]));

const variantClasses = computed(() => ({
    primary:   'bg-indigo-600 text-white hover:bg-indigo-700 focus:ring-indigo-500',
    secondary: 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 focus:ring-indigo-500',
    danger:    'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
    ghost:     'text-gray-600 hover:bg-gray-100 focus:ring-indigo-500',
}[props.variant]));
</script>
