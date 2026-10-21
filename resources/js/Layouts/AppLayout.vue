<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const flash = computed(() => page.props.flash ?? {});

const roleLabels = {
    super_admin: 'Super Admin',
    finance: 'Finance',
    warehouse_staff: 'Staf Gudang',
    buyer: 'Buyer',
};

const roleText = computed(() => (user.value?.roles ?? []).map((role) => roleLabels[role] ?? role).join(', '));

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                <div class="flex items-center gap-6">
                    <Link href="/dashboard" class="text-lg font-bold tracking-tight">KoreGrid</Link>
                    <nav class="flex gap-4 text-sm">
                        <Link href="/dashboard" class="font-medium text-slate-700 hover:text-slate-900">Dashboard</Link>
                        <Link
                            v-if="user?.can?.['product.view']"
                            href="/catalog"
                            class="font-medium text-slate-700 hover:text-slate-900"
                        >
                            Katalog
                        </Link>
                    </nav>
                </div>

                <div v-if="user" class="flex items-center gap-4 text-sm">
                    <div class="text-right leading-tight">
                        <p class="font-medium">{{ user.name }}</p>
                        <p class="text-xs text-slate-500">{{ roleText }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-100"
                        @click="logout"
                    >
                        Keluar
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8">
            <p
                v-if="flash.success"
                role="status"
                class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ flash.success }}
            </p>
            <p
                v-if="flash.error"
                role="alert"
                class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
            >
                {{ flash.error }}
            </p>

            <slot />
        </main>
    </div>
</template>
