<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import { Link } from "@inertiajs/vue3";

defineOptions({ layout: AppLayout });
defineProps<{
    lodge: { id: number; name: string };
    events: {
        data: Array<{
            id: number;
            title: string;
            slug: string;
            status: string;
            first_starts_at: string;
            category: { name: string } | null;
        }>;
    };
}>();
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
                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="`/lodges/${lodge.id}/events/${event.id}/edit`"
                                class="text-primary underline"
                                >Edit</Link
                            >
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
    </main>
</template>
