<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import {Head, useForm} from "@inertiajs/vue3";

defineOptions({layout: AppLayout});
const props = defineProps<{ lodge: any; requests: any[]; subscriptions: any[]; people: any[]; relationships: any[] }>();
const approval = (request: any) => useForm({
    recipient_person_id: null as number | null,
    sponsoring_person_id: null as number | null,
    person_relationship_id: null as number | null,
    receives_email: request.receives_email,
    receives_print: request.receives_print,
    administrative_note: ''
});
const forms = new Map<number, any>();
const formFor = (request: any) => {
    let form = forms.get(request.id);
    if (!form) {
        form = approval(request);
        forms.set(request.id, form);
    }

    return form;
};
</script>
<template>
    <Head title="Newsletter recipients"/>
    <main class="mx-auto max-w-6xl space-y-6 p-6"><h1 class="text-3xl font-bold">Newsletter recipients</h1>
        <section class="rounded border p-5"><h2 class="text-xl font-semibold">Requests awaiting review</h2>
            <p v-if="!requests.length" class="mt-3 text-slate-600">No requests awaiting review.</p>
            <article v-for="request in requests" :key="request.id" class="mt-4 border-t pt-4"><p class="font-medium">
                {{ request.requester_name }} · {{ request.status }}</p>
                <p class="text-sm text-slate-600">{{ request.claimed_relationship }} ·
                    {{ request.claimed_related_member_name }}</p>
                <form class="mt-3 grid gap-2 sm:grid-cols-2"
                      @submit.prevent="formFor(request).post(`/lodges/${lodge.id}/newsletter-recipients/requests/${request.id}/approve`)">
                    <select v-model.number="formFor(request).recipient_person_id" required class="field-input">
                        <option :value="null">Recipient person</option>
                        <option v-for="person in people" :key="person.id" :value="person.id">{{ person.name }}</option>
                    </select><select v-model.number="formFor(request).sponsoring_person_id" required
                                     class="field-input">
                    <option :value="null">Sponsor person</option>
                    <option v-for="person in people" :key="person.id" :value="person.id">{{ person.name }}</option>
                </select><select v-model.number="formFor(request).person_relationship_id" required class="field-input">
                    <option :value="null">Qualifying relationship</option>
                    <option v-for="relationship in relationships" :key="relationship.id" :value="relationship.id">
                        {{ relationship.type.name }}
                    </option>
                </select><label><input v-model="formFor(request).receives_email" type="checkbox"/>
                    Email</label><label><input v-model="formFor(request).receives_print" type="checkbox"/> Print</label>
                    <button class="w-fit rounded bg-slate-900 px-3 py-2 text-white">Approve</button>
                </form>
                <button class="mt-2 text-red-700 underline"
                        @click="useForm({}).post(`/lodges/${lodge.id}/newsletter-recipients/requests/${request.id}/reject`)">
                    Reject
                </button>
            </article>
        </section>
        <section class="rounded border p-5"><h2 class="text-xl font-semibold">Approved family subscriptions</h2>
            <p v-if="!subscriptions.length" class="mt-3 text-slate-600">No active subscriptions.</p>
            <ul v-else class="mt-3 divide-y">
                <li v-for="subscription in subscriptions" :key="subscription.id" class="py-3">
                    <strong>{{ subscription.recipient.display_name }}</strong> — sponsor:
                    {{ subscription.sponsor.display_name }} · {{ subscription.status }}
                </li>
            </ul>
        </section>
    </main>
</template>
