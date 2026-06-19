<template>
    <div class="min-h-screen flex bg-gray-50">

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 flex flex-col bg-gray-900 transition-all duration-300',
                sidebarOpen ? 'w-64' : 'w-16'
            ]"
        >
            <!-- Logo -->
            <div class="flex h-16 items-center justify-between px-4 border-b border-gray-700">
                <Link href="/dashboard" class="flex items-center gap-3 min-w-0">
                    <div class="flex-shrink-0 w-8 h-8 bg-indigo-500 rounded-lg flex items-center justify-center">
                        <ShieldCheckIcon class="w-5 h-5 text-white" />
                    </div>
                    <span v-if="sidebarOpen" class="text-white font-semibold text-sm truncate">
                        MSME Tracker
                    </span>
                </Link>
                <button
                    @click="sidebarOpen = !sidebarOpen"
                    class="text-gray-400 hover:text-white transition-colors flex-shrink-0"
                >
                    <ChevronLeftIcon v-if="sidebarOpen" class="w-5 h-5" />
                    <ChevronRightIcon v-else class="w-5 h-5" />
                </button>
            </div>

            <!-- Nav Links -->
            <nav class="flex-1 overflow-y-auto py-4 space-y-1 px-2">
                <NavItem
                    v-for="item in navigation"
                    :key="item.href"
                    :item="item"
                    :collapsed="!sidebarOpen"
                />
            </nav>

            <!-- User Info -->
            <div class="border-t border-gray-700 p-3">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center">
                        <span class="text-white text-xs font-semibold">
                            {{ userInitials }}
                        </span>
                    </div>
                    <div v-if="sidebarOpen" class="min-w-0">
                        <p class="text-white text-xs font-medium truncate">{{ $page.props.auth.user?.name }}</p>
                        <p class="text-gray-400 text-xs truncate">{{ $page.props.auth.user?.role_label }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main area -->
        <div :class="['flex-1 flex flex-col transition-all duration-300', sidebarOpen ? 'ml-64' : 'ml-16']">

            <!-- Topbar -->
            <header class="sticky top-0 z-40 h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 shadow-sm">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">{{ title }}</h1>
                    <p v-if="subtitle" class="text-xs text-gray-500">{{ subtitle }}</p>
                </div>

                <div class="flex items-center gap-4">
                    <!-- Trial countdown badge -->
                    <span
                        v-if="$page.props.auth.user?.tenant?.is_trial"
                        :class="[
                            'hidden sm:block text-xs font-semibold px-3 py-1 rounded-full',
                            $page.props.auth.user.tenant.trial_days_remaining <= 3
                                ? 'bg-red-100 text-red-700'
                                : 'bg-yellow-100 text-yellow-700'
                        ]"
                    >
                        Trial: {{ $page.props.auth.user.tenant.trial_days_remaining }}d left
                    </span>

                    <!-- Tenant name -->
                    <span
                        v-else-if="$page.props.auth.user?.tenant"
                        class="hidden sm:block text-xs font-medium text-gray-500 bg-gray-100 px-3 py-1 rounded-full"
                    >
                        {{ $page.props.auth.user.tenant.name }}
                    </span>

                    <!-- Logout -->
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="flex items-center gap-2 text-sm text-gray-500 hover:text-red-600 transition-colors"
                    >
                        <ArrowRightOnRectangleIcon class="w-5 h-5" />
                        <span class="hidden sm:block">Logout</span>
                    </Link>
                </div>
            </header>

            <!-- Flash Messages -->
            <div v-if="flash.success || flash.error || flash.warning" class="px-6 pt-4">
                <FlashMessage
                    v-if="flash.success"
                    type="success"
                    :message="flash.success"
                />
                <FlashMessage
                    v-if="flash.error"
                    type="error"
                    :message="flash.error"
                />
                <FlashMessage
                    v-if="flash.warning"
                    type="warning"
                    :message="flash.warning"
                />
            </div>

            <!-- Page Content -->
            <main class="flex-1 p-6">
                <slot />
            </main>

            <!-- Footer -->
            <footer class="px-6 py-3 border-t border-gray-200 bg-white">
                <p class="text-xs text-gray-400 text-center">
                    MSME Payment Risk Tracker &mdash; Section 43B(h) Compliance &mdash;
                    FY {{ currentFinancialYear }}
                </p>
            </footer>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    HomeIcon,
    BuildingStorefrontIcon,
    DocumentTextIcon,
    CreditCardIcon,
    ArrowUpTrayIcon,
    BellIcon,
    CalculatorIcon,
    ShieldCheckIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ArrowRightOnRectangleIcon,
    Cog6ToothIcon,
} from '@heroicons/vue/24/outline';
import NavItem from '@/Components/NavItem.vue';
import FlashMessage from '@/Components/FlashMessage.vue';

defineProps({
    title: { type: String, default: 'Dashboard' },
    subtitle: { type: String, default: null },
});

const page = usePage();
const sidebarOpen = ref(true);

const flash = computed(() => page.props.flash ?? {});

const userInitials = computed(() => {
    const name = page.props.auth?.user?.name ?? '';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
});

const currentFinancialYear = computed(() => {
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth() + 1;
    return month >= 4
        ? `${year}-${String(year + 1).slice(-2)}`
        : `${year - 1}-${String(year).slice(-2)}`;
});

const navigation = [
    { label: 'Dashboard',  href: '/dashboard',        icon: HomeIcon },
    { label: 'Vendors',    href: '/vendors',           icon: BuildingStorefrontIcon },
    { label: 'Invoices',   href: '/invoices',          icon: DocumentTextIcon },
    { label: 'Payments',   href: '/payments',          icon: CreditCardIcon },
    { label: 'Import',     href: '/import',            icon: ArrowUpTrayIcon },
    { label: 'Alerts',     href: '/alerts',            icon: BellIcon },
    { label: 'Calculator', href: '/calculator',        icon: CalculatorIcon },
    { label: 'Settings',   href: '/settings',          icon: Cog6ToothIcon },
];
</script>
