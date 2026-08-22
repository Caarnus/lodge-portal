<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
</script>
<template>
    <Head title="Pending approval" />
    <main class="mx-auto mt-24 max-w-lg rounded-xl border p-8">
        <h1 class="text-2xl font-semibold">Registration pending</h1>
        <p class="mt-3 text-slate-600">
            Verify your email and wait for a platform or home-lodge
            administrator to approve your registration.
        </p>
        <Link
            href="/logout"
            method="post"
            as="button"
            class="mt-6 rounded bg-slate-900 px-4 py-2 text-white"
            >Sign out</Link
        >
    </main>
</template>
