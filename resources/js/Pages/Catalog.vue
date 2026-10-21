<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({
    products: { type: Array, required: true },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const tier = computed(() => user.value.company?.pricing_tier ?? null);
const canOrder = computed(() => Boolean(user.value.can?.['po.create'] && tier.value));

const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
const formatPrice = (value) => (value === null || value === undefined ? '-' : rupiah.format(Number(value)));

// Keranjang di sisi klien: kunci "uuid-produk|kode-gudang" berisi jumlah. Server tetap memeriksa ulang semuanya.
const cart = ref({});
const keyOf = (product, stock) => `${product.uuid}|${stock.warehouse_code}`;

const lines = computed(() =>
    props.products.flatMap((product) =>
        product.stock
            .map((stock) => ({
                product: product.uuid,
                warehouse: stock.warehouse_code,
                quantity: Number(cart.value[keyOf(product, stock)]),
            }))
            .filter((line) => Number.isInteger(line.quantity) && line.quantity > 0),
    ),
);

// MOQ dihitung dari total jumlah produk di semua gudang (sama dengan aturan server).
const summary = computed(() =>
    props.products
        .map((product) => {
            const quantity = lines.value
                .filter((line) => line.product === product.uuid)
                .reduce((sum, line) => sum + line.quantity, 0);
            const estimate = product.price === null ? 0 : quantity * Number(product.price);
            const short = product.moq !== null && quantity < product.moq;

            return { product, quantity, estimate, short };
        })
        .filter((row) => row.quantity > 0),
);

const subtotal = computed(() => summary.value.reduce((sum, row) => sum + row.estimate, 0));
const hasShortfall = computed(() => summary.value.some((row) => row.short));

const form = useForm({ lines: [] });
const errorMessages = computed(() => Object.values(form.errors));

function submit() {
    form.transform(() => ({ lines: lines.value })).post('/orders', { preserveScroll: true });
}
</script>

<template>
    <Head title="Katalog" />

    <AppLayout>
        <section class="mb-6">
            <h1 class="text-2xl font-bold tracking-tight">Katalog produk</h1>
            <p v-if="canOrder" class="mt-1 text-sm text-slate-600">
                Harga mengikuti tier perusahaan Anda
                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold uppercase">{{ tier }}</span>
                dan minimum order (MOQ) dihitung dari total jumlah di semua gudang.
            </p>
            <p v-else class="mt-1 text-sm text-slate-600">
                Akun Anda dapat melihat stok per gudang, tetapi tidak membuat pesanan.
            </p>
        </section>

        <div class="grid gap-6" :class="canOrder ? 'lg:grid-cols-3' : ''">
            <div class="space-y-4" :class="canOrder ? 'lg:col-span-2' : ''">
                <p v-if="products.length === 0" class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-600">
                    Belum ada produk aktif.
                </p>

                <article
                    v-for="product in products"
                    :key="product.uuid"
                    class="rounded-lg border border-slate-200 bg-white p-4"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h2 class="font-semibold">{{ product.name }}</h2>
                            <p class="text-xs text-slate-500">{{ product.sku }} &middot; satuan {{ product.unit }}</p>
                        </div>
                        <div v-if="product.price !== null" class="text-right">
                            <p class="font-semibold">{{ formatPrice(product.price) }}</p>
                            <p class="text-xs text-slate-500">MOQ {{ product.moq }}</p>
                        </div>
                    </div>

                    <ul class="mt-3 divide-y divide-slate-100 text-sm">
                        <li v-if="product.stock.length === 0" class="py-2 text-slate-500">Belum ada stok di gudang mana pun.</li>
                        <li
                            v-for="stock in product.stock"
                            :key="stock.warehouse_code"
                            class="flex items-center justify-between gap-3 py-2"
                        >
                            <span>
                                {{ stock.warehouse_name }}
                                <span class="text-xs text-slate-500">({{ stock.warehouse_code }})</span>
                            </span>
                            <span class="flex items-center gap-3">
                                <span :class="stock.available > 0 ? 'text-slate-700' : 'text-red-600'">
                                    Tersedia {{ stock.available }}
                                </span>
                                <input
                                    v-if="canOrder"
                                    v-model.number="cart[keyOf(product, stock)]"
                                    type="number"
                                    min="0"
                                    :max="stock.available"
                                    :disabled="stock.available < 1"
                                    :aria-label="`Jumlah ${product.name} dari ${stock.warehouse_name}`"
                                    class="w-24 rounded-md border border-slate-300 px-2 py-1 text-right text-sm disabled:bg-slate-100"
                                />
                            </span>
                        </li>
                    </ul>
                </article>
            </div>

            <aside v-if="canOrder" class="lg:sticky lg:top-6 lg:self-start">
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <h2 class="font-semibold">Keranjang</h2>

                    <p v-if="summary.length === 0" class="mt-2 text-sm text-slate-500">
                        Isi jumlah pada gudang yang Anda pilih.
                    </p>

                    <ul v-else class="mt-3 space-y-3 text-sm">
                        <li v-for="row in summary" :key="row.product.uuid">
                            <div class="flex justify-between gap-2">
                                <span class="font-medium">{{ row.product.name }}</span>
                                <span>{{ row.quantity }}</span>
                            </div>
                            <p class="text-xs text-slate-500">{{ formatPrice(row.estimate) }}</p>
                            <p v-if="row.short" class="text-xs text-red-600">
                                Di bawah MOQ ({{ row.product.moq }}).
                            </p>
                        </li>
                    </ul>

                    <div v-if="summary.length > 0" class="mt-4 border-t border-slate-100 pt-3 text-sm">
                        <div class="flex justify-between font-semibold">
                            <span>Estimasi subtotal</span>
                            <span>{{ formatPrice(subtotal) }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            Belum termasuk PPN. Angka final dihitung server saat draft dibuat.
                        </p>
                    </div>

                    <ul v-if="errorMessages.length > 0" role="alert" class="mt-3 space-y-1 text-sm text-red-600">
                        <li v-for="message in errorMessages" :key="message">{{ message }}</li>
                    </ul>

                    <button
                        type="button"
                        :disabled="lines.length === 0 || hasShortfall || form.processing"
                        class="mt-4 w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                        @click="submit"
                    >
                        {{ form.processing ? 'Memproses...' : 'Buat draft PO' }}
                    </button>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
