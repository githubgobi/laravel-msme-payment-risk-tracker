<template>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div v-if="title || $slots.actions" class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">{{ title }}</h3>
                <p v-if="description" class="text-xs text-gray-500 mt-0.5">{{ description }}</p>
            </div>
            <div v-if="$slots.actions">
                <slot name="actions" />
            </div>
        </div>
        <div :class="['p-5', bodyClass]">
            <slot />
        </div>
    </div>
</template>

<script setup>
defineProps({
    title:       { type: String, default: null },
    description: { type: String, default: null },
    bodyClass:   { type: String, default: '' },
});
</script>
