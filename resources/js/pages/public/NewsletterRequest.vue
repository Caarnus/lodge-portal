<script lang="ts" setup>
import {Head, useForm} from "@inertiajs/vue3";

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
    <Head title="Newsletter request"/>
    <div class="flex min-h-dvh flex-col bg-background text-foreground">
        <main class="mx-auto w-full max-w-2xl flex-1 p-6">
            <h1 class="text-3xl font-bold">Newsletter delivery request</h1>
            <p class="mt-2 text-muted-foreground">
                Request a family newsletter. This does not create an account or
                member access.
            </p>
            <form
                class="mt-6 grid gap-3"
                @submit.prevent="
                    form.post(`/l/${lodge.slug}/newsletters/request`)
                "
            >
                <input
                    v-model="form.requester_name"
                    class="field-input"
                    placeholder="Your name"
                    required
                /><input
                v-model="form.requester_email"
                class="field-input"
                placeholder="Email"
                type="email"
            /><label
            ><input v-model="form.receives_email" type="checkbox"/>
                Electronic delivery</label
            ><label
            ><input v-model="form.receives_print" type="checkbox"/>
                Mailed delivery</label
            ><input
                v-model="form.mailing_address_line_1"
                class="field-input"
                placeholder="Address line 1"
            /><input
                v-model="form.mailing_city"
                class="field-input"
                placeholder="City"
            /><input
                v-model="form.mailing_state"
                class="field-input"
                maxlength="2"
                placeholder="State"
            /><input
                v-model="form.mailing_postal_code"
                class="field-input"
                placeholder="Postal code"
            /><input
                v-model="form.claimed_relationship"
                class="field-input"
                placeholder="Relationship"
            /><input
                v-model="form.claimed_related_member_name"
                class="field-input"
                placeholder="Related member name"
            /><input
                v-model="form.website"
                autocomplete="off"
                class="hidden"
                tabindex="-1"
            />
                <p v-if="Object.keys(form.errors).length" class="text-red-700">
                    {{ Object.values(form.errors)[0] }}
                </p>
                <button class="primary-button w-fit">Submit request</button>
            </form>
        </main>
        <footer
            class="border-t border-border/80 bg-foreground px-5 py-10 text-center text-sm text-background"
        >
            <p class="font-semibold">{{ lodge.name }}</p>
            <p class="mt-1">{{ lodge.city }}, {{ lodge.state }}</p>
        </footer>
    </div>
</template>
