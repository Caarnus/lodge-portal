<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import RichTextField from "@/components/website/RichTextField.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
defineOptions({ layout: AppLayout });
const props = defineProps<{
    lodge: any;
    communication: any;
    memberships: any[];
    relations: any[];
}>();
const form = useForm({
    subject: props.communication.subject,
    body_html: props.communication.body_html,
    audience_mode: props.communication.audience_mode ?? "all",
    degree_keys: props.communication.degree_keys ?? [],
    membership_status_keys: props.communication.membership_status_keys ?? [],
    membership_ids: props.communication.membership_ids ?? [],
    relation_person_ids: props.communication.relation_person_ids ?? [],
});
const degrees = [
    ...new Set(props.memberships.map((m) => m.degree?.key).filter(Boolean)),
];
const statuses = [
    ...new Set(props.memberships.map((m) => m.status?.key).filter(Boolean)),
];
const send = () => {
    form.put(
        `/lodges/${props.lodge.id}/communications/manage/${props.communication.id}`,
        {
            onSuccess: () =>
                router.post(
                    `/lodges/${props.lodge.id}/communications/manage/${props.communication.id}/send`,
                ),
        },
    );
};
</script>
<template>
    <Head :title="communication.subject" />
    <main class="mx-auto max-w-4xl space-y-6 p-6">
        <Link
            :href="`/lodges/${lodge.id}/communications/manage`"
            class="underline"
            >← Communications</Link
        >
        <form
            v-if="communication.status === 'draft'"
            class="grid gap-4 rounded border p-5"
            @submit.prevent="
                form.put(
                    `/lodges/${lodge.id}/communications/manage/${communication.id}`,
                )
            "
        >
            <input
                v-model="form.subject"
                required
                class="field-input"
            /><RichTextField v-model="form.body_html" />
            <fieldset class="grid gap-3 border-t pt-4">
                <legend class="font-semibold">Recipients</legend>
                <select v-model="form.audience_mode" class="field-input">
                    <option value="all">All eligible members</option>
                    <option value="filtered">Filter by degree or status</option>
                    <option value="selected">
                        Selected members and relations
                    </option>
                </select>
                <template v-if="form.audience_mode === 'filtered'">
                    <label
                        >Degrees<select
                            v-model="form.degree_keys"
                            multiple
                            class="field-input"
                        >
                            <option
                                v-for="degree in degrees"
                                :key="degree"
                                :value="degree"
                            >
                                {{ degree }}
                            </option>
                        </select></label
                    >
                    <label
                        >Membership statuses<select
                            v-model="form.membership_status_keys"
                            multiple
                            class="field-input"
                        >
                            <option
                                v-for="status in statuses"
                                :key="status"
                                :value="status"
                            >
                                {{ status }}
                            </option>
                        </select></label
                    >
                </template>
                <template v-if="form.audience_mode === 'selected'">
                    <label
                        >Members<select
                            v-model="form.membership_ids"
                            multiple
                            class="field-input"
                        >
                            <option
                                v-for="membership in memberships"
                                :key="membership.id"
                                :value="membership.id"
                            >
                                {{ membership.person.display_name }} —
                                {{ membership.degree?.name }} /
                                {{ membership.status?.name }}
                            </option>
                        </select></label
                    >
                    <label
                        >Relations<select
                            v-model="form.relation_person_ids"
                            multiple
                            class="field-input"
                        >
                            <option
                                v-for="relation in relations"
                                :key="relation.person_id"
                                :value="relation.person_id"
                            >
                                {{ relation.name }} — {{ relation.type }} of
                                {{ relation.related_to }}
                            </option>
                        </select></label
                    >
                </template>
            </fieldset>
            <button
                v-if="communication.status === 'draft'"
                class="w-fit rounded bg-slate-900 px-4 py-2 text-white"
            >
                Save draft
            </button>
        </form>
        <section v-else class="space-y-4 rounded border p-5">
            <p>
                Sent messages cannot be changed in place. Create an editable
                copy to revise its content or recipients and send it again.
            </p>
            <article
                class="public-rich-text"
                v-html="communication.body_html"
            />
            <button
                class="rounded border px-4 py-2"
                @click="
                    router.post(
                        `/lodges/${lodge.id}/communications/manage/${communication.id}/duplicate`,
                    )
                "
            >
                Create editable resend
            </button>
        </section>
        <button
            v-if="communication.status === 'draft'"
            class="rounded border px-4 py-2"
            @click="send"
        >
            Save and send
        </button>
    </main>
</template>
