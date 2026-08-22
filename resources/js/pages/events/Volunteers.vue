<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3";

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
        }>;
    }>;
}>();
const remove = (id: number) =>
    router.patch(
        `/lodges/${props.lodge.id}/events/${props.event.id}/occurrences/${props.occurrence.id}/volunteers/${id}/remove`,
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
                </li>
            </ul>
        </section>
    </main>
</template>
