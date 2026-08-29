<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
defineProps<{ lodge: any; token: string }>();
const form = useForm({});
</script>
<template>
    <Head title="Unsubscribe" />
    <div class="flex min-h-dvh flex-col bg-background text-foreground">
        <main class="mx-auto w-full max-w-xl flex-1 p-6">
            <h1 class="text-3xl font-bold">Lodge email preference</h1>
            <p class="mt-3">
                Stop receiving lodge email from {{ lodge.name }}.
            </p>
            <button
                class="mt-5 rounded bg-slate-900 px-4 py-2 text-white"
                @click="
                    form.post(
                        `/l/${lodge.slug}/communications/unsubscribe/${token}`,
                    )
                "
            >
                Unsubscribe
            </button>
        </main>
        <footer
            class="border-t bg-slate-950 px-5 py-10 text-center text-sm text-white"
        >
            <p class="font-semibold">{{ lodge.name }}</p>
            <p class="mt-1">{{ lodge.city }}, {{ lodge.state }}</p>
        </footer>
    </div>
</template>
