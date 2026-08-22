<script setup lang="ts">
import { Head, Link, router, useForm } from "@inertiajs/vue3";

const props = defineProps<{
    lodge: { id: number; name: string };
    event: { id: number; title: string };
    occurrence: { id: number };
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
    );
</script>

<template>
    <Head :title="`Volunteer roster — ${event.title}`" />
    <main class="mx-auto max-w-4xl space-y-6 p-6">
        <Link
            :href="`/lodges/${lodge.id}/events/${event.id}/occurrences/${occurrence.id}/reservations`"
            class="text-sm underline"
            >Reservation roster</Link
        >
        <h1 class="text-2xl font-semibold">
            Volunteer roster — {{ event.title }}
        </h1>
        <form
            class="grid gap-3 rounded border p-4 md:grid-cols-3"
            @submit.prevent="add"
        >
            <label
                >Position<select
                    v-model="addForm.position_id"
                    required
                    class="mt-1 w-full rounded border bg-background p-2"
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
            ><label
                >Member<select
                    v-model="addForm.person_id"
                    required
                    class="mt-1 w-full rounded border bg-background p-2"
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
            <div class="flex items-end">
                <button
                    type="submit"
                    :disabled="addForm.processing"
                    class="rounded bg-primary px-3 py-2 text-primary-foreground"
                >
                    Add volunteer
                </button>
            </div>
        </form>
        <section
            v-for="position in positions"
            :key="position.id"
            class="rounded border p-4"
        >
            <h2 class="font-medium">
                {{ position.name }}
                <span v-if="!position.is_active" class="text-sm"
                    >(inactive)</span
                >
            </h2>
            <p class="text-sm">
                Needed {{ position.needed_count }} · Filled
                {{
                    position.commitments.filter(
                        (item) => item.status === "committed",
                    ).length
                }}
            </p>
            <ul class="mt-3 space-y-2">
                <li
                    v-for="commitment in position.commitments"
                    :key="commitment.id"
                    class="flex justify-between"
                >
                    <span
                        >{{ commitment.person?.display_name ?? "Unknown" }} —
                        {{ commitment.status }}</span
                    ><button
                        v-if="commitment.status === 'committed'"
                        class="underline"
                        @click="remove(commitment.id)"
                    >
                        Remove
                    </button>
                    <span
                        v-if="commitment.reminder"
                        class="ml-2 text-xs text-muted-foreground"
                        >Reminder: {{ commitment.reminder.status
                        }}<span v-if="commitment.reminder.last_error">
                            — {{ commitment.reminder.last_error }}</span
                        ></span
                    ><button
                        v-if="commitment.reminder?.status === 'failed'"
                        class="ml-2 underline"
                        @click="retryReminder(commitment.reminder.id)"
                    >
                        Retry reminder
                    </button>
                </li>
            </ul>
        </section>
    </main>
</template>
