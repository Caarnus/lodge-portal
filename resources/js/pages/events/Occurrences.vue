<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import {
    Dialog,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from "@/components/ui/dialog";
import { Link, router, useForm } from "@inertiajs/vue3";
import { ArchiveRestore, Trash } from "lucide-vue-next";
import { computed, ref } from "vue";

defineOptions({ layout: AppLayout });
const props = defineProps<{
    lodge: { id: number; name: string };
    event: { id: number; title: string };
    occurrences: { data: Array<any> };
    members: Array<{ id: number; display_name: string }>;
}>();
const formatStartTime = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
        hour12: true,
    }).format(new Date(value));
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
const rosterTitle = computed(() =>
    rosterType.value === "reservations"
        ? "Reservation roster"
        : "Volunteer roster",
);
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
    if (!roster.value) return;
    router.post(
        `/lodges/${props.lodge.id}/events/${props.event.id}/occurrences/${roster.value.id}/volunteer-reminders/${id}/retry`,
        {},
        { preserveScroll: true },
    );
};
</script>

<template>
    <main class="mx-auto max-w-6xl space-y-5 p-4 md:p-6">
        <div>
            <Link
                :href="`/lodges/${lodge.id}/events`"
                class="inline-flex items-center rounded-md border border-border bg-card px-3 py-2 text-sm font-medium hover:bg-accent"
            >
                Back to events
            </Link>
            <h1 class="mt-3 text-2xl font-semibold">
                Occurrences: {{ event.title }}
            </h1>
        </div>
        <div
            class="hidden overflow-hidden rounded-lg border border-border md:block"
        >
            <table class="w-full table-fixed text-left text-sm">
                <thead class="bg-muted/50">
                    <tr>
                        <th class="w-[28%] px-4 py-3 font-medium">
                            Start time
                        </th>
                        <th class="w-[16%] px-4 py-3 font-medium">Status</th>
                        <th class="w-[18%] px-4 py-3 font-medium">
                            Reservations
                        </th>
                        <th class="w-[22%] px-4 py-3 font-medium">
                            Volunteer staffing
                        </th>
                        <th class="w-[16%] px-4 py-3">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="occurrence in occurrences.data"
                        :key="occurrence.id"
                        class="border-t border-border"
                    >
                        <td class="px-4 py-3">
                            {{ formatStartTime(occurrence.starts_at) }}
                        </td>
                        <td class="px-4 py-3 capitalize">
                            {{ occurrence.status }}
                        </td>
                        <td class="px-4 py-3">
                            <button
                                v-if="occurrence.reservation_count !== null"
                                type="button"
                                class="cursor-pointer font-medium text-primary underline underline-offset-2 hover:text-primary/80"
                                @click="showRoster(occurrence, 'reservations')"
                            >
                                {{ occurrence.reservation_count }} confirmed
                            </button>
                            <span v-else>—</span>
                        </td>
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                class="cursor-pointer font-medium text-primary underline underline-offset-2 hover:text-primary/80"
                                @click="showRoster(occurrence, 'volunteers')"
                            >
                                {{ occurrence.volunteer_filled }}/{{
                                    occurrence.volunteer_needed
                                }}
                                filled
                            </button>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex min-h-9 justify-end gap-2">
                                <button
                                    v-if="occurrence.status === 'scheduled'"
                                    type="button"
                                    title="Cancel occurrence"
                                    aria-label="Cancel occurrence"
                                    class="icon-button text-destructive"
                                    @click="transition(occurrence, 'cancel')"
                                >
                                    <Trash class="size-4" aria-hidden="true" />
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    title="Restore occurrence"
                                    aria-label="Restore occurrence"
                                    class="icon-button"
                                    @click="transition(occurrence, 'restore')"
                                >
                                    <ArchiveRestore
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="space-y-3 md:hidden">
            <article
                v-for="occurrence in occurrences.data"
                :key="occurrence.id"
                class="rounded-lg border border-border p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-medium">
                        {{ formatStartTime(occurrence.starts_at) }}
                    </h2>
                    <span class="text-sm capitalize text-muted-foreground">{{
                        occurrence.status
                    }}</span>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Reservations</dt>
                        <dd>
                            <button
                                v-if="occurrence.reservation_count !== null"
                                type="button"
                                class="cursor-pointer font-medium text-primary underline underline-offset-2 hover:text-primary/80"
                                @click="showRoster(occurrence, 'reservations')"
                            >
                                {{ occurrence.reservation_count }}
                                confirmed</button
                            ><span v-else>—</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">
                            Volunteer staffing
                        </dt>
                        <dd>
                            <button
                                type="button"
                                class="cursor-pointer font-medium text-primary underline underline-offset-2 hover:text-primary/80"
                                @click="showRoster(occurrence, 'volunteers')"
                            >
                                {{ occurrence.volunteer_filled }}/{{
                                    occurrence.volunteer_needed
                                }}
                                filled
                            </button>
                        </dd>
                    </div>
                </dl>
                <div class="mt-4 flex min-h-9 justify-end gap-2">
                    <button
                        v-if="occurrence.status === 'scheduled'"
                        type="button"
                        title="Cancel occurrence"
                        aria-label="Cancel occurrence"
                        class="icon-button text-destructive"
                        @click="transition(occurrence, 'cancel')"
                    >
                        <Trash class="size-4" aria-hidden="true" />
                    </button>
                    <button
                        v-else
                        type="button"
                        title="Restore occurrence"
                        aria-label="Restore occurrence"
                        class="icon-button"
                        @click="transition(occurrence, 'restore')"
                    >
                        <ArchiveRestore class="size-4" aria-hidden="true" />
                    </button>
                </div>
            </article>
        </div>
        <Dialog
            :open="roster !== null"
            @update:open="!$event && (roster = null)"
        >
            <DialogScrollContent class="max-w-2xl">
                <DialogHeader
                    ><DialogTitle>{{ rosterTitle }}</DialogTitle></DialogHeader
                >
                <div
                    v-if="rosterType === 'reservations'"
                    class="space-y-3 text-sm"
                >
                    <p
                        v-if="!roster?.reservation_roster?.length"
                        class="text-muted-foreground"
                    >
                        No confirmed reservations.
                    </p>
                    <article
                        v-for="(item, index) in roster?.reservation_roster"
                        :key="index"
                        class="rounded-md border border-border bg-card p-3"
                    >
                        <p class="font-medium">{{ item.name }}</p>
                        <p>
                            {{ item.party_size }} people ·
                            <span class="capitalize">{{ item.status }}</span>
                        </p>
                        <p class="text-muted-foreground">
                            {{ item.email }} · {{ item.phone }}
                        </p>
                    </article>
                </div>
                <div v-else class="space-y-4 text-sm">
                    <form
                        class="grid gap-3 rounded-md border border-border bg-card p-3 md:grid-cols-3"
                        @submit.prevent="addVolunteer"
                    >
                        <label class="field-label"
                            >Position<select
                                v-model="volunteerForm.position_id"
                                required
                                class="field-input"
                            >
                                <option value="">Choose position</option>
                                <option
                                    v-for="position in roster?.volunteer_positions?.filter(
                                        (item: any) => item.is_active,
                                    )"
                                    :key="position.id"
                                    :value="position.id"
                                >
                                    {{ position.name }}
                                </option>
                            </select></label
                        >
                        <label class="field-label"
                            >Member<select
                                v-model="volunteerForm.person_id"
                                required
                                class="field-input"
                            >
                                <option value="">Choose member</option>
                                <option
                                    v-for="member in members"
                                    :key="member.id"
                                    :value="member.id"
                                >
                                    {{ member.display_name }}
                                </option>
                            </select></label
                        >
                        <div class="flex items-end justify-end">
                            <button
                                type="submit"
                                :disabled="volunteerForm.processing"
                                class="primary-button w-full md:w-auto"
                            >
                                Add volunteer
                            </button>
                        </div>
                    </form>
                    <section
                        v-for="position in roster?.volunteer_positions"
                        :key="position.id"
                        class="rounded-md border border-border bg-card p-3"
                    >
                        <div
                            class="flex flex-wrap items-baseline justify-between gap-2"
                        >
                            <h3 class="font-medium">{{ position.name }}</h3>
                            <p class="text-muted-foreground">
                                Needed {{ position.needed_count }} · Filled
                                {{
                                    position.commitments.filter(
                                        (item: any) =>
                                            item.status === "committed",
                                    ).length
                                }}
                            </p>
                        </div>
                        <p
                            v-if="!position.commitments.length"
                            class="mt-2 text-muted-foreground"
                        >
                            No commitments.
                        </p>
                        <div
                            v-for="item in position.commitments"
                            :key="item.id"
                            class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3"
                        >
                            <p>
                                <span class="font-medium">{{ item.name }}</span>
                                —
                                <span class="capitalize">{{ item.status }}</span
                                ><span v-if="item.reminder">
                                    · Reminder: {{ item.reminder.status }}</span
                                ><span v-if="item.reminder?.last_error">
                                    — {{ item.reminder.last_error }}</span
                                >
                            </p>
                            <div class="flex min-h-9 gap-2">
                                <button
                                    v-if="item.status === 'committed'"
                                    type="button"
                                    class="inline-flex items-center rounded-md border border-border bg-card px-3 py-2 font-medium text-destructive hover:bg-accent"
                                    @click="removeVolunteer(item)"
                                >
                                    Remove
                                </button>
                                <button
                                    v-if="item.reminder?.status === 'failed'"
                                    type="button"
                                    class="inline-flex items-center rounded-md border border-border bg-card px-3 py-2 font-medium hover:bg-accent"
                                    @click="retryReminder(item.reminder.id)"
                                >
                                    Retry reminder
                                </button>
                            </div>
                        </div>
                    </section>
                </div>
            </DialogScrollContent>
        </Dialog>
    </main>
</template>
