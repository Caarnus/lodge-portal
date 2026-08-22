<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Link, router, useForm } from "@inertiajs/vue3";
import { Pencil } from "lucide-vue-next";
import { ref } from "vue";

defineOptions({ layout: AppLayout });
const props = defineProps<{
    lodge: { id: number; name: string };
    events: {
        data: Array<{
            id: number;
            title: string;
            slug: string;
            status: string;
            first_starts_at: string;
            category: { name: string } | null;
            occurrence_count: number;
            occurrence: null | {
                id: number;
                reservation_count: number | null;
                volunteer_filled: number;
                volunteer_needed: number;
                reservation_roster: Array<{
                    name: string;
                    email: string;
                    phone: string | null;
                    party_size: number;
                    status: string;
                }>;
                volunteer_positions: Array<any>;
            };
        }>;
    };
    members: Array<{ id: number; display_name: string }>;
}>();
const rosterEvent = ref<any>(null);
const rosterType = ref<"reservations" | "volunteers">("reservations");
const showRoster = (event: any, type: "reservations" | "volunteers") => {
    rosterEvent.value = event;
    rosterType.value = type;
};
const removeVolunteer = (item: any) => {
    const occurrence = rosterEvent.value?.occurrence;
    if (
        !occurrence ||
        !window.confirm(`Remove ${item.name} from ${item.position}?`)
    )
        return;
    router.patch(
        `/lodges/${props.lodge.id}/events/${rosterEvent.value.id}/occurrences/${occurrence.id}/volunteers/${item.id}/remove`,
        {},
        { preserveScroll: true },
    );
};
const volunteerForm = useForm({ position_id: "", person_id: "" });
const addVolunteer = () => {
    const occurrence = rosterEvent.value?.occurrence;
    if (!occurrence) return;
    volunteerForm.post(
        `/lodges/${props.lodge.id}/events/${rosterEvent.value.id}/occurrences/${occurrence.id}/volunteers`,
        { preserveScroll: true, onSuccess: () => volunteerForm.reset() },
    );
};
const retryReminder = (id: number) => {
    const occurrence = rosterEvent.value?.occurrence;
    if (occurrence)
        router.post(
            `/lodges/${props.lodge.id}/events/${rosterEvent.value.id}/occurrences/${occurrence.id}/volunteer-reminders/${id}/retry`,
            {},
            { preserveScroll: true },
        );
};
</script>

<template>
    <main class="mx-auto max-w-6xl space-y-6 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Events</h1>
                <p class="text-sm text-muted-foreground">
                    Manage events for {{ lodge.name }}.
                </p>
            </div>
            <div class="flex gap-2">
                <Link
                    :href="`/lodges/${lodge.id}/event-categories`"
                    class="rounded-md border px-3 py-2 text-sm"
                    >Categories</Link
                ><Link
                    :href="`/lodges/${lodge.id}/events/create`"
                    class="rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground"
                    >Create event</Link
                >
            </div>
        </div>
        <div
            v-if="events.data.length"
            class="overflow-hidden rounded-lg border"
        >
            <table class="w-full text-left text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3">Event</th>
                        <th class="px-4 py-3">Starts</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Occurrences</th>
                        <th class="px-4 py-3">Single occurrence</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="event in events.data"
                        :key="event.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ event.title }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ event.category?.name ?? "Uncategorized" }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            {{
                                new Date(event.first_starts_at).toLocaleString()
                            }}
                        </td>
                        <td class="px-4 py-3 capitalize">{{ event.status }}</td>
                        <td class="px-4 py-3">
                            <Link
                                :href="`/lodges/${lodge.id}/events/${event.id}/occurrences`"
                                class="text-primary underline"
                            >
                                {{ event.occurrence_count }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <template v-if="event.occurrence">
                                <p
                                    v-if="
                                        event.occurrence.reservation_count !==
                                        null
                                    "
                                >
                                    <button
                                        type="button"
                                        class="cursor-pointer text-primary underline"
                                        @click="
                                            showRoster(event, 'reservations')
                                        "
                                    >
                                        Reservations:
                                        {{ event.occurrence.reservation_count }}
                                    </button>
                                </p>
                                <p>
                                    <button
                                        type="button"
                                        class="cursor-pointer text-primary underline"
                                        @click="showRoster(event, 'volunteers')"
                                    >
                                        Staffing:
                                        {{
                                            event.occurrence.volunteer_filled
                                        }}/{{
                                            event.occurrence.volunteer_needed
                                        }}
                                    </button>
                                </p>
                            </template>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="`/lodges/${lodge.id}/events/${event.id}/edit`"
                                title="Edit event"
                                aria-label="Edit event"
                                class="inline-flex rounded p-1 text-primary hover:bg-muted"
                                ><Pencil class="size-4" aria-hidden="true"
                            /></Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div
            v-else
            class="rounded-lg border border-dashed p-10 text-center text-muted-foreground"
        >
            No events have been created yet.
        </div>
        <Dialog
            :open="rosterEvent !== null"
            @update:open="!$event && (rosterEvent = null)"
        >
            <DialogContent class="max-w-xl">
                <DialogHeader
                    ><DialogTitle>{{
                        rosterType === "reservations"
                            ? "Reservation roster"
                            : "Volunteer roster"
                    }}</DialogTitle></DialogHeader
                >
                <div
                    v-if="rosterType === 'reservations'"
                    class="space-y-2 text-sm"
                >
                    <p
                        v-if="
                            !rosterEvent?.occurrence?.reservation_roster?.length
                        "
                        class="text-muted-foreground"
                    >
                        No confirmed reservations.
                    </p>
                    <div
                        v-for="(item, index) in rosterEvent?.occurrence
                            ?.reservation_roster"
                        :key="index"
                        class="rounded border p-3"
                    >
                        <strong>{{ item.name }}</strong> ·
                        {{ item.party_size }} guests · {{ item.status }}
                        <div class="text-muted-foreground">
                            {{ item.email }} · {{ item.phone }}
                        </div>
                    </div>
                </div>
                <div v-else class="space-y-2 text-sm">
                    <form
                        class="grid gap-2 rounded border p-3 sm:grid-cols-3"
                        @submit.prevent="addVolunteer"
                    >
                        <select
                            v-model="volunteerForm.position_id"
                            required
                            class="rounded border bg-background p-2"
                        >
                            <option value="">Position</option>
                            <option
                                v-for="position in rosterEvent?.occurrence?.volunteer_positions?.filter(
                                    (item: any) => item.is_active,
                                )"
                                :key="position.id"
                                :value="position.id"
                            >
                                {{ position.name }}
                            </option></select
                        ><select
                            v-model="volunteerForm.person_id"
                            required
                            class="rounded border bg-background p-2"
                        >
                            <option value="">Member</option>
                            <option
                                v-for="member in members"
                                :key="member.id"
                                :value="member.id"
                            >
                                {{ member.display_name }}
                            </option></select
                        ><button
                            type="submit"
                            class="rounded bg-primary px-3 py-2 text-primary-foreground"
                        >
                            Add volunteer
                        </button>
                    </form>
                    <section
                        v-for="position in rosterEvent?.occurrence
                            ?.volunteer_positions"
                        :key="position.id"
                        class="rounded border p-3"
                    >
                        <strong>{{ position.name }}</strong> · Needed
                        {{ position.needed_count }} · Filled
                        {{
                            position.commitments.filter(
                                (item: any) => item.status === "committed",
                            ).length
                        }}
                        <p
                            v-if="!position.commitments.length"
                            class="mt-2 text-muted-foreground"
                        >
                            No commitments.
                        </p>
                        <div
                            v-for="item in position.commitments"
                            :key="item.id"
                            class="mt-2 flex flex-wrap items-center justify-between gap-2 border-t pt-2"
                        >
                            <span
                                >{{ item.name }} — {{ item.status
                                }}<span v-if="item.reminder">
                                    · Reminder: {{ item.reminder.status
                                    }}<span v-if="item.reminder.last_error">
                                        — {{ item.reminder.last_error }}</span
                                    ></span
                                ></span
                            ><span
                                ><button
                                    v-if="item.status === 'committed'"
                                    type="button"
                                    class="mr-2 text-destructive underline"
                                    @click="removeVolunteer(item)"
                                >
                                    Remove</button
                                ><button
                                    v-if="item.reminder?.status === 'failed'"
                                    type="button"
                                    class="underline"
                                    @click="retryReminder(item.reminder.id)"
                                >
                                    Retry reminder
                                </button></span
                            >
                        </div>
                    </section>
                </div>
            </DialogContent>
        </Dialog>
    </main>
</template>
