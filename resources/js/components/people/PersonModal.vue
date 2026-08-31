<script lang="ts" setup>
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from "@/components/ui/dialog";
import {formatPhone} from "@/lib/phone";
import {router, useForm} from "@inertiajs/vue3";
import {Link2, Link2Off, Trash2} from "lucide-vue-next";
import {computed, watch} from "vue";

const props = defineProps<{
    open: boolean;
    mode: "view" | "edit";
    lodge: any;
    person: any | null;
    membershipTypes: any[];
    membershipStatuses: any[];
    degrees: any[];
    relationshipTypes: any[];
    availablePeople: any[];
    canManageMemberships: boolean;
    canManageRoles: boolean;
    canManageCommunicationPreferences: boolean;
}>();
const emit = defineEmits<{
    "update:open": [value: boolean];
    "update:mode": [value: "view" | "edit"];
}>();

const membership = computed(() => props.person?.memberships?.[0] ?? null);
const dateValue = (value: unknown) => (value ? String(value).slice(0, 10) : "");
const personForm = useForm({
    legal_first_name: "",
    legal_middle_name: "",
    legal_last_name: "",
    legal_suffix: "",
    preferred_name: "",
    email: "",
    phone: "",
    mailing_address_line_1: "",
    mailing_address_line_2: "",
    mailing_city: "",
    mailing_state: "",
    mailing_postal_code: "",
    birth_date: "",
    is_deceased: false,
    death_date: "",
});
const membershipForm = useForm({
    membership_type_id: null as number | null,
    membership_status_id: null as number | null,
    masonic_degree_id: null as number | null,
    primary_lodge_number: "",
    member_number: "",
    is_award_of_gold: false,
    entered_apprentice_date: "",
    fellow_craft_date: "",
    master_mason_date: "",
    affiliation_date: "",
    demit_withdrawal_date: "",
    end_date: "",
    notes: "",
});
const communicationPreferenceForm = useForm({
    receives_lodge_email: true,
    receives_print_newsletter: false,
});
const relationshipForm = useForm({
    related_person_id: null as number | null,
    relationship_type_id: null as number | null,
    relationship_subject: "current" as "current" | "related",
    related_person: null as null | {
        legal_first_name: string;
        legal_last_name: string;
        preferred_name: string;
        email: string;
        phone: string;
    },
});
const pastMasterForm = useForm({year: new Date().getFullYear()});
const photoForm = useForm<{ photo: File | null }>({photo: null});
const availableRelatedPeople = (personId: number) =>
    props.availablePeople.filter((candidate) => candidate.id !== personId);

const loadForms = () => {
    const person = props.person;
    const currentMembership = membership.value;
    if (!person) {
        personForm.reset();
        membershipForm.reset();
        communicationPreferenceForm.reset();
        relationshipForm.reset();
        return;
    }
    Object.assign(personForm, {
        legal_first_name: person.legal_first_name ?? "",
        legal_middle_name: person.legal_middle_name ?? "",
        legal_last_name: person.legal_last_name ?? "",
        legal_suffix: person.legal_suffix ?? "",
        preferred_name: person.preferred_name ?? "",
        email: person.email ?? "",
        phone: formatPhone(person.phone),
        mailing_address_line_1: person.mailing_address_line_1 ?? "",
        mailing_address_line_2: person.mailing_address_line_2 ?? "",
        mailing_city: person.mailing_city ?? "",
        mailing_state: person.mailing_state ?? "",
        mailing_postal_code: person.mailing_postal_code ?? "",
        birth_date: dateValue(person.birth_date),
        is_deceased: Boolean(person.is_deceased),
        death_date: dateValue(person.death_date),
    });
    Object.assign(membershipForm, {
        membership_type_id: currentMembership?.membership_type_id ?? null,
        membership_status_id: currentMembership?.membership_status_id ?? null,
        masonic_degree_id: currentMembership?.masonic_degree_id ?? null,
        primary_lodge_number: currentMembership?.primary_lodge_number ?? "",
        member_number: currentMembership?.member_number ?? "",
        is_award_of_gold: Boolean(currentMembership?.is_award_of_gold),
        entered_apprentice_date: dateValue(
            currentMembership?.entered_apprentice_date,
        ),
        fellow_craft_date: dateValue(currentMembership?.fellow_craft_date),
        master_mason_date: dateValue(currentMembership?.master_mason_date),
        affiliation_date: dateValue(currentMembership?.affiliation_date),
        demit_withdrawal_date: dateValue(
            currentMembership?.demit_withdrawal_date,
        ),
        end_date: dateValue(currentMembership?.end_date),
        notes: currentMembership?.notes ?? "",
    });
    Object.assign(communicationPreferenceForm, {
        receives_lodge_email:
            currentMembership?.communication_preference?.receives_lodge_email ??
            true,
        receives_print_newsletter:
            currentMembership?.communication_preference
                ?.receives_print_newsletter ?? false,
    });
    personForm.clearErrors();
    membershipForm.clearErrors();
};
watch(() => [props.person, props.mode, props.open], loadForms, {
    immediate: true,
});

const savePerson = () => {
    personForm.phone = formatPhone(personForm.phone);
    const options = {
        preserveScroll: true,
        onSuccess: () => emit("update:open", false),
    };
    if (props.person)
        personForm.put(
            `/lodges/${props.lodge.id}/people/${props.person.id}`,
            options,
        );
    else personForm.post(`/lodges/${props.lodge.id}/people`, options);
};
const saveMembership = () => {
    if (!membership.value) return;
    membershipForm.put(
        `/lodges/${props.lodge.id}/memberships/${membership.value.id}`,
        {preserveScroll: true},
    );
};
const addPastMasterYear = () => {
    if (!membership.value) return;
    pastMasterForm.post(
        `/lodges/${props.lodge.id}/memberships/${membership.value.id}/past-master-terms`,
        {preserveScroll: true},
    );
};
const removePastMasterYear = (term: any) => {
    if (!membership.value || !confirm(`Remove Past Master year ${term.year}?`))
        return;
    router.delete(
        `/lodges/${props.lodge.id}/memberships/${membership.value.id}/past-master-terms/${term.id}`,
        {preserveScroll: true},
    );
};
const saveCommunicationPreference = () => {
    if (!membership.value) return;
    communicationPreferenceForm.put(
        `/lodges/${props.lodge.id}/memberships/${membership.value.id}/communication-preference`,
        {preserveScroll: true},
    );
};
const addRelationship = () => {
    if (!props.person) return;
    relationshipForm.post(
        `/lodges/${props.lodge.id}/people/${props.person.id}/relationships`,
        {preserveScroll: true, onSuccess: () => relationshipForm.reset()},
    );
};
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
const updateRelationship = (relationship: any) =>
    props.person &&
    router.put(`/lodges/${props.lodge.id}/relationships/${relationship.id}`, {
        relationship_type_id: relationship.relationship_type_id,
        subject_person_id: props.person.id,
    });
const removeRelationship = (id: number) =>
    confirm("Remove this family relationship?") &&
    router.delete(`/lodges/${props.lodge.id}/relationships/${id}`);
const invite = () =>
    props.person &&
    router.post(`/lodges/${props.lodge.id}/people/${props.person.id}/account`);
const revoke = () =>
    props.person &&
    confirm(`Revoke this account's access to ${props.lodge.name}?`) &&
    router.delete(`/lodges/${props.lodge.id}/people/${props.person.id}/access`);
const uploadPhoto = () => {
    if (!props.person) return;
    photoForm.post(
        `/lodges/${props.lodge.id}/people/${props.person.id}/photo`,
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => photoForm.reset(),
        },
    );
};
const endMembership = () => {
    if (!membership.value || !confirm("End this membership today?")) return;
    router.patch(
        `/lodges/${props.lodge.id}/memberships/${membership.value.id}/end`,
        {
            end_date: new Date().toISOString().slice(0, 10),
        },
    );
};
const location = computed(() =>
    [
        props.person?.mailing_city,
        props.person?.mailing_state,
        props.person?.mailing_postal_code,
    ]
        .filter(Boolean)
        .join(", "),
);
const address = computed(() =>
    [props.person?.mailing_address_line_1, props.person?.mailing_address_line_2]
        .filter(Boolean)
        .join(", "),
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogScrollContent
            class="max-h-[calc(100vh-4rem)] max-w-4xl overflow-y-auto"
        >
            <DialogHeader>
                <DialogTitle
                >{{ person ? (mode === "edit" ? "Edit" : "View") : "Add" }}
                    {{ person?.display_name ?? "person" }}
                </DialogTitle
                >
                <DialogDescription
                >Identity information is shared with every lodge authorized
                    to access this person.
                </DialogDescription
                >
            </DialogHeader>

            <template v-if="person && mode === 'view'">
                <dl
                    class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            Legal name
                        </dt>
                        <dd>{{ person.name }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            Preferred name
                        </dt>
                        <dd>{{ person.preferred_name || "—" }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">Phone</dt>
                        <dd>{{ formatPhone(person.phone) || "—" }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">Email</dt>
                        <dd class="break-all">{{ person.email || "—" }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            Address
                        </dt>
                        <dd>{{ address || "—" }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            City / State
                        </dt>
                        <dd>{{ location || "—" }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            Account
                        </dt>
                        <dd class="flex items-center gap-2">
                            <Link2
                                v-if="person.user"
                                class="size-4 text-primary"
                            />
                            <Link2Off
                                v-else
                                class="size-4 text-muted-foreground"
                            />
                            {{ person.user ? "Linked" : "Not linked" }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-muted-foreground">
                            Birth date
                        </dt>
                        <dd>{{ dateValue(person.birth_date) || "—" }}</dd>
                    </div>
                    <div v-if="person.is_deceased">
                        <dt class="font-medium text-muted-foreground">
                            Death date
                        </dt>
                        <dd>
                            {{ dateValue(person.death_date) || "Not recorded" }}
                        </dd>
                    </div>
                </dl>
                <section
                    v-if="membership"
                    class="rounded-lg border border-border/80 bg-card p-4 text-sm"
                >
                    <h3 class="font-semibold">{{ lodge.name }} membership</h3>
                    <dl
                        class="mt-3 grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div>
                            <dt class="text-muted-foreground">Type</dt>
                            <dd>
                                {{ membership.type?.name || "Not recorded" }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Status</dt>
                            <dd>
                                {{ membership.status?.name || "Not recorded" }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Degree</dt>
                            <dd>
                                {{ membership.degree?.name || "Not recorded" }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Member number</dt>
                            <dd>{{ membership.member_number || "—" }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                Primary lodge number
                            </dt>
                            <dd>
                                {{ membership.primary_lodge_number || "—" }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Award of Gold</dt>
                            <dd>
                                {{ membership.is_award_of_gold ? "Yes" : "No" }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Past Master</dt>
                            <dd>
                                {{
                                    person.past_master_terms?.length
                                        ? person.past_master_terms
                                            .map((term: any) => term.year)
                                            .join(", ")
                                        : "No"
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">EA date</dt>
                            <dd>
                                {{
                                    dateValue(
                                        membership.entered_apprentice_date,
                                    ) || "—"
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">FC date</dt>
                            <dd>
                                {{
                                    dateValue(membership.fellow_craft_date) ||
                                    "—"
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">MM date</dt>
                            <dd>
                                {{
                                    dateValue(membership.master_mason_date) ||
                                    "—"
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                Affiliation date
                            </dt>
                            <dd>
                                {{
                                    dateValue(membership.affiliation_date) ||
                                    "—"
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                Demit/withdrawal date
                            </dt>
                            <dd>
                                {{
                                    dateValue(
                                        membership.demit_withdrawal_date,
                                    ) || "—"
                                }}
                            </dd>
                        </div>
                    </dl>
                </section>
                <section
                    v-if="person.relationship_summaries?.length"
                    class="rounded-lg border border-border/80 bg-card p-4 text-sm"
                >
                    <h3 class="font-semibold">Relationships</h3>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li
                            v-for="relationship in person.relationship_summaries"
                            :key="relationship.id"
                        >
                            {{ relationship.statement }}
                        </li>
                    </ul>
                </section>
                <DialogFooter class="border-t border-border/70 pt-4">
                    <button
                        v-if="person.can_manage"
                        class="primary-button"
                        type="button"
                        @click="emit('update:mode', 'edit')"
                    >
                        Edit
                    </button>
                </DialogFooter>
            </template>

            <template v-else-if="mode === 'edit'">
                <form
                    class="rounded-lg border border-border/80 bg-card p-4"
                    @submit.prevent="savePerson"
                >
                    <h3 class="font-semibold">Identity and contact</h3>
                    <div class="mt-4 space-y-5">
                        <fieldset
                            class="grid gap-3 md:grid-cols-[1fr_1fr_1fr_7rem]"
                        >
                            <legend
                                class="mb-2 text-sm font-medium md:col-span-4"
                            >
                                Legal name
                            </legend>
                            <label class="field-label"
                            >First name<input
                                v-model="personForm.legal_first_name"
                                class="field-input"
                                required
                            /></label>
                            <label class="field-label"
                            >Middle name<input
                                v-model="personForm.legal_middle_name"
                                class="field-input"
                            /></label>
                            <label class="field-label"
                            >Last name<input
                                v-model="personForm.legal_last_name"
                                class="field-input"
                                required
                            /></label>
                            <label class="field-label"
                            >Suffix<select
                                v-model="personForm.legal_suffix"
                                class="field-input"
                            >
                                <option value="">None</option>
                                <option value="Jr.">Jr.</option>
                                <option value="Sr.">Sr.</option>
                                <option value="I">I</option>
                                <option value="II">II</option>
                                <option value="III">III</option>
                                <option value="IV">IV</option>
                                <option value="V">V</option>
                                <option value="Esq.">Esq.</option>
                                <option value="MD">MD</option>
                                <option value="DO">DO</option>
                                <option value="DDS">DDS</option>
                                <option value="DVM">DVM</option>
                                <option value="PhD">PhD</option>
                                <option value="JD">JD</option>
                                <option value="CPA">CPA</option>
                                <option value="PE">PE</option>
                                <option value="Ret.">Ret.</option>
                            </select></label
                            >
                        </fieldset>

                        <fieldset class="grid gap-3 md:grid-cols-3">
                            <legend
                                class="mb-2 text-sm font-medium md:col-span-3"
                            >
                                Contact
                            </legend>
                            <label class="field-label"
                            >Preferred name<input
                                v-model="personForm.preferred_name"
                                class="field-input"
                            /></label>
                            <label class="field-label"
                            >Email<input
                                v-model="personForm.email"
                                class="field-input"
                                type="email"
                            /></label>
                            <label class="field-label"
                            >Phone<input
                                v-model="personForm.phone"
                                class="field-input"
                                placeholder="(812)555-0100 or +44 20 7946 0958"
                                type="tel"
                                @blur="
                                        personForm.phone = formatPhone(
                                            personForm.phone,
                                        )
                                    "
                            /></label>
                        </fieldset>

                        <fieldset class="space-y-3">
                            <legend class="mb-2 text-sm font-medium">
                                Mailing address
                            </legend>
                            <label class="field-label"
                            >Address line 1<input
                                v-model="personForm.mailing_address_line_1"
                                class="field-input"
                            /></label>
                            <label class="field-label"
                            >Address line 2<input
                                v-model="personForm.mailing_address_line_2"
                                class="field-input"
                            /></label>
                            <div
                                class="grid gap-3 md:grid-cols-[1fr_7rem_10rem]"
                            >
                                <label class="field-label"
                                >City<input
                                    v-model="personForm.mailing_city"
                                    class="field-input"
                                /></label>
                                <label class="field-label"
                                >State<input
                                    v-model="personForm.mailing_state"
                                    class="field-input uppercase"
                                    maxlength="2"
                                /></label>
                                <label class="field-label"
                                >Postal code<input
                                    v-model="personForm.mailing_postal_code"
                                    class="field-input"
                                /></label>
                            </div>
                        </fieldset>

                        <section>
                            <div
                                class="mb-2 flex items-center justify-between gap-3"
                            >
                                <h4 class="text-sm font-medium">
                                    Personal status
                                </h4>
                                <label class="flex items-center gap-2 text-sm"
                                ><input
                                    v-model="personForm.is_deceased"
                                    type="checkbox"
                                />
                                    Deceased</label
                                >
                            </div>
                            <div class="grid gap-3 md:grid-cols-3">
                                <label class="field-label"
                                >Birth date<input
                                    v-model="personForm.birth_date"
                                    class="field-input"
                                    type="date"
                                /></label>
                                <label
                                    v-if="personForm.is_deceased"
                                    class="field-label"
                                >Death date<input
                                    v-model="personForm.death_date"
                                    class="field-input"
                                    type="date"
                                /></label>
                            </div>
                        </section>
                    </div>
                    <p
                        v-for="message in personForm.errors"
                        :key="message"
                        class="mt-2 text-sm text-destructive"
                    >
                        {{ message }}
                    </p>
                    <button
                        :disabled="
                            personForm.processing ||
                            (person && !person.can_manage)
                        "
                        class="primary-button mt-4"
                    >
                        {{
                            person
                                ? "Save person"
                                : "Create person and membership"
                        }}
                    </button>
                </form>

                <section
                    v-if="person"
                    class="rounded-lg border border-border/80 bg-card p-4"
                >
                    <h3 class="font-semibold">Private profile photo</h3>
                    <img
                        v-if="person.profile_photo_status === 'ready'"
                        :src="`/lodges/${lodge.id}/people/${person.id}/photo`"
                        alt="Profile"
                        class="mt-3 size-32 rounded-lg object-cover"
                    />
                    <p
                        v-else-if="person.profile_photo_status"
                        class="mt-2 text-sm"
                    >
                        Processing status: {{ person.profile_photo_status }}
                        <span v-if="person.profile_photo_error"
                        >— {{ person.profile_photo_error }}</span
                        >
                    </p>
                    <form
                        class="mt-3 flex flex-wrap items-end gap-3"
                        @submit.prevent="uploadPhoto"
                    >
                        <label class="field-label"
                        >Photo<input
                            accept="image/jpeg,image/png,image/webp,image/heic,image/heif"
                            class="file-input"
                            required
                            type="file"
                            @change="
                                    photoForm.photo =
                                        ($event.target as HTMLInputElement)
                                            .files?.[0] ?? null
                                "
                        /></label>
                        <button class="secondary-button">Upload</button>
                    </form>
                    <p
                        v-if="photoForm.errors.photo"
                        class="mt-2 text-sm text-destructive"
                    >
                        {{ photoForm.errors.photo }}
                    </p>
                </section>

                <form
                    v-if="membership"
                    class="rounded-lg border border-border/80 bg-card p-4"
                    @submit.prevent="saveMembership"
                >
                    <h3 class="font-semibold">{{ lodge.name }} membership</h3>
                    <div class="mt-4 flex flex-col gap-5">
                        <fieldset class="order-1 grid gap-3 md:grid-cols-3">
                            <legend
                                class="mb-2 text-sm font-medium md:col-span-3"
                            >
                                Membership standing
                            </legend>
                            <label class="field-label"
                            >Type<select
                                v-model="membershipForm.membership_type_id"
                                class="field-input"
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
                            <label class="field-label"
                            >Status<select
                                v-model="
                                        membershipForm.membership_status_id
                                    "
                                class="field-input"
                                required
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
                            <label class="field-label"
                            >Degree<select
                                v-model="membershipForm.masonic_degree_id"
                                class="field-input"
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
                        </fieldset>
                        <fieldset class="order-3 grid gap-3 md:grid-cols-3">
                            <div
                                class="mb-2 flex items-center justify-between gap-3 md:col-span-3"
                            >
                                <h4 class="text-sm font-medium">
                                    Lodge records
                                </h4>
                                <label class="flex items-center gap-2 text-sm"
                                ><input
                                    v-model="
                                            membershipForm.is_award_of_gold
                                        "
                                    type="checkbox"
                                />
                                    Award of Gold (50-year member)</label
                                >
                            </div>
                            <label class="field-label"
                            >Primary lodge number<input
                                v-model="
                                        membershipForm.primary_lodge_number
                                    "
                                class="field-input"
                            /></label>
                            <label class="field-label"
                            >Member number<input
                                v-model="membershipForm.member_number"
                                class="field-input"
                            /></label>
                        </fieldset>
                        <fieldset class="order-2 grid gap-3 md:grid-cols-3">
                            <legend
                                class="mb-2 text-sm font-medium md:col-span-3"
                            >
                                Masonic dates
                            </legend>
                            <label class="field-label"
                            >EA date<input
                                v-model="
                                        membershipForm.entered_apprentice_date
                                    "
                                class="field-input"
                                type="date"
                            /></label>
                            <label class="field-label"
                            >FC date<input
                                v-model="membershipForm.fellow_craft_date"
                                class="field-input"
                                type="date"
                            /></label>
                            <label class="field-label"
                            >MM date<input
                                v-model="membershipForm.master_mason_date"
                                class="field-input"
                                type="date"
                            /></label>
                            <label class="field-label"
                            >Affiliation date<input
                                v-model="membershipForm.affiliation_date"
                                class="field-input"
                                type="date"
                            /></label>
                            <label class="field-label"
                            >Demit/withdrawal date<input
                                v-model="
                                        membershipForm.demit_withdrawal_date
                                    "
                                class="field-input"
                                type="date"
                            /></label>
                        </fieldset>
                        <label class="order-4 field-label"
                        >Private lodge notes<textarea
                            v-model="membershipForm.notes"
                            class="field-input"
                            rows="3"
                        />
                        </label>
                    </div>
                    <p
                        v-for="message in membershipForm.errors"
                        :key="message"
                        class="mt-2 text-sm text-destructive"
                    >
                        {{ message }}
                    </p>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <button
                            :disabled="
                                membershipForm.processing ||
                                !canManageMemberships
                            "
                            class="primary-button"
                        >
                            Save membership
                        </button>
                        <button
                            v-if="!membership.end_date && canManageMemberships"
                            class="secondary-button border-destructive/50 text-destructive hover:bg-destructive/10"
                            type="button"
                            @click="endMembership"
                        >
                            End membership
                        </button>
                        <span
                            v-else-if="membership.end_date"
                            class="text-sm text-muted-foreground"
                        >Ended {{ dateValue(membership.end_date) }}</span
                        >
                    </div>
                </form>

                <form
                    v-if="membership && canManageCommunicationPreferences"
                    class="rounded-lg border border-border/80 bg-card p-4"
                    @submit.prevent="saveCommunicationPreference"
                >
                    <h3 class="font-semibold">
                        Lodge communication preferences
                    </h3>
                    <div class="mt-3 flex flex-wrap gap-5 text-sm">
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
                    <button class="primary-button mt-4">
                        Save communication preferences
                    </button>
                </form>

                <section
                    v-if="membership"
                    class="rounded-lg border border-border/80 bg-card p-4"
                >
                    <h3 class="font-semibold">Past Master years</h3>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span
                            v-for="term in person.past_master_terms"
                            :key="term.id"
                            class="inline-flex items-center gap-1 rounded-full bg-muted py-1 pl-3 pr-1 text-sm"
                        >{{
                                term.year
                            }}<button
                                v-if="canManageMemberships"
                                :aria-label="`Remove Past Master year ${term.year}`"
                                class="inline-flex size-7 items-center justify-center rounded-full text-destructive"
                                type="button"
                                @click="removePastMasterYear(term)"
                            >
                                <Trash2 class="size-3.5"/></button></span
                        ><span
                        v-if="!person.past_master_terms?.length"
                        class="text-sm text-muted-foreground"
                    >No Past Master years recorded.</span
                    >
                    </div>
                    <form
                        v-if="canManageMemberships"
                        class="mt-3 flex gap-2"
                        @submit.prevent="addPastMasterYear"
                    >
                        <input
                            v-model="pastMasterForm.year"
                            :max="new Date().getFullYear()"
                            aria-label="Past Master year"
                            class="field-input w-32"
                            min="1700"
                            type="number"
                        />
                        <button class="secondary-button">Add year</button>
                    </form>
                    <p
                        v-if="pastMasterForm.errors.year"
                        class="mt-2 text-sm text-destructive"
                    >
                        {{ pastMasterForm.errors.year }}
                    </p>
                </section>

                <section
                    class="rounded-lg border border-border/80 bg-card p-4 text-sm"
                >
                    <h3 class="font-semibold">Family relationships</h3>
                    <ul
                        v-if="person.relationship_summaries?.length"
                        class="mt-3 divide-y"
                    >
                        <li
                            v-for="relationship in person.relationship_summaries"
                            :key="relationship.id"
                            class="flex flex-wrap items-center gap-2 py-2"
                        >
                            <span class="min-w-0 flex-1">{{
                                    relationship.statement
                                }}</span>
                            <select
                                v-if="relationship.can_manage"
                                v-model="relationship.relationship_type_id"
                                :aria-label="`Relationship type for ${relationship.related_person.display_name}`"
                                class="field-input w-auto"
                            >
                                <option
                                    v-for="item in relationshipTypes"
                                    :key="item.id"
                                    :value="item.id"
                                >
                                    {{ item.name }}
                                </option>
                            </select>
                            <button
                                v-if="relationship.can_manage"
                                class="secondary-button px-3"
                                type="button"
                                @click="updateRelationship(relationship)"
                            >
                                Save
                            </button>
                            <button
                                v-if="relationship.can_manage"
                                class="secondary-button border-destructive/50 px-3 text-destructive hover:bg-destructive/10"
                                type="button"
                                @click="removeRelationship(relationship.id)"
                            >
                                Remove
                            </button>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-muted-foreground">
                        No family relationships recorded.
                    </p>
                    <button
                        class="mt-3 text-sm underline"
                        type="button"
                        @click="toggleNewRelative"
                    >
                        {{
                            relationshipForm.related_person
                                ? "Select an existing person"
                                : "Create a new non-member relative"
                        }}
                    </button>
                    <form
                        class="mt-3 space-y-3"
                        @submit.prevent="addRelationship"
                    >
                        <label class="field-label">
                            Relationship
                            <span class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex h-10 items-center text-sm font-medium"
                                >{{ person.display_name }} is</span
                                >
                                <select
                                    v-model="
                                        relationshipForm.relationship_type_id
                                    "
                                    aria-label="Relationship type"
                                    class="field-input w-full sm:w-56"
                                    required
                                >
                                    <option :value="null">
                                        Select relationship
                                    </option>
                                    <option
                                        v-for="item in relationshipTypes"
                                        :key="item.id"
                                        :value="item.id"
                                    >
                                        {{ item.name }}
                                    </option>
                                </select>
                            </span>
                        </label>
                        <div
                            v-if="!relationshipForm.related_person"
                            class="grid gap-2 md:grid-cols-[minmax(0,1fr)_auto] md:items-end"
                        >
                            <label class="field-label"
                            >Person<select
                                v-model="relationshipForm.related_person_id"
                                class="field-input"
                                required
                            >
                                <option :value="null">Select person</option>
                                <option
                                    v-for="item in availableRelatedPeople(
                                            person.id,
                                        )"
                                    :key="item.id"
                                    :value="item.id"
                                >
                                    {{ item.display_name }}
                                </option>
                            </select></label
                            >
                            <button class="secondary-button">
                                Add relationship
                            </button>
                        </div>
                        <div v-else class="space-y-3">
                            <p class="text-sm text-muted-foreground">
                                Enter the new non-member's information.
                            </p>
                            <div class="grid gap-2 md:grid-cols-2">
                                <input
                                    v-model="
                                        relationshipForm.related_person
                                            .legal_first_name
                                    "
                                    class="field-input"
                                    placeholder="First name"
                                    required
                                />
                                <input
                                    v-model="
                                        relationshipForm.related_person
                                            .legal_last_name
                                    "
                                    class="field-input"
                                    placeholder="Last name"
                                    required
                                />
                                <input
                                    v-model="
                                        relationshipForm.related_person
                                            .preferred_name
                                    "
                                    class="field-input"
                                    placeholder="Preferred name"
                                />
                                <input
                                    v-model="
                                        relationshipForm.related_person.email
                                    "
                                    class="field-input"
                                    placeholder="Email"
                                    type="email"
                                />
                                <input
                                    v-model="
                                        relationshipForm.related_person.phone
                                    "
                                    class="field-input"
                                    placeholder="Phone"
                                    type="tel"
                                    @blur="
                                        relationshipForm.related_person &&
                                        (relationshipForm.related_person.phone =
                                            formatPhone(
                                                relationshipForm.related_person
                                                    .phone,
                                            ))
                                    "
                                />
                            </div>
                            <div class="flex justify-end">
                                <button class="secondary-button">
                                    Add person and relationship
                                </button>
                            </div>
                        </div>
                    </form>
                </section>

                <section class="rounded-lg border border-border/80 bg-card p-4">
                    <h3 class="font-semibold">Account access</h3>
                    <p class="mt-2 text-sm">
                        {{
                            person.user
                                ? `Linked to ${person.user.email}`
                                : "No account is linked."
                        }}
                    </p>
                    <button
                        v-if="!person.user && person.can_manage"
                        class="secondary-button mt-3"
                        type="button"
                        @click="invite"
                    >
                        Invite account
                    </button>
                    <button
                        v-else-if="person.user && canManageRoles"
                        class="secondary-button mt-3 border-destructive/50 text-destructive hover:bg-destructive/10"
                        type="button"
                        @click="revoke"
                    >
                        Revoke this lodge's access
                    </button>
                </section>
            </template>
        </DialogScrollContent>
    </Dialog>
</template>
