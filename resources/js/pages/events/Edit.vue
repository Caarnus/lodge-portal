<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import RecurrenceBuilder from "@/components/events/RecurrenceBuilder.vue";
import InputError from "@/components/InputError.vue";
import RichTextField from "@/components/website/RichTextField.vue";
import { normalizeSlug } from "@/utils/slug";
import {
    Dialog,
    DialogScrollContent,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Link, router, useForm } from "@inertiajs/vue3";
import { CircleOff, Pencil } from "lucide-vue-next";
import { computed, ref, watch } from "vue";

defineOptions({ layout: AppLayout });
const props = defineProps<{
    lodge: { id: number; name: string; timezone: string };
    event: any;
    categories: Array<{ id: number; name: string }>;
    media: Array<{ id: number; original_name: string }>;
    reservationFields: any[];
    reminderRules: Array<{ id: number; offset_minutes: number }>;
    reminderSubscriptionCount: number;
    volunteerPositions: Array<{
        id: number;
        event_occurrence_id: number | null;
        name: string;
        description: string | null;
        needed_count: number;
        sort_order: number;
        is_active: boolean;
    }>;
    occurrences: Array<{ id: number; starts_at: string; status: string }>;
    embedded?: boolean;
}>();
const emit = defineEmits<{ saved: [] }>();
const isNew = computed(() => !props.event.id);
const scheduleChanged = computed(() => {
    if (isNew.value) return false;

    return (
        new Date(form.first_starts_at).getTime() !==
            new Date(props.event.first_starts_at).getTime() ||
        Number(form.duration_minutes) !==
            Number(props.event.duration_minutes) ||
        form.time_zone !== props.event.time_zone ||
        form.rrule !== (props.event.rrule ?? "")
    );
});
const requiresScheduleConfirmation = computed(
    () =>
        !isNew.value &&
        props.occurrences.filter(
            (occurrence) => occurrence.status === "scheduled",
        ).length > 1 &&
        scheduleChanged.value,
);
const form = useForm({
    title: props.event.title ?? "",
    slug: props.event.slug ?? "",
    description: props.event.description ?? "",
    location_name: props.event.location_name ?? "",
    location_details: props.event.location_details ?? "",
    contact_name: props.event.contact_name ?? "",
    contact_email: props.event.contact_email ?? "",
    contact_phone: props.event.contact_phone ?? "",
    time_zone: props.event.time_zone ?? props.lodge.timezone,
    first_starts_at: props.event.first_starts_at
        ? new Date(props.event.first_starts_at).toISOString().slice(0, 16)
        : "",
    duration_minutes: props.event.duration_minutes ?? 120,
    rrule: props.event.rrule ?? "",
    event_category_id: props.event.event_category_id ?? "",
    visibility: props.event.visibility ?? "public",
    required_qualification: props.event.required_qualification ?? "",
    allows_cross_lodge_reservations: Boolean(
        props.event.allows_cross_lodge_reservations,
    ),
    reservations_enabled: Boolean(props.event.reservations_enabled),
    guest_reservations_enabled: Boolean(props.event.guest_reservations_enabled),
    capacity: props.event.capacity ?? "",
    maximum_party_size: props.event.maximum_party_size ?? "",
    reminders_enabled: props.event.reminders_enabled ?? true,
    guest_reminders_enabled: props.event.guest_reminders_enabled ?? true,
    cover_media_asset_id: props.event.cover_media_asset_id ?? "",
    confirm_schedule_change: false,
});
const canAllowGuestReservations = computed(
    () => form.reservations_enabled && form.visibility === "public",
);
const canAllowCrossLodgeReservations = computed(
    () => form.reservations_enabled && form.visibility === "masons",
);
watch(
    () => form.visibility,
    (visibility) => {
        if (visibility !== "public") {
            form.guest_reservations_enabled = false;
        }
        if (visibility !== "masons") {
            form.allows_cross_lodge_reservations = false;
        }
    },
);
watch(
    () => form.reservations_enabled,
    (enabled) => {
        if (enabled) return;

        form.guest_reservations_enabled = false;
        form.allows_cross_lodge_reservations = false;
        form.capacity = "";
        form.maximum_party_size = "";
    },
);
const submit = () => {
    const options = { onSuccess: () => emit("saved") };

    if (isNew.value) {
        form.post(`/lodges/${props.lodge.id}/events`, options);
    } else {
        form.put(`/lodges/${props.lodge.id}/events/${props.event.id}`, options);
    }
};
const transition = (action: "publish" | "cancel" | "archive") =>
    router.post(`/lodges/${props.lodge.id}/events/${props.event.id}/${action}`);
const addReminderRule = () => {
    const offset = window.prompt("Minutes before event");
    if (offset)
        router.post(
            `/lodges/${props.lodge.id}/events/${props.event.id}/reminder-rules`,
            { offset_minutes: offset },
        );
};
const removeReminderRule = (rule: { id: number }) =>
    router.delete(
        `/lodges/${props.lodge.id}/events/${props.event.id}/reminder-rules/${rule.id}`,
    );
const volunteerForm = useForm({
    event_occurrence_id: "" as string | number,
    name: "",
    description: "",
    needed_count: 1,
    sort_order: 0,
    is_active: true,
});
const volunteerModalOpen = ref(false);
const editingVolunteerPosition = ref<number | null>(null);
const openVolunteerModal = (
    position?: (typeof props.volunteerPositions)[number],
) => {
    editingVolunteerPosition.value = position?.id ?? null;
    volunteerForm.reset();
    volunteerForm.defaults({
        event_occurrence_id: position?.event_occurrence_id ?? "",
        name: position?.name ?? "",
        description: position?.description ?? "",
        needed_count: position?.needed_count ?? 1,
        sort_order: position?.sort_order ?? 0,
        is_active: position?.is_active ?? true,
    });
    volunteerForm.reset();
    volunteerModalOpen.value = true;
};
const addVolunteerPosition = () =>
    editingVolunteerPosition.value
        ? volunteerForm.put(
              `/lodges/${props.lodge.id}/events/${props.event.id}/volunteer-positions/${editingVolunteerPosition.value}`,
              {
                  preserveScroll: true,
                  onSuccess: () => (volunteerModalOpen.value = false),
              },
          )
        : volunteerForm.post(
              `/lodges/${props.lodge.id}/events/${props.event.id}/volunteer-positions`,
              {
                  preserveScroll: true,
                  onSuccess: () => (volunteerModalOpen.value = false),
              },
          );
const deactivateVolunteerPosition = (id: number) =>
    router.patch(
        `/lodges/${props.lodge.id}/events/${props.event.id}/volunteer-positions/${id}/deactivate`,
        {},
        { preserveScroll: true },
    );
</script>

<template>
    <main
        :class="
            embedded
                ? 'w-full min-w-0 space-y-5'
                : 'mx-auto w-full min-w-0 max-w-5xl space-y-5 p-4 md:p-6'
        "
    >
        <PageHeader
            v-if="!embedded"
            :title="isNew ? 'Create event' : 'Edit event'"
            :description="
                !isNew
                    ? `Status: ${event.status}`
                    : 'Add the details, schedule, and attendance options for this event.'
            "
        >
            <template #actions>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="`/lodges/${lodge.id}/events`"
                        class="inline-flex items-center rounded-md border border-border bg-card px-3 py-2 text-sm font-medium hover:bg-accent"
                        >All events</Link
                    >
                    <Link
                        v-if="!isNew"
                        :href="`/lodges/${lodge.id}/events/${event.id}/occurrences`"
                        class="inline-flex items-center rounded-md border border-border bg-card px-4 py-2 text-sm font-medium hover:bg-accent"
                        >Occurrences
                    </Link>
                    <button
                        v-if="!isNew && event.status === 'draft'"
                        type="button"
                        class="primary-button"
                        @click="transition('publish')"
                    >
                        Publish event
                    </button>
                    <button
                        v-if="!isNew && event.status === 'published'"
                        type="button"
                        class="inline-flex cursor-pointer items-center justify-center rounded-md border border-destructive/50 bg-card px-4 py-2 text-sm font-medium text-destructive hover:bg-destructive/10"
                        @click="transition('cancel')"
                    >
                        Cancel event
                    </button>
                    <button
                        v-if="!isNew && event.status === 'cancelled'"
                        type="button"
                        class="inline-flex cursor-pointer items-center justify-center rounded-md border border-border bg-card px-4 py-2 text-sm font-medium hover:bg-accent"
                        @click="transition('archive')"
                    >
                        Archive event
                    </button>
                </div>
            </template>
        </PageHeader>
        <form class="w-full min-w-0 space-y-6" @submit.prevent="submit">
            <div
                v-if="Object.keys(form.errors).length"
                class="rounded-md border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive"
            >
                <p class="font-medium">
                    Please correct the highlighted event details.
                </p>
                <ul class="mt-2 list-disc pl-5">
                    <li v-for="(message, field) in form.errors" :key="field">
                        {{ message }}
                    </li>
                </ul>
            </div>
            <section
                class="grid gap-4 rounded-lg border border-border bg-card p-4 md:grid-cols-2 md:p-5"
            >
                <h2 class="md:col-span-2 text-lg font-medium">Event details</h2>
                <label class="field-label md:col-span-2"
                    >Title<input
                        v-model="form.title"
                        class="field-input"
                        required
                    />
                    <InputError :message="form.errors.title" />
                </label>
                <label class="field-label"
                    >Slug<input
                        v-model="form.slug"
                        @input="form.slug = normalizeSlug(form.slug)"
                        class="field-input"
                        required
                    />
                    <InputError :message="form.errors.slug" />
                </label>
                <label class="field-label"
                    >Category<select
                        v-model="form.event_category_id"
                        class="field-input"
                    >
                        <option value="">Uncategorized</option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.event_category_id" />
                </label>
                <RichTextField
                    v-model="form.description"
                    class="min-w-0 md:col-span-2"
                />
                <div class="md:col-span-2 border-t border-border pt-4">
                    <h3 class="text-sm font-medium">Location</h3>
                </div>
                <label class="field-label"
                    >Venue or building<input
                        v-model="form.location_name"
                        class="field-input"
                    />
                </label>
                <label class="field-label"
                    >Room, street address, or directions<input
                        v-model="form.location_details"
                        class="field-input"
                    />
                </label>
                <div class="md:col-span-2 border-t border-border pt-4">
                    <h3 class="text-sm font-medium">Event contact</h3>
                </div>
                <label class="field-label"
                    >Contact name<input
                        v-model="form.contact_name"
                        class="field-input"
                    />
                </label>
                <label class="field-label"
                    >Contact email<input
                        v-model="form.contact_email"
                        type="email"
                        class="field-input"
                    />
                </label>
                <label class="field-label"
                    >Contact phone<input
                        v-model="form.contact_phone"
                        type="tel"
                        class="field-input"
                    />
                </label>
            </section>
            <section
                class="grid gap-4 rounded-lg border border-border bg-card p-4 md:grid-cols-2 md:p-5"
            >
                <h2 class="md:col-span-2 text-lg font-medium">Schedule</h2>
                <label class="field-label"
                    >Starts<input
                        v-model="form.first_starts_at"
                        type="datetime-local"
                        class="field-input"
                        required
                    />
                    <InputError
                        :message="form.errors.first_starts_at"
                    /> </label
                ><label class="field-label"
                    >Time zone<input
                        v-model="form.time_zone"
                        class="field-input"
                        required
                    />
                    <InputError :message="form.errors.time_zone" /> </label
                ><label class="field-label"
                    >Duration (minutes)<input
                        v-model.number="form.duration_minutes"
                        type="number"
                        min="1"
                        class="field-input"
                        required
                    />
                    <InputError :message="form.errors.duration_minutes" />
                </label>
                <div class="md:col-span-2">
                    <RecurrenceBuilder
                        v-model="form.rrule"
                        :starts-at="form.first_starts_at"
                    />
                    <InputError :message="form.errors.rrule" />
                </div>
            </section>
            <section
                class="space-y-4 rounded-lg border border-border bg-card p-4 md:p-5"
            >
                <h2 class="text-lg font-medium">Audience and reservations</h2>
                <div class="grid gap-4 md:grid-cols-3">
                    <label class="field-label"
                        >Visibility<select
                            v-model="form.visibility"
                            class="field-input"
                        >
                            <option value="public">Public</option>
                            <option value="masons">Masons only</option>
                            <option value="lodge">Lodge only</option>
                        </select></label
                    >
                    <label
                        v-if="form.visibility !== 'public'"
                        class="field-label"
                        >Minimum degree<select
                            v-model="form.required_qualification"
                            class="field-input"
                        >
                            <option value="ea">Entered Apprentice</option>
                            <option value="fc">Fellow Craft</option>
                            <option value="mm">Master Mason</option>
                            <option value="pm">Past Master</option>
                        </select></label
                    >
                    <div v-else aria-hidden="true"></div>
                    <label class="field-toggle">
                        <input
                            v-model="form.reservations_enabled"
                            type="checkbox"
                        />
                        Enable reservations
                    </label>
                    <label v-if="form.reservations_enabled" class="field-label"
                        >Capacity<input
                            v-model.number="form.capacity"
                            type="number"
                            min="1"
                            class="field-input"
                        />
                    </label>
                    <div v-else aria-hidden="true"></div>
                    <label v-if="form.reservations_enabled" class="field-label"
                        >Maximum party size<input
                            v-model.number="form.maximum_party_size"
                            type="number"
                            min="1"
                            class="field-input"
                        />
                    </label>
                    <div v-else aria-hidden="true"></div>
                    <label
                        v-if="canAllowCrossLodgeReservations"
                        class="field-toggle"
                    >
                        <input
                            v-model="form.allows_cross_lodge_reservations"
                            type="checkbox"
                        />
                        Allow cross-lodge reservations
                    </label>
                    <label
                        v-else-if="canAllowGuestReservations"
                        class="field-toggle"
                    >
                        <input
                            v-model="form.guest_reservations_enabled"
                            type="checkbox"
                        />
                        Allow guest reservations
                    </label>
                    <div v-else aria-hidden="true"></div>
                </div>
            </section>
            <section
                class="space-y-3 rounded-lg border border-border bg-card p-4 md:p-5"
            >
                <h2 class="text-lg font-medium">Reminders</h2>
                <p v-if="!isNew" class="text-sm text-muted-foreground">
                    Active subscriptions: {{ reminderSubscriptionCount }}
                </p>
                <label class="flex items-center gap-2 text-sm font-medium"
                    ><input v-model="form.reminders_enabled" type="checkbox" />
                    Enable reminder subscriptions</label
                ><label class="flex items-center gap-2 text-sm font-medium"
                    ><input
                        v-model="form.guest_reminders_enabled"
                        type="checkbox"
                    />
                    Allow guest reminder subscriptions</label
                >
                <div v-if="!isNew" class="border-t pt-3">
                    <p class="text-sm font-medium">Reminder times</p>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li
                            v-for="rule in reminderRules"
                            :key="rule.id"
                            class="flex gap-3"
                        >
                            {{ rule.offset_minutes }}
                            minutes before
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md border border-border bg-card px-2 py-1 text-destructive hover:bg-accent"
                                @click="removeReminderRule(rule)"
                            >
                                Remove
                            </button>
                        </li>
                    </ul>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-border bg-card px-3 py-2 text-sm font-medium hover:bg-accent"
                        @click="addReminderRule"
                    >
                        Add reminder time
                    </button>
                </div>
            </section>
            <section
                v-if="!isNew"
                class="space-y-4 rounded-lg border border-border bg-card p-4 md:p-5"
            >
                <h2 class="text-lg font-medium">Volunteer staffing</h2>
                <p class="text-sm text-muted-foreground">
                    Named staffing needs are separate from attendance
                    reservations and reminder subscriptions.
                </p>
                <button
                    type="button"
                    class="primary-button"
                    @click="openVolunteerModal()"
                >
                    Add staffing position
                </button>
                <div
                    v-if="volunteerPositions.length"
                    class="hidden overflow-hidden rounded-md border border-border md:block"
                >
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="px-3 py-2">Position</th>
                                <th class="px-3 py-2">Scope</th>
                                <th class="px-3 py-2">Needed</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="position in volunteerPositions"
                                :key="position.id"
                                class="border-t"
                            >
                                <td class="px-3 py-2">
                                    <strong>{{ position.name }}</strong
                                    ><span
                                        v-if="!position.is_active"
                                        class="ml-2 text-muted-foreground"
                                        >Inactive</span
                                    >
                                </td>
                                <td class="px-3 py-2">
                                    {{
                                        position.event_occurrence_id
                                            ? "Occurrence-only"
                                            : "Every occurrence"
                                    }}
                                </td>
                                <td class="px-3 py-2">
                                    {{ position.needed_count }}
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            title="Edit position"
                                            aria-label="Edit position"
                                            class="icon-button"
                                            @click="
                                                openVolunteerModal(position)
                                            "
                                        >
                                            <Pencil
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </button>
                                        <button
                                            v-if="position.is_active"
                                            type="button"
                                            title="Deactivate position"
                                            aria-label="Deactivate position"
                                            class="icon-button text-destructive"
                                            @click="
                                                deactivateVolunteerPosition(
                                                    position.id,
                                                )
                                            "
                                        >
                                            <CircleOff
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
                <div
                    v-if="volunteerPositions.length"
                    class="space-y-3 md:hidden"
                >
                    <article
                        v-for="position in volunteerPositions"
                        :key="position.id"
                        class="rounded-md border border-border bg-background p-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-medium">{{ position.name }}</h3>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{
                                        position.event_occurrence_id
                                            ? "Occurrence-only"
                                            : "Every occurrence"
                                    }}
                                    · Needed {{ position.needed_count }}
                                </p>
                            </div>
                            <span
                                v-if="!position.is_active"
                                class="text-xs text-muted-foreground"
                            >
                                Inactive
                            </span>
                        </div>
                        <div class="mt-3 flex min-h-9 justify-end gap-2">
                            <button
                                type="button"
                                title="Edit position"
                                aria-label="Edit position"
                                class="icon-button"
                                @click="openVolunteerModal(position)"
                            >
                                <Pencil class="size-4" aria-hidden="true" />
                            </button>
                            <button
                                v-if="position.is_active"
                                type="button"
                                title="Deactivate position"
                                aria-label="Deactivate position"
                                class="icon-button text-destructive"
                                @click="
                                    deactivateVolunteerPosition(position.id)
                                "
                            >
                                <CircleOff class="size-4" aria-hidden="true" />
                            </button>
                        </div>
                    </article>
                </div>
                <Dialog
                    :open="volunteerModalOpen"
                    @update:open="volunteerModalOpen = $event"
                >
                    <DialogScrollContent class="max-w-xl"
                        ><DialogHeader
                            ><DialogTitle>{{
                                editingVolunteerPosition
                                    ? "Edit staffing position"
                                    : "Add staffing position"
                            }}</DialogTitle></DialogHeader
                        >
                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="field-label"
                                >Position name<input
                                    v-model="volunteerForm.name"
                                    maxlength="120"
                                    class="field-input"
                                />
                                <InputError
                                    :message="volunteerForm.errors.name"
                                /> </label
                            ><label class="field-label"
                                >Scope<select
                                    v-model="volunteerForm.event_occurrence_id"
                                    class="field-input"
                                >
                                    <option value="">
                                        Every occurrence in series
                                    </option>
                                    <option
                                        v-for="occurrence in occurrences.filter(
                                            (item) =>
                                                item.status === 'scheduled',
                                        )"
                                        :key="occurrence.id"
                                        :value="occurrence.id"
                                    >
                                        This occurrence:
                                        {{
                                            new Date(
                                                occurrence.starts_at,
                                            ).toLocaleString()
                                        }}
                                    </option>
                                </select></label
                            ><label class="field-label md:col-span-2"
                                >Description / instructions<textarea
                                    v-model="volunteerForm.description"
                                    maxlength="2000"
                                    class="field-input min-h-20"
                                />
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="field-label"
                                    >Needed<input
                                        v-model.number="
                                            volunteerForm.needed_count
                                        "
                                        type="number"
                                        min="1"
                                        class="field-input" /></label
                                ><label class="field-label"
                                    >Order<input
                                        v-model.number="
                                            volunteerForm.sort_order
                                        "
                                        type="number"
                                        min="0"
                                        class="field-input"
                                /></label>
                            </div>
                            <div class="md:col-span-2">
                                <button
                                    type="button"
                                    :disabled="volunteerForm.processing"
                                    class="primary-button"
                                    @click="addVolunteerPosition"
                                >
                                    {{
                                        editingVolunteerPosition
                                            ? "Save staffing position"
                                            : "Add staffing position"
                                    }}
                                </button>
                            </div>
                        </div>
                    </DialogScrollContent>
                </Dialog>
            </section>
            <div
                class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end"
            >
                <label
                    v-if="requiresScheduleConfirmation"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        v-model="form.confirm_schedule_change"
                        type="checkbox"
                    />
                    I understand this changes future occurrences.
                </label>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="primary-button"
                >
                    Save event
                </button>
            </div>
        </form>
    </main>
</template>
