<template>
  <AppLayout title="AI Vendor Classification">
    <div class="mx-auto max-w-5xl space-y-6 p-6">

      <!-- Header -->
      <div class="flex items-start justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">AI Vendor Classification</h1>
          <p class="mt-1 text-sm text-gray-500">
            Model: <span class="font-mono text-violet-700">{{ llmModel }}</span> &nbsp;·&nbsp;
            Auto-apply threshold: <strong>{{ Math.round(threshold * 100) }}%</strong>
          </p>
        </div>

        <button
          type="button"
          @click="runBatch"
          :disabled="batching"
          class="inline-flex items-center gap-2 rounded-md bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-violet-700 disabled:opacity-50"
        >
          <svg v-if="!batching" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
          </svg>
          <svg v-else class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
          </svg>
          {{ batching ? 'Running batch…' : 'Classify All' }}
        </button>
      </div>

      <!-- Batch result summary -->
      <div v-if="batchSummary" class="rounded-lg border border-green-200 bg-green-50 p-4">
        <p class="text-sm font-medium text-green-800">Batch complete</p>
        <p class="mt-1 text-sm text-green-700">
          {{ batchSummary.applied }} auto-applied &nbsp;·&nbsp;
          {{ batchSummary.suggested }} need review &nbsp;·&nbsp;
          {{ batchSummary.failed }} failed
        </p>
      </div>

      <!-- Batch error -->
      <div v-if="batchError" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
        {{ batchError }}
      </div>

      <!-- Empty state -->
      <div v-if="localVendors.length === 0" class="rounded-xl border-2 border-dashed border-gray-200 p-12 text-center">
        <p class="text-lg font-medium text-gray-600">No unclassified vendors</p>
        <p class="mt-1 text-sm text-gray-400">All vendors in your account have been classified.</p>
      </div>

      <!-- Vendor cards -->
      <div v-else class="grid gap-4 sm:grid-cols-2">
        <div
          v-for="vendor in localVendors"
          :key="vendor.id"
          class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm space-y-3"
        >
          <div>
            <p class="font-semibold text-gray-900">{{ vendor.name }}</p>
            <p v-if="vendor.gstin" class="text-xs font-mono text-gray-500 mt-0.5">{{ vendor.gstin }}</p>
            <p v-if="vendor.state" class="text-xs text-gray-400">{{ vendor.state }}</p>
          </div>

          <AiClassifyButton
            :vendor-id="vendor.id"
            :vendor-name="vendor.name"
            @applied="onApplied(vendor.id)"
          />
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import AiClassifyButton from '@/Components/AiClassifyButton.vue'

const props = defineProps({
  vendors:   { type: Array, default: () => [] },
  llmModel:  { type: String, default: '' },
  threshold: { type: Number, default: 0.8 },
})

const localVendors  = ref([...props.vendors])
const batching      = ref(false)
const batchSummary  = ref(null)
const batchError    = ref(null)

function onApplied(vendorId) {
  localVendors.value = localVendors.value.filter(v => v.id !== vendorId)
}

async function runBatch() {
  batching.value     = true
  batchSummary.value = null
  batchError.value   = null

  try {
    const resp = await fetch(route('vendors.ai-classify.batch'), {
      method:  'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept':       'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
      },
      body: JSON.stringify({}),
    })

    const body = await resp.json()

    if (!resp.ok) {
      batchError.value = body.error ?? `Server error (${resp.status})`
      return
    }

    batchSummary.value = body.summary

    // Remove auto-applied vendors from the list
    const appliedIds = new Set(
      (body.results ?? [])
        .filter(r => r.auto_applied)
        .map(r => r.vendor_id)
    )
    localVendors.value = localVendors.value.filter(v => !appliedIds.has(v.id))

  } catch (e) {
    batchError.value = 'Network error — please try again.'
  } finally {
    batching.value = false
  }
}
</script>
