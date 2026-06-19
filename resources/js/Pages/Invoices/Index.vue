<template>
  <AppLayout title="Invoices" subtitle="Track 43B(h) payment deadlines and risk exposure.">
    <Head title="Invoices" />

    <!-- Summary cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <StatCard label="At-Risk Balance" :value="formatCurrency(summary.at_risk_balance)" icon="shield" color="orange" />
      <StatCard label="Overdue" :value="summary.overdue_count.toString()" icon="exclamation" color="red" />
      <StatCard label="Pending Payment" :value="summary.pending_count.toString()" icon="clock" color="yellow" />
      <StatCard label="Total Tax Exposure" :value="formatCurrency(summary.total_exposure)" icon="calculator" color="purple" />
    </div>

    <!-- Filters -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4 mb-4">
      <div class="flex flex-wrap gap-3 items-end">
        <!-- Search -->
        <div class="flex-1 min-w-48">
          <input
            v-model="localFilters.search"
            type="text"
            placeholder="Search invoice number..."
            class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500"
            @input="debouncedFilter"
          />
        </div>

        <!-- Status -->
        <div>
          <select v-model="localFilters.status" @change="applyFilters"
                  class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>

        <!-- Financial Year -->
        <div>
          <select v-model="localFilters.financial_year" @change="applyFilters"
                  class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500">
            <option value="">All Years</option>
            <option v-for="fy in financial_years" :key="fy" :value="fy">FY {{ fy }}</option>
          </select>
        </div>

        <!-- Vendor -->
        <div>
          <select v-model="localFilters.vendor_id" @change="applyFilters"
                  class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500">
            <option value="">All Vendors</option>
            <option v-for="v in vendors" :key="v.id" :value="v.id">{{ v.name }}</option>
          </select>
        </div>

        <!-- Clear -->
        <button v-if="hasFilters" @click="clearFilters"
                class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 bg-gray-100 rounded-lg">
          Clear
        </button>
      </div>
    </div>

    <!-- Status filter pills -->
    <div class="flex gap-2 mb-4 flex-wrap">
      <button
        v-for="s in quickStatuses"
        :key="s.value"
        @click="setStatus(s.value)"
        :class="[
          'px-3 py-1 rounded-full text-xs font-semibold transition-colors',
          localFilters.status === s.value ? s.activeClass : 'bg-gray-100 text-gray-600 hover:bg-gray-200',
        ]"
      >
        {{ s.label }}
      </button>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50">
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Invoice #</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Vendor</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount</th>
            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Balance</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Deadline</th>
            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Tax Exposure</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-if="invoices.data.length === 0">
            <td colspan="8" class="text-center py-12 text-gray-400 text-sm">No invoices found.</td>
          </tr>
          <tr
            v-for="inv in invoices.data"
            :key="inv.id"
            @click="$inertia.visit(`/invoices/${inv.id}`)"
            class="hover:bg-gray-50 cursor-pointer transition-colors"
          >
            <td class="px-4 py-3 font-medium text-gray-800">{{ inv.invoice_number }}</td>
            <td class="px-4 py-3">
              <div class="text-gray-800">{{ inv.vendor_name }}</div>
              <div class="text-xs text-gray-400">{{ categoryLabel(inv.vendor_category) }}</div>
            </td>
            <td class="px-4 py-3 text-gray-600 text-xs">{{ formatDate(inv.invoice_date) }}</td>
            <td class="px-4 py-3 text-right text-gray-700 font-mono text-xs">{{ formatCurrency(inv.amount) }}</td>
            <td class="px-4 py-3 text-right font-mono text-xs">
              <span :class="inv.balance > 0 ? 'text-red-600 font-semibold' : 'text-green-600'">
                {{ formatCurrency(inv.balance) }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="text-xs text-gray-700">{{ formatDate(inv.effective_deadline) }}</div>
              <div :class="['text-xs font-medium mt-0.5', deadlineClass(inv)]">
                {{ deadlineText(inv) }}
              </div>
            </td>
            <td class="px-4 py-3 text-right font-mono text-xs">
              <span v-if="inv.tax_exposure > 0" class="text-red-600 font-semibold">
                {{ formatCurrency(inv.tax_exposure) }}
              </span>
              <span v-else class="text-gray-400">—</span>
            </td>
            <td class="px-4 py-3">
              <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-semibold', statusClass(inv.status)]">
                {{ inv.status_label }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="invoices.last_page > 1" class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
        <p class="text-xs text-gray-500">
          Showing {{ invoices.from }}–{{ invoices.to }} of {{ invoices.total }} invoices
        </p>
        <div class="flex gap-1">
          <Link
            v-for="link in invoices.links"
            :key="link.label"
            :href="link.url ?? '#'"
            :class="[
              'px-3 py-1 text-xs rounded border transition-colors',
              link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-600 border-gray-200 hover:border-gray-300',
              ! link.url ? 'opacity-40 pointer-events-none' : '',
            ]"
            preserve-scroll
            v-html="link.label"
          />
        </div>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/StatCard.vue';

const props = defineProps({
  invoices:        { type: Object, required: true },
  filters:         { type: Object, default: () => ({}) },
  vendors:         { type: Array,  default: () => [] },
  financial_years: { type: Array,  default: () => [] },
  statuses:        { type: Array,  default: () => [] },
  summary:         { type: Object, required: true },
  canManage:       { type: Boolean, default: false },
});

const localFilters = ref({
  search:         props.filters.search         ?? '',
  status:         props.filters.status         ?? '',
  financial_year: props.filters.financial_year ?? '',
  vendor_id:      props.filters.vendor_id      ?? '',
});

const hasFilters = computed(() =>
  Object.values(localFilters.value).some(v => v !== '')
);

const quickStatuses = [
  { value: '',           label: 'All',          activeClass: 'bg-gray-700 text-white' },
  { value: 'pending',    label: 'Pending',       activeClass: 'bg-yellow-500 text-white' },
  { value: 'partial',    label: 'Partial',       activeClass: 'bg-blue-500 text-white' },
  { value: 'overdue',    label: 'Overdue',       activeClass: 'bg-red-600 text-white' },
  { value: 'paid',       label: 'Paid',          activeClass: 'bg-green-600 text-white' },
  { value: 'disallowed', label: 'Disallowed',    activeClass: 'bg-red-900 text-white' },
];

let debounceTimer = null;
function debouncedFilter() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(applyFilters, 400);
}

function applyFilters() {
  router.get('/invoices', cleanFilters(localFilters.value), { preserveState: true, replace: true });
}

function setStatus(status) {
  localFilters.value.status = status;
  applyFilters();
}

function clearFilters() {
  localFilters.value = { search: '', status: '', financial_year: '', vendor_id: '' };
  applyFilters();
}

function cleanFilters(f) {
  return Object.fromEntries(Object.entries(f).filter(([, v]) => v !== ''));
}

// ─── Display helpers ─────────────────────────────────────────────────────────

function formatCurrency(val) {
  const n = Number(val) || 0;
  if (n >= 10000000) return '₹' + (n / 10000000).toFixed(2) + ' Cr';
  if (n >= 100000)   return '₹' + (n / 100000).toFixed(2) + ' L';
  if (n >= 1000)     return '₹' + (n / 1000).toFixed(1) + 'K';
  return '₹' + n.toFixed(0);
}

function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function categoryLabel(cat) {
  return { micro: 'Micro', small: 'Small', medium: 'Medium', large: 'Large', unclassified: 'Unclassified' }[cat] ?? cat;
}

function deadlineText(inv) {
  const days = inv.days_to_deadline;
  if (inv.status === 'paid')       return 'Paid';
  if (inv.status === 'disallowed') return 'FY Disallowed';
  if (days < 0) return `${Math.abs(days)}d overdue`;
  if (days === 0) return 'Due today!';
  if (days <= 3)  return `${days}d — urgent`;
  if (days <= 10) return `${days}d — warning`;
  return `${days}d remaining`;
}

function deadlineClass(inv) {
  const days = inv.days_to_deadline;
  if (inv.status === 'paid')       return 'text-green-600';
  if (inv.status === 'disallowed') return 'text-gray-500';
  if (days < 0)   return 'text-red-600';
  if (days <= 3)  return 'text-red-500';
  if (days <= 10) return 'text-orange-500';
  return 'text-gray-500';
}

function statusClass(status) {
  return {
    pending:    'bg-yellow-100 text-yellow-800',
    partial:    'bg-blue-100 text-blue-800',
    paid:       'bg-green-100 text-green-800',
    overdue:    'bg-red-100 text-red-800',
    disallowed: 'bg-red-900 text-white',
  }[status] ?? 'bg-gray-100 text-gray-700';
}
</script>
