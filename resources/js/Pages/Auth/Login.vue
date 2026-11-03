<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const highlights = [
    { title: 'Harga per tier perusahaan', body: 'Gold, Silver, dan Bronze — dihitung server, tidak bisa dimanipulasi klien.' },
    { title: 'Stok terkunci otomatis', body: 'Draft PO mengunci stok gudang sehingga tidak terjadi penjualan berlebih.' },
    { title: 'Persetujuan end-to-end', body: 'Draft, approval Finance, pengemasan gudang, hingga invoice yang bisa dicetak.' },
];

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Masuk" />

    <div class="grid min-h-screen bg-slate-950 text-slate-100 md:grid-cols-2">
        <div class="relative hidden overflow-hidden md:flex md:flex-col md:justify-between md:p-12">
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -left-32 top-1/4 h-96 w-96 rounded-full bg-emerald-500/10 blur-3xl"
            ></div>
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -right-24 bottom-1/4 h-72 w-72 rounded-full bg-sky-500/10 blur-3xl"
            ></div>

            <p class="relative flex items-center gap-2 font-bold tracking-tight">
                <span
                    aria-hidden="true"
                    class="flex h-9 w-9 items-center justify-center rounded-md bg-emerald-500 text-base font-extrabold text-slate-950"
                    >K</span
                >
                KoreGrid
            </p>

            <div class="relative max-w-md">
                <h2 class="text-4xl font-bold leading-tight tracking-tight">
                    Rantai pasok B2B multi-gudang,<br>
                    <span class="text-emerald-400">terkendali dari satu portal.</span>
                </h2>
                <p class="mt-4 text-sm leading-6 text-slate-400">
                    Portal korporat untuk memesan stok dari beberapa gudang dengan harga tier, alur persetujuan, dan
                    jejak audit yang lengkap.
                </p>

                <ul class="mt-10 space-y-5">
                    <li v-for="item in highlights" :key="item.title" class="flex gap-3">
                        <span
                            aria-hidden="true"
                            class="mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-400"
                        >
                            <svg viewBox="0 0 20 20" fill="none" class="h-3 w-3" aria-hidden="true">
                                <path
                                    d="M4 10.5 8.5 15 16 5.5"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </span>
                        <span>
                            <span class="block text-sm font-semibold">{{ item.title }}</span>
                            <span class="mt-0.5 block text-xs leading-5 text-slate-400">{{ item.body }}</span>
                        </span>
                    </li>
                </ul>
            </div>

            <p class="relative text-xs text-slate-600">&copy; 2026 KoreGrid &mdash; PT Maju Jaya, PT Artha Sentosa, dan partner lainnya.</p>
        </div>

        <div class="flex items-center justify-center px-4 py-12">
            <div class="w-full max-w-sm">
                <div class="mb-8 md:hidden">
                    <p class="flex items-center gap-2 text-xl font-bold tracking-tight text-white">
                        <span
                            aria-hidden="true"
                            class="flex h-8 w-8 items-center justify-center rounded-md bg-emerald-500 text-sm font-extrabold text-slate-950"
                            >K</span
                        >
                        KoreGrid
                    </p>
                </div>

                <h1 class="text-2xl font-bold tracking-tight text-white">Masuk</h1>
                <p class="mt-1 text-sm text-slate-400">Gunakan akun perusahaan Anda.</p>

                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-300">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="username"
                            required
                            autofocus
                            class="mt-1 block w-full rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 placeholder-slate-600 outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                        />
                        <p v-if="form.errors.email" role="alert" class="mt-1 text-sm text-red-400">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-300">Password</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="mt-1 block w-full rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 placeholder-slate-600 outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                        />
                        <p v-if="form.errors.password" role="alert" class="mt-1 text-sm text-red-400">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-400">
                        <input v-model="form.remember" type="checkbox" class="rounded border-slate-700 bg-slate-900" />
                        Ingat saya di perangkat ini
                    </label>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-md bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 outline-none transition-colors hover:bg-emerald-400 disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald-300"
                    >
                        {{ form.processing ? 'Memproses...' : 'Masuk' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
