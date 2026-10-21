<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { formatDate, formatRupiah } from '../../lib/orderStatus';

function printPage() {
    window.print();
}

defineProps({
    invoice: { type: Object, required: true },
});
</script>

<template>
    <Head :title="invoice.invoice_number" />

    <div class="min-h-screen bg-slate-100 px-4 py-8 print:bg-white print:p-0">
        <div class="mx-auto mb-4 flex max-w-3xl items-center justify-between print:hidden">
            <Link :href="`/orders/${invoice.uuid}`" class="text-sm text-slate-600 hover:text-slate-900">
                &larr; Kembali ke pesanan
            </Link>
            <button
                type="button"
                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                @click="printPage"
            >
                Cetak
            </button>
        </div>

        <article class="mx-auto max-w-3xl rounded-lg bg-white p-10 shadow print:max-w-none print:rounded-none print:shadow-none">
            <header class="flex flex-wrap items-start justify-between gap-6 border-b border-slate-200 pb-6">
                <div>
                    <p class="text-2xl font-bold tracking-tight">KoreGrid</p>
                    <p class="text-sm text-slate-500">Enterprise B2B Supply Chain &amp; Multi-Warehouse Portal</p>
                </div>
                <div class="text-right">
                    <p class="text-xl font-bold uppercase tracking-wide">Invoice</p>
                    <p class="mt-1 font-mono text-sm">{{ invoice.invoice_number }}</p>
                    <p class="text-xs text-slate-500">Terbit {{ formatDate(invoice.invoice_issued_at) }}</p>
                </div>
            </header>

            <section class="grid gap-6 border-b border-slate-200 py-6 text-sm sm:grid-cols-2">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Ditagihkan kepada</p>
                    <p class="mt-1 font-semibold">{{ invoice.company }}</p>
                    <p v-if="invoice.billing_address" class="text-slate-600">{{ invoice.billing_address }}</p>
                </div>
                <div class="sm:text-right">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Referensi</p>
                    <p class="mt-1">PO {{ invoice.po_number }}</p>
                    <p class="text-slate-600">Tier harga {{ invoice.pricing_tier }}</p>
                    <p v-if="invoice.tracking_number" class="text-slate-600">Resi {{ invoice.tracking_number }}</p>
                </div>
            </section>

            <table class="mt-6 w-full text-left text-sm">
                <thead class="border-b border-slate-300 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="py-2">Produk</th>
                        <th class="py-2">Gudang</th>
                        <th class="py-2 text-right">Jumlah</th>
                        <th class="py-2 text-right">Harga</th>
                        <th class="py-2 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="item in invoice.items" :key="item.sku + item.warehouse_code">
                        <td class="py-2">
                            <p class="font-medium">{{ item.name }}</p>
                            <p class="text-xs text-slate-500">{{ item.sku }}</p>
                        </td>
                        <td class="py-2">{{ item.warehouse_code }}</td>
                        <td class="py-2 text-right">{{ item.quantity }} {{ item.unit }}</td>
                        <td class="py-2 text-right">{{ formatRupiah(item.unit_price) }}</td>
                        <td class="py-2 text-right">{{ formatRupiah(item.line_total) }}</td>
                    </tr>
                </tbody>
            </table>

            <dl class="ml-auto mt-6 w-full max-w-xs space-y-1 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-600">Subtotal</dt>
                    <dd>{{ formatRupiah(invoice.subtotal) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-600">PPN {{ invoice.tax_percent }}%</dt>
                    <dd>{{ formatRupiah(invoice.tax_amount) }}</dd>
                </div>
                <div class="flex justify-between border-t border-slate-300 pt-2 text-base font-bold">
                    <dt>Total</dt>
                    <dd>{{ formatRupiah(invoice.total_amount) }}</dd>
                </div>
            </dl>
        </article>
    </div>
</template>
