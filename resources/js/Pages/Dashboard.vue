<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({
    stats: { type: Object, required: true },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const roleLabels = {
    super_admin: 'Super Admin',
    finance: 'Finance',
    warehouse_staff: 'Staf Gudang',
    buyer: 'Buyer',
};

const tierStyles = {
    gold: 'bg-amber-100 text-amber-800',
    silver: 'bg-slate-200 text-slate-700',
    bronze: 'bg-orange-100 text-orange-800',
};

const statusCards = [
    { key: 'draft', label: 'Draf' },
    { key: 'pending_approval', label: 'Menunggu persetujuan' },
    { key: 'approved', label: 'Disetujui' },
    { key: 'packing', label: 'Dikemas' },
    { key: 'shipped', label: 'Dikirim' },
    { key: 'completed', label: 'Selesai' },
    { key: 'rejected', label: 'Ditolak' },
    { key: 'cancelled', label: 'Dibatalkan' },
];

const roleText = computed(() => (user.value.roles ?? []).map((role) => roleLabels[role] ?? role).join(', '));
const total = computed(() => Object.values(props.stats).reduce((sum, count) => sum + count, 0));
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <section class="mb-8">
            <h1 class="text-2xl font-bold tracking-tight">Halo, {{ user.name }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-600">
                <span>{{ roleText || 'Belum ada peran' }}</span>
                <template v-if="user.company">
                    <span aria-hidden="true">&middot;</span>
                    <span>{{ user.company.name }}</span>
                    <span
                        v-if="user.company.pricing_tier"
                        class="rounded-full px-2 py-0.5 text-xs font-semibold uppercase"
                        :class="tierStyles[user.company.pricing_tier]"
                    >
                        {{ user.company.pricing_tier }}
                    </span>
                </template>
                <template v-if="user.warehouse">
                    <span aria-hidden="true">&middot;</span>
                    <span>{{ user.warehouse.name }}</span>
                </template>
            </div>
        </section>

        <section>
            <div class="mb-3 flex items-end justify-between gap-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                    Ringkasan purchase order ({{ total }})
                </h2>
                <Link href="/orders" class="text-sm font-medium text-sky-700 hover:underline">
                    Lihat semua pesanan &rarr;
                </Link>
            </div>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <Link
                    v-for="card in statusCards"
                    :key="card.key"
                    :href="`/orders?status=${card.key}`"
                    class="group rounded-lg border border-slate-200 bg-white p-4 outline-none transition hover:-translate-y-0.5 hover:border-slate-400 hover:shadow-md focus-visible:ring-2 focus-visible:ring-slate-900"
                >
                    <p class="text-3xl font-bold tabular-nums">{{ stats[card.key] ?? 0 }}</p>
                    <p class="mt-1 text-sm text-slate-600 transition group-hover:text-slate-900">{{ card.label }}</p>
                </Link>
            </div>
        </section>
    </AppLayout>
</template>
