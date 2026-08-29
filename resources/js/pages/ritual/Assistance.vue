<script setup lang="ts">
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from "@/components/ui/dialog";
import AppLayout from "@/layouts/AppLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";

const props = defineProps<{
    requestingLodge: { id: number; name: string; number: string };
    filters: Record<string, any>;
    results: {
        data: any[];
        current_page: number;
        last_page: number;
        total: number;
    } | null;
    searched: boolean;
    categories: any[];
    lodges: any[];
}>();
const form = reactive({
    audience: props.filters.audience ?? "own_lodge",
    category: props.filters.category ?? "",
    part: props.filters.part ?? "",
    lodge: props.filters.lodge ?? "",
    day_of_week: props.filters.day_of_week ?? "",
    daypart: props.filters.daypart ?? "",
    query: props.filters.query ?? "",
});
const sort = ref(props.filters.sort ?? "name");
const direction = ref(props.filters.direction ?? "asc");
const selectedPerson = ref<any | null>(null);
const parts = computed(() =>
    props.categories.flatMap((category) =>
        category.parts.map((part: any) => ({
            ...part,
            category_name: category.name,
        })),
    ),
);
const search = (page = 1) =>
    router.get(
        `/lodges/${props.requestingLodge.id}/ritual-assistance`,
        {
            ...form,
            searched: 1,
            sort: sort.value,
            direction: direction.value,
            page,
        },
        { preserveState: true, preserveScroll: true },
    );
const reset = () => {
    Object.assign(form, {
        audience: "own_lodge",
        category: "",
        part: "",
        lodge: "",
        day_of_week: "",
        daypart: "",
        query: "",
    });
    sort.value = "name";
    direction.value = "asc";
    router.get(
        `/lodges/${props.requestingLodge.id}/ritual-assistance`,
        {},
        { preserveScroll: true },
    );
};
const sortBy = (column: string) => {
    direction.value =
        sort.value === column && direction.value === "asc" ? "desc" : "asc";
    sort.value = column;
    search();
};
const sortLabel = (column: string) =>
    sort.value === column
        ? direction.value === "asc"
            ? "ascending"
            : "descending"
        : "none";
const dayName = (day: number) =>
    [
        "Monday",
        "Tuesday",
        "Wednesday",
        "Thursday",
        "Friday",
        "Saturday",
        "Sunday",
    ][day - 1];
const availability = (person: any) =>
    person.availability
        .map((item: any) => `${dayName(item.day_of_week)} ${item.daypart}`)
        .join(", ");
</script>

<template>
    <Head title="Ritual Assistance" />
    <AppLayout
        :breadcrumbs="[
            {
                title: requestingLodge.name,
                href: `/lodges/${requestingLodge.id}/ritual-assistance`,
            },
            {
                title: 'Ritual Assistance',
                href: `/lodges/${requestingLodge.id}/ritual-assistance`,
            },
        ]"
    >
        <main class="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
            <header>
                <h1 class="text-2xl font-bold">Ritual Assistance</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Proficiency and availability are self-reported. A listed
                    member has not accepted an assignment; contact him
                    separately.
                </p>
            </header>
            <form
                class="grid gap-3 rounded-lg bg-muted/20 p-4 md:grid-cols-4"
                @submit.prevent="search()"
            >
                <label class="text-sm"
                    >Audience<select
                        v-model="form.audience"
                        class="mt-1 w-full rounded border bg-background p-2"
                    >
                        <option value="own_lodge">Own lodge</option>
                        <option value="participating_lodges">
                            Participating lodges
                        </option>
                    </select></label
                >
                <label class="text-sm"
                    >Category<select
                        v-model="form.category"
                        class="mt-1 w-full rounded border bg-background p-2"
                    >
                        <option value="">All active categories</option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select></label
                >
                <label class="text-sm"
                    >Part<select
                        v-model="form.part"
                        class="mt-1 w-full rounded border bg-background p-2"
                    >
                        <option value="">All active parts</option>
                        <option
                            v-for="part in parts"
                            :key="part.id"
                            :value="part.id"
                        >
                            {{ part.category_name }} — {{ part.name }}
                        </option>
                    </select></label
                >
                <label class="text-sm"
                    >Lodge affiliation<select
                        v-model="form.lodge"
                        class="mt-1 w-full rounded border bg-background p-2"
                    >
                        <option value="">Any active lodge</option>
                        <option
                            v-for="item in lodges"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }} · {{ item.number }}
                        </option>
                    </select></label
                >
                <label class="text-sm"
                    >Weekday<select
                        v-model="form.day_of_week"
                        class="mt-1 w-full rounded border bg-background p-2"
                    >
                        <option value="">Any weekday</option>
                        <option
                            v-for="(day, index) in [
                                'Monday',
                                'Tuesday',
                                'Wednesday',
                                'Thursday',
                                'Friday',
                                'Saturday',
                                'Sunday',
                            ]"
                            :key="day"
                            :value="index + 1"
                        >
                            {{ day }}
                        </option>
                    </select></label
                >
                <label class="text-sm"
                    >Daypart<select
                        v-model="form.daypart"
                        class="mt-1 w-full rounded border bg-background p-2"
                    >
                        <option value="">Any daypart</option>
                        <option value="morning">Morning</option>
                        <option value="afternoon">Afternoon</option>
                        <option value="evening">Evening</option>
                    </select></label
                >
                <label class="text-sm md:col-span-2"
                    >Name<input
                        v-model="form.query"
                        class="mt-1 w-full rounded border bg-background p-2"
                        maxlength="120"
                        placeholder="Display name"
                /></label>
                <div class="flex gap-2 md:col-span-4">
                    <button
                        class="rounded bg-primary px-4 py-2 text-primary-foreground"
                    >
                        Search</button
                    ><button
                        type="button"
                        class="rounded border px-4 py-2"
                        @click="reset"
                    >
                        Clear
                    </button>
                </div>
            </form>
            <p v-if="!searched" class="text-sm text-muted-foreground">
                Choose filters, then select Search.
            </p>
            <p
                v-else-if="!results?.total"
                class="rounded-lg bg-muted/20 p-5 text-sm text-muted-foreground"
            >
                No matching members. Try broader filters; hidden and unavailable
                profiles are not counted.
            </p>
            <section v-else class="overflow-x-auto rounded-lg bg-muted/20 p-4">
                <table class="w-full min-w-[800px] text-left text-sm">
                    <thead
                        class="border-b text-xs uppercase tracking-wide text-muted-foreground"
                    >
                        <tr>
                            <th class="p-3" :aria-sort="sortLabel('name')">
                                <button
                                    type="button"
                                    class="font-semibold hover:underline"
                                    @click="sortBy('name')"
                                >
                                    Member
                                    <span aria-hidden="true">{{
                                        sort === "name"
                                            ? direction === "asc"
                                                ? "↑"
                                                : "↓"
                                            : ""
                                    }}</span>
                                </button>
                            </th>
                            <th
                                class="p-3"
                                :aria-sort="sortLabel('affiliations')"
                            >
                                <button
                                    type="button"
                                    class="font-semibold hover:underline"
                                    @click="sortBy('affiliations')"
                                >
                                    Affiliations
                                    <span aria-hidden="true">{{
                                        sort === "affiliations"
                                            ? direction === "asc"
                                                ? "↑"
                                                : "↓"
                                            : ""
                                    }}</span>
                                </button>
                            </th>
                            <th
                                class="p-3 text-center"
                                :aria-sort="sortLabel('roles')"
                            >
                                <button
                                    type="button"
                                    class="font-semibold hover:underline"
                                    @click="sortBy('roles')"
                                >
                                    Roles
                                    <span aria-hidden="true">{{
                                        sort === "roles"
                                            ? direction === "asc"
                                                ? "↑"
                                                : "↓"
                                            : ""
                                    }}</span>
                                </button>
                            </th>
                            <th
                                class="p-3 text-center"
                                :aria-sort="sortLabel('availability')"
                            >
                                <button
                                    type="button"
                                    class="font-semibold hover:underline"
                                    @click="sortBy('availability')"
                                >
                                    Availability
                                    <span aria-hidden="true">{{
                                        sort === "availability"
                                            ? direction === "asc"
                                                ? "↑"
                                                : "↓"
                                            : ""
                                    }}</span>
                                </button>
                            </th>
                            <th class="p-3">Contact</th>
                            <th class="p-3">
                                <span class="sr-only">Details</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="person in results?.data"
                            :key="person.id"
                            class="border-b border-border/50 last:border-0"
                        >
                            <td class="p-3 font-medium">
                                {{ person.display_name }}
                            </td>
                            <td class="p-3 text-muted-foreground">
                                {{
                                    person.affiliations
                                        .map(
                                            (item: any) =>
                                                `${item.name} · ${item.number}`,
                                        )
                                        .join(" • ")
                                }}
                            </td>
                            <td class="p-3 text-center">
                                {{ person.parts.length }}
                            </td>
                            <td class="p-3 text-center">
                                {{ person.availability.length }}
                            </td>
                            <td class="p-3">
                                <a
                                    v-if="person.email"
                                    :href="`mailto:${person.email}`"
                                    class="block underline"
                                    >{{ person.email }}</a
                                ><a
                                    v-if="person.phone"
                                    :href="`tel:${person.phone}`"
                                    class="block underline"
                                    >{{ person.phone }}</a
                                ><span
                                    v-if="!person.email && !person.phone"
                                    class="text-muted-foreground"
                                    >Not shared</span
                                >
                            </td>
                            <td class="p-3 text-right">
                                <button
                                    type="button"
                                    class="rounded border px-3 py-2"
                                    :aria-label="`View ${person.display_name} ritual roles and availability`"
                                    @click="selectedPerson = person"
                                >
                                    Details
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
            <nav
                v-if="results && results.last_page > 1"
                class="flex items-center justify-between"
            >
                <button
                    class="rounded border px-3 py-2"
                    :disabled="results.current_page === 1"
                    @click="search(results.current_page - 1)"
                >
                    Previous</button
                ><span class="text-sm"
                    >Page {{ results.current_page }} of
                    {{ results.last_page }}</span
                ><button
                    class="rounded border px-3 py-2"
                    :disabled="results.current_page === results.last_page"
                    @click="search(results.current_page + 1)"
                >
                    Next
                </button>
            </nav>
        </main>
        <Dialog
            :open="selectedPerson !== null"
            @update:open="
                (open) => {
                    if (!open) selectedPerson = null;
                }
            "
            ><DialogScrollContent v-if="selectedPerson" class="max-w-2xl"
                ><DialogHeader
                    ><DialogTitle>{{ selectedPerson.display_name }}</DialogTitle
                    ><DialogDescription
                        >Self-reported ritual roles and broad availability. This
                        is not an assignment or commitment.</DialogDescription
                    ></DialogHeader
                >
                <section class="space-y-2">
                    <h2 class="font-semibold">Ritual roles</h2>
                    <ul class="space-y-2 text-sm">
                        <li v-for="part in selectedPerson.parts" :key="part.id">
                            <strong
                                >{{ part.category }} — {{ part.name }}</strong
                            ><span class="text-muted-foreground">
                                · Self-reported · Updated
                                {{
                                    new Date(
                                        part.updated_at,
                                    ).toLocaleDateString()
                                }}</span
                            >
                        </li>
                    </ul>
                </section>
                <section class="space-y-2">
                    <h2 class="font-semibold">Broad availability</h2>
                    <p class="text-sm">
                        {{
                            availability(selectedPerson) ||
                            "No availability shared."
                        }}
                    </p>
                    <p
                        v-if="selectedPerson.public_availability_note"
                        class="text-sm text-muted-foreground"
                    >
                        {{ selectedPerson.public_availability_note }}
                    </p>
                </section></DialogScrollContent
            ></Dialog
        >
    </AppLayout>
</template>
