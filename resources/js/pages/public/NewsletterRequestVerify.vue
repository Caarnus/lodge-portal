<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
const props = defineProps<{ lodge: any; token: string }>();
const form = useForm({});
</script>
<template>
    <Head title="Verify newsletter request" />
    <main class="mx-auto max-w-xl p-6">
        <h1 class="text-3xl font-bold">Verify request</h1>
        <p class="mt-3">
            Confirm email ownership to send this request to lodge review.
        </p>
        <button
            class="mt-5 rounded bg-slate-900 px-4 py-2 text-white"
            @click="
                form.post(
                    `/l/${lodge.slug}/newsletters/request/verify/${token}`,
                )
            "
        >
            Verify email
        </button>
    </main>
</template>
