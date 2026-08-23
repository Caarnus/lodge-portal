<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import { formatPhone } from "@/lib/phone";
import { Head, router, useForm } from "@inertiajs/vue3";

defineOptions({ layout: AppLayout });
const props = defineProps<{
    lodge: any;
    person: any | null;
    membership: any | null;
    relationships: any[];
    account: any | null;
    canManagePerson: boolean;
    canManageRoles?: boolean;
    canManageCommunicationPreferences?: boolean;
    availablePeople?: any[];
    membershipTypes: any[];
    membershipStatuses: any[];
    degrees: any[];
    relationshipTypes: any[];
}>();
const personForm = useForm({
    legal_first_name: props.person?.legal_first_name ?? "",
    legal_middle_name: props.person?.legal_middle_name ?? "",
    legal_last_name: props.person?.legal_last_name ?? "",
    legal_suffix: props.person?.legal_suffix ?? "",
    preferred_name: props.person?.preferred_name ?? "",
    email: props.person?.email ?? "",
    phone: props.person?.phone ?? "",
    mailing_address_line_1: props.person?.mailing_address_line_1 ?? "",
    mailing_address_line_2: props.person?.mailing_address_line_2 ?? "",
    mailing_city: props.person?.mailing_city ?? "",
    mailing_state: props.person?.mailing_state ?? "",
    mailing_postal_code: props.person?.mailing_postal_code ?? "",
    birth_date: props.person?.birth_date ?? "",
    is_deceased: props.person?.is_deceased ?? false,
    death_date: props.person?.death_date ?? "",
});
const membershipForm = useForm({
    membership_type_id: props.membership?.membership_type_id ?? null,
    membership_status_id: props.membership?.membership_status_id ?? null,
    masonic_degree_id: props.membership?.masonic_degree_id ?? null,
    primary_lodge_number:
        props.membership?.primary_lodge_number ?? props.lodge.number,
    member_number: props.membership?.member_number ?? "",
    is_award_of_gold: props.membership?.is_award_of_gold ?? false,
    entered_apprentice_date: props.membership?.entered_apprentice_date ?? "",
    fellow_craft_date: props.membership?.fellow_craft_date ?? "",
    master_mason_date: props.membership?.master_mason_date ?? "",
    affiliation_date: props.membership?.affiliation_date ?? "",
    demit_withdrawal_date: props.membership?.demit_withdrawal_date ?? "",
    end_date: props.membership?.end_date ?? "",
    notes: props.membership?.notes ?? "",
});
const communicationPreferenceForm = useForm({
    receives_lodge_email:
        props.membership?.communication_preference?.receives_lodge_email ??
        true,
    receives_print_newsletter:
        props.membership?.communication_preference?.receives_print_newsletter ??
        false,
});
const relationshipForm = useForm({
    related_person_id: null as number | null,
    relationship_type_id: null as number | null,
    relationship_subject: "related" as "current" | "related",
    related_person: null as null | {
        legal_first_name: string;
        legal_last_name: string;
        preferred_name: string;
        email: string;
        phone: string;
    },
});
const pastMasterForm = useForm({ year: new Date().getFullYear() });
const photoForm = useForm<{ photo: File | null }>({ photo: null });
const savePerson = () =>
    props.person
        ? personForm.put(`/lodges/${props.lodge.id}/people/${props.person.id}`)
        : personForm.post(`/lodges/${props.lodge.id}/people`);
const saveMembership = () =>
    membershipForm.put(
        `/lodges/${props.lodge.id}/memberships/${props.membership.id}`,
    );
const saveCommunicationPreference = () =>
    communicationPreferenceForm.put(
        `/lodges/${props.lodge.id}/memberships/${props.membership.id}/communication-preference`,
    );
const addRelationship = () =>
    relationshipForm.post(
        `/lodges/${props.lodge.id}/people/${props.person.id}/relationships`,
        { onSuccess: () => relationshipForm.reset() },
    );
const toggleNewRelative = () => {
    relationshipForm.related_person_id = null;
    relationshipForm.related_person = relationshipForm.related_person
        ? null
        : {
              legal_first_name: "",
              legal_last_name: "",
              preferred_name: "",
              email: "",
              phone: "",
          };
};
const removeRelationship = (id: number) =>
    confirm("Remove this family relationship?") &&
    router.delete(`/lodges/${props.lodge.id}/relationships/${id}`);
const updateRelationship = (relationship: any) =>
    router.put(`/lodges/${props.lodge.id}/relationships/${relationship.id}`, {
        relationship_type_id: relationship.relationship_type_id,
        subject_person_id: props.person.id,
    });
const invite = () =>
    router.post(`/lodges/${props.lodge.id}/people/${props.person.id}/account`);
const revoke = () =>
    confirm(`Revoke this account's access to ${props.lodge.name}?`) &&
    router.delete(`/lodges/${props.lodge.id}/people/${props.person.id}/access`);
const uploadPhoto = () =>
    photoForm.post(
        `/lodges/${props.lodge.id}/people/${props.person.id}/photo`,
        { forceFormData: true, onSuccess: () => photoForm.reset() },
    );
const endMembership = () =>
    confirm(
        `End this membership today? The person and historical records will be preserved.`,
    ) &&
    router.patch(
        `/lodges/${props.lodge.id}/memberships/${props.membership.id}/end`,
        { end_date: new Date().toISOString().slice(0, 10) },
    );
const formatPersonPhone = () => {
    personForm.phone = formatPhone(personForm.phone);
};
const formatRelativePhone = () => {
    if (relationshipForm.related_person)
        relationshipForm.related_person.phone = formatPhone(
            relationshipForm.related_person.phone,
        );
};
const addPastMasterYear = () =>
    pastMasterForm.post(
        `/lodges/${props.lodge.id}/memberships/${props.membership.id}/past-master-terms`,
        { preserveScroll: true },
    );
const removePastMasterYear = (term: any) =>
    confirm(`Remove Past Master year ${term.year}?`) &&
    router.delete(
        `/lodges/${props.lodge.id}/memberships/${props.membership.id}/past-master-terms/${term.id}`,
        { preserveScroll: true },
    );
</script>

<template>
    <Head :title="person ? `Edit ${person.display_name}` : 'Add person'" />
    <main class="mx-auto w-full max-w-5xl p-4 sm:p-6 lg:p-8">
        <h1 class="text-2xl font-bold">
            {{ person ? person.display_name : "Add person" }}
        </h1>
        <p v-if="person" class="mt-1 text-sm text-amber-700">
            Identity and contact changes are shared with every authorized lodge.
        </p>
        <form class="mt-6 rounded-lg border p-4" @submit.prevent="savePerson">
            <h2 class="font-semibold">Identity and contact</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label
                    >First name<input
                        v-model="personForm.legal_first_name"
                        required
                        class="mt-1 w-full rounded border p-2"
                /></label>
                <label
                    >Middle name<input
                        v-model="personForm.legal_middle_name"
                        class="mt-1 w-full rounded border p-2"
                /></label>
                <label
                    >Last name<input
                        v-model="personForm.legal_last_name"
                        required
                        class="mt-1 w-full rounded border p-2"
                /></label>
                <label
                    >Suffix<input
                        v-model="personForm.legal_suffix"
                        class="mt-1 w-full rounded border p-2"
                /></label>
                <label
                    >Preferred name<input
                        v-model="personForm.preferred_name"
                        class="mt-1 w-full rounded border p-2"
                /></label>
                <label
                    >Email<input
                        v-model="personForm.email"
                        type="email"
                        class="mt-1 w-full rounded border p-2"
                /></label>
                <label
                    >Phone<input
                        v-model="personForm.phone"
                        type="tel"
                        class="mt-1 w-full rounded border p-2"
                        placeholder="(812)555-0100 or +44 20 7946 0958"
                        @blur="formatPersonPhone"
                /></label>
                <label
                    >Address line 1<input
                        v-model="personForm.mailing_address_line_1"
                        class="mt-1 w-full rounded border p-2"
                /></label>
                <label
                    >Address line 2<input
                        v-model="personForm.mailing_address_line_2"
                        class="mt-1 w-full rounded border p-2"
                /></label>
                <label
                    >City<input
                        v-model="personForm.mailing_city"
                        class="mt-1 w-full rounded border p-2"
                /></label>
                <label
                    >State<input
                        v-model="personForm.mailing_state"
                        maxlength="2"
                        class="mt-1 w-full rounded border p-2 uppercase"
                /></label>
                <label
                    >Postal code<input
                        v-model="personForm.mailing_postal_code"
                        class="mt-1 w-full rounded border p-2"
                /></label>
                <label
                    >Birth date<input
                        v-model="personForm.birth_date"
                        type="date"
                        class="mt-1 w-full rounded border p-2"
                /></label>
                <label class="flex items-center gap-2"
                    ><input v-model="personForm.is_deceased" type="checkbox" />
                    Deceased</label
                >
                <label v-if="personForm.is_deceased"
                    >Death date<input
                        v-model="personForm.death_date"
                        type="date"
                        class="mt-1 w-full rounded border p-2"
                /></label>
            </div>
            <p
                v-for="message in personForm.errors"
                :key="message"
                class="mt-2 text-sm text-red-700"
            >
                {{ message }}
            </p>
            <button
                :disabled="personForm.processing || !canManagePerson"
                class="mt-4 rounded bg-slate-900 px-4 py-2 text-white disabled:opacity-50"
            >
                {{ person ? "Save person" : "Create person and membership" }}
            </button>
        </form>

        <section v-if="person" class="mt-6 rounded-lg border p-4">
            <h2 class="font-semibold">Private profile photo</h2>
            <img
                v-if="person.profile_photo_status === 'ready'"
                :src="`/lodges/${lodge.id}/people/${person.id}/photo`"
                alt="Profile"
                class="mt-3 size-32 rounded-lg object-cover"
            />
            <p v-else-if="person.profile_photo_status" class="mt-2 text-sm">
                Processing status: {{ person.profile_photo_status
                }}<span v-if="person.profile_photo_error">
                    — {{ person.profile_photo_error }}</span
                >
            </p>
            <form
                class="mt-3 flex flex-wrap items-end gap-3"
                @submit.prevent="uploadPhoto"
            >
                <label
                    >Photo<input
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/heic,image/heif"
                        required
                        class="mt-1 block"
                        @change="
                            photoForm.photo =
                                ($event.target as HTMLInputElement)
                                    .files?.[0] ?? null
                        " /></label
                ><button class="rounded border px-4 py-2">Upload</button>
            </form>
            <p v-if="photoForm.errors.photo" class="mt-2 text-sm text-red-700">
                {{ photoForm.errors.photo }}
            </p>
        </section>

        <form
            v-if="membership"
            class="mt-6 rounded-lg border p-4"
            @submit.prevent="saveMembership"
        >
            <h2 class="font-semibold">{{ lodge.name }} membership</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <label
                    >Type<select
                        v-model="membershipForm.membership_type_id"
                        class="mt-1 w-full rounded border p-2"
                    >
                        <option :value="null">Unknown</option>
                        <option
                            v-for="item in membershipTypes"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }}
                        </option>
                    </select></label
                >
                <label
                    >Status<select
                        v-model="membershipForm.membership_status_id"
                        required
                        class="mt-1 w-full rounded border p-2"
                    >
                        <option
                            v-for="item in membershipStatuses"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }}
                        </option>
                    </select></label
                >
                <label
                    >Degree<select
                        v-model="membershipForm.masonic_degree_id"
                        class="mt-1 w-full rounded border p-2"
                    >
                        <option :value="null">Unknown</option>
                        <option
                            v-for="item in degrees"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }}
                        </option>
                    </select></label
                >
                <label
                    >Primary lodge number<input
                        v-model="membershipForm.primary_lodge_number"
                        class="mt-1 w-full rounded border p-2"
                /></label>
                <label
                    >Member number<input
                        v-model="membershipForm.member_number"
                        class="mt-1 w-full rounded border p-2"
                /></label>
                <label class="flex items-center gap-2"
                    ><input
                        v-model="membershipForm.is_award_of_gold"
                        type="checkbox"
                    />
                    Award of Gold (50-year member)</label
                >
                <label
                    >EA date<input
                        v-model="membershipForm.entered_apprentice_date"
                        type="date"
                        class="mt-1 w-full rounded border p-2"
                /></label>
                <label
                    >FC date<input
                        v-model="membershipForm.fellow_craft_date"
                        type="date"
                        class="mt-1 w-full rounded border p-2"
                /></label>
                <label
                    >MM date<input
                        v-model="membershipForm.master_mason_date"
                        type="date"
                        class="mt-1 w-full rounded border p-2"
                /></label>
                <label
                    >Affiliation date<input
                        v-model="membershipForm.affiliation_date"
                        type="date"
                        class="mt-1 w-full rounded border p-2"
                /></label>
                <label
                    >Demit/withdrawal date<input
                        v-model="membershipForm.demit_withdrawal_date"
                        type="date"
                        class="mt-1 w-full rounded border p-2"
                /></label>
                <label class="sm:col-span-3"
                    >Private lodge notes<textarea
                        v-model="membershipForm.notes"
                        rows="3"
                        class="mt-1 w-full rounded border p-2"
                    />
                </label>
            </div>
            <p
                v-for="message in membershipForm.errors"
                :key="message"
                class="mt-2 text-sm text-red-700"
            >
                {{ message }}
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <button class="rounded bg-slate-900 px-4 py-2 text-white">
                    Save membership</button
                ><button
                    v-if="!membership.end_date"
                    type="button"
                    class="rounded border border-red-300 px-4 py-2 text-red-700"
                    @click="endMembership"
                >
                    End membership</button
                ><span v-else class="self-center text-sm text-slate-600"
                    >Ended {{ String(membership.end_date).slice(0, 10) }}</span
                >
            </div>
        </form>

        <form
            v-if="membership && canManageCommunicationPreferences"
            class="mt-6 rounded-lg border p-4"
            @submit.prevent="saveCommunicationPreference"
        >
            <h2 class="font-semibold">Lodge communication preferences</h2>
            <p class="mt-1 text-sm text-slate-600">
                These settings apply only to {{ lodge.name }}. Contact and
                address changes above may affect other authorized lodge
                workflows.
            </p>
            <div class="mt-4 flex flex-wrap gap-5">
                <label class="flex items-center gap-2"
                    ><input
                        v-model="
                            communicationPreferenceForm.receives_lodge_email
                        "
                        type="checkbox"
                    />
                    Receive lodge email</label
                >
                <label class="flex items-center gap-2"
                    ><input
                        v-model="
                            communicationPreferenceForm.receives_print_newsletter
                        "
                        type="checkbox"
                    />
                    Receive mailed newsletter</label
                >
            </div>
            <button class="mt-4 rounded bg-slate-900 px-4 py-2 text-white">
                Save communication preferences
            </button>
        </form>

        <section v-if="membership" class="mt-6 rounded-lg border p-4">
            <h2 class="font-semibold">Past Master years</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                <span
                    v-for="term in person.past_master_terms"
                    :key="term.id"
                    class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-sm"
                    >{{ term.year
                    }}<button
                        type="button"
                        class="text-red-700"
                        @click="removePastMasterYear(term)"
                    >
                        Remove
                    </button></span
                ><span
                    v-if="!person.past_master_terms?.length"
                    class="text-sm text-slate-500"
                    >No Past Master years recorded.</span
                >
            </div>
            <form class="mt-3 flex gap-2" @submit.prevent="addPastMasterYear">
                <input
                    v-model="pastMasterForm.year"
                    type="number"
                    min="1700"
                    :max="new Date().getFullYear()"
                    aria-label="Past Master year"
                    class="w-32 rounded border p-2"
                /><button class="rounded border px-4 py-2">Add year</button>
            </form>
            <p
                v-if="pastMasterForm.errors.year"
                class="mt-2 text-sm text-red-700"
            >
                {{ pastMasterForm.errors.year }}
            </p>
        </section>

        <section v-if="person" class="mt-6 rounded-lg border p-4">
            <h2 class="font-semibold">Family relationships</h2>
            <ul class="mt-3 divide-y">
                <li
                    v-for="relationship in relationships"
                    :key="relationship.id"
                    class="flex flex-wrap items-center gap-2 py-2"
                >
                    <span class="min-w-0 flex-1">{{
                        relationship.relationship_statement
                    }}</span
                    ><select
                        v-if="relationship.can_manage"
                        v-model="relationship.relationship_type_id"
                        :aria-label="`How ${person.display_name} is related to ${relationship.related_person.display_name}`"
                        class="rounded border p-2"
                    >
                        <option
                            v-for="item in relationshipTypes"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }}
                        </option></select
                    ><button
                        v-if="relationship.can_manage"
                        class="text-sm underline"
                        @click="updateRelationship(relationship)"
                    >
                        Save</button
                    ><button
                        v-if="relationship.can_manage"
                        class="text-sm text-red-700"
                        @click="removeRelationship(relationship.id)"
                    >
                        Remove
                    </button>
                </li>
            </ul>
            <button
                type="button"
                class="mt-3 text-sm underline"
                @click="toggleNewRelative"
            >
                {{
                    relationshipForm.related_person
                        ? "Select an existing person"
                        : "Create a new non-member relative"
                }}
            </button>
            <form
                class="mt-4 grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
                @submit.prevent="addRelationship"
            >
                <fieldset class="sm:col-span-3">
                    <legend class="text-sm font-medium">
                        Relationship direction
                    </legend>
                    <label class="mt-2 flex gap-2 text-sm"
                        ><input
                            v-model="relationshipForm.relationship_subject"
                            type="radio"
                            value="related"
                        />
                        The related person is the selected relationship of
                        {{ person.display_name }}</label
                    ><label class="mt-2 flex gap-2 text-sm"
                        ><input
                            v-model="relationshipForm.relationship_subject"
                            type="radio"
                            value="current"
                        />
                        {{ person.display_name }} is the selected relationship
                        of the related person</label
                    >
                </fieldset>
                <select
                    v-if="!relationshipForm.related_person"
                    v-model="relationshipForm.related_person_id"
                    required
                    aria-label="Related person"
                    class="rounded border p-2"
                >
                    <option :value="null">Select person</option>
                    <option
                        v-for="item in availablePeople"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.display_name }}
                    </option>
                </select>
                <div v-else class="grid gap-2 sm:grid-cols-2">
                    <input
                        v-model="
                            relationshipForm.related_person.legal_first_name
                        "
                        required
                        class="rounded border p-2"
                        placeholder="First name"
                    /><input
                        v-model="
                            relationshipForm.related_person.legal_last_name
                        "
                        required
                        class="rounded border p-2"
                        placeholder="Last name"
                    /><input
                        v-model="relationshipForm.related_person.preferred_name"
                        class="rounded border p-2"
                        placeholder="Preferred name"
                    /><input
                        v-model="relationshipForm.related_person.email"
                        type="email"
                        class="rounded border p-2"
                        placeholder="Email (optional)"
                    /><input
                        v-model="relationshipForm.related_person.phone"
                        type="tel"
                        class="rounded border p-2"
                        placeholder="Phone (optional)"
                        @blur="formatRelativePhone"
                    />
                </div>
                <select
                    v-model="relationshipForm.relationship_type_id"
                    required
                    aria-label="Relationship type"
                    class="rounded border p-2"
                >
                    <option :value="null">Relationship</option>
                    <option
                        v-for="item in relationshipTypes"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }}
                    </option>
                </select>
                <button class="rounded border px-4 py-2">Add</button>
            </form>
        </section>

        <section v-if="person" class="mt-6 rounded-lg border p-4">
            <h2 class="font-semibold">Account access</h2>
            <p class="mt-2 text-sm">
                {{
                    account
                        ? `Linked to ${account.email}`
                        : "No account is linked."
                }}
            </p>
            <button
                v-if="!account && canManagePerson"
                class="mt-3 rounded border px-4 py-2"
                @click="invite"
            >
                Invite account</button
            ><button
                v-else-if="account && canManageRoles"
                class="mt-3 rounded border border-red-300 px-4 py-2 text-red-700"
                @click="revoke"
            >
                Revoke this lodge's access
            </button>
        </section>
    </main>
</template>
