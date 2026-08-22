<script setup lang="ts">
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import InputError from '@/components/InputError.vue';
import PublicNavigationItem from '@/components/website/PublicNavigationItem.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type Viewer = { name: string; email: string } | null;
type StaffingPosition = { id: number; name: string; description: string | null; needed_count: number; filled_count: number; remaining_count: number; commitment_id: number | null; can_commit: boolean; can_withdraw: boolean };
type Occurrence = {
    id: number;
    event_id: number;
    title: string;
    description: string | null;
    starts_at: string;
    ends_at: string;
    time_zone: string;
    location_name: string | null;
    location_details: string | null;
    category: string | null;
    cover_image: string | null;
    is_recurring: boolean;
    reminders_enabled: boolean;
    guest_reminders_enabled: boolean;
    reservations_enabled: boolean;
    guest_reservations_enabled: boolean;
    visibility: string;
    reservation_fields: Array<{ key: string; label: string; help_text: string | null; type: 'short_text' | 'long_text' | 'select' | 'checkbox'; is_required: boolean; options: string[] | null }>;
};

const props = defineProps<{ lodge: { name: string; slug: string; secondary_color?: string }; navigation: any[]; occurrence: Occurrence; viewer: Viewer; staffing?: StaffingPosition[] }>();
const date = new Intl.DateTimeFormat(undefined, { dateStyle: 'full', timeStyle: 'short', timeZone: props.occurrence.time_zone }).format(new Date(props.occurrence.starts_at));
const googleCalendarUrl = computed(() => { const compact = (value: string) => new Date(value).toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, ''); return `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(props.occurrence.title)}&dates=${compact(props.occurrence.starts_at)}/${compact(props.occurrence.ends_at)}`; });
const canSubscribe = computed(() => props.occurrence.reminders_enabled && (props.viewer !== null || (props.occurrence.visibility === 'public' && props.occurrence.guest_reminders_enabled)));
const canReserve = computed(() => props.occurrence.reservations_enabled && (props.viewer !== null || (props.occurrence.visibility === 'public' && props.occurrence.guest_reservations_enabled)));
const form = useForm({ name: props.viewer?.name ?? '', email: props.viewer?.email ?? '', scope: 'occurrence', occurrence_id: props.occurrence.id });
const reservationForm = useForm<{ name: string; email: string; phone: string; party_size: number; subscribe_to_reminders: boolean; responses: Record<string, any> }>({ name: props.viewer?.name ?? '', email: props.viewer?.email ?? '', phone: '', party_size: 1, subscribe_to_reminders: false, responses: Object.fromEntries(props.occurrence.reservation_fields.map((field) => [field.key, field.type === 'checkbox' ? false : ''])) });
const subscribe = () => form.post(`/l/${props.lodge.slug}/events/${props.occurrence.event_id}/reminders`, { preserveScroll: true });
const reserve = () => reservationForm.post(`/l/${props.lodge.slug}/events/${props.occurrence.id}/reservations`, { preserveScroll: true });
const volunteer = (position: StaffingPosition) => useForm({ position_id: position.id }).post(`/l/${props.lodge.slug}/events/${props.occurrence.id}/volunteer-commitments`, { preserveScroll: true });
const withdrawVolunteer = (position: StaffingPosition) => useForm({}).patch(`/l/${props.lodge.slug}/events/${props.occurrence.id}/volunteer-commitments/${position.commitment_id}/withdraw`, { preserveScroll: true });
const subscriptionError = computed(() => {
    const errors = form.errors as Record<string, string>;

    return errors.event || errors.occurrence || errors.scope;
});
</script>

<template>
    <Head :title="`${occurrence.title} — ${lodge.name}`" />
    <div class="min-h-screen bg-background text-foreground">
        <header class="border-b" :style="{ borderColor: lodge.secondary_color }"><div class="mx-auto flex max-w-7xl flex-wrap items-center gap-4 px-5 py-4"><a :href="`/l/${lodge.slug}`" class="text-xl font-bold">{{ lodge.name }}</a><div class="ml-auto flex flex-wrap items-center gap-3"><nav><ul class="flex flex-wrap gap-1"><PublicNavigationItem v-for="item in navigation" :key="item.slug" :item="item" :lodge-slug="lodge.slug" /></ul></nav><a href="/login" class="rounded-md border px-3 py-2 text-sm font-medium">Access portal</a><AppearanceTabs compact /></div></div></header>
        <main class="mx-auto max-w-4xl px-5 py-12">
            <Link :href="`/l/${lodge.slug}/events`" class="text-sm text-primary underline">All events</Link>
            <img v-if="occurrence.cover_image" :src="occurrence.cover_image" alt="" class="mt-6 aspect-[16/8] w-full rounded-xl object-cover" />
            <p v-if="occurrence.category" class="mt-7 text-sm font-semibold uppercase tracking-wide text-muted-foreground">{{ occurrence.category }}</p>
            <h1 class="mt-2 text-4xl font-bold">{{ occurrence.title }}</h1><p class="mt-5 text-lg font-medium">{{ date }}</p><p v-if="occurrence.location_name" class="mt-2">{{ occurrence.location_name }}</p><p v-if="occurrence.location_details" class="mt-1 text-muted-foreground">{{ occurrence.location_details }}</p>
            <article v-if="occurrence.description" class="public-rich-text mt-8" v-html="occurrence.description"></article>
            <p class="mt-6 flex flex-wrap gap-4 text-sm"><a :href="`/l/${lodge.slug}/events/${occurrence.id}.ics`" class="text-primary underline">Download calendar file</a><a v-if="occurrence.is_recurring" :href="`/l/${lodge.slug}/events/${occurrence.event_id}/series.ics`" class="text-primary underline">Download series calendar</a><a :href="googleCalendarUrl" target="_blank" rel="noopener" class="text-primary underline">Add to Google Calendar</a></p>
            <section v-if="staffing" class="mt-10 rounded-xl border bg-card p-6"><h2 class="text-xl font-semibold">Volunteer staffing</h2><p class="mt-2 text-sm text-muted-foreground">Volunteer commitment is separate from reservation and reminders.</p><div v-for="position in staffing" :key="position.id" class="mt-4 border-t pt-4"><h3 class="font-medium">{{ position.name }}</h3><p v-if="position.description" class="mt-1 whitespace-pre-line text-sm">{{ position.description }}</p><p class="mt-1 text-sm">{{ position.filled_count }} filled · {{ position.remaining_count }} open of {{ position.needed_count }}</p><button v-if="position.can_commit" class="mt-2 cursor-pointer rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground" @click="volunteer(position)">Volunteer</button><button v-else-if="position.can_withdraw" class="mt-2 cursor-pointer underline" @click="withdrawVolunteer(position)">Withdraw commitment</button></div></section>
            <section v-if="canReserve" class="mt-10 rounded-xl border bg-card p-6"><h2 class="text-xl font-semibold">Reserve a place</h2><p class="mt-2 text-sm text-muted-foreground">Reservation confirms attendance. Reminder request stays separate.</p><form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="reserve"><label>Name<input v-model="reservationForm.name" required class="mt-1 w-full rounded-md border bg-background px-3 py-2" /><InputError :message="reservationForm.errors.name" /></label><label>Email<input v-model="reservationForm.email" type="email" required class="mt-1 w-full rounded-md border bg-background px-3 py-2" /><InputError :message="reservationForm.errors.email" /></label><label>Phone<input v-model="reservationForm.phone" class="mt-1 w-full rounded-md border bg-background px-3 py-2" /></label><label>People<input v-model.number="reservationForm.party_size" type="number" min="1" required class="mt-1 w-full rounded-md border bg-background px-3 py-2" /><InputError :message="reservationForm.errors.party_size" /></label><template v-for="field in occurrence.reservation_fields" :key="field.key"><label v-if="field.type === 'short_text'" class="sm:col-span-2">{{ field.label }}<input v-model="reservationForm.responses[field.key]" :required="field.is_required" class="mt-1 w-full rounded-md border bg-background px-3 py-2" /><small v-if="field.help_text" class="mt-1 block text-muted-foreground">{{ field.help_text }}</small><InputError :message="reservationForm.errors[`responses.${field.key}`]" /></label><label v-else-if="field.type === 'long_text'" class="sm:col-span-2">{{ field.label }}<textarea v-model="reservationForm.responses[field.key]" :required="field.is_required" class="mt-1 min-h-24 w-full rounded-md border bg-background px-3 py-2" /><small v-if="field.help_text" class="mt-1 block text-muted-foreground">{{ field.help_text }}</small><InputError :message="reservationForm.errors[`responses.${field.key}`]" /></label><label v-else-if="field.type === 'select'" class="sm:col-span-2">{{ field.label }}<select v-model="reservationForm.responses[field.key]" :required="field.is_required" class="mt-1 w-full rounded-md border bg-background px-3 py-2"><option value="">Choose one</option><option v-for="option in field.options ?? []" :key="option" :value="option">{{ option }}</option></select><small v-if="field.help_text" class="mt-1 block text-muted-foreground">{{ field.help_text }}</small><InputError :message="reservationForm.errors[`responses.${field.key}`]" /></label><label v-else class="sm:col-span-2 flex items-center gap-2"><input v-model="reservationForm.responses[field.key]" type="checkbox" /> {{ field.label }}<span v-if="field.is_required">(required)</span><InputError :message="reservationForm.errors[`responses.${field.key}`]" /></label></template><label v-if="canSubscribe" class="sm:col-span-2 flex items-center gap-2"><input v-model="reservationForm.subscribe_to_reminders" type="checkbox" /> Also subscribe me to reminders. This is separate from my reservation.</label><button :disabled="reservationForm.processing" class="cursor-pointer rounded-md bg-primary px-4 py-2 font-medium text-primary-foreground">Reserve</button><p v-if="reservationForm.recentlySuccessful" class="text-sm text-green-700 dark:text-green-400">Reservation confirmed.</p></form></section>
            <section v-if="canSubscribe" class="mt-10 rounded-xl border bg-card p-6"><h2 class="text-xl font-semibold">Get reminders</h2><p class="mt-2 text-sm text-muted-foreground">Receive event notifications without reserving a place or indicating attendance.</p><form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="subscribe"><label>Name<input v-model="form.name" autocomplete="name" class="mt-1 w-full rounded-md border bg-background px-3 py-2" /><InputError :message="form.errors.name" /></label><label>Email<input v-model="form.email" type="email" autocomplete="email" required class="mt-1 w-full rounded-md border bg-background px-3 py-2" /><InputError :message="form.errors.email" /></label><fieldset v-if="occurrence.is_recurring" class="sm:col-span-2"><legend class="text-sm font-medium">Reminder scope</legend><label class="mt-2 flex items-center gap-2 text-sm"><input v-model="form.scope" type="radio" value="occurrence" /> This occurrence only</label><label class="mt-2 flex items-center gap-2 text-sm"><input v-model="form.scope" type="radio" value="series" /> This event series</label></fieldset><InputError :message="subscriptionError" class="sm:col-span-2" /><div class="sm:col-span-2"><button type="submit" :disabled="form.processing" class="cursor-pointer rounded-md bg-primary px-4 py-2 font-medium text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50">Request reminders</button><p v-if="form.recentlySuccessful" class="mt-3 text-sm text-green-700 dark:text-green-400">Your reminder subscription is active.</p></div></form></section>
        </main>
    </div>
</template>
