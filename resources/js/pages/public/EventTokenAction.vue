<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";

const props = defineProps<{
    lodge: { name: string; slug: string };
    token: string;
    kind: "reservation" | "reminder";
}>();
const form = useForm({});
const label =
    props.kind === "reservation"
        ? "Cancel reservation"
        : "Unsubscribe from reminders";
const action =
    props.kind === "reservation"
        ? `/l/${props.lodge.slug}/reservations/cancel/${props.token}`
        : `/l/${props.lodge.slug}/reminders/unsubscribe/${props.token}`;
</script>

<template>
    <Head :title="label" />
    <main class="mx-auto max-w-lg p-6">
        <Link
            :href="`/l/${lodge.slug}/events`"
            class="text-sm text-primary underline"
            >{{ lodge.name }} events</Link
        >
        <section class="mt-6 rounded-xl border bg-card p-6">
            <h1 class="text-2xl font-semibold">{{ label }}</h1>
            <p class="mt-3 text-muted-foreground">
                This action cannot be undone. You can make a new request later
                if needed.
            </p>
            <form class="mt-6 flex gap-3" @submit.prevent="form.post(action)">
                <button
                    :disabled="form.processing"
                    class="rounded-md bg-destructive px-4 py-2 font-medium text-destructive-foreground"
                >
                    Confirm</button
                ><Link
                    :href="`/l/${lodge.slug}/events`"
                    class="rounded-md border px-4 py-2 font-medium"
                    >Keep current status</Link
                >
            </form>
        </section>
    </main>
</template>
