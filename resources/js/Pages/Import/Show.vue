<template>
    <AppLayout :title="`Import #${batch.id}`">
        <div class="max-w-5xl mx-auto space-y-6">

            <!-- Batch header -->
            <div class="flex items-center justify-between">
                <div>
                    <Link :href="route('import.index')" class="text-sm text-indigo-600 hover:text-indigo-800 flex items-center gap-1 mb-2">
                        <ChevronLeftIcon class="w-4 h-4" />
                        Back to Imports
                    </Link>
                    <h1 class="text-xl font-bold text-gray-900">{{ batch.original_filename }}</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ batch.source_label }} &mdash; {{ batch.created_at }}</p>
                </div>
                <AppBadge :color="statusColor(batch.status)" class="text-sm">{{ batch.status_label }}</AppBadge>
            </div>

            <!-- Stats grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <StatCard
                    label="Total Rows"
                    :value="batch.total_rows"
                    icon="DocumentTextIcon"
                    color="gray"
                />
                <StatCard
                    label="Imported"
                    :value="batch.processed_rows"
                    icon="CheckCircleIcon"
                    color="green"
                />
                <StatCard
                    label="Skipped"
                    :value="batch.skipped_rows"
                    icon="MinusCircleIcon"
                    color="yellow"
                    sub-label="Duplicates"
                />
                <StatCard
                    label="Failed"
                    :value="batch.failed_rows"
                    icon="XCircleIcon"
                    :color="batch.failed_rows > 0 ? 'red' : 'gray'"
                />
            </div>

            <!-- Progress bar -->
            <div v-if="batch.status === 'processing'" class="bg-white rounded-xl border border-gray-100 p-4">
                <div class="flex justify-between text-sm text-gray-600 mb-2">
                    <span>Processing…</span>
                    <span>{{ batch.processed_rows + batch.skipped_rows + batch.failed_rows }} / {{ batch.total_rows }}</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2">
                    <div
                        class="bg-indigo-500 h-2 rounded-full transition-all duration-500"
                        :style="{ width: progressPct + '%' }"
                    />
                </div>
            </div>

            <!-- Timing info -->
            <div class="bg-white rounded-xl border border-gray-100 p-4 text-sm text-gray-600 grid grid-cols-2 gap-3">
                <div><span class="font-medium text-gray-700">Started:</span> {{ batch.started_at ?? '—' }}</div>
                <div><span class="font-medium text-gray-700">Completed:</span> {{ batch.completed_at ?? '—' }}</div>
                <div><span class="font-medium text-gray-700">Success rate:</span> {{ batch.success_rate }}%</div>
            </div>

            <!-- Error table -->
            <AppCard
                v-if="errors.length > 0"
                title="Rows with Issues"
                :description="`${errors.length} row(s) could not be imported or were skipped`"
            >
                <div class="overflow-x-auto -mx-6">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left font-medium text-gray-500 px-6 py-3 w-16">Row</th>
                                <th class="text-left font-medium text-gray-500 px-6 py-3">Invoice #</th>
                                <th class="text-left font-medium text-gray-500 px-6 py-3">Status</th>
                                <th class="text-left font-medium text-gray-500 px-6 py-3">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="(err, i) in errors" :key="i" class="hover:bg-gray-50">
                                <td class="px-6 py-2.5 text-gray-400 font-mono">{{ err.row }}</td>
                                <td class="px-6 py-2.5 font-medium text-gray-900">{{ err.invoice_number || '—' }}</td>
                                <td class="px-6 py-2.5">
                                    <AppBadge :color="err.status === 'skipped' ? 'warning' : 'danger'" dot>
                                        {{ err.status }}
                                    </AppBadge>
                                </td>
                                <td class="px-6 py-2.5 text-gray-600">{{ err.message }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <template #actions>
                    <AppButton variant="secondary" size="sm" @click="downloadErrors">
                        <ArrowDownTrayIcon class="w-4 h-4" />
                        Download Error Report
                    </AppButton>
                </template>
            </AppCard>

            <!-- All clean -->
            <div
                v-else-if="batch.status === 'completed'"
                class="bg-green-50 border border-green-100 rounded-xl p-6 text-center"
            >
                <CheckCircleIconSolid class="w-10 h-10 text-green-400 mx-auto mb-2" />
                <p class="text-sm font-medium text-green-800">All rows imported successfully</p>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    ChevronLeftIcon,
    ArrowDownTrayIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline';
import { CheckCircleIcon as CheckCircleIconSolid } from '@heroicons/vue/24/solid';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppCard from '@/Components/AppCard.vue';
import AppBadge from '@/Components/AppBadge.vue';
import AppButton from '@/Components/AppButton.vue';
import StatCard from '@/Components/StatCard.vue';

const props = defineProps({
    batch:  { type: Object, required: true },
    errors: { type: Array, default: () => [] },
});

const progressPct = computed(() => {
    if (!props.batch.total_rows) return 0;
    const done = props.batch.processed_rows + props.batch.skipped_rows + props.batch.failed_rows;
    return Math.round((done / props.batch.total_rows) * 100);
});

function statusColor(status) {
    return { pending: 'warning', processing: 'info', completed: 'success', failed: 'danger' }[status] ?? 'default';
}

function downloadErrors() {
    const rows = [['Row', 'Invoice Number', 'Status', 'Reason']];
    props.errors.forEach(e => rows.push([e.row, e.invoice_number, e.status, e.message]));
    const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `import-errors-${props.batch.id}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

function route(name, param) {
    const map = { 'import.index': '/import' };
    return map[name] ?? '/';
}
</script>
