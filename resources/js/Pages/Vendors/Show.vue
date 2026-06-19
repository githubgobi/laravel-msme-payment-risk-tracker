<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Page header -->
    <div class="bg-white border-b border-gray-200">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center gap-3 mb-4">
          <Link :href="route('vendors.index')" class="text-sm text-gray-500 hover:text-gray-700">
            ← Vendors
          </Link>
        </div>
        <div class="flex items-start justify-between">
          <div>
            <div class="flex items-center gap-3">
              <h1 class="text-2xl font-bold text-gray-900">{{ vendor.name }}</h1>
              <span :class="['inline-flex items-center px-3 py-1 rounded-full text-sm font-medium', categoryBadgeClass(vendor.category)]">
                {{ vendor.category_label }}
              </span>
              <span v-if="!vendor.is_active" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                Inactive
              </span>
            </div>
            <p class="mt-1 text-sm text-gray-500 font-mono">
              <span v-if="vendor.gstin">GSTIN: {{ vendor.gstin }}</span>
              <span v-if="vendor.gstin && vendor.udyam_number"> · </span>
              <span v-if="vendor.udyam_number">Udyam: {{ vendor.udyam_number }}</span>
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
           class="rounded-lg bg-green-50 border border-green-200 p-4 flex items-start gap-3">
        <CheckCircleIcon class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" />
        <p class="text-sm text-green-800">{{ $page.props.flash.success }}</p>
      </div>

      <!-- Stat cards -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
          <div class="text-2xl font-bold text-gray-900">{{ stats.total_invoices }}</div>
          <div class="text-xs text-gray-500 mt-1">Total Invoices</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
          <div :class="['text-2xl font-bold', stats.at_risk_invoices > 0 ? 'text-red-600' : 'text-gray-900']">
            {{ stats.at_risk_invoices }}
          </div>
          <div class="text-xs text-gray-500 mt-1">At Risk</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
          <div :class="['text-xl font-bold', stats.total_disallowance > 0 ? 'text-red-600' : 'text-gray-900']">
            {{ stats.total_disallowance > 0 ? '₹' + formatCurrency(stats.total_disallowance) : '—' }}
          </div>
          <div class="text-xs text-gray-500 mt-1">Disallowance</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
          <div :class="['text-xl font-bold', stats.total_interest > 0 ? 'text-orange-600' : 'text-gray-900']">
            {{ stats.total_interest > 0 ? '₹' + formatCurrency(stats.total_interest) : '—' }}
          </div>
          <div class="text-xs text-gray-500 mt-1">Interest</div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Edit form -->
        <div class="lg:col-span-2 space-y-6">
          <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-5">Vendor Details</h2>
            <form @submit.prevent="submitUpdate" class="space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                  <input v-model="form.name" type="text" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                  <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                  <select v-model="form.category" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option v-for="cat in categories" :key="cat.value" :value="cat.value">
                      {{ cat.label }}
                    </option>
                  </select>
                  <p v-if="form.errors.category" class="mt-1 text-xs text-red-600">{{ form.errors.category }}</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">GSTIN</label>
                  <input v-model="form.gstin" type="text" maxlength="15" placeholder="22AAAAA0000A1Z5"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 uppercase" />
                  <p v-if="form.errors.gstin" class="mt-1 text-xs text-red-600">{{ form.errors.gstin }}</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Udyam Number</label>
                  <input v-model="form.udyam_number" type="text" placeholder="UDYAM-TN-00-0000000"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 uppercase" />
                  <p v-if="form.errors.udyam_number" class="mt-1 text-xs text-red-600">{{ form.errors.udyam_number }}</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">PAN</label>
                  <input v-model="form.pan" type="text" maxlength="10" placeholder="AAAAA0000A"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 uppercase" />
                  <p v-if="form.errors.pan" class="mt-1 text-xs text-red-600">{{ form.errors.pan }}</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                  <input v-model="form.phone" type="text" maxlength="15"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                  <p v-if="form.errors.phone" class="mt-1 text-xs text-red-600">{{ form.errors.phone }}</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                  <input v-model="form.email" type="email"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                  <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
                  <input v-model="form.contact_person" type="text"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                  <input v-model="form.state" type="text"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                  <input v-model="form.city" type="text"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                </div>

                <div class="sm:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                  <textarea v-model="form.address" rows="2"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>

                <div class="sm:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                  <textarea v-model="form.notes" rows="2" maxlength="1000"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>

                <div class="flex items-center gap-2">
                  <input type="checkbox" id="is_active" v-model="form.is_active"
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                  <label for="is_active" class="text-sm font-medium text-gray-700">Active vendor</label>
                </div>
              </div>

              <div class="flex justify-end pt-2">
                <button
                  type="submit"
                  :disabled="form.processing"
                  class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </button>
              </div>
            </form>
          </div>

          <!-- Recent invoices -->
          <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
              <h2 class="text-base font-semibold text-gray-900">Recent Invoices</h2>
            </div>
            <table class="min-w-full divide-y divide-gray-100">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount (₹)</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                  <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase hidden md:table-cell">Exposure (₹)</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="inv in invoices" :key="inv.id">
                  <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ inv.invoice_number }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ inv.invoice_date }}</td>
                  <td class="px-4 py-3 text-sm text-gray-800 text-right">{{ formatCurrency(inv.amount) }}</td>
                  <td class="px-4 py-3">
                    <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', statusBadgeClass(inv.status)]">
                      {{ inv.status_label }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right hidden md:table-cell">
                    <span :class="(inv.disallowance_amount + inv.interest_amount) > 0 ? 'text-red-600 text-sm font-medium' : 'text-gray-400 text-sm'">
                      {{ (inv.disallowance_amount + inv.interest_amount) > 0
                          ? formatCurrency(inv.disallowance_amount + inv.interest_amount)
                          : '—' }}
                    </span>
                  </td>
                </tr>
                <tr v-if="invoices.length === 0">
                  <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">No invoices found.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Udyam verification sidebar -->
        <div class="space-y-4">
          <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-base font-semibold text-gray-900 mb-3">Udyam Verification</h2>

            <div v-if="vendor.verification_source === 'api'"
                 class="rounded-lg bg-green-50 border border-green-200 p-3 mb-4">
              <div class="flex items-center gap-2 text-green-700 text-sm font-medium">
                <CheckBadgeIcon class="h-4 w-4 flex-shrink-0" />
                API Verified
              </div>
              <p class="text-xs text-green-600 mt-1">Verified on {{ vendor.udyam_verified_at }}</p>
            </div>

            <p class="text-sm text-gray-600 mb-4">
              Enter the Udyam registration number to verify the enterprise type against the government database.
            </p>

            <div class="space-y-3">
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Udyam Number</label>
                <input
                  v-model="udyamInput"
                  type="text"
                  placeholder="UDYAM-TN-00-0000000"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 uppercase"
                  @input="udyamInput = udyamInput.toUpperCase()"
                />
              </div>
              <button
                @click="runVerification"
                :disabled="verifyLoading || !udyamInput"
                class="w-full py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ verifyLoading ? 'Verifying...' : 'Verify via API' }}
              </button>
            </div>

            <!-- Verification result -->
            <div v-if="verifyResult" class="mt-4">
              <div v-if="verifyResult.verified"
                   class="rounded-lg bg-green-50 border border-green-200 p-3 space-y-1">
                <p class="text-sm font-semibold text-green-800">✓ Verified</p>
                <p class="text-sm text-green-700">{{ verifyResult.enterprise_name }}</p>
                <p class="text-sm text-green-700">Category: <strong>{{ verifyResult.category_label }}</strong></p>
                <p v-if="verifyResult.registered_at" class="text-xs text-green-600">
                  Registered: {{ verifyResult.registered_at }}
                </p>
                <p class="text-xs text-green-600 mt-2">Category and Udyam number applied to this vendor.</p>
              </div>
              <div v-else class="rounded-lg bg-yellow-50 border border-yellow-200 p-3">
                <p class="text-sm font-semibold text-yellow-800">
                  {{ verifyResult.api_available ? 'Not Found' : 'API Not Configured' }}
                </p>
                <p class="text-sm text-yellow-700 mt-1">{{ verifyResult.error_message }}</p>
              </div>
            </div>
          </div>

          <!-- Category guide -->
          <div class="bg-blue-50 rounded-xl border border-blue-200 p-4">
            <h3 class="text-sm font-semibold text-blue-900 mb-2">43B(h) Classification Guide</h3>
            <div class="space-y-1.5 text-xs text-blue-800">
              <p><strong>Micro & Small</strong> → Subject to 43B(h)</p>
              <p><strong>Deadline:</strong> 15 days (no agreement) / 45 days (with agreement)</p>
              <p><strong>Interest:</strong> 3× RBI bank rate, compounded monthly</p>
              <p><strong>Medium & Large</strong> → Not subject to 43B(h)</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { CheckCircleIcon, CheckBadgeIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  vendor:     Object,
  stats:      Object,
  invoices:   Array,
  categories: Array,
})

const form = useForm({
  name:           props.vendor.name,
  category:       props.vendor.category,
  gstin:          props.vendor.gstin ?? '',
  udyam_number:   props.vendor.udyam_number ?? '',
  pan:            props.vendor.pan ?? '',
  phone:          props.vendor.phone ?? '',
  email:          props.vendor.email ?? '',
  state:          props.vendor.state ?? '',
  city:           props.vendor.city ?? '',
  address:        props.vendor.address ?? '',
  contact_person: props.vendor.contact_person ?? '',
  notes:          props.vendor.notes ?? '',
  is_active:      props.vendor.is_active,
})

function submitUpdate() {
  form.put(route('vendors.update', props.vendor.id), { preserveScroll: true })
}

const udyamInput  = ref(props.vendor.udyam_number ?? '')
const verifyLoading = ref(false)
const verifyResult  = ref(null)

async function runVerification() {
  if (!udyamInput.value) return
  verifyLoading.value = true
  verifyResult.value  = null

  try {
    const response = await fetch(route('udyam.verify'), {
      method:  'POST',
      headers: {
        'Content-Type':     'application/json',
        'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]').content,
        'Accept':           'application/json',
      },
      body: JSON.stringify({
        udyam_number: udyamInput.value,
        vendor_id:    props.vendor.id,
      }),
    })

    verifyResult.value = await response.json()

    if (verifyResult.value.verified) {
      form.udyam_number = udyamInput.value
      form.category     = verifyResult.value.category
    }
  } catch (e) {
    verifyResult.value = {
      verified:      false,
      api_available: false,
      error_message: 'Network error. Please try again.',
    }
  } finally {
    verifyLoading.value = false
  }
}

function categoryBadgeClass(category) {
  const map = {
    micro:        'bg-blue-100 text-blue-800',
    small:        'bg-indigo-100 text-indigo-800',
    medium:       'bg-purple-100 text-purple-800',
    large:        'bg-gray-100 text-gray-800',
    unclassified: 'bg-yellow-100 text-yellow-800',
  }
  return map[category] ?? 'bg-gray-100 text-gray-700'
}

function statusBadgeClass(status) {
  const map = {
    paid:      'bg-green-100 text-green-800',
    overdue:   'bg-red-100 text-red-800',
    pending:   'bg-yellow-100 text-yellow-800',
    partial:   'bg-orange-100 text-orange-800',
  }
  return map[status] ?? 'bg-gray-100 text-gray-700'
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(amount)
}
</script>
