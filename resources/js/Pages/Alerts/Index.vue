<template>
  <AppLayout title="Alerts" subtitle="43B(h) deadline alerts — email and WhatsApp notifications.">
    <Head title="Alerts" />

    <!-- Tabs -->
    <div class="flex gap-1 mb-6 border-b border-gray-200">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        @click="activeTab = tab.key"
        :class="[
          'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 -mb-px',
          activeTab === tab.key
            ? 'border-indigo-600 text-indigo-700'
            : 'border-transparent text-gray-500 hover:text-gray-700',
        ]"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- ================= HISTORY TAB ================= -->
    <div v-if="activeTab === 'history'">

      <!-- Summary cards -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
          <p class="text-xs text-gray-500 mb-1">This Month</p>
          <p class="text-2xl font-bold text-gray-900">{{ summary.total_this_month }}</p>
          <p class="text-xs text-gray-400">alerts dispatched</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
          <p class="text-xs text-gray-500 mb-1">Sent</p>
          <p class="text-2xl font-bold text-green-600">{{ summary.sent }}</p>
          <p class="text-xs text-gray-400">delivered this month</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
          <p class="text-xs text-gray-500 mb-1">Failed</p>
          <p class="text-2xl font-bold text-red-600">{{ summary.failed }}</p>
          <p class="text-xs text-gray-400">this month</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
          <p class="text-xs text-gray-500 mb-1">Pending</p>
          <p class="text-2xl font-bold text-yellow-600">{{ summary.pending }}</p>
          <p class="text-xs text-gray-400">queued</p>
        </div>
      </div>

      <!-- Filter bar -->
      <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4 shadow-sm">
        <div class="flex flex-col sm:flex-row gap-3 flex-wrap">

          <!-- Search -->
          <div class="relative flex-1 min-w-48">
            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input
              v-model="filterSearch"
              @keydown.enter="applyFilters"
              type="text"
              placeholder="Search recipient, invoice or vendor..."
              class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            />
          </div>

          <!-- Status filter -->
          <select
            v-model="filterStatus"
            @change="applyFilters"
            class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500"
          >
            <option value="">All Statuses</option>
            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>

          <!-- Channel filter -->
          <select
            v-model="filterChannel"
            @change="applyFilters"
            class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500"
          >
            <option value="">All Channels</option>
            <option v-for="c in channels" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>

          <!-- Type filter -->
          <select
            v-model="filterType"
            @change="applyFilters"
            class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500"
          >
            <option value="">All Types</option>
            <option v-for="t in alertTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>

          <button
            v-if="hasActiveFilter"
            @click="clearFilters"
            class="text-sm text-red-600 hover:underline self-center"
          >
            Clear
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div v-if="logs.data.length === 0" class="text-center py-12 text-gray-500">
          <BellSlashIcon class="w-10 h-10 mx-auto mb-3 text-gray-300" />
          <p class="text-sm font-medium">No alerts found</p>
          <p class="text-xs mt-1">Configure your alert settings to start receiving notifications.</p>
        </div>

        <table v-else class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Date &amp; Time</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Type</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Channel</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Recipient</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Invoice / Vendor</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr
              v-for="log in logs.data"
              :key="log.id"
              class="hover:bg-gray-50 transition-colors"
            >
              <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                {{ formatDate(log.created_at) }}
              </td>
              <td class="px-4 py-3">
                <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold', typeBadgeClass(log.alert_type)]">
                  {{ log.alert_type_label }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1 text-gray-700">
                  <EnvelopeIcon v-if="log.channel === 'email'" class="w-4 h-4 text-indigo-500" />
                  <ChatBubbleLeftIcon v-else-if="log.channel === 'whatsapp'" class="w-4 h-4 text-green-600" />
                  <span class="text-xs">{{ log.channel_label }}</span>
                </span>
              </td>
              <td class="px-4 py-3 text-gray-700 max-w-32 truncate" :title="log.recipient">
                {{ log.recipient }}
              </td>
              <td class="px-4 py-3">
                <div v-if="log.invoice">
                  <p class="text-gray-800 font-medium">{{ log.invoice.invoice_number }}</p>
                  <p class="text-xs text-gray-500">{{ log.invoice.vendor_name }}</p>
                </div>
                <span v-else class="text-gray-400 text-xs">—</span>
              </td>
              <td class="px-4 py-3">
                <div>
                  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold', statusBadgeClass(log.status)]">
                    {{ log.status_label }}
                  </span>
                  <p v-if="log.failed_reason" class="text-xs text-red-500 mt-1 max-w-48 truncate" :title="log.failed_reason">
                    {{ log.failed_reason }}
                  </p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="logs.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
          <p class="text-xs text-gray-500">
            Showing {{ logs.from }}–{{ logs.to }} of {{ logs.total }} alerts
          </p>
          <div class="flex gap-1">
            <Link
              v-for="link in logs.links"
              :key="link.label"
              :href="link.url || '#'"
              :class="[
                'px-3 py-1 text-xs rounded border transition-colors',
                link.active
                  ? 'bg-indigo-600 text-white border-indigo-600'
                  : link.url
                    ? 'bg-white text-gray-700 border-gray-300 hover:border-indigo-400'
                    : 'bg-white text-gray-300 border-gray-200 cursor-not-allowed',
              ]"
              v-html="link.label"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- ================= SETTINGS TAB ================= -->
    <div v-if="activeTab === 'settings'">
      <div class="max-w-2xl">
        <form @submit.prevent="saveSettings" class="space-y-6">

          <!-- Email settings -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-sm font-semibold text-gray-900">Email Alerts</h3>
                <p class="text-xs text-gray-500 mt-0.5">Send 43B(h) deadline alerts via email</p>
              </div>
              <button
                type="button"
                @click="form.email_enabled = !form.email_enabled"
                :class="[
                  'relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
                  form.email_enabled ? 'bg-indigo-600' : 'bg-gray-200',
                ]"
              >
                <span :class="['inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform', form.email_enabled ? 'translate-x-6' : 'translate-x-1']" />
              </button>
            </div>

            <div v-if="form.email_enabled" class="space-y-3">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-2">
                  Recipient Emails
                  <span class="text-gray-400 font-normal ml-1">(leave empty to use all account users)</span>
                </label>
                <div class="space-y-2">
                  <div
                    v-for="(email, idx) in form.email_recipients"
                    :key="idx"
                    class="flex gap-2"
                  >
                    <input
                      v-model="form.email_recipients[idx]"
                      type="email"
                      placeholder="name@company.com"
                      class="flex-1 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    <button
                      type="button"
                      @click="removeEmail(idx)"
                      class="text-red-400 hover:text-red-600 p-1"
                    >
                      <XMarkIcon class="w-4 h-4" />
                    </button>
                  </div>
                  <button
                    v-if="form.email_recipients.length < 10"
                    type="button"
                    @click="form.email_recipients.push('')"
                    class="text-indigo-600 text-xs hover:underline flex items-center gap-1"
                  >
                    <PlusIcon class="w-3 h-3" /> Add recipient
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- WhatsApp settings -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-sm font-semibold text-gray-900">WhatsApp Alerts</h3>
                <p class="text-xs text-gray-500 mt-0.5">Send via AiSensy Business API (requires AISENSY_API_KEY in .env)</p>
              </div>
              <button
                type="button"
                @click="form.whatsapp_enabled = !form.whatsapp_enabled"
                :class="[
                  'relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
                  form.whatsapp_enabled ? 'bg-green-600' : 'bg-gray-200',
                ]"
              >
                <span :class="['inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform', form.whatsapp_enabled ? 'translate-x-6' : 'translate-x-1']" />
              </button>
            </div>

            <div v-if="form.whatsapp_enabled">
              <label class="block text-xs font-medium text-gray-700 mb-1">
                WhatsApp Number (E.164 format)
              </label>
              <input
                v-model="form.whatsapp_number"
                type="tel"
                placeholder="+919876543210"
                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500"
              />
              <p class="text-xs text-gray-400 mt-1">
                Example: +919876543210 — must match a WhatsApp account
              </p>
            </div>
          </div>

          <!-- Alert types -->
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Alert Types</h3>
            <div class="space-y-3">
              <label
                v-for="toggle in alertToggles"
                :key="toggle.key"
                class="flex items-start gap-3 cursor-pointer group"
              >
                <div class="mt-0.5">
                  <input
                    type="checkbox"
                    v-model="form[toggle.key]"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                  />
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-800 group-hover:text-indigo-700">{{ toggle.label }}</p>
                  <p class="text-xs text-gray-500">{{ toggle.description }}</p>
                </div>
              </label>
            </div>
          </div>

          <!-- Error messages -->
          <div v-if="Object.keys(errors).length" class="bg-red-50 border border-red-200 rounded-lg p-4">
            <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
              <li v-for="(msg, field) in errors" :key="field">{{ msg }}</li>
            </ul>
          </div>

          <div class="flex gap-3">
            <button
              type="submit"
              :disabled="saving"
              class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-60 transition-colors"
            >
              {{ saving ? 'Saving...' : 'Save Settings' }}
            </button>
          </div>
        </form>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
  MagnifyingGlassIcon,
  BellSlashIcon,
  EnvelopeIcon,
  ChatBubbleLeftIcon,
  XMarkIcon,
  PlusIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  logs:       { type: Object, required: true },
  summary:    { type: Object, required: true },
  settings:   { type: Object, required: true },
  filters:    { type: Object, default: () => ({}) },
  channels:   { type: Array, default: () => [] },
  alertTypes: { type: Array, default: () => [] },
  statuses:   { type: Array, default: () => [] },
});

// ─── Tabs ───────────────────────────────────────────────────────────────
const tabs = [
  { key: 'history',  label: 'Alert History' },
  { key: 'settings', label: 'Settings' },
];
const activeTab = ref('history');

// ─── History filters ─────────────────────────────────────────────────────
const filterSearch  = ref(props.filters.search  ?? '');
const filterStatus  = ref(props.filters.status  ?? '');
const filterChannel = ref(props.filters.channel ?? '');
const filterType    = ref(props.filters.type    ?? '');

const hasActiveFilter = computed(() =>
  filterSearch.value || filterStatus.value || filterChannel.value || filterType.value
);

function applyFilters() {
  router.get('/alerts', {
    search:  filterSearch.value  || undefined,
    status:  filterStatus.value  || undefined,
    channel: filterChannel.value || undefined,
    type:    filterType.value    || undefined,
  }, { preserveState: true, replace: true });
}

function clearFilters() {
  filterSearch.value = filterStatus.value = filterChannel.value = filterType.value = '';
  router.get('/alerts', {}, { replace: true });
}

// ─── Settings form ────────────────────────────────────────────────────────
const form = ref({
  email_enabled:    props.settings.email_enabled    ?? true,
  whatsapp_enabled: props.settings.whatsapp_enabled ?? false,
  email_recipients: [...(props.settings.email_recipients ?? [])],
  whatsapp_number:  props.settings.whatsapp_number  ?? '',
  t10_enabled:      props.settings.t10_enabled      ?? true,
  t3_enabled:       props.settings.t3_enabled       ?? true,
  overdue_enabled:  props.settings.overdue_enabled  ?? true,
});

const saving = ref(false);
const errors = ref({});

const alertToggles = [
  {
    key:         't10_enabled',
    label:       '10-Day Warning',
    description: 'Alert 8–10 days before the 43B(h) deadline (planning time).',
  },
  {
    key:         't3_enabled',
    label:       '3-Day Urgent Alert',
    description: 'Alert 1–3 days before deadline — last chance to pay.',
  },
  {
    key:         'overdue_enabled',
    label:       'Overdue Notices',
    description: 'Daily reminder once a payment is overdue and tax risk is active.',
  },
];

function removeEmail(idx) {
  form.value.email_recipients.splice(idx, 1);
}

function saveSettings() {
  saving.value = true;
  errors.value = {};

  router.put('/alerts/settings', form.value, {
    onSuccess: () => { saving.value = false; },
    onError:   (e) => { saving.value = false; errors.value = e; },
  });
}

// ─── Helpers ──────────────────────────────────────────────────────────────
function formatDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('en-IN', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

function typeBadgeClass(type) {
  return {
    't10_warning':      'bg-yellow-100 text-yellow-800',
    't3_urgent':        'bg-orange-100 text-orange-800',
    'overdue':          'bg-red-100 text-red-800',
    'year_end_summary': 'bg-indigo-100 text-indigo-800',
  }[type] ?? 'bg-gray-100 text-gray-700';
}

function statusBadgeClass(status) {
  return {
    'sent':      'bg-green-100 text-green-800',
    'delivered': 'bg-teal-100 text-teal-800',
    'pending':   'bg-yellow-100 text-yellow-800',
    'failed':    'bg-red-100 text-red-800',
  }[status] ?? 'bg-gray-100 text-gray-700';
}
</script>
