<template>
    <AppLayout title="Dashboard" :subtitle="`Financial Year ${currentFY}`">
        <Head title="Dashboard" />

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <StatCard
                label="Total At-Risk Exposure"
                :value="formatCurrency(stats.total_at_risk)"
                sub="Unpaid invoices past or near deadline"
                :icon="ExclamationTriangleIcon"
                icon-bg="bg-red-50"
                icon-color="text-red-600"
            />
            <StatCard
                label="Due This Week"
                :value="stats.due_this_week + ' invoices'"
                sub="Deadline within 7 days"
                :icon="ClockIcon"
                icon-bg="bg-amber-50"
                icon-color="text-amber-600"
            />
            <StatCard
                label="Projected Disallowance"
                :value="formatCurrency(stats.projected_disallowance)"
                sub="If unpaid by March 31"
                :icon="DocumentMinusIcon"
                icon-bg="bg-orange-50"
                icon-color="text-orange-600"
            />
            <StatCard
                label="Projected Interest"
                :value="formatCurrency(stats.projected_interest)"
                sub="At 3× RBI rate, compounded monthly"
                :icon="BanknotesIcon"
                icon-bg="bg-purple-50"
                icon-color="text-purple-600"
            />
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

            <!-- At-Risk Invoices Table -->
            <AppCard
                title="At-Risk Invoices"
                description="Invoices with deadlines approaching or overdue"
                class="xl:col-span-2"
            >
                <template #actions>
                    <AppButton href="/invoices" variant="secondary" size="sm">
                        View All
                    </AppButton>
                </template>

                <div v-if="atRiskInvoices.length === 0" class="text-center py-10">
                    <ShieldCheckIcon class="w-10 h-10 text-green-400 mx-auto mb-2" />
                    <p class="text-sm font-medium text-gray-700">All clear!</p>
                    <p class="text-xs text-gray-500">No at-risk invoices at this time.</p>
                </div>

                <div v-else class="overflow-x-auto -mx-5">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wide px-5 py-3">Vendor</th>
                                <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wide px-3 py-3">Invoice</th>
                                <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wide px-3 py-3">Balance</th>
                                <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wide px-3 py-3">Deadline</th>
                                <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wide px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr
                                v-for="invoice in atRiskInvoices"
                                :key="invoice.id"
                                class="hover:bg-gray-50 transition-colors"
                            >
                                <td class="px-5 py-3">
                                    <p class="font-medium text-gray-900 truncate max-w-[160px]">{{ invoice.vendor_name }}</p>
                                    <p class="text-xs text-gray-500">{{ invoice.vendor_category }}</p>
                                </td>
                                <td class="px-3 py-3 text-gray-600">{{ invoice.invoice_number }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-gray-900">
                                    {{ formatCurrency(invoice.balance) }}
                                </td>
                                <td class="px-3 py-3">
                                    <p :class="['text-xs font-medium', deadlineColor(invoice.days_remaining)]">
                                        {{ invoice.effective_deadline }}
                                    </p>
                                    <p class="text-xs text-gray-500">{{ deadlineLabel(invoice.days_remaining) }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <AppBadge :color="statusColor(invoice.status)">
                                        {{ invoice.status_label }}
                                    </AppBadge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>

            <!-- Right column -->
            <div class="space-y-4">

                <!-- Vendor Summary -->
                <AppCard title="Vendor Coverage">
                    <div class="space-y-3">
                        <div v-for="item in vendorSummary" :key="item.label" class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full flex-shrink-0" :class="item.dot"></span>
                                <span class="text-sm text-gray-600">{{ item.label }}</span>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">{{ item.count }}</span>
                        </div>
                    </div>
                </AppCard>

                <!-- Quick Actions -->
                <AppCard title="Quick Actions">
                    <div class="space-y-2">
                        <AppButton href="/import" variant="primary" size="md" :icon="ArrowUpTrayIcon" class="w-full justify-center">
                            Import Ledger
                        </AppButton>
                        <AppButton href="/calculator" variant="secondary" size="md" :icon="CalculatorIcon" class="w-full justify-center">
                            43B(h) Calculator
                        </AppButton>
                        <AppButton href="/vendors" variant="secondary" size="md" :icon="BuildingStorefrontIcon" class="w-full justify-center">
                            Manage Vendors
                        </AppButton>
                    </div>
                </AppCard>

            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    ExclamationTriangleIcon,
    ClockIcon,
    DocumentMinusIcon,
    BanknotesIcon,
    ShieldCheckIcon,
    ArrowUpTrayIcon,
    CalculatorIcon,
    BuildingStorefrontIcon,
} from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import AppCard from '@/Components/AppCard.vue';
import AppBadge from '@/Components/AppBadge.vue';
import AppButton from '@/Components/AppButton.vue';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            total_at_risk: 0,
            due_this_week: 0,
            projected_disallowance: 0,
            projected_interest: 0,
        }),
    },
    atRiskInvoices: { type: Array, default: () => [] },
    vendorCounts:   { type: Object, default: () => ({}) },
});

const currentFY = computed(() => {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth() + 1;
    return m >= 4 ? `${y}-${String(y + 1).slice(-2)}` : `${y - 1}-${String(y).slice(-2)}`;
});

const vendorSummary = computed(() => [
    { label: 'Micro',         count: props.vendorCounts.micro         ?? 0, dot: 'bg-red-500'    },
    { label: 'Small',         count: props.vendorCounts.small         ?? 0, dot: 'bg-amber-500'  },
    { label: 'Medium',        count: props.vendorCounts.medium        ?? 0, dot: 'bg-blue-400'   },
    { label: 'Unclassified',  count: props.vendorCounts.unclassified  ?? 0, dot: 'bg-gray-400'   },
]);

function formatCurrency(value) {
    const num = parseFloat(value) || 0;
    if (num >= 10000000) return `₹${(num / 10000000).toFixed(2)} Cr`;
    if (num >= 100000)   return `₹${(num / 100000).toFixed(2)} L`;
    if (num >= 1000)     return `₹${(num / 1000).toFixed(1)}K`;
    return `₹${num.toLocaleString('en-IN', { minimumFractionDigits: 0 })}`;
}

function deadlineColor(days) {
    if (days === null || days === undefined) return 'text-gray-600';
    if (days < 0)  return 'text-red-600';
    if (days <= 3) return 'text-red-500';
    if (days <= 10) return 'text-amber-600';
    return 'text-green-600';
}

function deadlineLabel(days) {
    if (days === null || days === undefined) return '';
    if (days < 0)  return `${Math.abs(days)} days overdue`;
    if (days === 0) return 'Due today';
    return `${days} days left`;
}

function statusColor(status) {
    return { pending: 'amber', partial: 'blue', overdue: 'red', disallowed: 'red', paid: 'green' }[status] ?? 'gray';
}
</script>
