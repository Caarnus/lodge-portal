<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import { Head, useForm } from "@inertiajs/vue3";

defineOptions({ layout: AppLayout });
const props = defineProps<{ people: any[] }>();
const form = useForm({
    source_person_id: null as number | null,
    survivor_person_id: null as number | null,
});
const selected = (id: number | null) =>
    props.people.find((person) => person.id === id);
const submit = () =>
    confirm(
        "Merge the source into the survivor? This retires the source record and cannot be undone in the UI.",
    ) && form.post("/platform/people/merge");
</script>
<template>
    <Head title="Merge people" />
    <main class="mx-auto w-full max-w-3xl p-4 sm:p-6 lg:p-8">
        <PageHeader
            title="Merge duplicate people"
            description="Membership and relationship history moves to the survivor. Conflicting lodge memberships or account links stop the merge."
        />
        <form
            class="mt-6 space-y-4 rounded-lg border border-border/80 bg-card p-4"
            @submit.prevent="submit"
        >
            <label class="block"
                >Source record (retired)<select
                    v-model="form.source_person_id"
                    required
                    class="field-input mt-1"
                >
                    <option :value="null">Select source</option>
                    <option
                        v-for="person in people"
                        :key="person.id"
                        :value="person.id"
                    >
                        {{ person.display_name }} —
                        {{ person.email || "no email" }} ({{
                            person.memberships_count
                        }}
                        memberships)
                    </option>
                </select></label
            ><label class="block"
                >Surviving record<select
                    v-model="form.survivor_person_id"
                    required
                    class="field-input mt-1"
                >
                    <option :value="null">Select survivor</option>
                    <option
                        v-for="person in people"
                        :key="person.id"
                        :value="person.id"
                    >
                        {{ person.display_name }} —
                        {{ person.email || "no email" }} ({{
                            person.memberships_count
                        }}
                        memberships)
                    </option>
                </select></label
            >
            <div
                v-if="
                    selected(form.source_person_id) &&
                    selected(form.survivor_person_id)
                "
                class="grid gap-3 sm:grid-cols-2"
            >
                <article
                    v-for="item in [
                        {
                            label: 'Source',
                            person: selected(form.source_person_id),
                        },
                        {
                            label: 'Survivor',
                            person: selected(form.survivor_person_id),
                        },
                    ]"
                    :key="item.label"
                    class="rounded border border-border/80 bg-muted/30 p-3"
                >
                    <h2 class="font-semibold">{{ item.label }}</h2>
                    <dl class="mt-2 text-sm">
                        <dt class="font-medium">Name</dt>
                        <dd>{{ item.person.display_name }}</dd>
                        <dt class="mt-2 font-medium">Email</dt>
                        <dd>{{ item.person.email || "None" }}</dd>
                        <dt class="mt-2 font-medium">Phone</dt>
                        <dd>{{ item.person.phone || "None" }}</dd>
                        <dt class="mt-2 font-medium">Location</dt>
                        <dd>
                            {{
                                [
                                    item.person.mailing_city,
                                    item.person.mailing_state,
                                ]
                                    .filter(Boolean)
                                    .join(", ") || "None"
                            }}
                        </dd>
                        <dt class="mt-2 font-medium">Memberships</dt>
                        <dd>{{ item.person.memberships_count }}</dd>
                    </dl>
                </article>
            </div>
            <p
                v-for="message in form.errors"
                :key="message"
                class="text-sm text-destructive"
            >
                {{ message }}
            </p>
            <button
                class="primary-button bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
                Merge records
            </button>
        </form>
    </main>
</template>
