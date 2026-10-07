<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    appName: String,
    phpVersion: String,
});
</script>

<template>
    <Head title="Welcome" />
    <main class="min-h-screen flex items-center justify-center bg-slate-950 text-slate-100">
        <div class="text-center space-y-3">
            <h1 class="text-4xl font-bold tracking-tight">{{ appName }}</h1>
            <p class="text-slate-400">
                Laravel + Inertia + Vue + Tailwind berjalan di PHP {{ phpVersion }}
            </p>
        </div>
    </main>
</template>
