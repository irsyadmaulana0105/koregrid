<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const flash = computed(() => page.props.flash ?? {});
const currentPath = computed(() => new URL(page.url, 'http://localhost').pathname);

const roleLabels = {
    super_admin: 'Super Admin',
    finance: 'Finance',
    warehouse_staff: 'Staf Gudang',
    buyer: 'Buyer',
};

const roleText = computed(() => (user.value?.roles ?? []).map((role) => roleLabels[role] ?? role).join(', '));
const canSeeOrders = computed(() => Boolean(user.value?.can?.['po.view.own'] || user.value?.can?.['po.view.all']));
const canSeeQueue = computed(() =>
    Boolean(user.value?.can?.['po.approve'] || user.value?.can?.['po.pack'] || user.value?.can?.['po.ship']),
);

const navLinks = computed(() => {
    const links = [{ href: '/dashboard', label: 'Dashboard', show: true }];
    links.push({ href: '/catalog', label: 'Katalog', show: Boolean(user.value?.can?.['product.view']) });
    links.push({ href: '/orders', label: 'Pesanan', show: canSeeOrders.value });
    links.push({ href: '/queue', label: 'Antrean', show: canSeeQueue.value });

    return links.filter((link) => link.show);
});

function isActive(href) {
    return currentPath.value === href || currentPath.value.startsWith(href + '/');
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50 text-slate-900">
        <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                <div class="flex items-center gap-6">
                    <Link href="/dashboard" class="flex items-center gap-2 font-bold tracking-tight">
                        <span
                            aria-hidden="true"
                            class="flex h-8 w-8 items-center justify-center rounded-md bg-slate-900 text-sm font-extrabold text-emerald-400"
                            >K</span
                        >
                        <span class="text-lg">KoreGrid</span>
                    </Link>
                    <nav aria-label="Navigasi utama" class="flex gap-1 text-sm">
                        <Link
                            v-for="link in navLinks"
                            :key="link.href"
                            :href="link.href"
                            :aria-current="isActive(link.href) ? 'page' : undefined"
                            class="rounded-md px-3 py-2 font-medium outline-none transition-colors focus-visible:ring-2 focus-visible:ring-slate-900"
                            :class="isActive(link.href)
                                ? 'bg-slate-900 text-white'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                        >
                            {{ link.label }}
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
                        class="rounded-md border border-slate-300 px-3 py-1.5 font-medium text-slate-700 outline-none transition-colors hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-slate-900"
                        @click="logout"
                    >
                        Keluar
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
            <p
                v-if="flash.success"
                role="status"
                class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
            >
                {{ flash.success }}
            </p>
            <p
                v-if="flash.error"
                role="alert"
                class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800"
            >
                {{ flash.error }}
            </p>

            <slot />
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 text-xs text-slate-500">
                <p>&copy; 2026 KoreGrid &mdash; Portal B2B rantai pasok multi-gudang</p>
                <p>Harga ditentukan server &middot; Stok terkunci otomatis</p>
            </div>
        </footer>
    </div>
</template>
