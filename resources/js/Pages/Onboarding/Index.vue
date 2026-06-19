<template>
  <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-start py-12 px-4">
    <div class="w-full max-w-2xl">
      <!-- Header -->
      <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-indigo-100 mb-4">
          <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">Welcome to MSME Risk Tracker</h1>
        <p class="mt-2 text-gray-500">
          Complete these steps to set up <strong>{{ tenantName }}</strong> and start tracking Section 43B(h) compliance.
        </p>
      </div>

      <!-- Progress bar -->
      <div class="mb-8">
        <div class="flex items-center justify-between text-sm text-gray-500 mb-2">
          <span>{{ doneCount }} of {{ steps.length }} steps complete</span>
          <span>{{ progressPct }}%</span>
        </div>
        <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
          <div
            class="h-full bg-indigo-600 transition-all duration-500"
            :style="{ width: progressPct + '%' }"
          ></div>
        </div>
      </div>

      <!-- Steps -->
      <div class="space-y-3 mb-8">
        <a
          v-for="step in steps"
          :key="step.key"
          :href="step.href"
          class="flex items-start gap-4 bg-white rounded-xl border p-5 hover:shadow-md transition-shadow group"
          :class="step.done ? 'border-green-200 bg-green-50' : 'border-gray-200'"
        >
          <!-- Icon -->
          <div
            class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center mt-0.5"
            :class="step.done ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400 group-hover:bg-indigo-50 group-hover:text-indigo-500'"
          >
            <!-- Done checkmark -->
            <svg v-if="step.done" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
            </svg>
            <!-- Pending circle -->
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="9" stroke-width="2" />
            </svg>
          </div>

          <!-- Text -->
          <div class="flex-1 min-w-0">
            <p class="font-medium text-gray-900" :class="step.done && 'line-through text-gray-400'">
              {{ step.title }}
            </p>
            <p class="text-sm text-gray-500 mt-0.5">{{ step.description }}</p>
          </div>

          <!-- Arrow -->
          <svg
            v-if="!step.done"
            class="flex-shrink-0 w-5 h-5 text-gray-300 group-hover:text-indigo-400 mt-1"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </a>
      </div>

      <!-- Complete button -->
      <div class="text-center">
        <form @submit.prevent="completeOnboarding">
          <button
            type="submit"
            :disabled="!allComplete || submitting"
            class="px-8 py-3 rounded-lg font-semibold text-white transition-colors"
            :class="allComplete
              ? 'bg-indigo-600 hover:bg-indigo-700 cursor-pointer'
              : 'bg-gray-300 cursor-not-allowed text-gray-500'"
          >
            <span v-if="submitting">Setting up your account…</span>
            <span v-else-if="allComplete">Complete Setup & Go to Dashboard →</span>
            <span v-else>Complete all steps above to continue</span>
          </button>
        </form>

        <p class="mt-4 text-sm text-gray-400">
          You can always come back here later via the Help menu.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  tenantName:  { type: String, required: true },
  steps:       { type: Array,  required: true },
  allComplete: { type: Boolean, default: false },
})

const submitting = ref(false)

const doneCount = computed(() => props.steps.filter(s => s.done).length)
const progressPct = computed(() => Math.round((doneCount.value / props.steps.length) * 100))

function completeOnboarding() {
  if (!props.allComplete || submitting.value) return
  submitting.value = true
  router.post(route('onboarding.complete'), {}, {
    onFinish: () => { submitting.value = false },
  })
}
</script>
