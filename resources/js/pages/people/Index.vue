<script setup lang="ts">
import PersonModal from "@/components/people/PersonModal.vue";
import WorkspaceTabs from "@/components/WorkspaceTabs.vue";
import AppLayout from "@/layouts/AppLayout.vue";
import { formatPhone } from "@/lib/phone";
import { Head, router } from "@inertiajs/vue3";
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Award,
    ChevronDown,
    Church,
    DraftingCompass,
    Eye,
    Link2,
    Link2Off,
    Pencil,
    Plus,
    SlidersHorizontal,
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
    relationshipTypes: any[];
    availablePeople: any[];
    canManage: boolean;
    canManageMemberships: boolean;
    canManageRoles: boolean;
    canManageCommunicationPreferences: boolean;
}>();
const filters = reactive<Filters>({ ...props.filters });
const selectedPerson = ref<any | null>(null);
const modalOpen = ref(false);
const modalMode = ref<"view" | "edit">("view");
const filtersOpen = ref(false);
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
    <main class="mx-auto w-full max-w-7xl p-4 md:p-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">People</h1>
                <p class="text-sm text-slate-600">{{ lodge.name }}</p>
            </div>
            <button
                v-if="canManage"
                class="primary-button shrink-0"
                @click="openPerson(null, 'edit')"
            >
                <Plus class="mr-1 size-4" /> Add person
            </button>
        </div>

        <WorkspaceTabs :lodge="lodge" workspace="people" active="people" class="mt-6" />

        <p class="admin-warning mt-4">
            This is the administrative People workspace. It can show full
            lodge-reachable records; member directory privacy controls
            presentation in Directory and does not erase lodge records. Only the
            member may change their privacy choices in Profile settings.
        </p>

        <section class="mt-6 rounded-lg border bg-slate-50">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 p-4 text-left font-medium"
                :aria-expanded="filtersOpen"
                aria-controls="people-filters"
                @click="filtersOpen = !filtersOpen"
            >
                <span class="inline-flex items-center gap-2"
                    ><SlidersHorizontal class="size-4" /> Search and
                    filters</span
                >
                <ChevronDown
                    class="size-4 transition-transform"
                    :class="filtersOpen && 'rotate-180'"
                />
            </button>
            <form
                v-show="filtersOpen"
                id="people-filters"
                class="grid gap-3 border-t p-4 md:grid-cols-3"
                @submit.prevent
            >
                <label class="min-w-0"
                    ><span class="text-sm font-medium">Search</span
                    ><input
                        v-model="filters.search"
                        class="field-input mt-1"
                        placeholder="Name, email, phone, city, or member no."
                /></label>
                <label
                    ><span class="text-sm font-medium">Status</span
                    ><select v-model="filters.status" class="field-input mt-1">
                        <option :value="null">All statuses</option>
                        <option
                            v-for="item in membershipStatuses"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name
                            }}{{ item.is_active ? "" : " (inactive)" }}
                        </option>
                    </select></label
                >
                <label
                    ><span class="text-sm font-medium">Degree</span
                    ><select v-model="filters.degree" class="field-input mt-1">
                        <option :value="null">All degrees</option>
                        <option
                            v-for="item in degrees"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name
                            }}{{ item.is_active ? "" : " (inactive)" }}
                        </option>
                    </select></label
                >
                <label
                    ><span class="text-sm font-medium">Account</span
                    ><select v-model="filters.account" class="field-input mt-1">
                        <option value="all">Any account</option>
                        <option value="linked">Linked</option>
                        <option value="unlinked">Not linked</option>
                    </select></label
                >
                <label
                    ><span class="text-sm font-medium">Record type</span
                    ><select v-model="filters.scope" class="field-input mt-1">
                        <option value="all">Members and relatives</option>
                        <option value="members">Members only</option>
                        <option value="related">Related people only</option>
                    </select></label
                >
                <button
                    type="button"
                    class="icon-button mt-6"
                    aria-label="Clear filters"
                    v-tooltip.top="{ value: 'Clear filters', showDelay: 2000 }"
                    @click="resetFilters"
                >
                    <X class="size-4" />
                </button>
            </form>
        </section>

        <div class="mt-4 rounded-lg border overflow-hidden">
            <div
                class="hidden grid-cols-[minmax(12rem,1.5fr)_minmax(9rem,1fr)_minmax(8rem,0.8fr)_16rem] gap-4 bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-700 md:grid"
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
                class="grid gap-3 border-t p-4 max-md:first:border-t-0 md:grid-cols-[minmax(12rem,1.5fr)_minmax(9rem,1fr)_minmax(8rem,0.8fr)_16rem] md:items-center md:gap-4"
            >
                <div class="min-w-0">
                    <p class="wrap-break-word font-medium">
                        {{ person.display_name }}
                    </p>
                    <p
                        class="mt-1 hidden truncate text-xs text-slate-500 md:block"
                    >
                        {{ formatPhone(person.phone) || "No phone" }}
                    </p>
                    <p
                        class="hidden truncate text-xs text-slate-500 md:block"
                        :title="person.email || 'No email'"
                    >
                        {{ person.email || "No email" }}
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
                <div class="min-w-0 text-sm">
                    <span
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 md:hidden"
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
                    <p
                        v-if="membership(person)?.member_number"
                        class="text-xs text-slate-500"
                    >
                        Member {{ membership(person).member_number }}
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
                        >
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 md:hidden">
                    <div class="min-w-0 text-sm">
                        <span
                            class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >Phone</span
                        >
                        <p class="wrap-break-word">
                            {{ formatPhone(person.phone) || "—" }}
                        </p>
                    </div>
                    <div class="min-w-0 text-sm">
                        <span
                            class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >City / State</span
                        >
                        <p class="wrap-break-word">
                            {{ location(person) || "—" }}
                        </p>
                    </div>
                </div>
                <div class="min-w-0 text-sm md:hidden">
                    <span
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >Email</span
                    >
                    <p class="break-all">{{ person.email || "—" }}</p>
                </div>
                <div class="hidden min-w-0 text-sm md:block">
                    <span
                        class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 md:hidden"
                        >City / State</span
                    >
                    <p class="wrap-break-word">{{ location(person) || "—" }}</p>
                </div>
                <div
                    class="flex items-center justify-end gap-2 md:col-start-4 md:row-start-1"
                >
                    <div class="flex min-w-38 items-center justify-end gap-2">
                        <span
                            v-if="person.is_deceased"
                            class="person-status-icon inline-flex size-8 items-center justify-center"
                            role="img"
                            aria-label="Deceased"
                            v-tooltip.left="{
                                value: 'Deceased',
                                showDelay: 2000,
                            }"
                        >
                            <Church class="size-4" />
                        </span>
                        <span
                            v-if="membership(person)?.is_award_of_gold"
                            class="person-award-icon inline-flex size-8 items-center justify-center"
                            role="img"
                            aria-label="Award of Gold"
                            v-tooltip.left="{
                                value: 'Award of Gold',
                                showDelay: 2000,
                            }"
                        >
                            <Award class="size-4" />
                        </span>
                        <span
                            v-if="person.past_master_terms?.length"
                            class="person-status-icon inline-flex size-8 items-center justify-center"
                            role="img"
                            aria-label="Past Master"
                            v-tooltip.left="{
                                value: 'Past Master',
                                showDelay: 2000,
                            }"
                        >
                            <DraftingCompass class="size-4" />
                        </span>
                        <span
                            class="inline-flex size-8 items-center justify-center"
                            role="img"
                            :aria-label="
                                person.user
                                    ? 'Account linked'
                                    : 'No linked account'
                            "
                            v-tooltip.left="{
                                value: person.user
                                    ? 'Account linked'
                                    : 'No linked account',
                                showDelay: 2000,
                            }"
                            ><Link2
                                v-if="person.user"
                                class="person-account-linked-icon size-4" /><Link2Off
                                v-else
                                class="person-account-unlinked-icon size-4"
                        /></span>
                    </div>
                    <button
                        type="button"
                        :aria-label="`View ${person.display_name}`"
                        class="icon-button"
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
                        class="icon-button"
                        v-tooltip.left="{
                            value: `Edit ${person.display_name}`,
                            showDelay: 2000,
                        }"
                        @click="openPerson(person, 'edit')"
                    >
                        <Pencil class="size-4" />
                    </button>
                    <span v-else class="inline-block size-10" />
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
        :relationship-types="relationshipTypes"
        :available-people="availablePeople"
        :can-manage-memberships="canManageMemberships"
        :can-manage-roles="canManageRoles"
        :can-manage-communication-preferences="
            canManageCommunicationPreferences
        "
    />
</template>
