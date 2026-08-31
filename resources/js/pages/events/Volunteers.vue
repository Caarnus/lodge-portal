<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import { formatLocalTimestamp } from "@/utils/date";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { Search, X } from "lucide-vue-next";
import { computed, ref } from "vue";

defineOptions({ layout: AppLayout });
const props = defineProps<{
    lodge: { id: number; name: string };
    event: { id: number; title: string };
    occurrence: { id: number; starts_at: string };
    positions: Array<{
        id: number;
        name: string;
        needed_count: number;
        is_active: boolean;
        commitments: Array<{
            id: number;
            status: string;
            person?: { display_name: string };
            reminder?: {
                id: number;
                status: string;
                last_error: string | null;
            } | null;
        }>;
    }>;
    members: Array<{ id: number; display_name: string }>;
}>();
const remove = (id: number) =>
    router.patch(
        `/lodges/${props.lodge.id}/events/${props.event.id}/occurrences/${props.occurrence.id}/volunteers/${id}/remove`,
        {},
        { preserveScroll: true },
    );
const addForm = useForm({ position_id: "", person_id: "" });
const add = () =>
    addForm.post(
        `/lodges/${props.lodge.id}/events/${props.event.id}/occurrences/${props.occurrence.id}/volunteers`,
        { preserveScroll: true, onSuccess: () => addForm.reset() },
    );
const retryReminder = (id: number) =>
    router.post(
        `/lodges/${props.lodge.id}/events/${props.event.id}/occurrences/${props.occurrence.id}/volunteer-reminders/${id}/retry`,
        {},
        { preserveScroll: true },
    );
const search = ref("");
const activeOnly = ref(false);
const displayedPositions = computed(() => {
    const term = search.value.trim().toLocaleLowerCase();
    return props.positions.filter(
        (position) =>
            (!activeOnly.value || position.is_active) &&
            (!term ||
                position.name.toLocaleLowerCase().includes(term) ||
                position.commitments.some((commitment) =>
                    commitment.person?.display_name
                        ?.toLocaleLowerCase()
                        .includes(term),
                )),
    );
});
</script>

<template>
    <Head :title="`Volunteer roster — ${event.title}`" />
    <main class="mx-auto max-w-6xl space-y-5 p-4 md:p-6">
        <PageHeader
            :title="`Volunteer roster — ${event.title}`"
            :description="formatLocalTimestamp(occurrence.starts_at)"
        >
            <template #actions>
                <Link
                    :href="`/lodges/${lodge.id}/events/${event.id}/occurrences`"
                    class="inline-flex items-center rounded-md border border-border bg-card px-3 py-2 text-sm font-medium hover:bg-accent"
                >
                    Back to occurrences
                </Link>
            </template>
        </PageHeader>

        <section class="rounded-lg border border-border bg-card p-4">
            <h2 class="text-lg font-medium">Add volunteer</h2>
            <form
                class="mt-3 grid gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"
                @submit.prevent="add"
            >
                <label class="field-label"
                    >Position<select
                        v-model="addForm.position_id"
                        required
                        class="field-input"
                    >
                        <option value="">Choose position</option>
                        <option
                            v-for="position in positions.filter(
                                (item) => item.is_active,
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
                        v-model="addForm.person_id"
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
                        :disabled="addForm.processing"
                        class="primary-button w-full md:w-auto"
                    >
                        Add volunteer
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-border bg-card p-4">
            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto]">
                <label class="field-label"
                    ><span class="sr-only">Search positions or volunteers</span
                    ><span class="relative"
                        ><Search
                            class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true" /><input
                            v-model="search"
                            type="search"
                            class="field-input pl-9"
                            placeholder="Search positions or volunteers" /></span
                ></label>
                <label class="flex items-center gap-2 text-sm font-medium"
                    ><input v-model="activeOnly" type="checkbox" /> Active
                    positions only</label
                >
                <button
                    v-if="search || activeOnly"
                    type="button"
                    class="inline-flex items-center justify-center gap-1 rounded-md border border-border bg-card px-3 py-2 text-sm font-medium hover:bg-accent"
                    @click="
                        search = '';
                        activeOnly = false;
                    "
                >
                    <X class="size-4" aria-hidden="true" /> Clear
                </button>
            </div>
        </section>

        <div
            class="hidden overflow-hidden rounded-lg border border-border bg-card md:block"
        >
            <table class="w-full table-fixed text-left text-sm">
                <thead class="bg-muted/50">
                    <tr>
                        <th class="w-[25%] px-4 py-3 font-medium">Position</th>
                        <th class="w-[15%] px-4 py-3 font-medium">Coverage</th>
                        <th class="w-[35%] px-4 py-3 font-medium">
                            Volunteers
                        </th>
                        <th class="w-[25%] px-4 py-3">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="position in displayedPositions"
                        :key="position.id"
                        class="border-t border-border align-top"
                    >
                        <td class="px-4 py-3 font-medium">
                            {{ position.name
                            }}<span
                                v-if="!position.is_active"
                                class="ml-2 text-xs font-normal text-muted-foreground"
                                >Inactive</span
                            >
                        </td>
                        <td class="px-4 py-3">
                            Needed {{ position.needed_count }}<br />Filled
                            {{
                                position.commitments.filter(
                                    (item) => item.status === "committed",
                                ).length
                            }}
                        </td>
                        <td class="px-4 py-3">
                            <p
                                v-if="!position.commitments.length"
                                class="text-muted-foreground"
                            >
                                No commitments.
                            </p>
                            <div
                                v-for="commitment in position.commitments"
                                :key="commitment.id"
                                class="mb-2 last:mb-0"
                            >
                                <span class="font-medium">{{
                                    commitment.person?.display_name ?? "Unknown"
                                }}</span>
                                —
                                <span class="capitalize">{{
                                    commitment.status
                                }}</span
                                ><span v-if="commitment.reminder">
                                    · Reminder:
                                    {{ commitment.reminder.status }}</span
                                ><span
                                    v-if="commitment.reminder?.last_error"
                                    class="text-destructive"
                                >
                                    — {{ commitment.reminder.last_error }}</span
                                >
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div
                                class="flex min-h-9 flex-wrap justify-end gap-2"
                            >
                                <template
                                    v-for="commitment in position.commitments"
                                    :key="commitment.id"
                                    ><button
                                        v-if="commitment.status === 'committed'"
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-border bg-card px-3 py-2 text-sm font-medium text-destructive hover:bg-accent"
                                        @click="remove(commitment.id)"
                                    >
                                        Remove
                                        {{
                                            commitment.person?.display_name ??
                                            "volunteer"
                                        }}</button
                                    ><button
                                        v-if="
                                            commitment.reminder?.status ===
                                            'failed'
                                        "
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-border bg-card px-3 py-2 text-sm font-medium hover:bg-accent"
                                        @click="
                                            retryReminder(
                                                commitment.reminder.id,
                                            )
                                        "
                                    >
                                        Retry reminder
                                    </button></template
                                >
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!displayedPositions.length">
                        <td
                            colspan="4"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            No staffing positions match these filters.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="space-y-3 md:hidden">
            <article
                v-for="position in displayedPositions"
                :key="position.id"
                class="rounded-lg border border-border bg-card p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-medium">{{ position.name }}</h2>
                    <span
                        v-if="!position.is_active"
                        class="text-xs text-muted-foreground"
                        >Inactive</span
                    >
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Needed {{ position.needed_count }} · Filled
                    {{
                        position.commitments.filter(
                            (item) => item.status === "committed",
                        ).length
                    }}
                </p>
                <p
                    v-if="!position.commitments.length"
                    class="mt-3 text-sm text-muted-foreground"
                >
                    No commitments.
                </p>
                <div
                    v-for="commitment in position.commitments"
                    :key="commitment.id"
                    class="mt-3 border-t border-border pt-3"
                >
                    <p class="text-sm">
                        <span class="font-medium">{{
                            commitment.person?.display_name ?? "Unknown"
                        }}</span>
                        — <span class="capitalize">{{ commitment.status }}</span
                        ><span v-if="commitment.reminder">
                            · Reminder: {{ commitment.reminder.status }}</span
                        ><span
                            v-if="commitment.reminder?.last_error"
                            class="text-destructive"
                        >
                            — {{ commitment.reminder.last_error }}</span
                        >
                    </p>
                    <div class="mt-3 flex min-h-9 justify-end gap-2">
                        <button
                            v-if="commitment.status === 'committed'"
                            type="button"
                            class="inline-flex items-center rounded-md border border-border bg-card px-3 py-2 text-sm font-medium text-destructive hover:bg-accent"
                            @click="remove(commitment.id)"
                        >
                            Remove</button
                        ><button
                            v-if="commitment.reminder?.status === 'failed'"
                            type="button"
                            class="inline-flex items-center rounded-md border border-border bg-card px-3 py-2 text-sm font-medium hover:bg-accent"
                            @click="retryReminder(commitment.reminder.id)"
                        >
                            Retry reminder
                        </button>
                    </div>
                </div>
            </article>
            <p
                v-if="!displayedPositions.length"
                class="rounded-lg border border-border bg-card p-6 text-center text-sm text-muted-foreground"
            >
                No staffing positions match these filters.
            </p>
        </div>
    </main>
</template>
