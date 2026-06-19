<template>
  <AppLayout title="Add Vendor">
    <div class="max-w-2xl mx-auto py-8 px-4">
      <!-- Header -->
      <div class="mb-6">
        <Link :href="route('vendors.index')" class="text-sm text-gray-500 hover:text-gray-700">
          &larr; Back to Vendors
        </Link>
        <h1 class="mt-2 text-2xl font-bold text-gray-900">Add Vendor Manually</h1>
        <p class="mt-1 text-sm text-gray-500">
          Vendor will be saved as unverified until Udyam number is confirmed via API.
        </p>
      </div>

      <form @submit.prevent="submit" class="bg-white rounded-xl shadow-sm border border-gray-200 divide-y divide-gray-100">

        <!-- Basic Info -->
        <div class="p-6 space-y-4">
          <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Basic Information</h2>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Vendor Name <span class="text-red-500">*</span></label>
            <input
              v-model="form.name"
              type="text"
              placeholder="e.g. Rajan Enterprises"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              :class="{ 'border-red-400': errors.name }"
            />
            <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
          </div>

          <!-- Category -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">MSME Category <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
              <button
                v-for="cat in categories"
                :key="cat.value"
                type="button"
                @click="form.category = cat.value"
                class="px-3 py-2 rounded-lg text-sm font-medium border transition-colors"
                :class="form.category === cat.value
                  ? 'bg-blue-600 text-white border-blue-600'
                  : 'bg-white text-gray-700 border-gray-300 hover:border-blue-400'"
              >
                {{ cat.label }}
              </button>
            </div>
            <p v-if="errors.category" class="mt-1 text-xs text-red-600">{{ errors.category }}</p>
            <p class="mt-2 text-xs text-gray-500">
              Section 43B(h) disallowance applies to Micro, Small &amp; Medium vendors only.
            </p>
          </div>
        </div>

        <!-- Tax IDs -->
        <div class="p-6 space-y-4">
          <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Tax Identifiers</h2>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">GSTIN</label>
              <input
                v-model="form.gstin"
                type="text"
                placeholder="27AABCU9603R1ZX"
                maxlength="15"
                class="w-full border rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-400': errors.gstin }"
                @input="form.gstin = form.gstin.toUpperCase()"
              />
              <p v-if="errors.gstin" class="mt-1 text-xs text-red-600">{{ errors.gstin }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">PAN</label>
              <input
                v-model="form.pan"
                type="text"
                placeholder="ABCDE1234F"
                maxlength="10"
                class="w-full border rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-400': errors.pan }"
                @input="form.pan = form.pan.toUpperCase()"
              />
              <p v-if="errors.pan" class="mt-1 text-xs text-red-600">{{ errors.pan }}</p>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Udyam Registration Number</label>
            <input
              v-model="form.udyam_number"
              type="text"
              placeholder="UDYAM-MH-01-0001234"
              class="w-full border rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              :class="{ 'border-red-400': errors.udyam_number }"
              @input="form.udyam_number = form.udyam_number.toUpperCase()"
            />
            <p v-if="errors.udyam_number" class="mt-1 text-xs text-red-600">{{ errors.udyam_number }}</p>
            <p class="mt-1 text-xs text-gray-500">
              Enter to pre-fill category. You can verify via Udyam API from the vendor detail page.
            </p>
          </div>
        </div>

        <!-- Contact -->
        <div class="p-6 space-y-4">
          <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Contact Details <span class="text-gray-400 font-normal">(Optional)</span></h2>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Contact Name</label>
              <input v-model="form.contact_name" type="text" placeholder="e.g. Ramesh Kumar"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Contact Email</label>
              <input v-model="form.contact_email" type="email" placeholder="vendor@example.com"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-400': errors.contact_email }" />
              <p v-if="errors.contact_email" class="mt-1 text-xs text-red-600">{{ errors.contact_email }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Contact Phone</label>
              <input v-model="form.contact_phone" type="tel" placeholder="+91 98765 43210"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
              <input v-model="form.city" type="text" placeholder="e.g. Mumbai"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
            <textarea v-model="form.address" rows="2" placeholder="Street address..."
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none" />
          </div>
        </div>

        <!-- Actions -->
        <div class="p-6 flex items-center justify-end gap-3">
          <Link :href="route('vendors.index')"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
            Cancel
          </Link>
          <button
            type="submit"
            :disabled="processing"
            class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
          >
            {{ processing ? 'Saving...' : 'Add Vendor' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  categories: Array,
})

const form = reactive({
  name:          '',
  category:      '',
  gstin:         '',
  pan:           '',
  udyam_number:  '',
  contact_name:  '',
  contact_email: '',
  contact_phone: '',
  address:       '',
  city:          '',
  state:         '',
})

const errors     = reactive({})
const processing = ref(false)

function submit() {
  processing.value = true
  Object.keys(errors).forEach(k => delete errors[k])

  router.post(route('vendors.store'), form, {
    onError: (errs) => {
      Object.assign(errors, errs)
      processing.value = false
    },
    onFinish: () => {
      processing.value = false
    },
  })
}
</script>
