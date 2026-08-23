<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Link, router, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import { Trash, ArchiveRestore } from "lucide-vue-next";

defineOptions({ layout: AppLayout });
const props = defineProps<{
    lodge: { id: number; name: string };
    event: { id: number; title: string };
    occurrences: { data: Array<any> };
    members: Array<{ id: number; display_name: string }>;
}>();
const transition = (occurrence: any, action: "cancel" | "restore") =>
    router.post(
        `/lodges/${props.lodge.id}/events/${props.event.id}/occurrences/${occurrence.id}/${action}`,
    );
const roster = ref<any>(null);
const rosterType = ref<"reservations" | "volunteers">("reservations");
const showRoster = (occurrence: any, type: "reservations" | "volunteers") => {
    roster.value = occurrence;
    rosterType.value = type;
};
const removeVolunteer = (commitment: any) => {
    if (
        !roster.value ||
        !window.confirm(
            `Remove ${commitment.name} from ${commitment.position}?`,
        )
    )
        return;
    router.patch(
        `/lodges/${props.lodge.id}/events/${props.event.id}/occurrences/${roster.value.id}/volunteers/${commitment.id}/remove`,
        {},
        { preserveScroll: true },
    );
};
const volunteerForm = useForm({ position_id: "", person_id: "" });
const addVolunteer = () => {
    if (!roster.value) return;
    volunteerForm.post(
        `/lodges/${props.lodge.id}/events/${props.event.id}/occurrences/${roster.value.id}/volunteers`,
        { preserveScroll: true, onSuccess: () => volunteerForm.reset() },
    );
};
const retryReminder = (id: number) => {
    if (roster.value)
        router.post(
            `/lodges/${props.lodge.id}/events/${props.event.id}/occurrences/${roster.value.id}/volunteer-reminders/${id}/retry`,
            {},
            { preserveScroll: true },
        );
};
</script>

<template>
    <main class="mx-auto max-w-6xl space-y-6 p-6">
        <div>
            <Link
                :href="`/lodges/${lodge.id}/events/${event.id}/edit`"
                class="text-sm text-primary underline"
                >Back to event</Link
            >
            <h1 class="mt-2 text-2xl font-semibold">
                Occurrences: {{ event.title }}
            </h1>
        </div>
        <div class="overflow-hidden rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted/50">
                    <tr>
                        <th class="px-4 py-3">Effective start</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Reservations</th>
                        <th class="px-4 py-3">Volunteer staffing</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="occurrence in occurrences.data"
                        :key="occurrence.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3">
                            {{
                                new Date(occurrence.starts_at).toLocaleString()
                            }}
                        </td>
                        <td class="px-4 py-3 capitalize">
                            {{ occurrence.status }}
                        </td>
                        <td class="px-4 py-3">
                            <button
                                v-if="occurrence.reservation_count !== null"
                                type="button"
                                class="cursor-pointer text-primary underline"
                                @click="showRoster(occurrence, 'reservations')"
                            >
                                {{ occurrence.reservation_count }}
                            </button>
                            <span v-else>—</span>
                        </td>
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                class="cursor-pointer text-primary underline"
                                @click="showRoster(occurrence, 'volunteers')"
                            >
                                {{ occurrence.volunteer_filled }}/{{
                                    occurrence.volunteer_needed
                                }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                v-if="occurrence.status === 'scheduled'"
                                title="Cancel event"
                                aria-label="Cancel event"
                                class="cursor-pointer text-destructive underline"
                                @click="transition(occurrence, 'cancel')"
                            >
                                <Trash
                                    class="size-4"
                                    aria-hidden="true"
                                /></button
                            ><button
                                v-else
                                title="Restore event"
                                aria-label="Restore event"
                                class="cursor-pointer text-primary underline"
                                @click="transition(occurrence, 'restore')"
                            >
                                <ArchiveRestore
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Dialog
            :open="roster !== null"
            @update:open="!$event && (roster = null)"
        >
            <DialogContent class="max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            rosterType === "reservations"
                                ? "Reservation roster"
                                : "Volunteer roster"
                        }}
                    </DialogTitle>
                </DialogHeader>
                <div
                    v-if="rosterType === 'reservations'"
                    class="space-y-2 text-sm"
                >
                    <p
                        v-if="!roster?.reservation_roster?.length"
                        class="text-muted-foreground"
                    >
                        No confirmed reservations.
                    </p>
                    <div
                        v-for="(item, index) in roster?.reservation_roster"
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
                                v-for="position in roster?.volunteer_positions?.filter(
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
                        v-for="position in roster?.volunteer_positions"
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
