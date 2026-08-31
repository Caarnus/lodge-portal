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
                class="primary-button mt-5"
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
            class="border-t border-border/80 bg-foreground px-5 py-10 text-center text-sm text-background"
        >
            <p class="font-semibold">{{ lodge.name }}</p>
            <p class="mt-1">{{ lodge.city }}, {{ lodge.state }}</p>
        </footer>
    </div>
</template>
