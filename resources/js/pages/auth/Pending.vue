<script lang="ts" setup>
import {Head, Link} from "@inertiajs/vue3";
</script>
<template>
    <Head title="Pending approval"/>
    <main class="mx-auto mt-24 max-w-lg rounded-xl border p-8">
        <h1 class="text-2xl font-semibold">Registration pending</h1>
        <p class="mt-3 text-muted-foreground">
            Verify your email and wait for a platform or home-lodge
            administrator to approve your registration.
        </p>
        <Link
            as="button"
            class="primary-button mt-6"
            href="/logout"
            method="post"
        >Sign out
        </Link
        >
    </main>
</template>
