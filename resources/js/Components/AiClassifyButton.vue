<template>
  <div class="inline-flex flex-col gap-2">
    <!-- Trigger button -->
    <button
      v-if="!result"
      type="button"
      :disabled="loading"
      @click="suggest"
      class="inline-flex items-center gap-1.5 rounded-md bg-violet-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-violet-700 disabled:opacity-50 disabled:cursor-not-allowed"
    >
      <svg v-if="!loading" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
      </svg>
      <svg v-else class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
      </svg>
      {{ loading ? 'Analysing…' : 'AI Suggest' }}
    </button>

    <!-- Error -->
    <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

    <!-- Result card -->
    <div v-if="result" class="rounded-lg border border-violet-200 bg-violet-50 p-3 text-sm space-y-2 w-64">
      <div class="flex items-center justify-between">
        <span class="font-semibold text-violet-800">AI Suggestion</span>
        <span
          :class="confidenceBadgeClass"
          class="rounded-full px-2 py-0.5 text-xs font-medium"
        >{{ confidenceLabel }}</span>
      </div>

      <div class="text-gray-700">
        Category: <strong>{{ result.category_label }}</strong>
      </div>

      <p class="text-xs text-gray-500 italic">{{ result.reasoning }}</p>

      <div class="flex gap-2 pt-1">
        <button
          type="button"
          @click="apply"
          :disabled="applying"
          class="flex-1 rounded bg-violet-600 px-2 py-1 text-xs font-medium text-white hover:bg-violet-700 disabled:opacity-50"
        >{{ applying ? 'Applying…' : 'Confirm' }}</button>
        <button
          type="button"
          @click="reset"
          class="flex-1 rounded bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200"
        >Dismiss</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  vendorId:   { type: Number, required: true },
  vendorName: { type: String, required: true },
})

const emit = defineEmits(['applied'])

const loading  = ref(false)
const applying = ref(false)
const result   = ref(null)
const error    = ref(null)

const confidenceLabel = computed(() => {
  if (!result.value) return ''
  const pct = Math.round(result.value.confidence * 100)
  return `${pct}% confident`
})

const confidenceBadgeClass = computed(() => {
  if (!result.value) return ''
  const c = result.value.confidence
  if (c >= 0.90) return 'bg-green-100 text-green-800'
  if (c >= 0.75) return 'bg-yellow-100 text-yellow-800'
  return 'bg-red-100 text-red-800'
})

async function suggest() {
  loading.value = true
  error.value   = null
  result.value  = null

  try {
    const resp = await fetch(route('vendors.ai-classify.suggest', props.vendorId), {
      method:  'POST',
      headers: {
        'Content-Type':     'application/json',
        'Accept':           'application/json',
        'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]')?.content ?? '',
      },
      body: JSON.stringify({}),
    })

    if (!resp.ok) {
      const body = await resp.json().catch(() => ({}))
      error.value = body.error ?? `Server error (${resp.status})`
      return
    }

    result.value = await resp.json()
  } catch (e) {
    error.value = 'Network error — please try again.'
  } finally {
    loading.value = false
  }
}

function apply() {
  applying.value = true

  router.post(
    route('vendors.ai-classify.apply', props.vendorId),
    {
      category:   result.value.category,
      confidence: result.value.confidence,
      reasoning:  result.value.reasoning,
    },
    {
      onSuccess: () => {
        emit('applied', { vendorId: props.vendorId, category: result.value.category })
        reset()
      },
      onFinish: () => { applying.value = false },
    }
  )
}

function reset() {
  result.value = null
  error.value  = null
}
</script>
