<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
defineProps<{ lodge: any }>();
const form = useForm({
    requester_name: "",
    requester_email: "",
    receives_email: true,
    receives_print: false,
    mailing_address_line_1: "",
    mailing_address_line_2: "",
    mailing_city: "",
    mailing_state: "",
    mailing_postal_code: "",
    claimed_relationship: "",
    claimed_related_member_name: "",
    website: "",
});
</script>
<template>
    <Head title="Newsletter request" />
    <main class="mx-auto max-w-2xl p-6">
        <h1 class="text-3xl font-bold">Newsletter delivery request</h1>
        <p class="mt-2 text-slate-600">
            Request a family newsletter. This does not create an account or
            member access.
        </p>
        <form
            class="mt-6 grid gap-3"
            @submit.prevent="form.post(`/l/${lodge.slug}/newsletters/request`)"
        >
            <input
                v-model="form.requester_name"
                required
                placeholder="Your name"
                class="field-input"
            /><input
                v-model="form.requester_email"
                type="email"
                placeholder="Email"
                class="field-input"
            /><label
                ><input v-model="form.receives_email" type="checkbox" />
                Electronic delivery</label
            ><label
                ><input v-model="form.receives_print" type="checkbox" /> Mailed
                delivery</label
            ><input
                v-model="form.mailing_address_line_1"
                placeholder="Address line 1"
                class="field-input"
            /><input
                v-model="form.mailing_city"
                placeholder="City"
                class="field-input"
            /><input
                v-model="form.mailing_state"
                maxlength="2"
                placeholder="State"
                class="field-input"
            /><input
                v-model="form.mailing_postal_code"
                placeholder="Postal code"
                class="field-input"
            /><input
                v-model="form.claimed_relationship"
                placeholder="Relationship"
                class="field-input"
            /><input
                v-model="form.claimed_related_member_name"
                placeholder="Related member name"
                class="field-input"
            /><input
                v-model="form.website"
                class="hidden"
                tabindex="-1"
                autocomplete="off"
            />
            <p v-if="Object.keys(form.errors).length" class="text-red-700">
                {{ Object.values(form.errors)[0] }}
            </p>
            <button class="w-fit rounded bg-slate-900 px-4 py-2 text-white">
                Submit request
            </button>
        </form>
    </main>
</template>
