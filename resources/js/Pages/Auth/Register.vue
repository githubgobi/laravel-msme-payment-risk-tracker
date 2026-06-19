<template>
  <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-blue-50 flex items-center justify-center px-4 py-12">
    <Head title="Create Account" />

    <div class="w-full max-w-lg">
      <!-- Logo / Brand -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-12 h-12 bg-indigo-600 rounded-xl mb-4">
          <ShieldCheckIcon class="w-7 h-7 text-white" />
        </div>
        <h1 class="text-2xl font-bold text-gray-900">MSME Payment Risk Tracker</h1>
        <p class="text-gray-500 text-sm mt-1">Start your 14-day free trial — no credit card required</p>
      </div>

      <!-- Card -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-6">Create your account</h2>

        <form @submit.prevent="submit" class="space-y-4">

          <!-- Business Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Business Name <span class="text-red-500">*</span></label>
            <input
              v-model="form.business_name"
              type="text"
              placeholder="Arjun Textiles Pvt Ltd"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              :class="{ 'border-red-400': errors.business_name }"
            />
            <p v-if="errors.business_name" class="text-xs text-red-600 mt-1">{{ errors.business_name }}</p>
          </div>

          <!-- Your Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Your Name <span class="text-red-500">*</span></label>
            <input
              v-model="form.name"
              type="text"
              placeholder="Rajesh Kumar"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              :class="{ 'border-red-400': errors.name }"
            />
            <p v-if="errors.name" class="text-xs text-red-600 mt-1">{{ errors.name }}</p>
          </div>

          <!-- Email -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Work Email <span class="text-red-500">*</span></label>
            <input
              v-model="form.email"
              type="email"
              placeholder="rajesh@arjuntextiles.com"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              :class="{ 'border-red-400': errors.email }"
            />
            <p v-if="errors.email" class="text-xs text-red-600 mt-1">{{ errors.email }}</p>
          </div>

          <!-- Password row -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
              <input
                v-model="form.password"
                type="password"
                placeholder="Min 8 characters"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                :class="{ 'border-red-400': errors.password }"
              />
              <p v-if="errors.password" class="text-xs text-red-600 mt-1">{{ errors.password }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password <span class="text-red-500">*</span></label>
              <input
                v-model="form.password_confirmation"
                type="password"
                placeholder="Repeat password"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              />
            </div>
          </div>

          <!-- Optional fields -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-gray-400 font-normal">(optional)</span></label>
              <input
                v-model="form.phone"
                type="tel"
                placeholder="+919876543210"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">GSTIN <span class="text-gray-400 font-normal">(optional)</span></label>
              <input
                v-model="form.gstin"
                type="text"
                placeholder="29ABCDE1234F1Z5"
                maxlength="15"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                :class="{ 'border-red-400': errors.gstin }"
              />
              <p v-if="errors.gstin" class="text-xs text-red-600 mt-1">{{ errors.gstin }}</p>
            </div>
          </div>

          <!-- What you get -->
          <div class="bg-indigo-50 rounded-lg p-3 text-xs text-indigo-800 space-y-1">
            <p class="font-semibold mb-1">14-day free trial includes:</p>
            <p>✓ Up to 50 vendors, unlimited invoices</p>
            <p>✓ 43B(h) risk computation on every invoice</p>
            <p>✓ Email alerts before payment deadlines</p>
            <p>✓ CSV &amp; Tally XML import</p>
          </div>

          <button
            type="submit"
            :disabled="processing"
            class="w-full bg-indigo-600 text-white py-2.5 rounded-lg text-sm font-semibold hover:bg-indigo-700 disabled:opacity-60 transition-colors"
          >
            {{ processing ? 'Creating your account...' : 'Start Free Trial' }}
          </button>
        </form>

        <p class="text-center text-sm text-gray-500 mt-4">
          Already have an account?
          <Link href="/login" class="text-indigo-600 hover:underline font-medium">Sign in</Link>
        </p>
      </div>

      <p class="text-center text-xs text-gray-400 mt-4">
        By signing up you agree to our Terms of Service and Privacy Policy.
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ShieldCheckIcon } from '@heroicons/vue/24/outline';

const form = ref({
  business_name:        '',
  name:                 '',
  email:                '',
  password:             '',
  password_confirmation: '',
  phone:                '',
  gstin:                '',
});

const processing = ref(false);
const errors     = ref({});

function submit() {
  processing.value = true;
  errors.value     = {};

  router.post('/register', form.value, {
    onError:  (e) => { processing.value = false; errors.value = e; },
    onFinish: ()  => { processing.value = false; },
  });
}
</script>
