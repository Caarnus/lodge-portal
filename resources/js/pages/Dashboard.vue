<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
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
    memberships: Array<{
        id: number;
        lodge: string;
        number: string;
        type: string | null;
        degree: string | null;
        site_url: string;
        directory_url: string | null;
        ritual_assistance_url: string | null;
        newsletters_url: string;
    }>;
    upcomingEvents: Array<{
        id: number;
        event: string;
        lodge: string;
        url: string;
    }>;
    reservations: Array<{ id: number; event: string; lodge: string }>;
    reminders: Array<{ id: number; event: string; lodge: string }>;
    profile: {
        linked: boolean;
        directory_scope: string | null;
        settings_url: string;
        ritual_url: string;
    };
    volunteerCommitments: VolunteerCommitment[];
    ritual: {
        current_total: number;
        highest_level: string | null;
        learning_count: number;
        proficient_count: number;
        credited_count: number;
        url: string;
    } | null;
}>();
const withdraw = (commitment: VolunteerCommitment) =>
    router.patch(
        `/l/${commitment.lodge_slug}/events/${commitment.occurrence_id}/volunteer-commitments/${commitment.id}/withdraw`,
    );
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
            <PageHeader
                title="Dashboard"
                description="Your lodge activity, memberships, and upcoming commitments."
            />
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Card>
                    <CardHeader
                        ><CardTitle>Memberships</CardTitle
                        ><CardDescription
                            >Your active lodge connections.</CardDescription
                        ></CardHeader
                    >
                    <CardContent>
                        <p
                            v-if="!memberships.length"
                            class="text-sm text-muted-foreground"
                        >
                            No active memberships.
                        </p>
                        <ul v-else class="space-y-4 text-sm">
                            <li v-for="item in memberships" :key="item.id">
                                <p class="font-medium">
                                    {{ item.lodge }} · {{ item.number }}
                                </p>
                                <p
                                    v-if="item.type || item.degree"
                                    class="mt-1 text-muted-foreground"
                                >
                                    {{
                                        [item.type, item.degree]
                                            .filter(Boolean)
                                            .join(" · ")
                                    }}
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <Button
                                        :as="Link"
                                        :href="item.site_url"
                                        variant="outline"
                                        size="sm"
                                        >Lodge site</Button
                                    >
                                    <Button
                                        v-if="item.directory_url"
                                        :as="Link"
                                        :href="item.directory_url"
                                        variant="outline"
                                        size="sm"
                                        >Directory</Button
                                    >
                                    <Button
                                        v-if="item.ritual_assistance_url"
                                        :as="Link"
                                        :href="item.ritual_assistance_url"
                                        variant="outline"
                                        size="sm"
                                        >Ritual assistance</Button
                                    >
                                    <Button
                                        :as="Link"
                                        :href="item.newsletters_url"
                                        variant="outline"
                                        size="sm"
                                        >Newsletters</Button
                                    >
                                </div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
                <Card v-if="ritual"
                    ><CardHeader
                        ><CardTitle>Ritual progress</CardTitle
                        ><CardDescription
                            >Self-reported knowledge and open-lodge
                            credit.</CardDescription
                        ></CardHeader
                    ><CardContent class="space-y-3"
                        ><div class="flex flex-wrap gap-2">
                            <Badge
                                >{{ ritual.current_total }} current
                                points</Badge
                            ><Badge
                                v-if="ritual.highest_level"
                                variant="secondary"
                                >{{ ritual.highest_level }}</Badge
                            >
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ ritual.learning_count }} learning ·
                            {{ ritual.proficient_count }} proficient ·
                            {{ ritual.credited_count }} credited
                        </p>
                        <Button
                            :as="Link"
                            :href="ritual.url"
                            variant="outline"
                            size="sm"
                            >Manage ritual progress</Button
                        ></CardContent
                    ></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle>Upcoming events</CardTitle
                        ><CardDescription
                            >Events on your lodge calendar.</CardDescription
                        ></CardHeader
                    ><CardContent>
                        <p
                            v-if="!upcomingEvents.length"
                            class="text-sm text-muted-foreground"
                        >
                            No upcoming events.
                        </p>
                        <ul v-else class="space-y-2 text-sm">
                            <li v-for="item in upcomingEvents" :key="item.id">
                                <Link
                                    :href="item.url"
                                    class="font-medium text-primary hover:underline"
                                    >{{ item.event }}</Link
                                >
                                · {{ item.lodge }}
                            </li>
                        </ul>
                    </CardContent></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle>Profile</CardTitle
                        ><CardDescription
                            >Account and directory privacy.</CardDescription
                        ></CardHeader
                    ><CardContent
                        ><p class="text-sm text-muted-foreground">
                            {{
                                profile.linked
                                    ? `Directory: ${profile.directory_scope ?? "own lodge"}`
                                    : "Your account is not linked to a member profile."
                            }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Button
                                :as="Link"
                                :href="profile.settings_url"
                                variant="outline"
                                size="sm"
                                >Profile and privacy</Button
                            ><Button
                                v-if="profile.linked"
                                :as="Link"
                                :href="profile.ritual_url"
                                variant="outline"
                                size="sm"
                                >Ritual progress</Button
                            >
                        </div></CardContent
                    ></Card
                >
                <Card
                    ><CardHeader><CardTitle>Reservations</CardTitle></CardHeader
                    ><CardContent>
                        <p
                            v-if="!reservations.length"
                            class="text-sm text-muted-foreground"
                        >
                            No active reservations.
                        </p>
                        <ul v-else class="space-y-2 text-sm">
                            <li v-for="item in reservations" :key="item.id">
                                {{ item.event }} · {{ item.lodge }}
                            </li>
                        </ul>
                    </CardContent></Card
                >
                <Card
                    ><CardHeader
                        ><CardTitle
                            >Reminder subscriptions</CardTitle
                        ></CardHeader
                    ><CardContent>
                        <p
                            v-if="!reminders.length"
                            class="text-sm text-muted-foreground"
                        >
                            No active reminders.
                        </p>
                        <ul v-else class="space-y-2 text-sm">
                            <li v-for="item in reminders" :key="item.id">
                                {{ item.event }} · {{ item.lodge }}
                            </li>
                        </ul>
                    </CardContent></Card
                >
            </div>
            <Card>
                <CardHeader
                    ><CardTitle>Upcoming volunteer commitments</CardTitle
                    ><CardDescription
                        >Positions you have volunteered to
                        fill.</CardDescription
                    ></CardHeader
                >
                <CardContent>
                    <p
                        v-if="!volunteerCommitments.length"
                        class="text-sm text-muted-foreground"
                    >
                        No upcoming volunteer commitments.
                    </p>
                    <ul v-else class="divide-y divide-border">
                        <li
                            v-for="commitment in volunteerCommitments"
                            :key="commitment.id"
                            class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <Link
                                    :href="`/l/${commitment.lodge_slug}/events/${commitment.occurrence_id}`"
                                    class="font-medium text-primary underline"
                                    >{{ commitment.position }} —
                                    {{ commitment.event }}</Link
                                >
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ commitment.lodge }}
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                @click="withdraw(commitment)"
                                >Withdraw commitment</Button
                            >
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </main>
    </AppLayout>
</template>
