<template>
  <AppLayout title="Upgrade Plan">
    <div class="max-w-5xl mx-auto py-8 px-4">

      <!-- Status banner -->
      <div v-if="isGracePeriod" class="mb-6 bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
        <span class="text-amber-500 text-xl">⚠</span>
        <div>
          <p class="font-semibold text-amber-800">Payment failed — grace period active</p>
          <p class="text-sm text-amber-700 mt-0.5">
            Your account remains accessible until <strong>{{ gracePeriodEndsAt }}</strong>.
            Please update your payment method to avoid service interruption.
          </p>
        </div>
      </div>

      <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold text-gray-900">Choose Your Plan</h1>
        <p class="mt-2 text-gray-500">
          All plans include Section 43B(h) compliance tracking, daily risk recompute, and CA-ready reports.
        </p>
      </div>

      <!-- Plan cards -->
      <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <div
          v-for="plan in plans"
          :key="plan.key"
          class="relative bg-white rounded-2xl border-2 shadow-sm transition-all"
          :class="plan.key === 'professional'
            ? 'border-blue-500 shadow-blue-100'
            : 'border-gray-200'"
        >
          <!-- Popular badge -->
          <div v-if="plan.key === 'professional'"
            class="absolute -top-3 left-1/2 -translate-x-1/2 px-4 py-1 bg-blue-600 text-white text-xs font-bold rounded-full">
            MOST POPULAR
          </div>

          <div class="p-6">
            <h2 class="text-lg font-bold text-gray-900">{{ plan.name }}</h2>
            <div class="mt-2 flex items-baseline gap-1">
              <span class="text-3xl font-extrabold text-gray-900">₹{{ plan.price.toLocaleString('en-IN') }}</span>
              <span class="text-gray-500 text-sm">/month</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">
              {{ plan.vendors === Infinity ? 'Unlimited' : plan.vendors }} vendors ·
              {{ plan.users === Infinity ? 'Unlimited' : plan.users }} users
            </p>

            <ul class="mt-4 space-y-2">
              <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2 text-sm text-gray-600">
                <svg class="w-4 h-4 mt-0.5 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
                {{ feature }}
              </li>
            </ul>

            <button
              @click="startCheckout(plan)"
              :disabled="currentPlan === plan.key && currentStatus === 'active'"
              class="mt-6 w-full py-2.5 rounded-xl text-sm font-semibold transition-colors"
              :class="plan.key === 'professional'
                ? 'bg-blue-600 text-white hover:bg-blue-700 disabled:bg-blue-300'
                : 'bg-gray-900 text-white hover:bg-gray-700 disabled:bg-gray-400'"
            >
              <span v-if="currentPlan === plan.key && currentStatus === 'active'">Current Plan</span>
              <span v-else-if="processingPlan === plan.key">Processing...</span>
              <span v-else>Subscribe — {{ plan.price_text }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Current subscription info -->
      <div v-if="currentStatus" class="mt-8 bg-gray-50 rounded-xl p-4 text-sm text-gray-600">
        <strong>Current plan:</strong> {{ currentPlan }}
        <span v-if="subscriptionEndsAt"> · Renews {{ subscriptionEndsAt }}</span>
        <span v-if="trialEndsAt && currentStatus === 'trial'"> · Trial ends {{ trialEndsAt }}</span>
      </div>

      <!-- GST note -->
      <p class="mt-4 text-center text-xs text-gray-400">
        All prices are exclusive of 18% GST. Billed monthly via Razorpay.
      </p>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  currentPlan:        String,
  currentStatus:      String,
  trialEndsAt:        String,
  subscriptionEndsAt: String,
  gracePeriodEndsAt:  String,
  razorpayKeyId:      String,
  plans:              Array,
})

const processingPlan = ref(null)

const isGracePeriod = computed(() => !!props.gracePeriodEndsAt)

async function startCheckout(plan) {
  processingPlan.value = plan.key

  try {
    const { data } = await axios.post(route('subscription.subscribe', plan.key))

    const options = {
      key:             props.razorpayKeyId,
      subscription_id: data.subscription_id,
      name:            'MSME 43B(h) Tracker',
      description:     `${plan.name} Plan — ${plan.price_text}`,
      image:           '/logo.svg',
      prefill: {
        name:    data.name,
        email:   data.email,
        contact: data.contact,
      },
      theme: { color: '#2563eb' },
      handler: function () {
        // Razorpay webhook handles activation; reload to show updated status
        window.location.reload()
      },
    }

    const rzp = new window.Razorpay(options)
    rzp.on('payment.failed', () => {
      processingPlan.value = null
    })
    rzp.open()
  } catch (err) {
    alert('Could not initiate payment. Please try again.')
    processingPlan.value = null
  }
}
</script>
