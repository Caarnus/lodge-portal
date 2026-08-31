<script lang="ts" setup>
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import {formatLocalTimestamp} from "@/utils/date";
import {Link} from "@inertiajs/vue3";
import {Search, X} from "lucide-vue-next";
import {computed, ref} from "vue";

defineOptions({layout: AppLayout});
const props = defineProps<{
    lodge: { id: number; name: string };
    event: { id: number; title: string };
    occurrence: { id: number; starts_at: string };
    reservations: { data: any[] };
}>();
const search = ref("");
const status = ref("");
const sort = ref<"name" | "party_size" | "status">("name");
const direction = ref<"asc" | "desc">("asc");
const rows = computed(() => {
    const term = search.value.trim().toLocaleLowerCase();
    return [...props.reservations.data]
        .filter((reservation) => {
            const matchesSearch =
                !term ||
                [reservation.name, reservation.email, reservation.phone].some(
                    (value) =>
                        String(value ?? "")
                            .toLocaleLowerCase()
                            .includes(term),
                );
            return (
                matchesSearch &&
                (!status.value || reservation.status === status.value)
            );
        })
        .sort((left, right) => {
            const a = String(left[sort.value] ?? "");
            const b = String(right[sort.value] ?? "");
            return (
                (sort.value === "party_size"
                    ? Number(a) - Number(b)
                    : a.localeCompare(b)) * (direction.value === "asc" ? 1 : -1)
            );
        });
});
const toggleSort = (column: typeof sort.value) => {
    if (sort.value === column)
        direction.value = direction.value === "asc" ? "desc" : "asc";
    else {
        sort.value = column;
        direction.value = "asc";
    }
};
const sortLabel = (column: typeof sort.value) =>
    sort.value === column ? (direction.value === "asc" ? " ↑" : " ↓") : "";
</script>

<template>
    <main class="mx-auto max-w-6xl space-y-5 p-4 md:p-6">
        <PageHeader
            :description="formatLocalTimestamp(occurrence.starts_at)"
            :title="`Reservations: ${event.title}`"
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
            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem_auto]">
                <label class="field-label">
                    <span class="sr-only">Search reservations</span>
                    <span class="relative"
                    ><Search
                        aria-hidden="true"
                        class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"/><input
                        v-model="search"
                        class="field-input pl-9"
                        placeholder="Search name, email, or phone"
                        type="search"
                    /></span>
                </label>
                <label class="field-label"
                ><span class="sr-only">Status</span
                ><select v-model="status" class="field-input">
                    <option value="">All statuses</option>
                    <option
                        v-for="value in [
                                ...new Set(
                                    reservations.data.map(
                                        (item) => item.status,
                                    ),
                                ),
                            ]"
                        :key="value"
                        :value="value"
                    >
                        {{ value }}
                    </option>
                </select></label
                >
                <button
                    v-if="search || status"
                    class="inline-flex items-center justify-center gap-1 rounded-md border border-border bg-card px-3 py-2 text-sm font-medium hover:bg-accent"
                    type="button"
                    @click="
                        search = '';
                        status = '';
                    "
                >
                    <X aria-hidden="true" class="size-4"/>
                    Clear
                </button>
            </div>
        </section>
        <div
            class="hidden overflow-hidden rounded-lg border border-border bg-card md:block"
        >
            <table class="w-full table-fixed text-left text-sm">
                <thead class="bg-muted/50">
                <tr>
                    <th class="w-[22%] px-4 py-3">
                        <button
                            class="font-medium hover:text-primary"
                            type="button"
                            @click="toggleSort('name')"
                        >
                            Name{{ sortLabel("name") }}
                        </button>
                    </th>
                    <th class="w-[25%] px-4 py-3 font-medium">Email</th>
                    <th class="w-[18%] px-4 py-3 font-medium">Phone</th>
                    <th class="w-[15%] px-4 py-3">
                        <button
                            class="font-medium hover:text-primary"
                            type="button"
                            @click="toggleSort('party_size')"
                        >
                            People{{ sortLabel("party_size") }}
                        </button>
                    </th>
                    <th class="w-[20%] px-4 py-3">
                        <button
                            class="font-medium hover:text-primary"
                            type="button"
                            @click="toggleSort('status')"
                        >
                            Status{{ sortLabel("status") }}
                        </button>
                    </th>
                </tr>
                </thead>
                <tbody>
                <tr
                    v-for="reservation in rows"
                    :key="reservation.id"
                    class="border-t border-border"
                >
                    <td class="truncate px-4 py-3 font-medium">
                        {{ reservation.name }}
                    </td>
                    <td class="truncate px-4 py-3">
                        {{ reservation.email }}
                    </td>
                    <td class="px-4 py-3">{{ reservation.phone }}</td>
                    <td class="px-4 py-3">{{ reservation.party_size }}</td>
                    <td class="px-4 py-3 capitalize">
                        {{ reservation.status }}
                    </td>
                </tr>
                <tr v-if="!rows.length">
                    <td
                        class="px-4 py-8 text-center text-muted-foreground"
                        colspan="5"
                    >
                        No reservations match these filters.
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
        <div class="space-y-3 md:hidden">
            <article
                v-for="reservation in rows"
                :key="reservation.id"
                class="rounded-lg border border-border bg-card p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-medium">{{ reservation.name }}</h2>
                    <span class="text-sm capitalize text-muted-foreground">{{
                            reservation.status
                        }}</span>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <div class="col-span-2">
                        <dt class="text-muted-foreground">Email</dt>
                        <dd class="break-all">{{ reservation.email }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Phone</dt>
                        <dd>{{ reservation.phone }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">People</dt>
                        <dd>{{ reservation.party_size }}</dd>
                    </div>
                </dl>
            </article>
            <p
                v-if="!rows.length"
                class="rounded-lg border border-border bg-card p-6 text-center text-sm text-muted-foreground"
            >
                No reservations match these filters.
            </p>
        </div>
    </main>
</template>
