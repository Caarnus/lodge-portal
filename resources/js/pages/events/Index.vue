<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import EventEditor from "@/pages/events/Edit.vue";
import {
    Dialog,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from "@/components/ui/dialog";
import { formatLocalTimestamp } from "@/utils/date";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    CalendarDays,
    Pencil,
    Plus,
    Search,
    SlidersHorizontal,
    X,
} from "lucide-vue-next";
import { nextTick, reactive, ref, watch } from "vue";

defineOptions({ layout: AppLayout });
const props = defineProps<{
    lodge: { id: number; name: string };
    events: {
        data: Array<{
            id: number;
            title: string;
            slug: string;
            status: string;
            visibility: string;
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
    filters: {
        search: string;
        status: string;
        visibility: string;
        category: number | null;
        sort: string;
        direction: string;
    };
    categories: Array<{ id: number; name: string }>;
    members: Array<{ id: number; display_name: string }>;
    eventEditor: any | null;
}>();
const filters = reactive({ ...props.filters });
const filtersOpen = ref(false);
let filterTimer: ReturnType<typeof setTimeout> | undefined;
const applyFilters = () =>
    router.get(
        `/lodges/${props.lodge.id}/events`,
        {
            search: filters.search || undefined,
            status: filters.status || undefined,
            visibility: filters.visibility || undefined,
            category: filters.category || undefined,
            sort: filters.sort === "first_starts_at" ? undefined : filters.sort,
            direction:
                filters.direction === "desc" ? undefined : filters.direction,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
watch(
    () => filters.search,
    () => {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(applyFilters, 350);
    },
);
watch(
    () => [filters.status, filters.visibility, filters.category],
    applyFilters,
);
const sortBy = (column: string) => {
    filters.direction =
        filters.sort === column && filters.direction === "asc" ? "desc" : "asc";
    filters.sort = column;
    applyFilters();
};
const sortIcon = (column: string) =>
    filters.sort !== column
        ? ArrowUpDown
        : filters.direction === "asc"
          ? ArrowUp
          : ArrowDown;
const resetFilters = () => {
    Object.assign(filters, {
        search: "",
        status: "",
        visibility: "",
        category: null,
        sort: "first_starts_at",
        direction: "desc",
    });
    nextTick(applyFilters);
};
const timestamp = formatLocalTimestamp;
const occurrencesUrl = (event: { id: number }) =>
    "/lodges/" + props.lodge.id + "/events/" + event.id + "/occurrences";
const editorOpen = ref(Boolean(props.eventEditor));
const editorUrl = (modal?: string | number) =>
    "/lodges/" +
    props.lodge.id +
    "/events" +
    (modal === undefined ? "" : "?modal=" + encodeURIComponent(String(modal)));
const openEditor = (modal: "create" | number) =>
    router.get(editorUrl(modal), {}, { preserveScroll: true });
const closeEditor = () => router.get(editorUrl(), {}, { preserveScroll: true });
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
    <Head title="Events" />
    <main class="mx-auto max-w-6xl space-y-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Events</h1>
                <p class="text-sm text-muted-foreground">
                    Manage events for {{ lodge.name }}.
                </p>
            </div>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="primary-button"
                    @click="openEditor('create')"
                >
                    <Plus class="mr-1 size-4" /> Create event
                </button>
            </div>
        </div>
        <section class="rounded-lg border bg-slate-50">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 p-4 text-left font-medium"
                :aria-expanded="filtersOpen"
                aria-controls="event-filters"
                @click="filtersOpen = !filtersOpen"
            >
                <span class="inline-flex items-center gap-2"
                    ><SlidersHorizontal class="size-4" /> Search and
                    filters</span
                >
                <span
                    v-if="
                        filters.search ||
                        filters.status ||
                        filters.visibility ||
                        filters.category
                    "
                    class="text-sm text-muted-foreground"
                    >Filters applied</span
                >
            </button>
            <form
                v-show="filtersOpen"
                id="event-filters"
                class="grid gap-3 border-t p-4 md:grid-cols-[minmax(14rem,2fr)_repeat(3,minmax(8rem,1fr))_auto]"
                @submit.prevent
            >
                <label class="relative"
                    ><Search
                        class="absolute left-3 top-3 size-4 text-muted-foreground" /><input
                        v-model="filters.search"
                        type="search"
                        class="field-input pl-9"
                        placeholder="Search events"
                /></label>
                <select v-model="filters.status" class="field-input">
                    <option value="">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="archived">Archived</option>
                </select>
                <select v-model="filters.visibility" class="field-input">
                    <option value="">All visibility</option>
                    <option value="public">Public</option>
                    <option value="masons">Masons only</option>
                    <option value="lodge">Lodge only</option>
                </select>
                <select v-model="filters.category" class="field-input">
                    <option :value="null">All categories</option>
                    <option
                        v-for="category in categories"
                        :key="category.id"
                        :value="category.id"
                    >
                        {{ category.name }}
                    </option>
                </select>
                <button
                    type="button"
                    class="icon-button"
                    title="Clear filters"
                    @click="resetFilters"
                >
                    <X class="size-4" />
                </button>
            </form>
        </section>
        <div
            v-if="events.data.length"
            class="hidden overflow-hidden rounded-lg border md:block"
        >
            <table class="w-full table-fixed text-left text-sm">
                <colgroup>
                    <col />
                    <col class="w-36" />
                    <col class="w-24" />
                    <col class="w-28" />
                    <col class="w-40" />
                    <col class="w-24" />
                </colgroup>
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th class="p-3">
                            <button
                                class="inline-flex items-center gap-1 whitespace-nowrap"
                                @click="sortBy('title')"
                            >
                                Event
                                <component
                                    :is="sortIcon('title')"
                                    class="size-3"
                                />
                            </button>
                        </th>
                        <th class="p-3">
                            <button
                                class="inline-flex items-center gap-1 whitespace-nowrap"
                                @click="sortBy('first_starts_at')"
                            >
                                Starts
                                <component
                                    :is="sortIcon('first_starts_at')"
                                    class="size-3"
                                />
                            </button>
                        </th>
                        <th class="p-3">
                            <button
                                class="inline-flex items-center gap-1 whitespace-nowrap"
                                @click="sortBy('status')"
                            >
                                Status
                                <component
                                    :is="sortIcon('status')"
                                    class="size-3"
                                />
                            </button>
                        </th>
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
                        <td class="p-3">
                            <p
                                class="truncate font-medium"
                                :title="event.title"
                            >
                                {{ event.title }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ event.category?.name ?? "Uncategorized" }}
                            </p>
                        </td>
                        <td class="p-3">
                            {{ timestamp(event.first_starts_at) }}
                        </td>
                        <td class="p-3 capitalize">{{ event.status }}</td>
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
                        <td class="w-24 px-1 py-3 text-right">
                            <button
                                type="button"
                                title="Edit event"
                                aria-label="Edit event"
                                class="icon-button"
                                @click="openEditor(event.id)"
                            >
                                <Pencil class="size-4" aria-hidden="true" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="events.data.length" class="space-y-3 md:hidden">
            <article
                v-for="event in events.data"
                :key="event.id"
                class="rounded-lg border p-4"
            >
                <strong class="block">{{ event.title }}</strong>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ event.category?.name ?? "Uncategorized" }}
                </p>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Starts</dt>
                        <dd>{{ timestamp(event.first_starts_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Status</dt>
                        <dd class="capitalize">{{ event.status }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Visibility</dt>
                        <dd class="capitalize">
                            {{
                                event.visibility === "masons"
                                    ? "Masons only"
                                    : event.visibility
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Occurrences</dt>
                        <dd>{{ event.occurrence_count }}</dd>
                    </div>
                </dl>
                <div v-if="event.occurrence" class="mt-3 text-sm">
                    <button
                        v-if="event.occurrence.reservation_count !== null"
                        type="button"
                        class="mr-3 text-primary underline"
                        @click="showRoster(event, 'reservations')"
                    >
                        Reservations: {{ event.occurrence.reservation_count }}
                    </button>
                    <button
                        type="button"
                        class="text-primary underline"
                        @click="showRoster(event, 'volunteers')"
                    >
                        Staffing: {{ event.occurrence.volunteer_filled }}/{{
                            event.occurrence.volunteer_needed
                        }}
                    </button>
                </div>
                <div class="mt-4 flex justify-end gap-1">
                    <Link
                        :href="occurrencesUrl(event)"
                        class="icon-button"
                        title="Manage occurrences"
                        ><CalendarDays class="size-4"
                    /></Link>
                    <button
                        type="button"
                        class="icon-button"
                        title="Edit event"
                        @click="openEditor(event.id)"
                    >
                        <Pencil class="size-4" />
                    </button>
                </div>
            </article>
        </div>
        <div
            v-if="!events.data.length"
            class="rounded-lg border border-dashed p-10 text-center text-muted-foreground"
        >
            No events match these filters.
        </div>
        <Dialog
            :open="rosterEvent !== null"
            @update:open="!$event && (rosterEvent = null)"
        >
            <DialogScrollContent class="max-w-xl">
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
            </DialogScrollContent>
        </Dialog>
        <Dialog :open="editorOpen" @update:open="!$event && closeEditor()">
            <DialogScrollContent class="w-[calc(100vw-2rem)] max-w-5xl">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            eventEditor?.event?.id
                                ? "Edit event"
                                : "Create event"
                        }}
                    </DialogTitle>
                </DialogHeader>
                <EventEditor
                    v-if="eventEditor"
                    :lodge="eventEditor.lodge"
                    :event="eventEditor.event"
                    :categories="eventEditor.categories"
                    :media="eventEditor.media"
                    :reservation-fields="eventEditor.reservationFields"
                    :reminder-rules="eventEditor.reminderRules"
                    :reminder-subscription-count="
                        eventEditor.reminderSubscriptionCount
                    "
                    :volunteer-positions="eventEditor.volunteerPositions"
                    :occurrences="eventEditor.occurrences"
                    embedded
                    @saved="closeEditor"
                />
            </DialogScrollContent>
        </Dialog>
    </main>
</template>
