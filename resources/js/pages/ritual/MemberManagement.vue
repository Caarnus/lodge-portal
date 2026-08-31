<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import WorkspaceTabs from "@/components/WorkspaceTabs.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import {
    Dialog,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from "@/components/ui/dialog";
import { Head, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";

type Proficiency = {
    ritual_part_id: number;
    status: "not_known" | "learning" | "proficient";
    interested_in_learning: boolean;
    willing_to_assist: boolean;
    performed_for_credit: boolean;
    first_marked_proficient_on: string | null;
};
type Member = {
    membership_id: number;
    display_name: string;
    has_linked_account: boolean;
    visibility_scope: string;
    proficiency_count: number;
    learning_count: number;
    proficient_count: number;
    completed_count: number;
    willing_count: number;
    current_total: number;
    proficiencies: Proficiency[];
    availability: { day_of_week: number; daypart: string }[];
};
type PartForm = Proficiency & { confirm_performed_for_credit: boolean };
type Window = { day_of_week: number; daypart: string };

const props = defineProps<{
    lodge: { id: number; name: string; number: string };
    memberships: Member[];
    categories: {
        id: number;
        name: string;
        parts: { id: number; name: string }[];
    }[];
}>();
const query = ref("");
const status = ref("with_records");
const sort = ref("display_name");
const direction = ref<"asc" | "desc">("asc");
const editing = ref<Member | null>(null);
const dayparts = ["morning", "afternoon", "evening"];
const weekdays = [
    "Monday",
    "Tuesday",
    "Wednesday",
    "Thursday",
    "Friday",
    "Saturday",
    "Sunday",
];
const form = useForm<{ parts: PartForm[]; windows: Window[] }>({
    parts: [],
    windows: [],
});
const rows = computed(() =>
    props.memberships
        .filter((member) => {
            if (
                !member.display_name
                    .toLowerCase()
                    .includes(query.value.trim().toLowerCase())
            )
                return false;
            if (status.value === "with_records")
                return member.proficiency_count > 0;
            if (status.value === "learning") return member.learning_count > 0;
            if (status.value === "proficient")
                return member.proficient_count > 0;
            if (status.value === "completed") return member.completed_count > 0;
            if (status.value === "willing") return member.willing_count > 0;
            return true;
        })
        .sort((left, right) => {
            const a = left[sort.value as keyof Member];
            const b = right[sort.value as keyof Member];
            const comparison =
                typeof a === "string"
                    ? a.localeCompare(b as string)
                    : Number(a) - Number(b);
            return direction.value === "asc" ? comparison : -comparison;
        }),
);
const sortBy = (column: string) => {
    direction.value =
        sort.value === column && direction.value === "asc" ? "desc" : "asc";
    sort.value = column;
};
const sortIcon = (column: string) =>
    sort.value === column ? (direction.value === "asc" ? "↑" : "↓") : "";
const visibilityLabel = (scope: string) =>
    ({
        hidden: "Hidden",
        own_lodge: "Own lodge",
        participating_lodges: "WorkingTools lodges",
    })[scope] ?? scope;
const openEditor = (member: Member) => {
    const saved = new Map(
        member.proficiencies.map((part) => [part.ritual_part_id, part]),
    );
    form.parts = props.categories.flatMap((category) =>
        category.parts.map((part) => {
            const value = saved.get(part.id);
            return {
                ritual_part_id: part.id,
                status: value?.status ?? "not_known",
                interested_in_learning: value?.interested_in_learning ?? false,
                willing_to_assist: value?.willing_to_assist ?? false,
                performed_for_credit: value?.performed_for_credit ?? false,
                confirm_performed_for_credit:
                    value?.performed_for_credit ?? false,
                first_marked_proficient_on:
                    value?.first_marked_proficient_on ?? null,
            };
        }),
    );
    form.windows = member.availability.map((window) => ({ ...window }));
    form.clearErrors();
    editing.value = member;
};
const hasWindow = (day: number, daypart: string) =>
    form.windows.some(
        (window) => window.day_of_week === day && window.daypart === daypart,
    );
const toggleWindow = (day: number, daypart: string) => {
    form.windows = hasWindow(day, daypart)
        ? form.windows.filter(
              (window) =>
                  window.day_of_week !== day || window.daypart !== daypart,
          )
        : [...form.windows, { day_of_week: day, daypart }];
};
const save = () => {
    if (editing.value)
        form.put(
            `/lodges/${props.lodge.id}/ritual-management/${editing.value.membership_id}`,
            {
                onSuccess: () => {
                    editing.value = null;
                },
            },
        );
};
const statusSelectClass = (part: PartForm) => {
    if (part.status === "learning") return "border-sky-500/40 bg-sky-500/10";
    if (part.status === "proficient")
        return "border-emerald-600/40 bg-emerald-500/10";
    return "border-muted-foreground/25 bg-muted/40";
};
const completionClass = (part: PartForm) =>
    part.performed_for_credit
        ? "border-sky-500/40 bg-sky-500/10"
        : "border-muted-foreground/25 bg-muted/40";
</script>

<template>
    <Head :title="`${lodge.name} Ritual Management`" />
    <AppLayout
        :breadcrumbs="[
            {
                title: lodge.name,
                href: `/lodges/${lodge.id}/ritual-management`,
            },
            {
                title: 'Ritual management',
                href: `/lodges/${lodge.id}/ritual-management`,
            },
        ]"
    >
        <main class="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
            <PageHeader
                title="Ritual management"
                :description="`Manage current ${lodge.name} members’ ritual work and broad availability. Private notes are not shown or changed.`"
            />
            <WorkspaceTabs :lodge="lodge" workspace="people" active="ritual" />
            <Card
                ><CardContent
                    class="grid gap-3 pt-6 md:grid-cols-[minmax(0,1fr)_16rem]"
                    ><label class="field-label"
                        >Member name<input
                            v-model="query"
                            class="field-input"
                            placeholder="Filter members" /></label
                    ><label class="field-label"
                        >Show<select v-model="status" class="field-input">
                            <option value="with_records">
                                Members with ritual records
                            </option>
                            <option value="all">All current members</option>
                            <option value="learning">Learning a part</option>
                            <option value="proficient">Proficient</option>
                            <option value="completed">
                                Completed in open lodge
                            </option>
                            <option value="willing">Willing to assist</option>
                        </select></label
                    ></CardContent
                ></Card
            >
            <p class="text-sm text-muted-foreground">
                {{ rows.length }} of {{ memberships.length }} current members
                shown.
            </p>
            <div class="space-y-3 md:hidden">
                <Card v-for="member in rows" :key="member.membership_id"
                    ><CardHeader class="pb-3"
                        ><CardTitle class="text-base">{{
                            member.display_name
                        }}</CardTitle
                        ><CardDescription
                            >{{ member.proficiency_count }} parts ·
                            {{ member.current_total }} points ·
                            {{ member.completed_count }}
                            completed</CardDescription
                        ></CardHeader
                    ><CardContent
                        class="flex items-center justify-between gap-3"
                        ><div class="flex flex-wrap gap-2">
                            <Badge
                                :variant="
                                    member.visibility_scope === 'hidden'
                                        ? 'muted'
                                        : 'default'
                                "
                                >{{
                                    visibilityLabel(member.visibility_scope)
                                }}</Badge
                            ><Badge variant="secondary">{{
                                member.has_linked_account
                                    ? "Linked"
                                    : "No account"
                            }}</Badge>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="openEditor(member)"
                            >Edit</Button
                        ></CardContent
                    ></Card
                >
            </div>
            <Card class="hidden md:block"
                ><CardContent class="overflow-x-auto p-0"
                    ><table class="w-full min-w-[900px] text-left text-sm">
                        <thead
                            class="border-b bg-muted text-xs uppercase tracking-wide text-muted-foreground"
                        >
                            <tr>
                                <th class="p-3">
                                    <button
                                        type="button"
                                        class="font-bold hover:text-foreground"
                                        @click="sortBy('display_name')"
                                    >
                                        MEMBER {{ sortIcon("display_name") }}
                                    </button>
                                </th>
                                <th class="p-3 text-center">
                                    <button
                                        type="button"
                                        class="font-bold hover:text-foreground"
                                        @click="sortBy('proficiency_count')"
                                    >
                                        PARTS
                                        {{ sortIcon("proficiency_count") }}
                                    </button>
                                </th>
                                <th class="p-3 text-center">
                                    <button
                                        type="button"
                                        class="font-bold hover:text-foreground"
                                        @click="sortBy('completed_count')"
                                    >
                                        COMPLETED
                                        {{ sortIcon("completed_count") }}
                                    </button>
                                </th>
                                <th class="p-3 text-center">
                                    <button
                                        type="button"
                                        class="font-bold hover:text-foreground"
                                        @click="sortBy('current_total')"
                                    >
                                        POINTS {{ sortIcon("current_total") }}
                                    </button>
                                </th>
                                <th class="p-3 text-center">
                                    <button
                                        type="button"
                                        class="font-bold hover:text-foreground"
                                        @click="sortBy('willing_count')"
                                    >
                                        WILLING {{ sortIcon("willing_count") }}
                                    </button>
                                </th>
                                <th class="p-3">VISIBILITY</th>
                                <th class="p-3">ACCOUNT</th>
                                <th class="w-24 p-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="member in rows"
                                :key="member.membership_id"
                                class="border-b border-border/50 last:border-0"
                            >
                                <td class="p-3 font-medium">
                                    {{ member.display_name }}
                                </td>
                                <td class="p-3 text-center">
                                    {{ member.proficiency_count
                                    }}<span
                                        v-if="
                                            member.learning_count ||
                                            member.proficient_count
                                        "
                                        class="block text-xs text-muted-foreground"
                                        >{{ member.learning_count }} learning ·
                                        {{ member.proficient_count }}
                                        proficient</span
                                    >
                                </td>
                                <td class="p-3 text-center">
                                    {{ member.completed_count }}
                                </td>
                                <td class="p-3 text-center">
                                    {{ member.current_total }}
                                </td>
                                <td class="p-3 text-center">
                                    {{ member.willing_count }}
                                </td>
                                <td class="p-3">
                                    <Badge
                                        :variant="
                                            member.visibility_scope === 'hidden'
                                                ? 'muted'
                                                : 'default'
                                        "
                                        >{{
                                            visibilityLabel(
                                                member.visibility_scope,
                                            )
                                        }}</Badge
                                    >
                                </td>
                                <td class="p-3 text-muted-foreground">
                                    {{
                                        member.has_linked_account
                                            ? "Linked"
                                            : "No linked account"
                                    }}
                                </td>
                                <td class="p-3">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="openEditor(member)"
                                        >Edit</Button
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table></CardContent
                ></Card
            >
        </main>
    </AppLayout>
    <Dialog :open="!!editing" @update:open="!$event && (editing = null)">
        <DialogScrollContent class="w-[calc(100vw-2rem)] max-w-6xl">
            <DialogHeader
                ><DialogTitle
                    >Edit ritual work — {{ editing?.display_name }}</DialogTitle
                ></DialogHeader
            >
            <form class="space-y-6" @submit.prevent="save">
                <section class="rounded-lg bg-muted/20 p-5">
                    <h2 class="font-semibold">Officer update</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Set credit only after speaking with or observing member.
                        New credit needs confirmation. Private notes remain
                        unchanged.
                    </p>
                </section>
                <section
                    v-for="category in categories"
                    :key="category.id"
                    class="rounded-lg bg-muted/20 p-5"
                >
                    <h2 class="font-semibold">{{ category.name }}</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="part in category.parts" :key="part.id">
                            <div
                                v-for="entry in form.parts.filter(
                                    (value) => value.ritual_part_id === part.id,
                                )"
                                :key="entry.ritual_part_id"
                                class="grid gap-3 rounded-md border border-border/50 bg-background p-3"
                            >
                                <strong>{{ part.name }}</strong>
                                <div
                                    class="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)] xl:items-end"
                                >
                                    <label class="block w-full"
                                        >Status<select
                                            v-model="entry.status"
                                            class="mt-1 block w-full rounded border p-2"
                                            :class="statusSelectClass(entry)"
                                        >
                                            <option value="not_known">
                                                Not known
                                            </option>
                                            <option value="learning">
                                                Learning
                                            </option>
                                            <option value="proficient">
                                                Proficient
                                            </option>
                                        </select></label
                                    >
                                    <label
                                        class="flex min-h-10 items-center gap-2 rounded border border-muted-foreground/25 bg-muted/40 px-3 py-2"
                                        ><input
                                            v-model="
                                                entry.interested_in_learning
                                            "
                                            type="checkbox"
                                        />
                                        Interested in learning</label
                                    >
                                    <label
                                        class="flex min-h-10 items-center gap-2 rounded border border-muted-foreground/25 bg-muted/40 px-3 py-2"
                                        ><input
                                            v-model="entry.willing_to_assist"
                                            type="checkbox"
                                            :disabled="
                                                entry.status !== 'proficient'
                                            "
                                        />
                                        Willing to assist</label
                                    >
                                    <label
                                        class="rounded border px-3 py-2"
                                        :class="completionClass(entry)"
                                        ><span class="flex items-center gap-2"
                                            ><input
                                                v-model="
                                                    entry.performed_for_credit
                                                "
                                                type="checkbox"
                                            />
                                            Open-lodge credit</span
                                        ><span
                                            v-if="
                                                entry.performed_for_credit &&
                                                !editing?.proficiencies.some(
                                                    (saved) =>
                                                        saved.ritual_part_id ===
                                                            entry.ritual_part_id &&
                                                        saved.performed_for_credit,
                                                )
                                            "
                                            class="mt-2 flex items-center gap-2 text-xs"
                                            ><input
                                                v-model="
                                                    entry.confirm_performed_for_credit
                                                "
                                                type="checkbox"
                                            />
                                            Confirm open-lodge performance</span
                                        ></label
                                    >
                                    <label class="block w-full"
                                        >First proficient<input
                                            v-model="
                                                entry.first_marked_proficient_on
                                            "
                                            type="date"
                                            class="mt-1 block w-full rounded border p-2"
                                    /></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <section class="rounded-lg bg-muted/20 p-5">
                    <h2 class="font-semibold">General availability</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Choose broad weekday/daypart windows. This is not a
                        reservation or commitment.
                    </p>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full min-w-[600px] text-sm">
                            <thead>
                                <tr>
                                    <th class="p-2 text-left">Day</th>
                                    <th
                                        v-for="daypart in dayparts"
                                        :key="daypart"
                                        class="p-2 text-center capitalize"
                                    >
                                        {{ daypart }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(weekday, index) in weekdays"
                                    :key="weekday"
                                >
                                    <th class="p-2 text-left font-medium">
                                        {{ weekday }}
                                    </th>
                                    <td
                                        v-for="daypart in dayparts"
                                        :key="daypart"
                                        class="p-2 text-center"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="
                                                hasWindow(index + 1, daypart)
                                            "
                                            :aria-label="
                                                weekday + ' ' + daypart
                                            "
                                            @change="
                                                toggleWindow(index + 1, daypart)
                                            "
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <p
                    v-if="form.errors.parts"
                    class="rounded border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
                    role="alert"
                >
                    {{ form.errors.parts }}
                </p>
                <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded border px-4 py-2"
                        @click="editing = null"
                    >
                        Cancel</button
                    ><button
                        type="submit"
                        class="rounded bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        Save changes
                    </button>
                </div>
            </form>
        </DialogScrollContent>
    </Dialog>
</template>
