<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4">
    <Head title="Account Suspended" />

    <div class="max-w-md w-full text-center">

      <!-- Icon -->
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-6"
           :class="reason === 'trial_expired' ? 'bg-yellow-100' : 'bg-red-100'">
        <ClockIcon v-if="reason === 'trial_expired'" class="w-8 h-8 text-yellow-600" />
        <ExclamationTriangleIcon v-else class="w-8 h-8 text-red-600" />
      </div>

      <!-- Message -->
      <h1 class="text-2xl font-bold text-gray-900 mb-2">
        {{ title }}
      </h1>
      <p class="text-gray-500 text-sm mb-6">{{ message }}</p>

      <!-- Trial expired info -->
      <div v-if="reason === 'trial_expired'" class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6 text-left">
        <p class="text-sm font-semibold text-yellow-800 mb-1">Your 14-day trial ended on {{ trial_ends_at }}</p>
        <p class="text-xs text-yellow-700">
          Your data is safe. Upgrade to a paid plan to continue using MSME Payment Risk Tracker.
        </p>
      </div>

      <!-- Plans CTA -->
      <div class="bg-white border border-gray-200 rounded-xl p-5 mb-6 text-left">
        <p class="text-sm font-semibold text-gray-800 mb-3">Choose a plan to reactivate</p>
        <div class="space-y-3">
          <div v-for="plan in plans" :key="plan.name"
               class="flex items-center justify-between border border-gray-100 rounded-lg px-4 py-3">
            <div>
              <p class="text-sm font-semibold text-gray-800">{{ plan.name }}</p>
              <p class="text-xs text-gray-500">{{ plan.description }}</p>
            </div>
            <p class="text-sm font-bold text-indigo-700">₹{{ plan.price }}/mo</p>
          </div>
        </div>
      </div>

      <a
        href="mailto:mailforgobi@gmail.com?subject=MSME Tracker - Upgrade Request"
        class="inline-flex items-center justify-center gap-2 w-full bg-indigo-600 text-white py-3 rounded-xl text-sm font-semibold hover:bg-indigo-700 transition-colors mb-3"
      >
        <EnvelopeIcon class="w-4 h-4" />
        Contact Us to Upgrade
      </a>

      <Link href="/logout" method="post" as="button"
            class="text-sm text-gray-500 hover:text-gray-700 transition-colors">
        Sign out
      </Link>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
  ClockIcon,
  ExclamationTriangleIcon,
  EnvelopeIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  reason:        { type: String, default: 'account_suspended' },
  plan:          { type: String, default: null },
  trial_ends_at: { type: String, default: null },
  status:        { type: String, default: null },
});

const title = computed(() => ({
  trial_expired:         'Your Trial Has Ended',
  subscription_expired:  'Subscription Expired',
  account_suspended:     'Account Suspended',
}[props.reason] ?? 'Access Restricted'));

const message = computed(() => ({
  trial_expired:        'Your 14-day free trial has expired. Upgrade to continue tracking 43B(h) compliance.',
  subscription_expired: 'Your subscription has expired. Please renew to continue.',
  account_suspended:    'Your account has been suspended. Please contact support.',
}[props.reason] ?? 'Your account does not have active access.'));

const plans = [
  { name: 'Starter',  price: '1,500', description: 'Up to 50 vendors' },
  { name: 'Growth',   price: '3,000', description: 'Up to 200 vendors' },
  { name: 'CA Firm',  price: '4,000', description: 'Unlimited vendors, 10 clients' },
];
</script>
