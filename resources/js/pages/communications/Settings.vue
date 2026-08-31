<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
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
    <main class="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
        <PageHeader
            title="Communication settings"
            description="Used for future lodge communication headers and replies."
        >
            <template #actions>
                <Link
                    :href="`/lodges/${lodge.id}/settings`"
                    class="secondary-button"
                >
                    Back to lodge settings
                </Link>
            </template>
        </PageHeader>
        <form
            class="grid gap-4 rounded-lg border border-border/80 bg-card p-5"
            @submit.prevent="
                form.put(`/lodges/${lodge.id}/communication-settings`)
            "
        >
            <label class="field-label"
                >Sender display name<input
                    v-model="form.sender_display_name"
                    class="field-input" /></label
            ><label class="field-label"
                >Reply-to email<input
                    v-model="form.reply_to_email"
                    type="email"
                    class="field-input" /></label
            ><label class="field-label"
                >Secretary email<input
                    v-model="form.secretary_email"
                    type="email"
                    class="field-input" /></label
            ><label class="field-label"
                >Newsletter contact email<input
                    v-model="form.newsletter_contact_email"
                    type="email"
                    class="field-input"
            /></label>
            <p v-if="Object.keys(form.errors).length" class="text-destructive">
                {{ Object.values(form.errors)[0] }}
            </p>
            <button class="primary-button w-fit">Save</button>
        </form>
    </main>
</template>
