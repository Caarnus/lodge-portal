<script setup lang="ts">
import { Dialog, DialogDescription, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { formatPhone } from '@/lib/phone';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Link2, Link2Off, Trash2 } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    mode: 'view' | 'edit';
    lodge: any;
    person: any | null;
    membershipTypes: any[];
    membershipStatuses: any[];
    degrees: any[];
    canManageMemberships: boolean;
}>();
const emit = defineEmits<{ 'update:open': [value: boolean]; 'update:mode': [value: 'view' | 'edit'] }>();

const membership = computed(() => props.person?.memberships?.[0] ?? null);
const dateValue = (value: unknown) => (value ? String(value).slice(0, 10) : '');
const personForm = useForm({
    legal_first_name: '', legal_middle_name: '', legal_last_name: '', legal_suffix: '', preferred_name: '',
    email: '', phone: '', mailing_address_line_1: '', mailing_address_line_2: '', mailing_city: '',
    mailing_state: '', mailing_postal_code: '', birth_date: '', is_deceased: false, death_date: '',
});
const membershipForm = useForm({
    membership_type_id: null as number | null, membership_status_id: null as number | null,
    masonic_degree_id: null as number | null, primary_lodge_number: '', member_number: '',
    is_award_of_gold: false,
    entered_apprentice_date: '', fellow_craft_date: '', master_mason_date: '', affiliation_date: '',
    demit_withdrawal_date: '', end_date: '', notes: '',
});
const pastMasterForm = useForm({ year: new Date().getFullYear() });

const loadForms = () => {
    const person = props.person;
    const currentMembership = membership.value;
    if (!person) return;
    Object.assign(personForm, {
        legal_first_name: person.legal_first_name ?? '', legal_middle_name: person.legal_middle_name ?? '',
        legal_last_name: person.legal_last_name ?? '', legal_suffix: person.legal_suffix ?? '',
        preferred_name: person.preferred_name ?? '', email: person.email ?? '', phone: formatPhone(person.phone),
        mailing_address_line_1: person.mailing_address_line_1 ?? '', mailing_address_line_2: person.mailing_address_line_2 ?? '',
        mailing_city: person.mailing_city ?? '', mailing_state: person.mailing_state ?? '',
        mailing_postal_code: person.mailing_postal_code ?? '', birth_date: dateValue(person.birth_date),
        is_deceased: Boolean(person.is_deceased), death_date: dateValue(person.death_date),
    });
    Object.assign(membershipForm, {
        membership_type_id: currentMembership?.membership_type_id ?? null,
        membership_status_id: currentMembership?.membership_status_id ?? null,
        masonic_degree_id: currentMembership?.masonic_degree_id ?? null,
        primary_lodge_number: currentMembership?.primary_lodge_number ?? '', member_number: currentMembership?.member_number ?? '',
        is_award_of_gold: Boolean(currentMembership?.is_award_of_gold),
        entered_apprentice_date: dateValue(currentMembership?.entered_apprentice_date),
        fellow_craft_date: dateValue(currentMembership?.fellow_craft_date), master_mason_date: dateValue(currentMembership?.master_mason_date),
        affiliation_date: dateValue(currentMembership?.affiliation_date), demit_withdrawal_date: dateValue(currentMembership?.demit_withdrawal_date),
        end_date: dateValue(currentMembership?.end_date), notes: currentMembership?.notes ?? '',
    });
    personForm.clearErrors();
    membershipForm.clearErrors();
};
watch(() => [props.person, props.mode, props.open], loadForms, { immediate: true });

const savePerson = () => {
    if (!props.person) return;
    personForm.phone = formatPhone(personForm.phone);
    personForm.put(`/lodges/${props.lodge.id}/people/${props.person.id}`, { preserveScroll: true });
};
const saveMembership = () => {
    if (!membership.value) return;
    membershipForm.put(`/lodges/${props.lodge.id}/memberships/${membership.value.id}`, { preserveScroll: true });
};
const addPastMasterYear = () => {
    if (!membership.value) return;
    pastMasterForm.post(`/lodges/${props.lodge.id}/memberships/${membership.value.id}/past-master-terms`, { preserveScroll: true });
};
const removePastMasterYear = (term: any) => {
    if (!membership.value || !confirm(`Remove Past Master year ${term.year}?`)) return;
    router.delete(`/lodges/${props.lodge.id}/memberships/${membership.value.id}/past-master-terms/${term.id}`, { preserveScroll: true });
};
const location = computed(() => [props.person?.mailing_city, props.person?.mailing_state, props.person?.mailing_postal_code].filter(Boolean).join(', '));
const address = computed(() => [props.person?.mailing_address_line_1, props.person?.mailing_address_line_2].filter(Boolean).join(', '));
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogScrollContent class="max-h-[calc(100vh-4rem)] max-w-4xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ mode === 'edit' ? 'Edit' : 'View' }} {{ person?.display_name }}</DialogTitle>
                <DialogDescription>Identity information is shared with every lodge authorized to access this person.</DialogDescription>
            </DialogHeader>

            <template v-if="person && mode === 'view'">
                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="font-medium text-slate-500">Legal name</dt><dd>{{ person.name }}</dd></div>
                    <div><dt class="font-medium text-slate-500">Preferred name</dt><dd>{{ person.preferred_name || '—' }}</dd></div>
                    <div><dt class="font-medium text-slate-500">Phone</dt><dd>{{ formatPhone(person.phone) || '—' }}</dd></div>
                    <div><dt class="font-medium text-slate-500">Email</dt><dd class="break-all">{{ person.email || '—' }}</dd></div>
                    <div><dt class="font-medium text-slate-500">Address</dt><dd>{{ address || '—' }}</dd></div>
                    <div><dt class="font-medium text-slate-500">City / State</dt><dd>{{ location || '—' }}</dd></div>
                    <div><dt class="font-medium text-slate-500">Account</dt><dd class="flex items-center gap-2"><Link2 v-if="person.user" class="size-4 text-green-700" /><Link2Off v-else class="size-4 text-slate-400" />{{ person.user ? 'Linked' : 'Not linked' }}</dd></div>
                    <div><dt class="font-medium text-slate-500">Birth date</dt><dd>{{ dateValue(person.birth_date) || '—' }}</dd></div>
                    <div v-if="person.is_deceased"><dt class="font-medium text-slate-500">Death date</dt><dd>{{ dateValue(person.death_date) || 'Not recorded' }}</dd></div>
                </dl>
                <section v-if="membership" class="rounded-lg border p-4 text-sm">
                    <h3 class="font-semibold">{{ lodge.name }} membership</h3>
                    <dl class="mt-3 grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div><dt class="text-slate-500">Type</dt><dd>{{ membership.type?.name || 'Not recorded' }}</dd></div>
                        <div><dt class="text-slate-500">Status</dt><dd>{{ membership.status?.name || 'Not recorded' }}</dd></div>
                        <div><dt class="text-slate-500">Degree</dt><dd>{{ membership.degree?.name || 'Not recorded' }}</dd></div>
                        <div><dt class="text-slate-500">Member number</dt><dd>{{ membership.member_number || '—' }}</dd></div>
                        <div><dt class="text-slate-500">Primary lodge number</dt><dd>{{ membership.primary_lodge_number || '—' }}</dd></div>
                        <div><dt class="text-slate-500">Award of Gold</dt><dd>{{ membership.is_award_of_gold ? 'Yes' : 'No' }}</dd></div>
                        <div><dt class="text-slate-500">Past Master</dt><dd>{{ person.past_master_terms?.length ? person.past_master_terms.map((term: any) => term.year).join(', ') : 'No' }}</dd></div>
                        <div><dt class="text-slate-500">EA date</dt><dd>{{ dateValue(membership.entered_apprentice_date) || '—' }}</dd></div>
                        <div><dt class="text-slate-500">FC date</dt><dd>{{ dateValue(membership.fellow_craft_date) || '—' }}</dd></div>
                        <div><dt class="text-slate-500">MM date</dt><dd>{{ dateValue(membership.master_mason_date) || '—' }}</dd></div>
                        <div><dt class="text-slate-500">Affiliation date</dt><dd>{{ dateValue(membership.affiliation_date) || '—' }}</dd></div>
                        <div><dt class="text-slate-500">Demit/withdrawal date</dt><dd>{{ dateValue(membership.demit_withdrawal_date) || '—' }}</dd></div>
                    </dl>
                </section>
                <section v-if="person.relationship_summaries?.length" class="rounded-lg border p-4 text-sm"><h3 class="font-semibold">Relationships</h3><ul class="mt-2 list-disc space-y-1 pl-5"><li v-for="relationship in person.relationship_summaries" :key="relationship.id">{{ relationship.statement }}</li></ul></section>
                <div class="flex flex-wrap justify-end gap-2 border-t pt-4">
                    <Link :href="`/lodges/${lodge.id}/people/${person.id}/edit`" class="rounded-md border px-4 py-2 text-sm">Open full record</Link>
                    <button v-if="person.can_manage" type="button" class="rounded-md bg-slate-900 px-4 py-2 text-sm text-white" @click="emit('update:mode', 'edit')">Edit</button>
                </div>
            </template>

            <template v-else-if="person">
                <form class="rounded-lg border p-4" @submit.prevent="savePerson">
                    <h3 class="font-semibold">Identity and contact</h3>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <label>Legal first name<input v-model="personForm.legal_first_name" required class="mt-1 w-full rounded border p-2" /></label>
                        <label>Legal middle name<input v-model="personForm.legal_middle_name" class="mt-1 w-full rounded border p-2" /></label>
                        <label>Legal last name<input v-model="personForm.legal_last_name" required class="mt-1 w-full rounded border p-2" /></label>
                        <label>Suffix<input v-model="personForm.legal_suffix" class="mt-1 w-full rounded border p-2" /></label>
                        <label>Preferred name<input v-model="personForm.preferred_name" class="mt-1 w-full rounded border p-2" /></label>
                        <label>Email<input v-model="personForm.email" type="email" class="mt-1 w-full rounded border p-2" /></label>
                        <label>Phone<input v-model="personForm.phone" type="tel" class="mt-1 w-full rounded border p-2" placeholder="(812)555-0100 or +44 20 7946 0958" @blur="personForm.phone = formatPhone(personForm.phone)" /></label>
                        <label>Address line 1<input v-model="personForm.mailing_address_line_1" class="mt-1 w-full rounded border p-2" /></label>
                        <label>Address line 2<input v-model="personForm.mailing_address_line_2" class="mt-1 w-full rounded border p-2" /></label>
                        <label>City<input v-model="personForm.mailing_city" class="mt-1 w-full rounded border p-2" /></label>
                        <label>State<input v-model="personForm.mailing_state" maxlength="2" class="mt-1 w-full rounded border p-2 uppercase" /></label>
                        <label>Postal code<input v-model="personForm.mailing_postal_code" class="mt-1 w-full rounded border p-2" /></label>
                        <label>Birth date<input v-model="personForm.birth_date" type="date" class="mt-1 w-full rounded border p-2" /></label>
                        <label class="flex items-center gap-2"><input v-model="personForm.is_deceased" type="checkbox" /> Deceased</label>
                        <label v-if="personForm.is_deceased">Death date<input v-model="personForm.death_date" type="date" class="mt-1 w-full rounded border p-2" /></label>
                    </div>
                    <p v-for="message in personForm.errors" :key="message" class="mt-2 text-sm text-red-700">{{ message }}</p>
                    <button :disabled="personForm.processing || !person.can_manage" class="mt-4 rounded bg-slate-900 px-4 py-2 text-white disabled:opacity-50">Save person</button>
                </form>

                <form v-if="membership" class="rounded-lg border p-4" @submit.prevent="saveMembership">
                    <h3 class="font-semibold">{{ lodge.name }} membership</h3>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <label>Type<select v-model="membershipForm.membership_type_id" class="mt-1 w-full rounded border p-2"><option :value="null">Unknown</option><option v-for="item in membershipTypes" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
                        <label>Status<select v-model="membershipForm.membership_status_id" required class="mt-1 w-full rounded border p-2"><option v-for="item in membershipStatuses" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
                        <label>Degree<select v-model="membershipForm.masonic_degree_id" class="mt-1 w-full rounded border p-2"><option :value="null">Unknown</option><option v-for="item in degrees" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
                        <label>Primary lodge number<input v-model="membershipForm.primary_lodge_number" class="mt-1 w-full rounded border p-2" /></label>
                        <label>Member number<input v-model="membershipForm.member_number" class="mt-1 w-full rounded border p-2" /></label>
                        <label class="flex items-center gap-2"><input v-model="membershipForm.is_award_of_gold" type="checkbox" /> Award of Gold (50-year member)</label>
                        <label>EA date<input v-model="membershipForm.entered_apprentice_date" type="date" class="mt-1 w-full rounded border p-2" /></label>
                        <label>FC date<input v-model="membershipForm.fellow_craft_date" type="date" class="mt-1 w-full rounded border p-2" /></label>
                        <label>MM date<input v-model="membershipForm.master_mason_date" type="date" class="mt-1 w-full rounded border p-2" /></label>
                        <label>Affiliation date<input v-model="membershipForm.affiliation_date" type="date" class="mt-1 w-full rounded border p-2" /></label>
                        <label>Demit/withdrawal date<input v-model="membershipForm.demit_withdrawal_date" type="date" class="mt-1 w-full rounded border p-2" /></label>
                        <label class="sm:col-span-2 lg:col-span-3">Private lodge notes<textarea v-model="membershipForm.notes" rows="3" class="mt-1 w-full rounded border p-2" /></label>
                    </div>
                    <p v-for="message in membershipForm.errors" :key="message" class="mt-2 text-sm text-red-700">{{ message }}</p>
                    <button :disabled="membershipForm.processing || !canManageMemberships" class="mt-4 rounded bg-slate-900 px-4 py-2 text-white disabled:opacity-50">Save membership</button>
                </form>

                <section v-if="membership" class="rounded-lg border p-4"><h3 class="font-semibold">Past Master years</h3><div class="mt-3 flex flex-wrap gap-2"><span v-for="term in person.past_master_terms" :key="term.id" class="inline-flex items-center gap-1 rounded-full bg-slate-100 py-1 pl-3 pr-1 text-sm">{{ term.year }}<button v-if="canManageMemberships" type="button" :aria-label="`Remove Past Master year ${term.year}`" class="inline-flex size-7 items-center justify-center rounded-full text-red-700" @click="removePastMasterYear(term)"><Trash2 class="size-3.5" /></button></span><span v-if="!person.past_master_terms?.length" class="text-sm text-slate-500">No Past Master years recorded.</span></div><form v-if="canManageMemberships" class="mt-3 flex gap-2" @submit.prevent="addPastMasterYear"><input v-model="pastMasterForm.year" type="number" min="1700" :max="new Date().getFullYear()" aria-label="Past Master year" class="w-32 rounded border p-2" /><button class="rounded border px-4 py-2">Add year</button></form><p v-if="pastMasterForm.errors.year" class="mt-2 text-sm text-red-700">{{ pastMasterForm.errors.year }}</p></section>

                <section v-if="person.relationship_summaries?.length" class="rounded-lg border p-4 text-sm"><h3 class="font-semibold">Relationships</h3><ul class="mt-2 list-disc space-y-1 pl-5"><li v-for="relationship in person.relationship_summaries" :key="relationship.id">{{ relationship.statement }}</li></ul></section>

                <div class="flex justify-end border-t pt-4"><Link :href="`/lodges/${lodge.id}/people/${person.id}/edit`" class="rounded-md border px-4 py-2 text-sm">Open full record</Link></div>
            </template>
        </DialogScrollContent>
    </Dialog>
</template>
