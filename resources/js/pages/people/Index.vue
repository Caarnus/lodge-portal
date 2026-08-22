<script setup lang="ts">
import PersonModal from "@/components/people/PersonModal.vue";
import AppLayout from "@/layouts/AppLayout.vue";
import { formatPhone } from "@/lib/phone";
import { Head, Link, router } from "@inertiajs/vue3";
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Eye,
    Link2,
    Link2Off,
    Pencil,
    Plus,
    X,
} from "lucide-vue-next";
import Tooltip from "primevue/tooltip";
import { nextTick, reactive, ref, watch } from "vue";

defineOptions({ layout: AppLayout });
const vTooltip = Tooltip;

interface Filters {
    search: string;
    status: number | null;
    degree: number | null;
    account: string;
    scope: string;
    sort: "name" | "membership" | "phone" | "email" | "location";
    direction: "asc" | "desc";
}

const props = defineProps<{
    lodge: { id: number; name: string };
    people: any[];
    filters: Filters;
    membershipTypes: any[];
    membershipStatuses: any[];
    degrees: any[];
    canManage: boolean;
    canManageMemberships: boolean;
}>();
const filters = reactive<Filters>({ ...props.filters });
const selectedPerson = ref<any | null>(null);
const modalOpen = ref(false);
const modalMode = ref<"view" | "edit">("view");
let searchTimer: ReturnType<typeof setTimeout> | undefined;
let suspendAutoApply = false;

const applyFilters = () =>
    router.get(
        `/lodges/${props.lodge.id}/people`,
        {
            search: filters.search || undefined,
            status: filters.status || undefined,
            degree: filters.degree || undefined,
            account: filters.account === "all" ? undefined : filters.account,
            scope: filters.scope === "all" ? undefined : filters.scope,
            sort: filters.sort === "name" ? undefined : filters.sort,
            direction:
                filters.direction === "asc" ? undefined : filters.direction,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );

watch(
    () => filters.search,
    () => {
        if (suspendAutoApply) return;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 450);
    },
);
watch(
    () => [filters.status, filters.degree, filters.account, filters.scope],
    () => {
        if (!suspendAutoApply) {
            clearTimeout(searchTimer);
            applyFilters();
        }
    },
);
watch(
    () => props.people,
    (people) => {
        if (selectedPerson.value)
            selectedPerson.value =
                people.find(
                    (person) => person.id === selectedPerson.value.id,
                ) ?? selectedPerson.value;
    },
);

const resetFilters = () => {
    suspendAutoApply = true;
    clearTimeout(searchTimer);
    Object.assign(filters, {
        search: "",
        status: null,
        degree: null,
        account: "all",
        scope: "all",
        sort: "name",
        direction: "asc",
    });
    nextTick(() => {
        suspendAutoApply = false;
        applyFilters();
    });
};
const sortBy = (sort: Filters["sort"]) => {
    filters.direction =
        filters.sort === sort && filters.direction === "asc" ? "desc" : "asc";
    filters.sort = sort;
    applyFilters();
};
const openPerson = (person: any, mode: "view" | "edit") => {
    selectedPerson.value = person;
    modalMode.value = mode;
    modalOpen.value = true;
};
const membership = (person: any) => person.memberships?.[0];
const location = (person: any) =>
    [person.mailing_city, person.mailing_state].filter(Boolean).join(", ");
const memberRelationships = (person: any) =>
    person.relationship_summaries?.filter(
        (relationship: any) => relationship.related_is_lodge_member,
    ) ?? [];
</script>

<template>
    <Head :title="`${lodge.name} people`" />
    <main class="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">People</h1>
                <p class="text-sm text-slate-600">{{ lodge.name }}</p>
            </div>
            <Link
                v-if="canManage"
                :href="`/lodges/${lodge.id}/people/create`"
                aria-label="Add person"
                class="inline-flex size-10 items-center justify-center rounded-md bg-slate-900 text-white"
                v-tooltip.left="{ value: 'Add person', showDelay: 2000 }"
                ><Plus class="size-5"
            /></Link>
        </div>

        <p class="mt-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
            This is the administrative People workspace. It can show full lodge-reachable records; member directory privacy controls presentation in Directory and does not erase lodge records. Only the member may change their privacy choices in Profile settings.
        </p>

        <form
            class="mt-6 grid gap-3 rounded-lg border bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(14rem,2fr)_repeat(3,minmax(8rem,1fr))_minmax(13rem,1.4fr)_auto]"
            @submit.prevent
        >
            <label class="min-w-0"
                ><span class="text-sm font-medium">Search</span
                ><input
                    v-model="filters.search"
                    class="mt-1 w-full rounded-md border bg-white px-3 py-2"
                    placeholder="Name, email, phone, city, or member no."
            /></label>
            <label
                ><span class="text-sm font-medium">Status</span
                ><select
                    v-model="filters.status"
                    class="mt-1 w-full rounded-md border bg-white p-2"
                >
                    <option :value="null">All statuses</option>
                    <option
                        v-for="item in membershipStatuses"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }}{{ item.is_active ? "" : " (inactive)" }}
                    </option>
                </select></label
            >
            <label
                ><span class="text-sm font-medium">Degree</span
                ><select
                    v-model="filters.degree"
                    class="mt-1 w-full rounded-md border bg-white p-2"
                >
                    <option :value="null">All degrees</option>
                    <option
                        v-for="item in degrees"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }}{{ item.is_active ? "" : " (inactive)" }}
                    </option>
                </select></label
            >
            <label
                ><span class="text-sm font-medium">Account</span
                ><select
                    v-model="filters.account"
                    class="mt-1 w-full rounded-md border bg-white p-2"
                >
                    <option value="all">Any account</option>
                    <option value="linked">Linked</option>
                    <option value="unlinked">Not linked</option>
                </select></label
            >
            <label
                ><span class="text-sm font-medium">Record type</span
                ><select
                    v-model="filters.scope"
                    class="mt-1 w-full rounded-md border bg-white p-2"
                >
                    <option value="all">Members and relatives</option>
                    <option value="members">Members only</option>
                    <option value="related">Related people only</option>
                </select></label
            >
            <button
                type="button"
                class="mt-6 inline-flex size-10 items-center justify-center rounded-md border bg-white"
                aria-label="Clear filters"
                v-tooltip.top="{ value: 'Clear filters', showDelay: 2000 }"
                @click="resetFilters"
            >
                <X class="size-4" />
            </button>
        </form>

        <div class="mt-4 overflow-x-auto rounded-lg border">
            <div
                class="hidden min-w-[70rem] grid-cols-[minmax(12rem,1.5fr)_minmax(10rem,1fr)_minmax(9rem,1fr)_minmax(13rem,1.3fr)_minmax(9rem,1fr)_9rem] gap-4 bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-700 lg:grid"
            >
                <button
                    class="flex items-center gap-1 text-left"
                    @click="sortBy('name')"
                >
                    Name<ArrowUp
                        v-if="
                            filters.sort === 'name' &&
                            filters.direction === 'asc'
                        "
                        class="size-3.5"
                    /><ArrowDown
                        v-else-if="filters.sort === 'name'"
                        class="size-3.5"
                    /><ArrowUpDown v-else class="size-3.5 text-slate-400" />
                </button>
                <button
                    class="flex items-center gap-1 text-left"
                    @click="sortBy('membership')"
                >
                    Membership<ArrowUp
                        v-if="
                            filters.sort === 'membership' &&
                            filters.direction === 'asc'
                        "
                        class="size-3.5"
                    /><ArrowDown
                        v-else-if="filters.sort === 'membership'"
                        class="size-3.5"
                    /><ArrowUpDown v-else class="size-3.5 text-slate-400" />
                </button>
                <button
                    class="flex items-center gap-1 text-left"
                    @click="sortBy('phone')"
                >
                    Phone<ArrowUp
                        v-if="
                            filters.sort === 'phone' &&
                            filters.direction === 'asc'
                        "
                        class="size-3.5"
                    /><ArrowDown
                        v-else-if="filters.sort === 'phone'"
                        class="size-3.5"
                    /><ArrowUpDown v-else class="size-3.5 text-slate-400" />
                </button>
                <button
                    class="flex items-center gap-1 text-left"
                    @click="sortBy('email')"
                >
                    Email<ArrowUp
                        v-if="
                            filters.sort === 'email' &&
                            filters.direction === 'asc'
                        "
                        class="size-3.5"
                    /><ArrowDown
                        v-else-if="filters.sort === 'email'"
                        class="size-3.5"
                    /><ArrowUpDown v-else class="size-3.5 text-slate-400" />
                </button>
                <button
                    class="flex items-center gap-1 text-left"
                    @click="sortBy('location')"
                >
                    City / State<ArrowUp
                        v-if="
                            filters.sort === 'location' &&
                            filters.direction === 'asc'
                        "
                        class="size-3.5"
                    /><ArrowDown
                        v-else-if="filters.sort === 'location'"
                        class="size-3.5"
                    /><ArrowUpDown v-else class="size-3.5 text-slate-400" />
                </button>
                <span class="sr-only">Account and actions</span>
            </div>
            <div
                v-for="person in people"
                :key="person.id"
                class="grid grid-cols-[minmax(0,1fr)_auto] gap-3 border-t p-4 first:border-t-0 lg:min-w-[70rem] lg:grid-cols-[minmax(12rem,1.5fr)_minmax(10rem,1fr)_minmax(9rem,1fr)_minmax(13rem,1.3fr)_minmax(9rem,1fr)_9rem] lg:items-center lg:gap-4 lg:first:border-t"
            >
                <div class="min-w-0">
                    <p class="break-words font-medium">
                        {{ person.display_name }}
                    </p>
                    <p
                        v-if="membership(person)?.member_number"
                        class="text-xs text-slate-500"
                    >
                        Member {{ membership(person).member_number }}
                    </p>
                    <p
                        v-for="relationship in memberRelationships(person)"
                        :key="relationship.id"
                        class="mt-1 text-xs text-slate-500"
                    >
                        {{ relationship.relationship_name }} of
                        {{ relationship.related_person.display_name }}
                    </p>
                </div>
                <div class="col-span-2 min-w-0 text-sm lg:col-span-1">
                    <span
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 lg:hidden"
                        >Membership</span
                    >
                    <p>
                        {{
                            membership(person)?.degree?.name ||
                            "Degree not recorded"
                        }}
                    </p>
                    <p class="text-slate-600">
                        {{
                            membership(person)?.status?.name || "Related person"
                        }}
                    </p>
                    <div
                        v-if="membership(person)"
                        class="mt-1 flex flex-wrap gap-1"
                    >
                        <span
                            v-if="person.past_master_terms?.length"
                            class="rounded bg-slate-100 px-1.5 py-0.5 text-xs"
                            >PM
                            {{
                                person.past_master_terms
                                    .map((term: any) => term.year)
                                    .join(", ")
                            }}</span
                        ><span
                            v-if="membership(person).is_award_of_gold"
                            class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-900"
                            >Award of Gold</span
                        >
                    </div>
                </div>
                <div class="col-span-2 min-w-0 text-sm lg:col-span-1">
                    <span
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 lg:hidden"
                        >Phone</span
                    >
                    <p class="break-words">
                        {{ formatPhone(person.phone) || "—" }}
                    </p>
                </div>
                <div class="col-span-2 min-w-0 text-sm lg:col-span-1">
                    <span
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 lg:hidden"
                        >Email</span
                    >
                    <p class="break-all">{{ person.email || "—" }}</p>
                </div>
                <div class="col-span-2 min-w-0 text-sm lg:col-span-1">
                    <span
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 lg:hidden"
                        >City / State</span
                    >
                    <p class="break-words">{{ location(person) || "—" }}</p>
                </div>
                <div
                    class="col-start-2 row-start-1 flex items-center justify-end lg:col-start-6"
                >
                    <span
                        class="inline-flex size-10 items-center justify-center rounded-md"
                        role="img"
                        :aria-label="
                            person.user ? 'Account linked' : 'No linked account'
                        "
                        v-tooltip.left="{
                            value: person.user
                                ? 'Account linked'
                                : 'No linked account',
                            showDelay: 2000,
                        }"
                        ><Link2
                            v-if="person.user"
                            class="size-5 text-green-700" /><Link2Off
                            v-else
                            class="size-5 text-slate-400"
                    /></span>
                    <button
                        type="button"
                        :aria-label="`View ${person.display_name}`"
                        class="inline-flex size-10 items-center justify-center rounded-md hover:bg-slate-100"
                        v-tooltip.left="{
                            value: `View ${person.display_name}`,
                            showDelay: 2000,
                        }"
                        @click="openPerson(person, 'view')"
                    >
                        <Eye class="size-4" />
                    </button>
                    <button
                        v-if="person.can_manage"
                        type="button"
                        :aria-label="`Edit ${person.display_name}`"
                        class="inline-flex size-10 items-center justify-center rounded-md hover:bg-slate-100"
                        v-tooltip.left="{
                            value: `Edit ${person.display_name}`,
                            showDelay: 2000,
                        }"
                        @click="openPerson(person, 'edit')"
                    >
                        <Pencil class="size-4" />
                    </button>
                </div>
            </div>
            <p
                v-if="!people.length"
                class="p-8 text-center text-sm text-slate-500"
            >
                No people match these filters.
            </p>
        </div>
    </main>

    <PersonModal
        v-model:open="modalOpen"
        v-model:mode="modalMode"
        :lodge="lodge"
        :person="selectedPerson"
        :membership-types="membershipTypes"
        :membership-statuses="membershipStatuses"
        :degrees="degrees"
        :can-manage-memberships="canManageMemberships"
    />
</template>
