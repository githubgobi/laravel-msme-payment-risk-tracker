<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Page header -->
    <div class="bg-white border-b border-gray-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Vendors</h1>
            <p class="mt-1 text-sm text-gray-500">
              Classify your MSME vendors to compute 43B(h) tax risk accurately.
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
      <!-- Flash message -->
      <div v-if="$page.props.flash?.success"
           class="rounded-lg bg-green-50 border border-green-200 p-4 flex items-start gap-3">
        <CheckCircleIcon class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" />
        <p class="text-sm text-green-800">{{ $page.props.flash.success }}</p>
      </div>

      <!-- Summary chips -->
      <div class="flex flex-wrap gap-3">
        <button
          v-for="chip in summaryChips"
          :key="chip.key"
          @click="setFilter('category', chip.value)"
          :class="[
            'inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium border transition-colors',
            activeFilter === chip.value
              ? 'bg-indigo-600 text-white border-indigo-600'
              : 'bg-white text-gray-700 border-gray-300 hover:border-indigo-400 hover:text-indigo-600',
          ]"
        >
          <span :class="['w-2 h-2 rounded-full', chip.dot]"></span>
          {{ chip.label }}
          <span :class="[
            'text-xs rounded-full px-2 py-0.5 font-semibold',
            activeFilter === chip.value ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600'
          ]">{{ chip.count }}</span>
        </button>
      </div>

      <!-- Search + bulk classify bar -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
          <!-- Search -->
          <div class="relative flex-1 max-w-md">
            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input
              v-model="searchInput"
              @keydown.enter="applySearch"
              type="text"
              placeholder="Search by name or GSTIN..."
              class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            />
          </div>

          <!-- Bulk classify section -->
          <div v-if="selectedIds.length > 0" class="flex items-center gap-3">
            <span class="text-sm text-gray-600 font-medium">
              {{ selectedIds.length }} selected
            </span>
            <select
              v-model="bulkCategory"
              class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500"
            >
              <option value="">Select category...</option>
              <option v-for="cat in categories" :key="cat.value" :value="cat.value">
                {{ cat.label }}
              </option>
            </select>
            <button
              @click="submitBulkClassify"
              :disabled="!bulkCategory || bulkLoading"
              class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ bulkLoading ? 'Classifying...' : 'Apply' }}
            </button>
            <button
              @click="selectedIds = []"
              class="text-sm text-gray-500 hover:text-gray-700"
            >
              Clear
            </button>
          </div>
        </div>
      </div>

      <!-- Vendors table -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 w-10">
                <input
                  type="checkbox"
                  :checked="allPageSelected"
                  @change="togglePageSelect"
                  class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                />
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Vendor
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Category
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">
                GSTIN / Udyam
              </th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">
                Invoices
              </th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">
                Tax Exposure (₹)
              </th>
              <th class="px-4 py-3 w-10"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr
              v-for="vendor in vendors.data"
              :key="vendor.id"
              class="hover:bg-gray-50 transition-colors"
            >
              <td class="px-4 py-3">
                <input
                  type="checkbox"
                  :value="vendor.id"
                  v-model="selectedIds"
                  class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                />
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <div>
                    <div class="text-sm font-medium text-gray-900">{{ vendor.name }}</div>
                    <div v-if="!vendor.is_active" class="text-xs text-red-500">Inactive</div>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3">
                <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', categoryBadgeClass(vendor.category)]">
                  {{ vendor.category_label }}
                </span>
                <div v-if="vendor.verification_source === 'api'" class="mt-0.5">
                  <span class="inline-flex items-center gap-1 text-xs text-green-600">
                    <CheckBadgeIcon class="h-3.5 w-3.5" />
                    API verified {{ vendor.udyam_verified_at }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-3 hidden sm:table-cell">
                <div class="text-sm text-gray-700 font-mono">{{ vendor.gstin || '—' }}</div>
                <div v-if="vendor.udyam_number" class="text-xs text-gray-500 font-mono">{{ vendor.udyam_number }}</div>
              </td>
              <td class="px-4 py-3 text-right hidden md:table-cell">
                <span class="text-sm text-gray-700">{{ vendor.invoice_count }}</span>
              </td>
              <td class="px-4 py-3 text-right hidden md:table-cell">
                <span :class="vendor.total_exposure > 0 ? 'text-red-600 font-semibold text-sm' : 'text-gray-400 text-sm'">
                  {{ vendor.total_exposure > 0 ? formatCurrency(vendor.total_exposure) : '—' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <Link
                  :href="route('vendors.show', vendor.id)"
                  class="text-indigo-600 hover:text-indigo-800 text-sm font-medium"
                >
                  View →
                </Link>
              </td>
            </tr>
            <tr v-if="vendors.data.length === 0">
              <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500">
                No vendors found.
                <span v-if="filters.search || filters.category">
                  <button @click="clearFilters" class="text-indigo-600 hover:text-indigo-800 ml-1">Clear filters</button>
                </span>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="vendors.last_page > 1" class="border-t border-gray-200 px-4 py-3 flex items-center justify-between">
          <p class="text-sm text-gray-500">
            Showing {{ vendors.from }}–{{ vendors.to }} of {{ vendors.total }} vendors
          </p>
          <div class="flex gap-2">
            <Link
              v-if="vendors.prev_page_url"
              :href="vendors.prev_page_url"
              class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50"
            >
              ← Previous
            </Link>
            <Link
              v-if="vendors.next_page_url"
              :href="vendors.next_page_url"
              class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50"
            >
              Next →
            </Link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import {
  MagnifyingGlassIcon,
  CheckCircleIcon,
  CheckBadgeIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  vendors:    Object,
  summary:    Object,
  filters:    Object,
  categories: Array,
})

const searchInput  = ref(props.filters.search ?? '')
const activeFilter = ref(props.filters.category ?? '')
const selectedIds  = ref([])
const bulkCategory = ref('')
const bulkLoading  = ref(false)

const summaryChips = computed(() => [
  { key: 'all',          value: '',             label: 'All',          count: props.summary.total,        dot: 'bg-gray-400' },
  { key: 'unclassified', value: 'unclassified', label: 'Unclassified', count: props.summary.unclassified, dot: 'bg-yellow-400' },
  { key: 'micro',        value: 'micro',        label: 'Micro',        count: props.summary.micro,        dot: 'bg-blue-400' },
  { key: 'small',        value: 'small',        label: 'Small',        count: props.summary.small,        dot: 'bg-indigo-400' },
  { key: 'medium',       value: 'medium',       label: 'Medium',       count: props.summary.medium,       dot: 'bg-purple-400' },
  { key: 'large',        value: 'large',        label: 'Large',        count: props.summary.large,        dot: 'bg-gray-500' },
])

const allPageSelected = computed(() =>
  props.vendors.data.length > 0 &&
  props.vendors.data.every(v => selectedIds.value.includes(v.id))
)

function togglePageSelect(e) {
  if (e.target.checked) {
    props.vendors.data.forEach(v => {
      if (!selectedIds.value.includes(v.id)) selectedIds.value.push(v.id)
    })
  } else {
    const pageIds = props.vendors.data.map(v => v.id)
    selectedIds.value = selectedIds.value.filter(id => !pageIds.includes(id))
  }
}

function setFilter(key, value) {
  activeFilter.value = value
  router.get(route('vendors.index'), {
    search:   searchInput.value,
    category: value,
  }, { preserveState: true, replace: true })
}

function applySearch() {
  router.get(route('vendors.index'), {
    search:   searchInput.value,
    category: activeFilter.value,
  }, { preserveState: true, replace: true })
}

function clearFilters() {
  searchInput.value  = ''
  activeFilter.value = ''
  router.get(route('vendors.index'), {}, { replace: true })
}

const bulkForm = useForm({ vendor_ids: [], category: '' })

function submitBulkClassify() {
  if (!bulkCategory.value || selectedIds.value.length === 0) return
  bulkLoading.value = true
  bulkForm.vendor_ids = [...selectedIds.value]
  bulkForm.category   = bulkCategory.value
  bulkForm.post(route('vendors.bulk-classify'), {
    preserveScroll: true,
    onSuccess: () => {
      selectedIds.value  = []
      bulkCategory.value = ''
      bulkLoading.value  = false
    },
    onError: () => { bulkLoading.value = false },
  })
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

function formatCurrency(amount) {
  return new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(amount)
}
</script>
