<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { formatDate, formatRupiah, statusLabels, statusStyles } from '../lib/orderStatus';

const props = defineProps({
    approval: { type: Array, default: null },
    fulfillment: { type: Array, default: null },
});

const sections = computed(() =>
    [
        { key: 'approval', title: 'Menunggu persetujuan Finance', rows: props.approval },
        { key: 'fulfillment', title: 'Siap diproses gudang (disetujui dan dikemas)', rows: props.fulfillment },
    ].filter((section) => section.rows !== null),
);
</script>

<template>
    <Head title="Antrean" />

    <AppLayout>
        <h1 class="mb-6 text-2xl font-bold tracking-tight">Antrean kerja</h1>

        <section v-for="section in sections" :key="section.key" class="mb-8">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
                {{ section.title }} ({{ section.rows.length }})
            </h2>

            <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">No. PO</th>
                            <th class="px-4 py-3">Perusahaan</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Dibuat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="section.rows.length === 0">
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">Antrean kosong.</td>
                        </tr>
                        <tr v-for="order in section.rows" :key="order.uuid" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium">
                                <Link :href="`/orders/${order.uuid}`" class="text-sky-700 underline underline-offset-2 hover:text-sky-900">
                                    {{ order.po_number }}
                                </Link>
                            </td>
                            <td class="px-4 py-3">{{ order.company }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusStyles[order.status]">
                                    {{ statusLabels[order.status] ?? order.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">{{ formatRupiah(order.total_amount) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ formatDate(order.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
