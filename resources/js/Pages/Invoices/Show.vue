<template>
  <AppLayout :title="invoice.invoice_number" subtitle="Invoice detail, payments, and 43B(h) risk.">
    <Head :title="invoice.invoice_number" />

    <!-- Back link -->
    <div class="mb-4">
      <Link href="/invoices" class="text-sm text-indigo-600 hover:underline flex items-center gap-1">
        <ChevronLeftIcon class="w-4 h-4" /> Back to Invoices
      </Link>
    </div>

    <!-- Header card -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 mb-5 flex items-start justify-between">
      <div>
        <p class="text-xs text-gray-500 mb-1">Invoice Number</p>
        <h2 class="text-xl font-bold text-gray-900">{{ invoice.invoice_number }}</h2>
        <p class="text-sm text-gray-500 mt-1">
          {{ invoice.vendor?.name }}
          <span class="text-xs bg-gray-100 text-gray-600 rounded px-1.5 py-0.5 ml-1">{{ categoryLabel(invoice.vendor_category) }}</span>
        </p>
      </div>
      <div class="text-right">
        <span :class="['inline-flex px-3 py-1 rounded-full text-sm font-semibold mb-2', statusClass(invoice.status)]">
          {{ invoice.status_label }}
        </span>
        <p class="text-xs text-gray-400">FY {{ invoice.financial_year }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

      <!-- Left: Financials -->
      <div class="lg:col-span-2 space-y-5">

        <!-- Risk panel -->
        <div :class="['rounded-xl border p-5', riskPanelClass]">
          <h3 class="text-sm font-semibold mb-4" :class="riskHeadingClass">43B(h) Risk Summary</h3>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
              <p class="text-xs text-gray-500">Invoice Amount</p>
              <p class="text-lg font-bold text-gray-900">{{ formatCurrency(invoice.amount) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Paid Amount</p>
              <p class="text-lg font-bold text-green-600">{{ formatCurrency(invoice.paid_amount) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Balance at Risk</p>
              <p :class="['text-lg font-bold', invoice.balance > 0 ? 'text-red-600' : 'text-green-600']">
                {{ formatCurrency(invoice.balance) }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Tax Exposure</p>
              <p :class="['text-lg font-bold', invoice.tax_exposure > 0 ? 'text-red-700' : 'text-gray-400']">
                {{ invoice.tax_exposure > 0 ? formatCurrency(invoice.tax_exposure) : '—' }}
              </p>
            </div>
          </div>

          <div v-if="invoice.tax_exposure > 0" class="mt-4 pt-4 border-t border-red-200 grid grid-cols-2 gap-3 text-sm">
            <div>
              <p class="text-xs text-gray-500">Disallowance</p>
              <p class="font-semibold text-red-600">{{ formatCurrency(invoice.disallowance_amount) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Interest (3× RBI)</p>
              <p class="font-semibold text-red-600">{{ formatCurrency(invoice.interest_amount) }}</p>
            </div>
          </div>
        </div>

        <!-- Invoice details -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
          <h3 class="text-sm font-semibold text-gray-900 mb-4">Invoice Details</h3>
          <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
              <dt class="text-xs text-gray-500">Invoice Date</dt>
              <dd class="font-medium text-gray-800">{{ formatDate(invoice.invoice_date) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-gray-500">Payment Deadline</dt>
              <dd :class="['font-medium', deadlineClass]">{{ formatDate(invoice.effective_deadline) }}</dd>
            </div>
            <div>
              <dt class="text-xs text-gray-500">Agreement Type</dt>
              <dd class="font-medium text-gray-800">
                {{ invoice.agreement_exists ? '45-day (with agreement)' : '15-day (no agreement)' }}
              </dd>
            </div>
            <div>
              <dt class="text-xs text-gray-500">Days Status</dt>
              <dd :class="['font-semibold', deadlineClass]">{{ deadlineText }}</dd>
            </div>
            <div v-if="invoice.narration">
              <dt class="text-xs text-gray-500">Narration</dt>
              <dd class="text-gray-700 col-span-2">{{ invoice.narration }}</dd>
            </div>
            <div v-if="invoice.import_batch">
              <dt class="text-xs text-gray-500">Imported From</dt>
              <dd class="text-gray-700 text-xs">
                {{ invoice.import_batch.original_filename }}
                <span class="text-gray-400">({{ invoice.import_batch.source.toUpperCase() }})</span>
              </dd>
            </div>
          </dl>

          <!-- Edit agreement / narration (owners/admins/finance) -->
          <div v-if="canManage" class="mt-4 pt-4 border-t border-gray-100">
            <button @click="showEdit = !showEdit" class="text-xs text-indigo-600 hover:underline">
              {{ showEdit ? 'Cancel' : 'Edit narration / agreement type' }}
            </button>
            <form v-if="showEdit" @submit.prevent="saveEdit" class="mt-3 space-y-3">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Narration</label>
                <textarea v-model="editForm.narration" rows="2"
                          class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500"></textarea>
              </div>
              <div class="flex items-center gap-2">
                <input v-model="editForm.agreement_exists" type="checkbox" id="agreement_exists"
                       class="rounded text-indigo-600" />
                <label for="agreement_exists" class="text-xs text-gray-700">
                  Written agreement exists (changes deadline to +45 days)
                </label>
              </div>
              <button type="submit" :disabled="savingEdit"
                      class="px-4 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-60">
                {{ savingEdit ? 'Saving...' : 'Save Changes' }}
              </button>
            </form>
          </div>
        </div>

        <!-- Payments history -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Payment History</h3>
            <span class="text-xs text-gray-500">{{ invoice.payments.length }} payment{{ invoice.payments.length !== 1 ? 's' : '' }}</span>
          </div>

          <div v-if="invoice.payments.length === 0" class="px-5 py-6 text-sm text-gray-400 text-center">
            No payments recorded yet.
          </div>

          <table v-else class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-50 bg-gray-50">
                <th class="text-left px-4 py-2.5 text-xs font-medium text-gray-500">Date</th>
                <th class="text-right px-4 py-2.5 text-xs font-medium text-gray-500">Amount</th>
                <th class="text-left px-4 py-2.5 text-xs font-medium text-gray-500">Mode</th>
                <th class="text-left px-4 py-2.5 text-xs font-medium text-gray-500">Reference</th>
                <th v-if="canManage" class="px-4 py-2.5"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="p in invoice.payments" :key="p.id">
                <td class="px-4 py-2.5 text-gray-700">{{ formatDate(p.payment_date) }}</td>
                <td class="px-4 py-2.5 text-right font-mono text-green-600 font-semibold">{{ formatCurrency(p.amount) }}</td>
                <td class="px-4 py-2.5">
                  <span class="text-xs bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded font-medium">{{ p.payment_mode_label }}</span>
                </td>
                <td class="px-4 py-2.5 text-gray-500 text-xs">{{ p.reference_number || '—' }}</td>
                <td v-if="canManage" class="px-4 py-2.5">
                  <button @click="deletePayment(p)"
                          class="text-xs text-red-400 hover:text-red-600">Remove</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Record payment form -->
        <div v-if="canManage && invoice.status !== 'paid'" class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
          <h3 class="text-sm font-semibold text-gray-900 mb-4">Record Payment</h3>
          <form @submit.prevent="recordPayment" class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Amount <span class="text-red-500">*</span></label>
                <input v-model.number="payForm.amount" type="number" step="0.01" :max="invoice.balance"
                       placeholder="₹0.00"
                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500"
                       :class="{ 'border-red-400': payErrors.amount }" />
                <p v-if="payErrors.amount" class="text-xs text-red-600 mt-1">{{ payErrors.amount }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Balance: {{ formatCurrency(invoice.balance) }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Payment Date <span class="text-red-500">*</span></label>
                <input v-model="payForm.payment_date" type="date" :max="today"
                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500"
                       :class="{ 'border-red-400': payErrors.payment_date }" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Payment Mode <span class="text-red-500">*</span></label>
                <select v-model="payForm.payment_mode"
                        class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500"
                        :class="{ 'border-red-400': payErrors.payment_mode }">
                  <option v-for="m in paymentModes" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Reference Number</label>
                <input v-model="payForm.reference_number" type="text" placeholder="TXN-12345"
                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>
            <button type="submit" :disabled="recordingPayment"
                    class="px-5 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 disabled:opacity-60">
              {{ recordingPayment ? 'Recording...' : 'Record Payment' }}
            </button>
          </form>
        </div>

      </div>

      <!-- Right sidebar -->
      <div class="space-y-4">

        <!-- Deadline countdown -->
        <div :class="['rounded-xl border p-5 text-center', urgencyCard]">
          <p class="text-xs font-medium mb-2" :class="urgencyText">Payment Deadline</p>
          <p class="text-2xl font-black" :class="urgencyText">{{ deadlineText }}</p>
          <p class="text-sm mt-1" :class="urgencyText">{{ formatDate(invoice.effective_deadline) }}</p>
        </div>

        <!-- Vendor info -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4">
          <h4 class="text-xs font-semibold text-gray-500 mb-3 uppercase tracking-wide">Vendor</h4>
          <p class="text-sm font-semibold text-gray-900">{{ invoice.vendor?.name }}</p>
          <p class="text-xs text-gray-500 mt-0.5">{{ categoryLabel(invoice.vendor_category) }}</p>
          <dl class="mt-2 text-xs space-y-1">
            <div v-if="invoice.vendor?.gstin">
              <dt class="text-gray-400 inline">GSTIN: </dt>
              <dd class="font-mono text-gray-700 inline">{{ invoice.vendor.gstin }}</dd>
            </div>
            <div v-if="invoice.vendor?.udyam_number">
              <dt class="text-gray-400 inline">Udyam: </dt>
              <dd class="font-mono text-gray-700 inline">{{ invoice.vendor.udyam_number }}</dd>
            </div>
          </dl>
          <Link :href="`/vendors/${invoice.vendor?.id}`"
                class="text-xs text-indigo-600 hover:underline mt-2 inline-block">
            View vendor →
          </Link>
        </div>

        <!-- Delete invoice -->
        <div v-if="canManage && invoice.status !== 'paid'" class="bg-white border border-gray-200 rounded-xl shadow-sm p-4">
          <h4 class="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">Danger Zone</h4>
          <button @click="deleteInvoice"
                  class="w-full text-xs text-red-600 border border-red-200 rounded-lg py-2 hover:bg-red-50 transition-colors">
            Delete Invoice
          </button>
          <p class="text-xs text-gray-400 mt-1">Cannot delete if payments exist.</p>
        </div>

      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ChevronLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  invoice:      { type: Object, required: true },
  paymentModes: { type: Array,  default: () => [] },
  canManage:    { type: Boolean, default: false },
});

// ─── Edit form ────────────────────────────────────────────────────────────────
const showEdit   = ref(false);
const savingEdit = ref(false);
const editForm   = ref({
  narration:       props.invoice.narration ?? '',
  agreement_exists: props.invoice.agreement_exists ?? false,
});

function saveEdit() {
  savingEdit.value = true;
  router.put(`/invoices/${props.invoice.id}`, editForm.value, {
    onSuccess: () => { savingEdit.value = false; showEdit.value = false; },
    onFinish:  () => { savingEdit.value = false; },
  });
}

// ─── Payment form ─────────────────────────────────────────────────────────────
const recordingPayment = ref(false);
const payErrors        = ref({});
const today            = new Date().toISOString().split('T')[0];

const payForm = ref({
  amount:           '',
  payment_date:     today,
  payment_mode:     props.paymentModes[0]?.value ?? 'neft',
  reference_number: '',
});

function recordPayment() {
  recordingPayment.value = true;
  payErrors.value        = {};

  router.post(`/invoices/${props.invoice.id}/payments`, payForm.value, {
    onSuccess: () => {
      recordingPayment.value = false;
      payForm.value.amount           = '';
      payForm.value.reference_number = '';
    },
    onError:  (e) => { recordingPayment.value = false; payErrors.value = e; },
    onFinish: ()  => { recordingPayment.value = false; },
  });
}

function deletePayment(payment) {
  if (! confirm('Remove this payment? The invoice risk will be recomputed.')) return;
  router.delete(`/invoices/${props.invoice.id}/payments/${payment.id}`);
}

function deleteInvoice() {
  if (! confirm('Delete this invoice? This cannot be undone if no payments exist.')) return;
  router.delete(`/invoices/${props.invoice.id}`);
}

// ─── Computed display ─────────────────────────────────────────────────────────
const days = computed(() => props.invoice.days_to_deadline);

const deadlineText = computed(() => {
  const d = days.value;
  if (props.invoice.status === 'paid')       return 'Fully Paid';
  if (props.invoice.status === 'disallowed') return 'Disallowed';
  if (d < 0)   return `${Math.abs(d)} days overdue`;
  if (d === 0) return 'Due today!';
  if (d <= 3)  return `${d} days — URGENT`;
  if (d <= 10) return `${d} days — Warning`;
  return `${d} days remaining`;
});

const deadlineClass = computed(() => {
  const d = days.value;
  if (props.invoice.status === 'paid') return 'text-green-600';
  if (d < 0)   return 'text-red-600 font-semibold';
  if (d <= 3)  return 'text-red-500 font-semibold';
  if (d <= 10) return 'text-orange-500';
  return 'text-gray-700';
});

const urgencyCard = computed(() => {
  const d = days.value;
  if (props.invoice.status === 'paid')       return 'border-green-200 bg-green-50';
  if (props.invoice.status === 'disallowed') return 'border-gray-200 bg-gray-50';
  if (d < 0)   return 'border-red-300 bg-red-50';
  if (d <= 3)  return 'border-red-200 bg-red-50';
  if (d <= 10) return 'border-orange-200 bg-orange-50';
  return 'border-gray-200 bg-gray-50';
});

const urgencyText = computed(() => {
  const d = days.value;
  if (props.invoice.status === 'paid') return 'text-green-700';
  if (d < 0)   return 'text-red-700';
  if (d <= 3)  return 'text-red-600';
  if (d <= 10) return 'text-orange-600';
  return 'text-gray-600';
});

const riskPanelClass = computed(() => {
  if (props.invoice.tax_exposure > 0) return 'border-red-200 bg-red-50';
  if (props.invoice.status === 'paid') return 'border-green-200 bg-green-50';
  return 'border-gray-200 bg-gray-50';
});

const riskHeadingClass = computed(() => {
  if (props.invoice.tax_exposure > 0) return 'text-red-800';
  if (props.invoice.status === 'paid') return 'text-green-800';
  return 'text-gray-800';
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
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
