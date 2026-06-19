<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="visible" :class="['flex items-start gap-3 p-4 rounded-lg mb-4 border', styles.wrapper]">
            <component :is="styles.icon" class="w-5 h-5 flex-shrink-0 mt-0.5" :class="styles.iconColor" />
            <p class="text-sm flex-1" :class="styles.text">{{ message }}</p>
            <button @click="visible = false" class="flex-shrink-0 opacity-60 hover:opacity-100 transition-opacity">
                <XMarkIcon class="w-4 h-4" :class="styles.text" />
            </button>
        </div>
    </Transition>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import {
    CheckCircleIcon,
    ExclamationCircleIcon,
    ExclamationTriangleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    type: { type: String, default: 'success' },
    message: { type: String, required: true },
    autoDismiss: { type: Number, default: 5000 },
});

const visible = ref(true);

const styleMap = {
    success: {
        wrapper: 'bg-green-50 border-green-200',
        icon: CheckCircleIcon,
        iconColor: 'text-green-500',
        text: 'text-green-800',
    },
    error: {
        wrapper: 'bg-red-50 border-red-200',
        icon: ExclamationCircleIcon,
        iconColor: 'text-red-500',
        text: 'text-red-800',
    },
    warning: {
        wrapper: 'bg-amber-50 border-amber-200',
        icon: ExclamationTriangleIcon,
        iconColor: 'text-amber-500',
        text: 'text-amber-800',
    },
};

const styles = styleMap[props.type] ?? styleMap.success;

onMounted(() => {
    if (props.autoDismiss > 0) {
        setTimeout(() => { visible.value = false; }, props.autoDismiss);
    }
});
</script>
