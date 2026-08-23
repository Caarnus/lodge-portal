<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import { type BreadcrumbItem } from "@/types";
import { Head, Link, router } from "@inertiajs/vue3";

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Dashboard",
        href: "/dashboard",
    },
];

type VolunteerCommitment = {
    id: number;
    position: string;
    event: string;
    lodge: string;
    lodge_slug: string;
    occurrence_id: number;
};

defineProps<{
    memberships: Array<{ id: number; lodge: string; number: string; type: string | null; degree: string | null; site_url: string; directory_url: string | null; newsletters_url: string }>;
    upcomingEvents: Array<{ id: number; event: string; lodge: string; url: string }>;
    reservations: Array<{ id: number; event: string; lodge: string }>;
    reminders: Array<{ id: number; event: string; lodge: string }>;
    profile: { linked: boolean; directory_scope: string | null; settings_url: string };
    volunteerCommitments: VolunteerCommitment[];
}>();
const withdraw = (commitment: VolunteerCommitment) =>
    router.patch(
        `/l/${commitment.lodge_slug}/events/${commitment.occurrence_id}/volunteer-commitments/${commitment.id}/withdraw`,
    );
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto grid w-full max-w-6xl gap-4 p-6 md:grid-cols-2 lg:grid-cols-3">
            <section class="rounded-xl border p-5"><h2 class="font-semibold">Memberships</h2><p v-if="!memberships.length" class="mt-2 text-sm text-muted-foreground">No active memberships.</p><ul v-else class="mt-2 space-y-3 text-sm"><li v-for="item in memberships" :key="item.id"><strong>{{ item.lodge }} · {{ item.number }}</strong><br />{{ [item.type, item.degree].filter(Boolean).join(' · ') }}<div class="mt-1 flex gap-3"><Link :href="item.site_url" class="underline">Lodge site</Link><Link v-if="item.directory_url" :href="item.directory_url" class="underline">Directory</Link><Link :href="item.newsletters_url" class="underline">Newsletters</Link></div></li></ul></section>
            <section class="rounded-xl border p-5"><h2 class="font-semibold">Upcoming events</h2><p v-if="!upcomingEvents.length" class="mt-2 text-sm text-muted-foreground">No upcoming events.</p><ul v-else class="mt-2 text-sm"><li v-for="item in upcomingEvents" :key="item.id"><Link :href="item.url" class="underline">{{ item.event }}</Link> · {{ item.lodge }}</li></ul></section>
            <section class="rounded-xl border p-5"><h2 class="font-semibold">Profile</h2><p class="mt-2 text-sm text-muted-foreground">{{ profile.linked ? `Directory: ${profile.directory_scope ?? 'own lodge'}` : 'Your account is not linked to a member profile.' }}</p><Link :href="profile.settings_url" class="mt-2 inline-block text-sm underline">Profile and privacy settings</Link></section>
            <section class="rounded-xl border p-5"><h2 class="font-semibold">Reservations</h2><p v-if="!reservations.length" class="mt-2 text-sm text-muted-foreground">No active reservations.</p><ul v-else class="mt-2 text-sm"><li v-for="item in reservations" :key="item.id">{{ item.event }} · {{ item.lodge }}</li></ul></section>
            <section class="rounded-xl border p-5"><h2 class="font-semibold">Reminder subscriptions</h2><p v-if="!reminders.length" class="mt-2 text-sm text-muted-foreground">No active reminders.</p><ul v-else class="mt-2 text-sm"><li v-for="item in reminders" :key="item.id">{{ item.event }} · {{ item.lodge }}</li></ul></section>
        </div>
        <div class="mx-auto w-full max-w-4xl rounded-xl border p-6">
            <h1 class="text-xl font-semibold">
                Upcoming volunteer commitments
            </h1>
            <p
                v-if="!volunteerCommitments.length"
                class="mt-3 text-sm text-muted-foreground"
            >
                No upcoming volunteer commitments.
            </p>
            <ul v-else class="mt-4 divide-y">
                <li
                    v-for="commitment in volunteerCommitments"
                    :key="commitment.id"
                    class="py-4"
                >
                    <Link
                        :href="`/l/${commitment.lodge_slug}/events/${commitment.occurrence_id}`"
                        class="font-medium text-primary underline"
                        >{{ commitment.position }} —
                        {{ commitment.event }}</Link
                    >
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ commitment.lodge }}
                    </p>
                    <button
                        class="mt-2 text-sm underline"
                        @click="withdraw(commitment)"
                    >
                        Withdraw commitment
                    </button>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
