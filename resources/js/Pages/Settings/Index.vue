<template>
  <AppLayout title="Settings" subtitle="Manage your business profile, team, and billing.">
    <Head title="Settings" />

    <!-- Tabs -->
    <div class="flex gap-1 mb-6 border-b border-gray-200">
      <button
        v-for="tab in visibleTabs"
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

    <!-- ================= PROFILE TAB ================= -->
    <div v-if="activeTab === 'profile'" class="max-w-2xl">

      <!-- Trial banner -->
      <div v-if="billing.is_trial && billing.trial_days_remaining <= 7"
           :class="[
             'rounded-xl border p-4 mb-6 flex items-start gap-3',
             billing.trial_days_remaining <= 2
               ? 'bg-red-50 border-red-200'
               : 'bg-yellow-50 border-yellow-200',
           ]">
        <ClockIcon :class="['w-5 h-5 flex-shrink-0 mt-0.5', billing.trial_days_remaining <= 2 ? 'text-red-500' : 'text-yellow-500']" />
        <div>
          <p :class="['text-sm font-semibold', billing.trial_days_remaining <= 2 ? 'text-red-800' : 'text-yellow-800']">
            {{ billing.trial_days_remaining }} day{{ billing.trial_days_remaining !== 1 ? 's' : '' }} left in your trial
          </p>
          <p :class="['text-xs mt-0.5', billing.trial_days_remaining <= 2 ? 'text-red-600' : 'text-yellow-600']">
            Contact us to upgrade before your trial ends on {{ billing.trial_ends_at }}.
          </p>
        </div>
      </div>

      <form @submit.prevent="saveProfile" class="space-y-6">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
          <h3 class="text-sm font-semibold text-gray-900 mb-4">Business Information</h3>

          <div class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Business Name <span class="text-red-500">*</span></label>
              <input v-model="profileForm.name" type="text"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500"
                     :class="{ 'border-red-400': profileErrors.name }" />
              <p v-if="profileErrors.name" class="text-xs text-red-600 mt-1">{{ profileErrors.name }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">GSTIN</label>
                <input v-model="profileForm.gstin" type="text" maxlength="15" placeholder="29ABCDE1234F1Z5"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500"
                       :class="{ 'border-red-400': profileErrors.gstin }" />
                <p v-if="profileErrors.gstin" class="text-xs text-red-600 mt-1">{{ profileErrors.gstin }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">PAN</label>
                <input v-model="profileForm.pan" type="text" maxlength="10" placeholder="ABCDE1234F"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500"
                       :class="{ 'border-red-400': profileErrors.pan }" />
                <p v-if="profileErrors.pan" class="text-xs text-red-600 mt-1">{{ profileErrors.pan }}</p>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">State</label>
                <input v-model="profileForm.state" type="text"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">City</label>
                <input v-model="profileForm.city" type="text"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Address</label>
              <textarea v-model="profileForm.address" rows="2"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Contact Email</label>
                <input v-model="profileForm.email" type="email"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Phone</label>
                <input v-model="profileForm.phone" type="tel"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
              </div>
            </div>
          </div>
        </div>

        <!-- RBI rate -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
          <h3 class="text-sm font-semibold text-gray-900 mb-1">RBI Bank Rate</h3>
          <p class="text-xs text-gray-500 mb-4">
            Used to compute 43B(h) interest: <strong>3× RBI Bank Rate</strong> compounded monthly.
            Update when RBI revises the rate.
          </p>
          <div class="flex items-center gap-3">
            <input
              v-model.number="profileForm.rbi_bank_rate"
              type="number" step="0.25" min="1" max="25"
              class="w-28 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500"
              :class="{ 'border-red-400': profileErrors.rbi_bank_rate }"
            />
            <span class="text-sm text-gray-500">% p.a.</span>
            <span class="text-sm text-indigo-600 font-medium">
              → Interest rate: {{ ((profileForm.rbi_bank_rate || 0) * 3).toFixed(2) }}% p.a.
            </span>
          </div>
          <p v-if="profileErrors.rbi_bank_rate" class="text-xs text-red-600 mt-1">{{ profileErrors.rbi_bank_rate }}</p>
        </div>

        <div v-if="canManage" class="flex gap-3">
          <button type="submit" :disabled="savingProfile"
                  class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-60 transition-colors">
            {{ savingProfile ? 'Saving...' : 'Save Profile' }}
          </button>
        </div>
        <p v-else class="text-xs text-gray-400">Only Owner or Admin can edit the business profile.</p>
      </form>
    </div>

    <!-- ================= TEAM TAB ================= -->
    <div v-if="activeTab === 'team'">

      <!-- Usage bar -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-5 flex items-center gap-4">
        <div class="flex-1">
          <div class="flex justify-between text-xs text-gray-500 mb-1">
            <span>Team Members</span>
            <span>{{ limits.users_used }} / {{ limits.users_max ?? '∞' }}</span>
          </div>
          <div class="w-full bg-gray-100 rounded-full h-1.5">
            <div class="bg-indigo-500 h-1.5 rounded-full transition-all"
                 :style="{ width: limits.users_max ? `${Math.min(100, limits.users_used / limits.users_max * 100)}%` : '10%' }"></div>
          </div>
        </div>
        <button v-if="canManage" @click="showAddUser = !showAddUser"
                class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 flex items-center gap-1">
          <PlusIcon class="w-3.5 h-3.5" />
          Add Member
        </button>
      </div>

      <!-- Add user form -->
      <div v-if="showAddUser && canManage" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Add Team Member</h3>
        <form @submit.prevent="addUser" class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
              <input v-model="addForm.name" type="text" placeholder="Full name"
                     class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500"
                     :class="{ 'border-red-400': addErrors.name }" />
              <p v-if="addErrors.name" class="text-xs text-red-600 mt-1">{{ addErrors.name }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
              <input v-model="addForm.email" type="email" placeholder="work@company.com"
                     class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500"
                     :class="{ 'border-red-400': addErrors.email }" />
              <p v-if="addErrors.email" class="text-xs text-red-600 mt-1">{{ addErrors.email }}</p>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Role <span class="text-red-500">*</span></label>
              <select v-model="addForm.role"
                      class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500">
                <option v-for="r in team.roles" :key="r.value" :value="r.value">{{ r.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
              <input v-model="addForm.password" type="password" placeholder="Min 8 characters"
                     class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500"
                     :class="{ 'border-red-400': addErrors.password }" />
              <p v-if="addErrors.password" class="text-xs text-red-600 mt-1">{{ addErrors.password }}</p>
            </div>
          </div>
          <div class="flex gap-2 pt-1">
            <button type="submit" :disabled="addingUser"
                    class="px-4 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-60">
              {{ addingUser ? 'Adding...' : 'Add Member' }}
            </button>
            <button type="button" @click="showAddUser = false"
                    class="px-4 py-1.5 bg-gray-100 text-gray-600 text-xs rounded-lg hover:bg-gray-200">
              Cancel
            </button>
          </div>
        </form>
      </div>

      <!-- Users table -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Name</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Email</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Role</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Last Login</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
              <th v-if="canManage" class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="u in team.users" :key="u.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-medium text-gray-800">
                {{ u.name }}
                <span v-if="u.id === $page.props.auth.user?.id" class="text-xs text-indigo-500 ml-1">(you)</span>
              </td>
              <td class="px-4 py-3 text-gray-600">{{ u.email }}</td>
              <td class="px-4 py-3">
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                  {{ u.role_label }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-500 text-xs">{{ u.last_login_at ?? 'Never' }}</td>
              <td class="px-4 py-3">
                <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-semibold', u.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500']">
                  {{ u.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td v-if="canManage" class="px-4 py-3">
                <button
                  v-if="u.is_active && u.id !== $page.props.auth.user?.id"
                  @click="deactivateUser(u)"
                  class="text-xs text-red-500 hover:text-red-700 hover:underline"
                >
                  Deactivate
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ================= BILLING TAB ================= -->
    <div v-if="activeTab === 'billing'" class="max-w-2xl">

      <!-- Current plan -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-5">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-xs text-gray-500 mb-1">Current Plan</p>
            <p class="text-xl font-bold text-gray-900">{{ billing.plan_label }}</p>
            <p class="text-sm text-gray-500">₹{{ billing.plan_price?.toLocaleString('en-IN') }}/month</p>
          </div>
          <span :class="['px-3 py-1 rounded-full text-xs font-semibold', statusBadgeClass]">
            {{ billing.status_label }}
          </span>
        </div>

        <div v-if="billing.is_trial" class="mt-4 pt-4 border-t border-gray-100">
          <p class="text-sm text-gray-600">
            Trial ends on
            <strong class="text-gray-900">{{ billing.trial_ends_at }}</strong>
            &mdash;
            <span :class="billing.trial_days_remaining <= 3 ? 'text-red-600 font-semibold' : 'text-gray-700'">
              {{ billing.trial_days_remaining }} day{{ billing.trial_days_remaining !== 1 ? 's' : '' }} remaining
            </span>
          </p>
        </div>
      </div>

      <!-- Usage -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Resource Usage</h3>
        <div class="space-y-3">
          <div>
            <div class="flex justify-between text-xs text-gray-500 mb-1">
              <span>Vendors</span>
              <span>{{ limits.vendors_used }} / {{ limits.vendors_max ?? 'Unlimited' }}</span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2">
              <div class="bg-indigo-500 h-2 rounded-full"
                   :style="{ width: limits.vendors_max ? `${Math.min(100, limits.vendors_used / limits.vendors_max * 100)}%` : '5%' }"></div>
            </div>
          </div>
          <div>
            <div class="flex justify-between text-xs text-gray-500 mb-1">
              <span>Team Members</span>
              <span>{{ limits.users_used }} / {{ limits.users_max ?? 'Unlimited' }}</span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2">
              <div class="bg-purple-500 h-2 rounded-full"
                   :style="{ width: limits.users_max ? `${Math.min(100, limits.users_used / limits.users_max * 100)}%` : '5%' }"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Available plans -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-1">Available Plans</h3>
        <p class="text-xs text-gray-500 mb-4">Contact us to upgrade or change your plan.</p>
        <div class="space-y-3">
          <div v-for="plan in availablePlans" :key="plan.name"
               :class="['border rounded-lg px-4 py-3 flex items-center justify-between',
                        billing.plan_label === plan.name ? 'border-indigo-300 bg-indigo-50' : 'border-gray-100']">
            <div>
              <p class="text-sm font-semibold text-gray-800">
                {{ plan.name }}
                <span v-if="billing.plan_label === plan.name" class="text-xs text-indigo-600 ml-1">(current)</span>
              </p>
              <p class="text-xs text-gray-500">{{ plan.description }}</p>
            </div>
            <p class="text-sm font-bold text-gray-800">₹{{ plan.price }}/mo</p>
          </div>
        </div>
      </div>

      <a href="mailto:mailforgobi@gmail.com?subject=MSME Tracker Upgrade"
         class="inline-flex items-center gap-2 bg-indigo-600 text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-indigo-700 transition-colors">
        <EnvelopeIcon class="w-4 h-4" />
        Contact Us to Upgrade
      </a>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
  ClockIcon,
  PlusIcon,
  EnvelopeIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  activeTab: { type: String, default: 'profile' },
  profile:   { type: Object, required: true },
  billing:   { type: Object, required: true },
  team:      { type: Object, required: true },
  limits:    { type: Object, required: true },
  canManage: { type: Boolean, default: false },
});

const page = usePage();

// ─── Tabs ────────────────────────────────────────────────────────────────────
const activeTab   = ref(props.activeTab);
const visibleTabs = [
  { key: 'profile', label: 'Business Profile' },
  { key: 'team',    label: 'Team' },
  { key: 'billing', label: 'Billing' },
];

// ─── Profile form ─────────────────────────────────────────────────────────────
const profileForm = ref({ ...props.profile });
const savingProfile = ref(false);
const profileErrors = ref({});

function saveProfile() {
  savingProfile.value = true;
  profileErrors.value = {};

  router.put('/settings/profile', profileForm.value, {
    onError:  (e) => { savingProfile.value = false; profileErrors.value = e; },
    onFinish: ()  => { savingProfile.value = false; },
  });
}

// ─── Team management ──────────────────────────────────────────────────────────
const showAddUser = ref(false);
const addingUser  = ref(false);
const addErrors   = ref({});
const addForm     = ref({ name: '', email: '', role: 'finance', password: '' });

function addUser() {
  addingUser.value = true;
  addErrors.value  = {};

  router.post('/settings/team', addForm.value, {
    onSuccess: () => {
      addingUser.value = false;
      showAddUser.value = false;
      addForm.value = { name: '', email: '', role: 'finance', password: '' };
    },
    onError: (e) => { addingUser.value = false; addErrors.value = e; },
  });
}

function deactivateUser(user) {
  if (! confirm(`Deactivate ${user.name}? They will no longer be able to log in.`)) return;

  router.delete(`/settings/team/${user.id}`);
}

// ─── Billing ─────────────────────────────────────────────────────────────────
const availablePlans = [
  { name: 'Starter', price: '1,500', description: 'Up to 50 vendors, 5 users' },
  { name: 'Growth',  price: '3,000', description: 'Up to 200 vendors, 15 users' },
  { name: 'CA Firm', price: '4,000', description: 'Unlimited vendors & users, 10 clients' },
];

const statusBadgeClass = computed(() => ({
  trial:    'bg-blue-100 text-blue-800',
  active:   'bg-green-100 text-green-800',
  inactive: 'bg-gray-100 text-gray-600',
  suspended: 'bg-red-100 text-red-800',
}[props.billing.subscription_status] ?? 'bg-gray-100 text-gray-700'));
</script>
