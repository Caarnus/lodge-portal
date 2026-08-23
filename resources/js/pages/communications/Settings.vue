<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
defineOptions({ layout: AppLayout });
const props = defineProps<{ lodge: any; settings: any }>();
const form = useForm({
    sender_display_name: props.settings.sender_display_name ?? "",
    reply_to_email: props.settings.reply_to_email ?? "",
    secretary_email: props.settings.secretary_email ?? "",
    newsletter_contact_email: props.settings.newsletter_contact_email ?? "",
});
</script>
<template>
    <Head title="Communication settings" />
    <main class="mx-auto max-w-3xl p-6">
        <Link :href="`/lodges/${lodge.id}/settings`" class="underline"
            >← Lodge settings</Link
        >
        <h1 class="mt-4 text-3xl font-bold">Communication settings</h1>
        <p class="mt-2 text-slate-600">
            Used for future lodge communication headers and replies.
        </p>
        <form
            class="mt-6 grid gap-4 rounded border p-5"
            @submit.prevent="
                form.put(`/lodges/${lodge.id}/communication-settings`)
            "
        >
            <label
                >Sender display name<input
                    v-model="form.sender_display_name"
                    class="field-input" /></label
            ><label
                >Reply-to email<input
                    v-model="form.reply_to_email"
                    type="email"
                    class="field-input" /></label
            ><label
                >Secretary email<input
                    v-model="form.secretary_email"
                    type="email"
                    class="field-input" /></label
            ><label
                >Newsletter contact email<input
                    v-model="form.newsletter_contact_email"
                    type="email"
                    class="field-input"
            /></label>
            <p v-if="Object.keys(form.errors).length" class="text-red-700">
                {{ Object.values(form.errors)[0] }}
            </p>
            <button class="w-fit rounded bg-slate-900 px-4 py-2 text-white">
                Save
            </button>
        </form>
    </main>
</template>
