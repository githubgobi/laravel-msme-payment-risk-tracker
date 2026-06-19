<template>
    <AppLayout :title="`Dashboard`" :subtitle="`FY ${financialYear} · Section 43B(h) Risk Overview`">
        <Head title="Dashboard" />

        <!-- Unclassified vendor warning -->
        <div
            v-if="unclassifiedVendors > 0"
            class="mb-5 flex items-center justify-between gap-4 rounded-xl border border-yellow-300 bg-yellow-50 px-5 py-3"
        >
            <div class="flex items-center gap-3">
                <ExclamationTriangleIcon class="h-5 w-5 flex-shrink-0 text-yellow-600" />
                <p class="text-sm font-medium text-yellow-800">
                    {{ unclassifiedVendors }} vendor{{ unclassifiedVendors > 1 ? 's are' : ' is' }}
                    unclassified — their invoices show <strong>₹0 disallowance</strong> until classified.
                </p>
            </div>
            <Link
                :href="route('vendors.index', { category: 'unclassified' })"
                class="flex-shrink-0 text-sm font-semibold text-yellow-700 underline hover:text-yellow-900"
            >
                Classify now →
            </Link>
        </div>

        <!-- FY Tabs -->
        <div class="mb-5 flex items-center gap-2">
            <span class="text-xs font-medium text-gray-500 mr-1">Financial Year:</span>
            <button
                v-for="yr in availableYears"
                :key="yr"
                @click="switchFy(yr)"
                :class="[
                    'px-3 py-1.5 rounded-full text-sm font-medium border transition-colors',
                    yr === financialYear
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'bg-white text-gray-600 border-gray-300 hover:border-indigo-400 hover:text-indigo-600',
                ]"
            >
                {{ yr }}
            </button>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <StatCard
                label="At-Risk Balance"
                :value="formatCurrency(stats.total_at_risk)"
                :sub="`${stats.at_risk_count} unpaid Micro/Small invoices`"
                :icon="ExclamationTriangleIcon"
                icon-bg="bg-red-50"
                icon-color="text-red-600"
            />
            <StatCard
                label="Due This Week"
                :value="stats.due_this_week + ' invoice' + (stats.due_this_week !== 1 ? 's' : '')"
                sub="Deadline within 7 days"
                :icon="ClockIcon"
                icon-bg="bg-amber-50"
                icon-color="text-amber-600"
            />
            <StatCard
                label="Projected Disallowance"
                :value="formatCurrency(stats.projected_disallowance)"
                sub="Added back to taxable income"
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

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-4">

            <!-- At-Risk Invoices Table -->
            <AppCard
                title="At-Risk Invoices"
                description="Sorted by urgency — overdue first"
                class="xl:col-span-2"
            >
                <template #actions>
                    <AppButton :href="route('vendors.index')" variant="secondary" size="sm">
                        View All →
                    </AppButton>
                </template>

                <div v-if="atRiskInvoices.length === 0" class="text-center py-10">
                    <ShieldCheckIcon class="w-10 h-10 text-green-400 mx-auto mb-2" />
                    <p class="text-sm font-medium text-gray-700">All clear!</p>
                    <p class="text-xs text-gray-500">No at-risk invoices in FY {{ financialYear }}.</p>
                </div>

                <div v-else class="overflow-x-auto -mx-5">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wide px-5 py-3">Vendor</th>
                                <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wide px-3 py-3 hidden sm:table-cell">Invoice</th>
                                <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wide px-3 py-3">Balance</th>
                                <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wide px-3 py-3 hidden md:table-cell">Deadline</th>
                                <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wide px-3 py-3 hidden lg:table-cell">Exposure (₹)</th>
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
                                    <p class="text-xs text-gray-400">{{ invoice.vendor_category }}</p>
                                </td>
                                <td class="px-3 py-3 text-gray-500 text-xs hidden sm:table-cell">{{ invoice.invoice_number }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-gray-900">
                                    {{ formatCurrency(invoice.balance) }}
                                </td>
                                <td class="px-3 py-3 hidden md:table-cell">
                                    <p :class="['text-xs font-medium', deadlineColor(invoice.days_remaining)]">
                                        {{ invoice.effective_deadline }}
                                    </p>
                                    <p class="text-xs text-gray-400">{{ deadlineLabel(invoice.days_remaining) }}</p>
                                </td>
                                <td class="px-3 py-3 text-right hidden lg:table-cell">
                                    <span :class="(invoice.disallowance_amount + invoice.interest_amount) > 0 ? 'text-red-600 font-medium text-sm' : 'text-gray-400 text-sm'">
                                        {{ formatCurrency(invoice.disallowance_amount + invoice.interest_amount) }}
                                    </span>
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
                        <div class="pt-2 border-t border-gray-100 flex justify-between">
                            <span class="text-xs text-gray-500 font-medium">Total vendors</span>
                            <span class="text-xs font-semibold text-gray-700">{{ vendorCounts.total ?? 0 }}</span>
                        </div>
                    </div>
                </AppCard>

                <!-- Overdue Alert -->
                <div
                    v-if="stats.overdue_count > 0"
                    class="rounded-xl border border-red-200 bg-red-50 p-4"
                >
                    <div class="flex items-center gap-3">
                        <ExclamationTriangleIcon class="h-5 w-5 text-red-600 flex-shrink-0" />
                        <div>
                            <p class="text-sm font-semibold text-red-800">
                                {{ stats.overdue_count }} overdue invoice{{ stats.overdue_count !== 1 ? 's' : '' }}
                            </p>
                            <p class="text-xs text-red-600 mt-0.5">
                                Interest accruing at 3× RBI rate
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <AppCard title="Quick Actions">
                    <div class="space-y-2">
                        <AppButton :href="route('import.index')" variant="primary" size="md" :icon="ArrowUpTrayIcon" class="w-full justify-center">
                            Import Ledger
                        </AppButton>
                        <AppButton :href="route('calculator.index')" variant="secondary" size="md" :icon="CalculatorIcon" class="w-full justify-center">
                            43B(h) Calculator
                        </AppButton>
                        <AppButton :href="route('vendors.index')" variant="secondary" size="md" :icon="BuildingStorefrontIcon" class="w-full justify-center">
                            Manage Vendors
                        </AppButton>
                    </div>
                </AppCard>
            </div>
        </div>

        <!-- Monthly Trend Chart -->
        <AppCard
            title="Monthly Disallowance Trend"
            :description="`At-risk disallowance + interest by invoice month — FY ${financialYear}`"
        >
            <div v-if="hasTrendData">
                <VueApexCharts
                    type="bar"
                    height="240"
                    :options="chartOptions"
                    :series="chartSeries"
                />
            </div>
            <div v-else class="py-10 text-center text-sm text-gray-400">
                No invoice data for FY {{ financialYear }} yet.
                <Link :href="route('import.index')" class="text-indigo-600 hover:text-indigo-800 ml-1">Import ledger →</Link>
            </div>
        </AppCard>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
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
    financialYear:       { type: String,  required: true },
    availableYears:      { type: Array,   default: () => [] },
    stats:               { type: Object,  default: () => ({
        at_risk_count: 0, total_at_risk: 0,
        projected_disallowance: 0, projected_interest: 0,
        overdue_count: 0, due_this_week: 0,
    })},
    atRiskInvoices:      { type: Array,   default: () => [] },
    vendorCounts:        { type: Object,  default: () => ({}) },
    monthlyTrend:        { type: Array,   default: () => [] },
    unclassifiedVendors: { type: Number,  default: 0 },
});

const vendorSummary = computed(() => [
    { label: 'Micro (43B(h))',  count: props.vendorCounts.micro        ?? 0, dot: 'bg-red-500'    },
    { label: 'Small (43B(h))',  count: props.vendorCounts.small        ?? 0, dot: 'bg-amber-500'  },
    { label: 'Medium',          count: props.vendorCounts.medium       ?? 0, dot: 'bg-blue-400'   },
    { label: 'Large',           count: props.vendorCounts.large        ?? 0, dot: 'bg-gray-400'   },
    { label: 'Unclassified',    count: props.vendorCounts.unclassified ?? 0, dot: 'bg-yellow-400' },
]);

const hasTrendData = computed(() =>
    props.monthlyTrend.some(m => m.count > 0)
);

const chartOptions = computed(() => ({
    chart: { toolbar: { show: false }, fontFamily: 'inherit', stacked: false },
    plotOptions: {
        bar: { borderRadius: 4, columnWidth: '55%' },
    },
    dataLabels: { enabled: false },
    xaxis: {
        categories: props.monthlyTrend.map(m => m.month),
        labels: { style: { colors: '#6b7280', fontSize: '11px' } },
    },
    yaxis: {
        labels: {
            style: { colors: '#6b7280', fontSize: '11px' },
            formatter: (v) => formatCurrencyShort(v),
        },
    },
    tooltip: {
        y: { formatter: (v) => '₹' + v.toLocaleString('en-IN') },
        shared: true,
        intersect: false,
    },
    colors: ['#f97316', '#a855f7'],
    legend: { position: 'top', fontSize: '12px' },
    grid: { borderColor: '#f3f4f6', strokeDashArray: 4 },
}));

const chartSeries = computed(() => [
    {
        name: 'Disallowance',
        data: props.monthlyTrend.map(m => m.disallowance),
    },
    {
        name: 'Interest',
        data: props.monthlyTrend.map(m => m.interest),
    },
]);

function switchFy(yr) {
    router.get(route('dashboard'), { fy: yr }, { preserveScroll: true, replace: true });
}

function formatCurrency(value) {
    const num = parseFloat(value) || 0;
    if (num >= 10_000_000) return `₹${(num / 10_000_000).toFixed(2)} Cr`;
    if (num >= 100_000)    return `₹${(num / 100_000).toFixed(2)} L`;
    if (num >= 1_000)      return `₹${(num / 1_000).toFixed(1)}K`;
    return `₹${num.toLocaleString('en-IN', { minimumFractionDigits: 0 })}`;
}

function formatCurrencyShort(value) {
    const num = parseFloat(value) || 0;
    if (num >= 10_000_000) return `₹${(num / 10_000_000).toFixed(1)}Cr`;
    if (num >= 100_000)    return `₹${(num / 100_000).toFixed(1)}L`;
    if (num >= 1_000)      return `₹${(num / 1_000).toFixed(0)}K`;
    return `₹${num}`;
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
    if (days < 0)  return `${Math.abs(days)}d overdue`;
    if (days === 0) return 'Due today!';
    return `${days}d left`;
}

function statusColor(status) {
    return { pending: 'amber', partial: 'blue', overdue: 'red', disallowed: 'red', paid: 'green' }[status] ?? 'gray';
}
</script>
