<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { formatDate, formatRupiah, statusLabels, statusStyles } from '../../lib/orderStatus';

const props = defineProps({
    orders: { type: Object, required: true },
    filters: { type: Object, required: true },
});

function filterByStatus(event) {
    const status = event.target.value;
    router.get('/orders', status ? { status } : {}, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Pesanan" />

    <AppLayout>
        <section class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Pesanan</h1>
                <p class="mt-1 text-sm text-slate-600">{{ orders.total }} purchase order</p>
            </div>

            <label class="text-sm">
                <span class="mr-2 text-slate-600">Status</span>
                <select
                    :value="filters.status ?? ''"
                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm"
                    @change="filterByStatus"
                >
                    <option value="">Semua</option>
                    <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
                </select>
            </label>
        </section>

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
                    <tr v-if="orders.data.length === 0">
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">Belum ada pesanan.</td>
                    </tr>
                    <tr v-for="order in orders.data" :key="order.uuid" class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">
                            <Link :href="`/orders/${order.uuid}`" class="text-sky-700 underline underline-offset-2 hover:text-sky-900">
                                {{ order.po_number }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">{{ order.company }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="statusStyles[order.status]"
                            >
                                {{ statusLabels[order.status] ?? order.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">{{ formatRupiah(order.total_amount) }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ formatDate(order.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="orders.last_page > 1" class="mt-4 flex items-center justify-between text-sm">
            <Link
                v-if="orders.prev_page_url"
                :href="orders.prev_page_url"
                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-100"
            >
                Sebelumnya
            </Link>
            <span v-else></span>
            <span class="text-slate-600">Halaman {{ orders.current_page }} dari {{ orders.last_page }}</span>
            <Link
                v-if="orders.next_page_url"
                :href="orders.next_page_url"
                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-100"
            >
                Berikutnya
            </Link>
            <span v-else></span>
        </nav>
    </AppLayout>
</template>
