<template>
  <AppLayout title="43B(h) Calculator" subtitle="Compute payment deadline, disallowance, and compound interest for any invoice.">
    <Head title="43B(h) Calculator" />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Input panel -->
      <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-5">Invoice Parameters</h3>
        <form @submit.prevent="compute" class="space-y-4">

          <!-- Vendor category -->
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Vendor Category <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-5 gap-2">
              <button
                v-for="cat in vendorCategories"
                :key="cat.value"
                type="button"
                @click="form.vendor_category = cat.value"
                :class="[
                  'py-2 rounded-lg text-xs font-medium border transition-colors',
                  form.vendor_category === cat.value
                    ? (cat.subject_to_43bh ? 'bg-red-600 text-white border-red-600' : 'bg-gray-700 text-white border-gray-700')
                    : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300',
                ]"
              >
                {{ cat.label }}
              </button>
            </div>
            <p v-if="selectedCategory?.subject_to_43bh" class="text-xs text-red-600 mt-1">
              ⚠ 43B(h) applies — disallowance and interest will accrue if overdue.
            </p>
            <p v-else class="text-xs text-gray-400 mt-1">
              43B(h) does not apply to this category — no tax disallowance.
            </p>
          </div>

          <!-- Invoice date + amount -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Invoice Date <span class="text-red-500">*</span></label>
              <input v-model="form.invoice_date" type="date"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500"
                     :class="{ 'border-red-400': errors.invoice_date }" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Invoice Amount (₹) <span class="text-red-500">*</span></label>
              <input v-model.number="form.amount" type="number" step="0.01" min="0.01" placeholder="500000"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500"
                     :class="{ 'border-red-400': errors.amount }" />
            </div>
          </div>

          <!-- Agreement + paid amount -->
          <div class="grid grid-cols-2 gap-3 items-end">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Already Paid (₹)</label>
              <input v-model.number="form.paid_amount" type="number" step="0.01" min="0" placeholder="0"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
              <p class="text-xs text-gray-400 mt-0.5">Leave 0 if unpaid</p>
            </div>
            <div class="flex items-center gap-2 pb-1">
              <input v-model="form.agreement_exists" id="agreement" type="checkbox"
                     class="rounded text-indigo-600 w-4 h-4" />
              <label for="agreement" class="text-sm text-gray-700">Written agreement exists</label>
            </div>
          </div>
          <p v-if="form.agreement_exists" class="text-xs text-blue-600 -mt-2">
            45-day deadline applies (maximum under MSME Act).
          </p>
          <p v-else class="text-xs text-gray-400 -mt-2">
            15-day deadline applies (no written agreement).
          </p>

          <!-- As of date + bank rate -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">As of Date</label>
              <input v-model="form.as_of" type="date"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
              <p class="text-xs text-gray-400 mt-0.5">Leave blank for today</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">RBI Bank Rate (%)</label>
              <input v-model.number="form.bank_rate" type="number" step="0.25" min="1" max="25"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
              <p class="text-xs text-gray-400 mt-0.5">43B(h) rate = 3× = {{ (form.bank_rate * 3).toFixed(2) }}%</p>
            </div>
          </div>

          <button type="submit" :disabled="computing"
                  class="w-full bg-indigo-600 text-white py-2.5 rounded-lg text-sm font-semibold hover:bg-indigo-700 disabled:opacity-60 transition-colors">
            {{ computing ? 'Computing...' : 'Compute 43B(h) Risk' }}
          </button>
        </form>
      </div>

      <!-- Result panel -->
      <div>
        <!-- Empty state -->
        <div v-if="! result" class="bg-gray-50 border border-gray-200 rounded-xl p-8 text-center h-full flex flex-col items-center justify-center">
          <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center mb-4">
            <CalculatorIcon class="w-6 h-6 text-indigo-500" />
          </div>
          <p class="text-sm font-medium text-gray-600">Fill in the invoice parameters and click "Compute"</p>
          <p class="text-xs text-gray-400 mt-1">Results will appear here instantly</p>
        </div>

        <!-- Result -->
        <div v-else :class="['border rounded-xl shadow-sm overflow-hidden', resultBorderClass]">

          <!-- Status header -->
          <div :class="['px-6 py-4 flex items-center justify-between', resultHeaderClass]">
            <div>
              <p class="text-xs font-medium opacity-80">Assessment Status</p>
              <p class="text-xl font-bold mt-0.5">{{ result.status_label }}</p>
            </div>
            <div class="text-right">
              <p class="text-xs opacity-80">FY {{ result.financial_year }}</p>
              <p class="text-sm font-medium mt-0.5">
                {{ result.deadline_days }}-day deadline
              </p>
            </div>
          </div>

          <!-- Key metrics -->
          <div class="bg-white p-6 space-y-4">

            <!-- Deadline info -->
            <div class="flex justify-between items-center pb-3 border-b border-gray-100">
              <span class="text-sm text-gray-600">Payment Deadline</span>
              <span class="font-semibold text-gray-900">{{ formatDate(result.effective_deadline) }}</span>
            </div>
            <div class="flex justify-between items-center pb-3 border-b border-gray-100">
              <span class="text-sm text-gray-600">Days Status</span>
              <span :class="['font-semibold', daysClass]">{{ daysText }}</span>
            </div>

            <!-- Financial breakdown -->
            <div class="flex justify-between items-center pb-3 border-b border-gray-100">
              <span class="text-sm text-gray-600">Invoice Balance</span>
              <span class="font-semibold text-gray-900">{{ formatCurrency(result.balance) }}</span>
            </div>

            <div v-if="result.is_subject_to_43bh">
              <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                <div>
                  <span class="text-sm text-gray-600">Disallowance Amount</span>
                  <p class="text-xs text-gray-400">Added back to taxable income</p>
                </div>
                <span :class="['font-bold text-lg', result.disallowance_amount > 0 ? 'text-red-600' : 'text-gray-400']">
                  {{ result.disallowance_amount > 0 ? formatCurrency(result.disallowance_amount) : '—' }}
                </span>
              </div>

              <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                <div>
                  <span class="text-sm text-gray-600">Interest ({{ result.annual_interest_rate }}% p.a.)</span>
                  <p class="text-xs text-gray-400">3× RBI rate, compound monthly — non-deductible</p>
                </div>
                <span :class="['font-bold text-lg', result.interest_amount > 0 ? 'text-red-600' : 'text-gray-400']">
                  {{ result.interest_amount > 0 ? formatCurrency(result.interest_amount) : '—' }}
                </span>
              </div>

              <div class="flex justify-between items-center pb-3 border-b border-gray-100 bg-red-50 -mx-6 px-6 py-3">
                <div>
                  <span class="text-sm font-bold text-red-800">Total Tax Exposure</span>
                  <p class="text-xs text-red-600">Disallowance + Interest</p>
                </div>
                <span class="font-black text-xl text-red-700">
                  {{ result.total_exposure > 0 ? formatCurrency(result.total_exposure) : '—' }}
                </span>
              </div>

              <div class="flex justify-between items-center pt-2">
                <span class="text-sm text-gray-600">Effective Tax Rate on Invoice</span>
                <span :class="['font-semibold', result.effective_tax_rate > 0 ? 'text-red-600' : 'text-gray-400']">
                  {{ result.effective_tax_rate > 0 ? result.effective_tax_rate + '%' : '—' }}
                </span>
              </div>
            </div>

            <div v-else class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-800">
              This vendor category is not subject to Section 43B(h). No disallowance or interest applies.
            </div>

            <!-- Days overdue detail -->
            <div v-if="result.days_overdue > 0" class="bg-red-50 border border-red-100 rounded-lg p-3 text-xs text-red-700 space-y-1">
              <p><strong>{{ result.days_overdue }} days overdue</strong> as of the "As of" date.</p>
              <p>Interest accrues on complete months overdue. Partial months do not count.</p>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Formula reference -->
    <div class="mt-6 bg-gray-50 border border-gray-200 rounded-xl p-5">
      <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Formula Reference — Section 43B(h)</h4>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs text-gray-600">
        <div>
          <p class="font-semibold mb-1">Deadline</p>
          <p>15 days — no written agreement</p>
          <p>45 days — written agreement exists</p>
          <p class="text-gray-400 mt-1">(Maximum under MSME Act)</p>
        </div>
        <div>
          <p class="font-semibold mb-1">Disallowance</p>
          <p>Unpaid balance on the effective deadline date is added back to taxable income.</p>
          <p class="text-gray-400 mt-1">Applies to Micro and Small vendors only.</p>
        </div>
        <div>
          <p class="font-semibold mb-1">Interest</p>
          <p class="font-mono bg-white px-2 py-1 rounded border border-gray-200 mb-1">I = P × ((1 + r)ⁿ − 1)</p>
          <p>r = (RBI rate × 3) / 12 / 100</p>
          <p>n = complete months overdue</p>
        </div>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { CalculatorIcon } from '@heroicons/vue/24/outline';
import axios from 'axios';

const props = defineProps({
  vendorCategories: { type: Array,  required: true },
  defaultBankRate:  { type: Number, default: 6.75 },
});

const today = new Date().toISOString().split('T')[0];

const form = ref({
  invoice_date:    '',
  amount:          '',
  agreement_exists: false,
  vendor_category: 'micro',
  bank_rate:       props.defaultBankRate,
  paid_amount:     0,
  as_of:           '',
});

const result    = ref(null);
const computing = ref(false);
const errors    = ref({});

const selectedCategory = computed(() =>
  props.vendorCategories.find(c => c.value === form.value.vendor_category)
);

async function compute() {
  computing.value = true;
  errors.value    = {};
  result.value    = null;

  try {
    const response = await axios.post('/calculator/compute', {
      ...form.value,
      as_of: form.value.as_of || undefined,
    });
    result.value = response.data;
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors ?? {};
    }
  } finally {
    computing.value = false;
  }
}

// ─── Result display helpers ────────────────────────────────────────────────────
const daysText = computed(() => {
  if (!result.value) return '';
  if (result.value.status === 'paid') return 'Fully Paid';
  const d = result.value.days_to_deadline;
  if (d < 0) return `${Math.abs(d)} days overdue`;
  if (d === 0) return 'Due today!';
  return `${d} days remaining`;
});

const daysClass = computed(() => {
  if (!result.value) return '';
  const d = result.value.days_to_deadline;
  if (result.value.status === 'paid') return 'text-green-600';
  if (d < 0) return 'text-red-600';
  if (d <= 3) return 'text-red-500';
  if (d <= 10) return 'text-orange-500';
  return 'text-gray-700';
});

const resultBorderClass = computed(() => {
  if (!result.value) return '';
  const s = result.value.status;
  if (s === 'paid') return 'border-green-300';
  if (s === 'overdue' || s === 'disallowed') return 'border-red-300';
  return 'border-gray-200';
});

const resultHeaderClass = computed(() => {
  if (!result.value) return '';
  const s = result.value.status;
  if (s === 'paid')       return 'bg-green-600 text-white';
  if (s === 'disallowed') return 'bg-red-900 text-white';
  if (s === 'overdue')    return 'bg-red-600 text-white';
  if (s === 'partial')    return 'bg-blue-600 text-white';
  return 'bg-gray-700 text-white';
});

function formatCurrency(val) {
  const n = Number(val) || 0;
  if (n >= 10000000) return '₹' + (n / 10000000).toFixed(2) + ' Cr';
  if (n >= 100000)   return '₹' + (n / 100000).toFixed(2) + ' L';
  if (n >= 1000)     return '₹' + (n / 1000).toFixed(1) + 'K';
  return '₹' + n.toFixed(2);
}

function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
