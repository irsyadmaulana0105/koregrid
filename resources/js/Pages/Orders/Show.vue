<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { formatDate, formatRupiah, statusLabels, statusStyles } from '../../lib/orderStatus';

const props = defineProps({
    order: { type: Object, required: true },
});

const page = usePage();
const actionError = computed(() => page.props.errors?.action ?? null);
const base = computed(() => `/orders/${props.order.uuid}`);
const actions = computed(() => props.order.actions);
const hasActions = computed(() => Object.values(actions.value).some(Boolean));

const timeline = computed(() =>
    [
        ['Dibuat', props.order.created_at],
        ['Diajukan', props.order.submitted_at],
        ['Disetujui', props.order.approved_at],
        ['Ditolak', props.order.rejected_at],
        ['Dikirim', props.order.shipped_at],
        ['Selesai', props.order.completed_at],
    ].filter(([, value]) => value),
);

const rejectForm = useForm({ reason: '' });
const shipForm = useForm({ tracking_number: '' });

function act(name) {
    router.post(`${base.value}/${name}`, {}, { preserveScroll: true });
}

function cancelOrder() {
    if (window.confirm('Batalkan pesanan ini? Stok yang terkunci akan dilepas.')) {
        act('cancel');
    }
}

function reject() {
    rejectForm.post(`${base.value}/reject`, { preserveScroll: true, onSuccess: () => rejectForm.reset() });
}

function ship() {
    shipForm.post(`${base.value}/ship`, { preserveScroll: true, onSuccess: () => shipForm.reset() });
}
</script>

<template>
    <Head :title="order.po_number" />

    <AppLayout>
        <Link href="/orders" class="text-sm text-slate-600 hover:text-slate-900">&larr; Semua pesanan</Link>

        <section class="mt-3 mb-6 flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold tracking-tight">{{ order.po_number }}</h1>
            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusStyles[order.status]">
                {{ statusLabels[order.status] ?? order.status }}
            </span>
            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold uppercase text-slate-700">
                {{ order.pricing_tier }}
            </span>
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Produk</th>
                                <th class="px-4 py-3">Gudang</th>
                                <th class="px-4 py-3 text-right">Jumlah</th>
                                <th class="px-4 py-3 text-right">Harga</th>
                                <th class="px-4 py-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in order.items" :key="item.sku + item.warehouse_code">
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ item.name }}</p>
                                    <p class="text-xs text-slate-500">{{ item.sku }}</p>
                                </td>
                                <td class="px-4 py-3">{{ item.warehouse_name }}</td>
                                <td class="px-4 py-3 text-right">{{ item.quantity }} {{ item.unit }}</td>
                                <td class="px-4 py-3 text-right">{{ formatRupiah(item.unit_price) }}</td>
                                <td class="px-4 py-3 text-right">{{ formatRupiah(item.line_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <dl class="space-y-1 border-t border-slate-200 px-4 py-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-600">Subtotal</dt>
                            <dd>{{ formatRupiah(order.subtotal) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-600">PPN</dt>
                            <dd>{{ formatRupiah(order.tax_amount) }}</dd>
                        </div>
                        <div class="flex justify-between text-base font-semibold">
                            <dt>Total</dt>
                            <dd>{{ formatRupiah(order.total_amount) }}</dd>
                        </div>
                    </dl>
                </div>

                <div v-if="order.rejection_reason" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-semibold">Alasan penolakan</p>
                    <p class="mt-1">{{ order.rejection_reason }}</p>
                </div>
            </div>

            <aside class="space-y-6">
                <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
                    <h2 class="font-semibold">Ringkasan</h2>
                    <dl class="mt-3 space-y-2">
                        <div>
                            <dt class="text-xs text-slate-500">Perusahaan</dt>
                            <dd>{{ order.company }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Pembuat</dt>
                            <dd>{{ order.buyer }}</dd>
                        </div>
                        <div v-if="order.tracking_number">
                            <dt class="text-xs text-slate-500">Nomor resi</dt>
                            <dd>{{ order.tracking_number }}</dd>
                        </div>
                        <div v-if="order.invoice_number">
                            <dt class="text-xs text-slate-500">Invoice</dt>
                            <dd>{{ order.invoice_number }}</dd>
                        </div>
                        <div v-if="order.status === 'draft' && order.reservation_expires_at">
                            <dt class="text-xs text-slate-500">Stok ditahan sampai</dt>
                            <dd>{{ formatDate(order.reservation_expires_at) }}</dd>
                        </div>
                    </dl>

                    <ul class="mt-4 space-y-1 border-t border-slate-100 pt-3 text-xs text-slate-600">
                        <li v-for="[label, value] in timeline" :key="label" class="flex justify-between gap-2">
                            <span>{{ label }}</span>
                            <span>{{ formatDate(value) }}</span>
                        </li>
                    </ul>
                </div>

                <div v-if="hasActions || actionError" class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
                    <h2 class="font-semibold">Tindakan</h2>

                    <p v-if="actionError" role="alert" class="mt-2 rounded-md bg-red-50 px-3 py-2 text-red-700">
                        {{ actionError }}
                    </p>

                    <div class="mt-3 space-y-3">
                        <button
                            v-if="actions.submit"
                            type="button"
                            class="w-full rounded-md bg-slate-900 px-4 py-2 font-semibold text-white hover:bg-slate-800"
                            @click="act('submit')"
                        >
                            Ajukan untuk persetujuan
                        </button>

                        <button
                            v-if="actions.approve"
                            type="button"
                            class="w-full rounded-md bg-emerald-700 px-4 py-2 font-semibold text-white hover:bg-emerald-800"
                            @click="act('approve')"
                        >
                            Setujui
                        </button>

                        <form v-if="actions.reject" class="space-y-2" @submit.prevent="reject">
                            <label for="reason" class="block text-xs font-medium text-slate-600">Alasan penolakan</label>
                            <textarea
                                id="reason"
                                v-model="rejectForm.reason"
                                rows="2"
                                maxlength="1000"
                                class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            ></textarea>
                            <p v-if="rejectForm.errors.reason" role="alert" class="text-xs text-red-600">
                                {{ rejectForm.errors.reason }}
                            </p>
                            <button
                                type="submit"
                                :disabled="rejectForm.processing"
                                class="w-full rounded-md border border-red-300 px-4 py-2 font-semibold text-red-700 hover:bg-red-50 disabled:opacity-50"
                            >
                                Tolak
                            </button>
                        </form>

                        <button
                            v-if="actions.pack"
                            type="button"
                            class="w-full rounded-md bg-indigo-700 px-4 py-2 font-semibold text-white hover:bg-indigo-800"
                            @click="act('pack')"
                        >
                            Mulai kemas
                        </button>

                        <form v-if="actions.ship" class="space-y-2" @submit.prevent="ship">
                            <label for="tracking" class="block text-xs font-medium text-slate-600">Nomor resi</label>
                            <input
                                id="tracking"
                                v-model="shipForm.tracking_number"
                                type="text"
                                maxlength="100"
                                class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            />
                            <p v-if="shipForm.errors.tracking_number" role="alert" class="text-xs text-red-600">
                                {{ shipForm.errors.tracking_number }}
                            </p>
                            <button
                                type="submit"
                                :disabled="shipForm.processing"
                                class="w-full rounded-md bg-violet-700 px-4 py-2 font-semibold text-white hover:bg-violet-800 disabled:opacity-50"
                            >
                                Kirim barang
                            </button>
                        </form>

                        <button
                            v-if="actions.complete"
                            type="button"
                            class="w-full rounded-md bg-emerald-700 px-4 py-2 font-semibold text-white hover:bg-emerald-800"
                            @click="act('complete')"
                        >
                            Konfirmasi barang diterima
                        </button>

                        <button
                            v-if="actions.cancel"
                            type="button"
                            class="w-full rounded-md border border-slate-300 px-4 py-2 font-semibold text-slate-700 hover:bg-slate-100"
                            @click="cancelOrder"
                        >
                            Batalkan pesanan
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
