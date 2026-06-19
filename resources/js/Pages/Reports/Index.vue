<template>
  <AppLayout title="Annual Reports">
    <div class="max-w-3xl mx-auto py-10 px-4">
      <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Section 43B(h) Annual Reports</h1>
        <p class="mt-1 text-gray-500">
          Download CA-grade PDF or Excel summaries of your MSME vendor payment exposure for each financial year.
        </p>
      </div>

      <!-- Info banner -->
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8 flex gap-3">
        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
        </svg>
        <div class="text-sm text-blue-800">
          <p class="font-medium">Interest at 3× RBI Bank Rate, compounded monthly</p>
          <p class="mt-0.5 text-blue-600">Reports include vendor-wise disallowance amounts under Section 43B(h) of the Income Tax Act, 1961. Share with your CA before ITR filing.</p>
        </div>
      </div>

      <!-- FY rows -->
      <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100 shadow-sm">
        <div
          v-for="fy in years"
          :key="fy"
          class="flex items-center justify-between px-6 py-5 hover:bg-gray-50 transition-colors"
        >
          <div>
            <p class="font-semibold text-gray-900">FY {{ fy }}–{{ fy + 1 }}</p>
            <p class="text-sm text-gray-400 mt-0.5">{{ fyLabel(fy) }}</p>
          </div>
          <div class="flex gap-3">
            <!-- PDF -->
            <a
              :href="route('reports.pdf', fy)"
              target="_blank"
              class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-red-200 text-red-700 bg-red-50 hover:bg-red-100 text-sm font-medium transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
              </svg>
              PDF
            </a>

            <!-- Excel -->
            <a
              :href="route('reports.excel', fy)"
              class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-green-200 text-green-700 bg-green-50 hover:bg-green-100 text-sm font-medium transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              Excel
            </a>
          </div>
        </div>
      </div>

      <p class="text-xs text-gray-400 mt-6 text-center">
        Reports are generated on demand from your current invoice and payment data. Figures are not audited.
      </p>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  years:     { type: Array,  required: true },
  currentFy: { type: Number, required: true },
})

function fyLabel(fy) {
  const months = ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar']
  return `1 Apr ${fy} – 31 Mar ${fy + 1}`
}
</script>
