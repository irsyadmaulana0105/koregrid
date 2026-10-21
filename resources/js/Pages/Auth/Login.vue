<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Masuk" />

    <div class="flex min-h-screen items-center justify-center bg-slate-950 px-4">
        <div class="w-full max-w-sm rounded-xl bg-white p-8 shadow-xl">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">KoreGrid</h1>
            <p class="mt-1 text-sm text-slate-500">Portal pemesanan B2B multi-gudang. Silakan masuk.</p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        required
                        autofocus
                        class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900"
                    />
                    <p v-if="form.errors.email" role="alert" class="mt-1 text-sm text-red-600">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900"
                    />
                    <p v-if="form.errors.password" role="alert" class="mt-1 text-sm text-red-600">
                        {{ form.errors.password }}
                    </p>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-300" />
                    Ingat saya di perangkat ini
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                >
                    {{ form.processing ? 'Memproses...' : 'Masuk' }}
                </button>
            </form>
        </div>
    </div>
</template>
