<template>
    <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-blue-50 flex items-center justify-center p-4">
        <Head title="Login" />

        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-14 h-14 bg-indigo-600 rounded-2xl shadow-lg mb-4">
                    <ShieldCheckIcon class="w-8 h-8 text-white" />
                </div>
                <h1 class="text-2xl font-bold text-gray-900">MSME Risk Tracker</h1>
                <p class="text-sm text-gray-500 mt-1">Section 43B(h) Compliance Platform</p>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-8">
                <h2 class="text-lg font-semibold text-gray-900 mb-6">Sign in to your account</h2>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                            Email address
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            required
                            :class="[
                                'w-full px-3 py-2.5 border rounded-lg text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent',
                                errors.email ? 'border-red-300 bg-red-50' : 'border-gray-300 bg-white'
                            ]"
                            placeholder="you@company.com"
                        />
                        <p v-if="errors.email" class="mt-1 text-xs text-red-600">{{ errors.email }}</p>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                            Password
                        </label>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                required
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent pr-10"
                                placeholder="••••••••"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            >
                                <EyeIcon v-if="!showPassword" class="w-4 h-4" />
                                <EyeSlashIcon v-else class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Remember -->
                    <div class="flex items-center">
                        <input
                            id="remember"
                            v-model="form.remember"
                            type="checkbox"
                            class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                        />
                        <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full flex items-center justify-center gap-2 bg-indigo-600 text-white font-medium py-2.5 px-4 rounded-lg hover:bg-indigo-700 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ form.processing ? 'Signing in…' : 'Sign in' }}
                    </button>
                </form>
            </div>

            <p class="text-center text-xs text-gray-400 mt-6">
                &copy; {{ new Date().getFullYear() }} MSME Risk Tracker &mdash; Section 43B(h) Compliance
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ShieldCheckIcon, EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';

defineProps({
    errors: { type: Object, default: () => ({}) },
});

const showPassword = ref(false);

const form = useForm({
    email:    '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>
