<template>
    <AppLayout title="Import Invoices">
        <div class="max-w-5xl mx-auto space-y-6">

            <!-- Upload Card -->
            <AppCard title="Upload Invoice File" description="Import purchase invoices from CSV/Excel or Tally XML. Duplicate invoices are automatically skipped.">
                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Source type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Import Source</label>
                        <div class="flex gap-3">
                            <label
                                v-for="src in sources"
                                :key="src.value"
                                :class="[
                                    'flex items-center gap-2 px-4 py-2.5 rounded-lg border cursor-pointer transition-colors text-sm font-medium',
                                    form.source === src.value
                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                        : 'border-gray-200 text-gray-600 hover:border-gray-300'
                                ]"
                            >
                                <input
                                    type="radio"
                                    :value="src.value"
                                    v-model="form.source"
                                    class="sr-only"
                                />
                                <component :is="src.icon" class="w-4 h-4" />
                                {{ src.label }}
                            </label>
                        </div>
                    </div>

                    <!-- File drop zone -->
                    <div
                        :class="[
                            'relative border-2 border-dashed rounded-xl p-8 text-center transition-colors',
                            dragOver ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'
                        ]"
                        @dragover.prevent="dragOver = true"
                        @dragleave="dragOver = false"
                        @drop.prevent="handleDrop"
                    >
                        <input
                            ref="fileInput"
                            type="file"
                            :accept="acceptedTypes"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                            @change="handleFileChange"
                        />
                        <ArrowUpTrayIcon class="w-10 h-10 text-gray-300 mx-auto mb-3" />
                        <p class="text-sm font-medium text-gray-700">
                            {{ selectedFile ? selectedFile.name : 'Drop your file here, or click to browse' }}
                        </p>
                        <p v-if="!selectedFile" class="text-xs text-gray-400 mt-1">
                            {{ form.source === 'csv' ? 'CSV or Excel (.xlsx, .xls)' : 'Tally XML (.xml)' }}
                            — max 10MB
                        </p>
                        <p v-else class="text-xs text-gray-500 mt-1">
                            {{ formatFileSize(selectedFile.size) }}
                        </p>
                    </div>

                    <p v-if="errors.file" class="text-sm text-red-600">{{ errors.file }}</p>

                    <!-- Actions row -->
                    <div class="flex items-center justify-between">
                        <div class="flex gap-3">
                            <a
                                :href="route('import.sample', 'csv')"
                                class="text-sm text-indigo-600 hover:text-indigo-800 flex items-center gap-1"
                            >
                                <ArrowDownTrayIcon class="w-4 h-4" />
                                Sample CSV
                            </a>
                            <a
                                :href="route('import.sample', 'tally_xml')"
                                class="text-sm text-indigo-600 hover:text-indigo-800 flex items-center gap-1"
                            >
                                <ArrowDownTrayIcon class="w-4 h-4" />
                                Sample Tally XML
                            </a>
                        </div>

                        <AppButton
                            type="submit"
                            :loading="submitting"
                            :disabled="!selectedFile || submitting"
                        >
                            <ArrowUpTrayIcon class="w-4 h-4" />
                            Import
                        </AppButton>
                    </div>
                </form>
            </AppCard>

            <!-- Recent batches -->
            <AppCard title="Recent Imports">
                <div v-if="batches.data.length === 0" class="py-10 text-center text-sm text-gray-400">
                    No imports yet. Upload your first file above.
                </div>
                <div v-else class="overflow-x-auto -mx-6">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left font-medium text-gray-500 px-6 py-3">File</th>
                                <th class="text-left font-medium text-gray-500 px-6 py-3">Source</th>
                                <th class="text-right font-medium text-gray-500 px-6 py-3">Rows</th>
                                <th class="text-right font-medium text-gray-500 px-6 py-3">Imported</th>
                                <th class="text-right font-medium text-gray-500 px-6 py-3">Failed</th>
                                <th class="text-left font-medium text-gray-500 px-6 py-3">Status</th>
                                <th class="text-left font-medium text-gray-500 px-6 py-3">Date</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="batch in batches.data" :key="batch.id" class="hover:bg-gray-50">
                                <td class="px-6 py-3 font-medium text-gray-900 max-w-[200px] truncate">
                                    {{ batch.original_filename }}
                                </td>
                                <td class="px-6 py-3 text-gray-500">{{ batch.source_label }}</td>
                                <td class="px-6 py-3 text-right text-gray-600">{{ batch.total_rows }}</td>
                                <td class="px-6 py-3 text-right text-green-600 font-medium">{{ batch.processed_rows }}</td>
                                <td class="px-6 py-3 text-right">
                                    <span :class="batch.failed_rows > 0 ? 'text-red-600 font-medium' : 'text-gray-400'">
                                        {{ batch.failed_rows }}
                                    </span>
                                </td>
                                <td class="px-6 py-3">
                                    <AppBadge :color="statusColor(batch.status)">{{ batch.status_label }}</AppBadge>
                                </td>
                                <td class="px-6 py-3 text-gray-400 text-xs">{{ batch.created_at }}</td>
                                <td class="px-6 py-3">
                                    <Link :href="route('import.show', batch.id)" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                                        View
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ArrowUpTrayIcon, ArrowDownTrayIcon, TableCellsIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppCard from '@/Components/AppCard.vue';
import AppButton from '@/Components/AppButton.vue';
import AppBadge from '@/Components/AppBadge.vue';

const props = defineProps({
    batches: { type: Object, required: true },
    errors:  { type: Object, default: () => ({}) },
});

const sources = [
    { value: 'csv',       label: 'CSV / Excel',  icon: TableCellsIcon },
    { value: 'tally_xml', label: 'Tally XML',    icon: DocumentTextIcon },
];

const form        = ref({ source: 'csv' });
const selectedFile = ref(null);
const fileInput   = ref(null);
const dragOver    = ref(false);
const submitting  = ref(false);

const acceptedTypes = computed(() =>
    form.value.source === 'csv'
        ? '.csv,.xlsx,.xls'
        : '.xml'
);

function handleFileChange(e) {
    selectedFile.value = e.target.files[0] ?? null;
}

function handleDrop(e) {
    dragOver.value = false;
    selectedFile.value = e.dataTransfer.files[0] ?? null;
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function submit() {
    if (!selectedFile.value) return;
    submitting.value = true;

    const data = new FormData();
    data.append('file', selectedFile.value);
    data.append('source', form.value.source);

    router.post(route('import.store'), data, {
        onFinish: () => { submitting.value = false; },
        forceFormData: true,
    });
}

function statusColor(status) {
    return { pending: 'warning', processing: 'info', completed: 'success', failed: 'danger' }[status] ?? 'default';
}

function route(name, param) {
    const map = {
        'import.store': '/import',
        'import.show':  `/import/${param}`,
        'import.sample': `/import/sample/${param}`,
    };
    return map[name] ?? '/';
}
</script>
