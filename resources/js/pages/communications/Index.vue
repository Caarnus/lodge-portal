<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import RichTextField from "@/components/website/RichTextField.vue";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Head, router, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
defineOptions({ layout: AppLayout });
const props = defineProps<{
    lodge: any;
    communications: any[];
    memberships: any[];
    relations: any[];
}>();
const form = useForm({
    subject: "",
    body_html: "<p></p>",
    audience_mode: "all",
    degree_keys: [],
    membership_status_keys: [],
    membership_ids: [],
    relation_person_ids: [],
});
const creating = ref(false);
const editingId = ref<number | null>(null);
const editingSent = ref(false);
const degrees = [
    ...new Set(props.memberships.map((m) => m.degree?.key).filter(Boolean)),
];
const statuses = [
    ...new Set(props.memberships.map((m) => m.status?.key).filter(Boolean)),
];
const submit = (sendNow: boolean) => {
    const options = {
        onSuccess: () => {
            creating.value = false;
            editingId.value = null;
            form.reset();
        },
    };
    form.transform((data) => ({ ...data, send_now: sendNow }));
    if (editingId.value) {
        form.put(
            `/lodges/${props.lodge.id}/communications/manage/${editingId.value}`,
            {
                onSuccess: () => {
                    if (sendNow) {
                        router.post(
                            `/lodges/${props.lodge.id}/communications/manage/${editingId.value}/send`,
                        );
                    } else {
                        options.onSuccess();
                    }
                },
            },
        );
    } else {
        form.post(`/lodges/${props.lodge.id}/communications/manage`, options);
    }
};
const open = (item?: any) => {
    editingId.value = item?.id ?? null;
    editingSent.value = item?.status === "sent";
    form.subject = item?.subject ?? "";
    form.body_html = item?.body_html ?? "<p></p>";
    form.audience_mode = item?.audience_mode ?? "all";
    form.degree_keys = item?.degree_keys ?? [];
    form.membership_status_keys = item?.membership_status_keys ?? [];
    form.membership_ids = item?.membership_ids ?? [];
    form.relation_person_ids = item?.relation_person_ids ?? [];
    creating.value = true;
};
</script>
<template>
    <Head title="Communications" />
    <main class="mx-auto max-w-5xl space-y-6 p-6">
        <h1 class="text-3xl font-bold">Lodge communications</h1>
        <div class="divide-y rounded border">
            <article
                v-for="item in communications"
                :key="item.id"
                class="flex gap-3 p-4"
            >
                <div class="flex-1">
                    <strong>{{ item.subject }}</strong>
                    <p class="text-sm">{{ item.status }}</p>
                </div>
                <button class="underline" @click="open(item)">Edit</button>
            </article>
        </div>
        <button
            class="rounded bg-slate-900 px-4 py-2 text-white"
            @click="open()"
        >
            New message
        </button>
        <Dialog :open="creating" @update:open="creating = $event">
            <DialogContent class="max-w-3xl">
                <DialogHeader
                    ><DialogTitle>{{
                        editingId ? "Edit message" : "New message"
                    }}</DialogTitle></DialogHeader
                >
                <form
                    v-if="!editingSent"
                    class="grid gap-3"
                    @submit.prevent="submit(false)"
                >
                    <input
                        v-model="form.subject"
                        required
                        placeholder="Subject"
                        class="field-input"
                    /><RichTextField v-model="form.body_html" />
                    <fieldset class="grid gap-3 border-t pt-4">
                        <legend class="font-semibold">Recipients</legend>
                        <select
                            v-model="form.audience_mode"
                            class="field-input"
                        >
                            <option value="all">All eligible members</option>
                            <option value="filtered">
                                Filter by degree or status
                            </option>
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
                                        {{ relation.name }} —
                                        {{ relation.type }} of
                                        {{ relation.related_to }}
                                    </option>
                                </select></label
                            >
                        </template>
                    </fieldset>
                    <div class="flex gap-3">
                        <button class="rounded border px-4 py-2">
                            Create draft
                        </button>
                        <button
                            type="button"
                            class="rounded bg-slate-900 px-4 py-2 text-white"
                            @click="submit(true)"
                        >
                            Send now
                        </button>
                    </div>
                </form>
                <section v-else class="space-y-4">
                    <article class="public-rich-text" v-html="form.body_html" />
                    <p>
                        Sent messages remain unchanged. Create an editable copy
                        to revise its text or recipients and send it again.
                    </p>
                    <button
                        class="rounded bg-slate-900 px-4 py-2 text-white"
                        @click="
                            router.post(
                                `/lodges/${lodge.id}/communications/manage/${editingId}/duplicate`,
                            )
                        "
                    >
                        Create editable resend
                    </button>
                </section>
            </DialogContent>
        </Dialog>
    </main>
</template>
