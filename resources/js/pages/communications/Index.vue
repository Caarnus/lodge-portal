<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import RichTextField from "@/components/website/RichTextField.vue";
import { formatLocalTimestamp } from "@/utils/date";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Head, router, useForm } from "@inertiajs/vue3";
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Pencil,
    Plus,
    Search,
    Trash2,
} from "lucide-vue-next";
import { computed, onMounted, reactive, ref, watch } from "vue";
defineOptions({ layout: AppLayout });
const props = defineProps<{
    lodge: any;
    communications: any[];
    memberships: any[];
    relations: any[];
    editCommunicationId: number | null;
    filters: {
        search: string;
        status: string;
        sort: string;
        direction: string;
    };
}>();
const filters = reactive({ ...props.filters });
let filterTimer: ReturnType<typeof setTimeout> | undefined;
const applyFilters = () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(`/lodges/${props.lodge.id}/communications/manage`, filters, {
            preserveState: true,
            replace: true,
        });
    }, 300);
};
watch(filters, applyFilters, { deep: true });
const sort = (column: string) => {
    filters.direction =
        filters.sort === column && filters.direction === "asc" ? "desc" : "asc";
    filters.sort = column;
};
const timestamp = formatLocalTimestamp;
const sortIcon = (column: string) =>
    filters.sort !== column
        ? ArrowUpDown
        : filters.direction === "asc"
          ? ArrowUp
          : ArrowDown;
const form = useForm({
    subject: "",
    body_html: "<p></p>",
    audience_mode: "all",
    degree_keys: [] as string[],
    membership_status_keys: [] as string[],
    membership_ids: [] as number[],
    relation_person_ids: [] as number[],
});
const creating = ref(false);
const editingId = ref<number | null>(null);
const editingSent = ref(false);
const expandedSubjectId = ref<number | null>(null);
const toggleSubject = (id: number) => {
    expandedSubjectId.value = expandedSubjectId.value === id ? null : id;
};
const selectorOpen = ref(false);
const selectorSearch = ref("");
const appliedSelectorSearch = ref("");
const selectorType = ref<"all" | "member" | "relation">("all");
const selectorSortColumn = ref<"name" | "type" | "details">("name");
const selectorSortDirection = ref<"asc" | "desc">("asc");
let selectorSearchTimer: ReturnType<typeof setTimeout> | undefined;

watch(selectorSearch, (value) => {
    clearTimeout(selectorSearchTimer);
    selectorSearchTimer = setTimeout(() => {
        appliedSelectorSearch.value = value;
    }, 300);
});

const selectorRows = computed(() => {
    const search = appliedSelectorSearch.value.trim().toLowerCase();
    const rows = [
        ...props.memberships.map((membership) => ({
            id: membership.id,
            kind: "member" as const,
            name: membership.person.display_name,
            type: "Member",
            details: `${membership.degree?.name ?? "No degree"} / ${membership.status?.name ?? "No status"}`,
        })),
        ...props.relations.map((relation) => ({
            id: relation.person_id,
            kind: "relation" as const,
            name: relation.name,
            type: "Relation",
            details: `${relation.type} of ${relation.related_to}`,
        })),
    ].filter((row) => {
        if (selectorType.value !== "all" && row.kind !== selectorType.value) {
            return false;
        }

        return (
            !search ||
            `${row.name} ${row.type} ${row.details}`
                .toLowerCase()
                .includes(search)
        );
    });

    return rows.sort((left, right) => {
        const result = left[selectorSortColumn.value].localeCompare(
            right[selectorSortColumn.value],
        );

        return selectorSortDirection.value === "asc" ? result : -result;
    });
});
const isSelected = (row: { id: number; kind: "member" | "relation" }) =>
    row.kind === "member"
        ? form.membership_ids.includes(row.id)
        : form.relation_person_ids.includes(row.id);
const toggleSelected = (
    row: { id: number; kind: "member" | "relation" },
    checked: boolean,
) => {
    const selected =
        row.kind === "member" ? form.membership_ids : form.relation_person_ids;
    const index = selected.indexOf(row.id);

    if (checked && index === -1) selected.push(row.id);
    if (!checked && index !== -1) selected.splice(index, 1);
};
const sortSelector = (column: "name" | "type" | "details") => {
    selectorSortDirection.value =
        selectorSortColumn.value === column &&
        selectorSortDirection.value === "asc"
            ? "desc"
            : "asc";
    selectorSortColumn.value = column;
};
const selectorSortIcon = (column: "name" | "type" | "details") =>
    selectorSortColumn.value !== column
        ? ArrowUpDown
        : selectorSortDirection.value === "asc"
          ? ArrowUp
          : ArrowDown;
const openSelector = () => {
    selectorSearch.value = "";
    appliedSelectorSearch.value = "";
    selectorType.value = "all";
    selectorOpen.value = true;
};
const degrees = Array.from(
    new Map(
        props.memberships
            .filter((membership) => membership.degree)
            .map((membership) => [membership.degree.key, membership.degree]),
    ).values(),
);
const statuses = Array.from(
    new Map(
        props.memberships
            .filter((membership) => membership.status)
            .map((membership) => [membership.status.key, membership.status]),
    ).values(),
);
const submit = (sendNow: boolean) => {
    const close = () => {
        creating.value = false;
        editingId.value = null;
        editingSent.value = false;
        form.reset();
    };
    const options = {
        onSuccess: close,
    };
    form.transform((data) => ({ ...data, send_now: sendNow }));
    if (editingId.value) {
        form.put(
            `/lodges/${props.lodge.id}/communications/manage/${editingId.value}`,
            {
                onSuccess: () => {
                    if (sendNow) {
                        close();
                        router.post(
                            `/lodges/${props.lodge.id}/communications/manage/${editingId.value}/send`,
                        );
                    } else {
                        close();
                    }
                },
            },
        );
    } else {
        form.post(`/lodges/${props.lodge.id}/communications/manage`, options);
    }
};
const open = (item?: any) => {
    editingId.value = item?.id ?? null;
    editingSent.value = item?.status === "sent";
    form.subject = item?.subject ?? "";
    form.body_html = item?.body_html ?? "<p></p>";
    form.audience_mode = item?.audience_mode ?? "all";
    form.degree_keys = item?.degree_keys ?? [];
    form.membership_status_keys = item?.membership_status_keys ?? [];
    form.membership_ids = item?.membership_ids ?? [];
    form.relation_person_ids = item?.relation_person_ids ?? [];
    creating.value = true;
};
onMounted(() => {
    const item = props.communications.find(
        (communication) => communication.id === props.editCommunicationId,
    );
    if (item) open(item);
});
</script>
<template>
    <Head title="Communications" />
    <main class="mx-auto max-w-5xl space-y-6 p-6">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-3xl font-bold">Lodge communications</h1>
            <button class="primary-button shrink-0" @click="open()">
                <Plus class="mr-1 inline size-4" /> New message
            </button>
        </div>
        <div class="flex flex-col gap-3 rounded border p-4 md:flex-row">
            <label class="relative md:flex-1"
                ><Search
                    class="absolute left-3 top-3 size-4 text-muted-foreground" /><input
                    v-model="filters.search"
                    type="search"
                    placeholder="Search messages"
                    class="field-input w-full pl-9"
            /></label>
            <select v-model="filters.status" class="field-input md:w-48">
                <option value="">All statuses</option>
                <option value="draft">Draft</option>
                <option value="sending">Sending</option>
                <option value="sent">Sent</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        <div class="hidden overflow-x-auto rounded border md:block">
            <table class="w-full table-fixed text-left text-sm">
                <colgroup>
                    <col />
                    <col class="w-28" />
                    <col class="w-24" />
                    <col class="w-36" />
                    <col class="w-36" />
                    <col class="w-24" />
                </colgroup>
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="p-3">
                            <button
                                class="inline-flex items-center gap-1 whitespace-nowrap"
                                @click="sort('subject')"
                            >
                                Title<component
                                    :is="sortIcon('subject')"
                                    class="size-3"
                                />
                            </button>
                        </th>
                        <th class="p-3">
                            <button
                                class="inline-flex items-center gap-1 whitespace-nowrap"
                                @click="sort('recipient_count')"
                            >
                                Recipients<component
                                    :is="sortIcon('recipient_count')"
                                    class="size-3"
                                />
                            </button>
                        </th>
                        <th class="p-3">
                            <button
                                class="inline-flex items-center gap-1 whitespace-nowrap"
                                @click="sort('status')"
                            >
                                Status<component
                                    :is="sortIcon('status')"
                                    class="size-3"
                                />
                            </button>
                        </th>
                        <th class="p-3">
                            <button
                                class="inline-flex items-center gap-1 whitespace-nowrap"
                                @click="sort('created_at')"
                            >
                                Created<component
                                    :is="sortIcon('created_at')"
                                    class="size-3"
                                />
                            </button>
                        </th>
                        <th class="p-3">
                            <button
                                class="inline-flex items-center gap-1 whitespace-nowrap"
                                @click="sort('sent_at')"
                            >
                                Sent<component
                                    :is="sortIcon('sent_at')"
                                    class="size-3"
                                />
                            </button>
                        </th>
                        <th class="p-3">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in communications"
                        :key="item.id"
                        class="border-b last:border-0"
                    >
                        <td class="p-3 font-medium">
                            <button
                                type="button"
                                class="block w-full text-left hover:underline"
                                :class="
                                    expandedSubjectId === item.id
                                        ? 'whitespace-normal break-words'
                                        : 'truncate'
                                "
                                :aria-expanded="expandedSubjectId === item.id"
                                :title="
                                    expandedSubjectId === item.id
                                        ? 'Collapse title'
                                        : 'Expand title'
                                "
                                @click="toggleSubject(item.id)"
                            >
                                {{ item.subject }}
                            </button>
                        </td>
                        <td class="p-3">{{ item.recipient_count ?? 0 }}</td>
                        <td class="p-3 capitalize">{{ item.status }}</td>
                        <td class="p-3">{{ timestamp(item.created_at) }}</td>
                        <td class="p-3">{{ timestamp(item.sent_at) }}</td>
                        <td class="w-24 px-1 py-3 text-right">
                            <span
                                class="inline-flex items-center gap-1 whitespace-nowrap"
                            >
                                <button
                                    class="icon-button"
                                    title="Edit message"
                                    @click="open(item)"
                                >
                                    <Pencil class="size-4" />
                                </button>
                                <button
                                    v-if="item.status === 'draft'"
                                    class="icon-button text-destructive"
                                    title="Delete draft"
                                    @click="
                                        router.delete(
                                            `/lodges/${lodge.id}/communications/manage/${item.id}`,
                                        )
                                    "
                                >
                                    <Trash2 class="size-4" />
                                </button>
                                <span v-else class="inline-block size-10" />
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="space-y-3 md:hidden">
            <article
                v-for="item in communications"
                :key="item.id"
                class="rounded border p-4"
            >
                <div>
                    <strong>{{ item.subject }}</strong>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Recipients</dt>
                        <dd>{{ item.recipient_count ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Status</dt>
                        <dd class="capitalize">{{ item.status }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Created</dt>
                        <dd>{{ timestamp(item.created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Sent</dt>
                        <dd>{{ timestamp(item.sent_at) }}</dd>
                    </div>
                </dl>
                <div class="mt-4 flex justify-end gap-1">
                    <button
                        class="icon-button"
                        title="Edit message"
                        @click="open(item)"
                    >
                        <Pencil class="size-4" />
                    </button>
                    <button
                        v-if="item.status === 'draft'"
                        class="icon-button text-destructive"
                        title="Delete draft"
                        @click="
                            router.delete(
                                `/lodges/${lodge.id}/communications/manage/${item.id}`,
                            )
                        "
                    >
                        <Trash2 class="size-4" />
                    </button>
                    <span v-else class="inline-block size-10" />
                </div>
            </article>
        </div>
        <Dialog :open="creating" @update:open="creating = $event">
            <DialogContent class="w-[calc(100vw-2rem)] max-w-3xl">
                <DialogHeader
                    ><DialogTitle>{{
                        editingId ? "Edit message" : "New message"
                    }}</DialogTitle></DialogHeader
                >
                <form
                    v-if="!editingSent"
                    class="grid w-full min-w-0 gap-3"
                    @submit.prevent="submit(false)"
                >
                    <input
                        v-model="form.subject"
                        required
                        placeholder="Subject"
                        class="field-input"
                    /><RichTextField v-model="form.body_html" />
                    <fieldset class="grid gap-3 border-t pt-4">
                        <legend class="font-semibold">Recipients</legend>
                        <select
                            v-model="form.audience_mode"
                            class="field-input"
                        >
                            <option value="all">All eligible members</option>
                            <option value="filtered">
                                Filter by degree or status
                            </option>
                            <option value="selected">
                                Selected members and relations
                            </option>
                        </select>
                        <template v-if="form.audience_mode === 'filtered'">
                            <fieldset>
                                <legend class="mb-2 text-sm font-medium">
                                    Degrees
                                </legend>
                                <label
                                    v-for="degree in degrees"
                                    :key="degree.key"
                                    class="mr-4 inline-flex items-center gap-2"
                                    ><input
                                        v-model="form.degree_keys"
                                        type="checkbox"
                                        :value="degree.key"
                                    />{{ degree.name }}</label
                                >
                            </fieldset>
                            <fieldset>
                                <legend class="mb-2 text-sm font-medium">
                                    Membership statuses
                                </legend>
                                <label
                                    v-for="status in statuses"
                                    :key="status.key"
                                    class="mr-4 inline-flex items-center gap-2"
                                    ><input
                                        v-model="form.membership_status_keys"
                                        type="checkbox"
                                        :value="status.key"
                                    />{{ status.name }}</label
                                >
                            </fieldset>
                        </template>
                        <template v-if="form.audience_mode === 'selected'">
                            <button
                                type="button"
                                class="rounded border border-border bg-card px-3 py-2 text-left hover:bg-accent"
                                @click="openSelector"
                            >
                                Select people ({{
                                    form.membership_ids.length +
                                    form.relation_person_ids.length
                                }})
                            </button>
                        </template>
                    </fieldset>
                    <div class="flex gap-3">
                        <button
                            class="rounded border border-border bg-card px-4 py-2 hover:bg-accent"
                        >
                            Create draft
                        </button>
                        <button
                            type="button"
                            class="primary-button"
                            @click="submit(true)"
                        >
                            Send now
                        </button>
                    </div>
                </form>
                <section v-else class="space-y-4">
                    <article class="public-rich-text" v-html="form.body_html" />
                    <p>
                        Sent messages remain unchanged. Create an editable copy
                        to revise its text or recipients and send it again.
                    </p>
                    <button
                        class="primary-button"
                        @click="
                            router.post(
                                `/lodges/${lodge.id}/communications/manage/${editingId}/duplicate`,
                            )
                        "
                    >
                        Create editable resend
                    </button>
                </section>
            </DialogContent>
        </Dialog>
        <Dialog :open="selectorOpen" @update:open="selectorOpen = $event">
            <DialogContent class="max-w-4xl">
                <DialogHeader
                    ><DialogTitle>Select people</DialogTitle></DialogHeader
                >
                <div class="flex flex-col gap-3 md:flex-row">
                    <label class="relative block md:flex-1"
                        ><Search
                            class="absolute left-3 top-3 size-4 text-muted-foreground" /><input
                            v-model="selectorSearch"
                            class="field-input w-full pl-9"
                            placeholder="Search"
                    /></label>
                    <select v-model="selectorType" class="field-input md:w-40">
                        <option value="all">All people</option>
                        <option value="member">Members</option>
                        <option value="relation">Relations</option>
                    </select>
                </div>
                <div
                    class="hidden max-h-96 overflow-auto rounded border md:block"
                >
                    <table class="w-full text-left text-sm">
                        <thead class="sticky top-0 bg-muted">
                            <tr>
                                <th class="w-12 p-3"></th>
                                <th class="p-3">
                                    <button
                                        class="inline-flex items-center gap-1"
                                        @click="sortSelector('name')"
                                    >
                                        Name
                                        <component
                                            :is="selectorSortIcon('name')"
                                            class="size-3"
                                        />
                                    </button>
                                </th>
                                <th class="p-3">
                                    <button
                                        class="inline-flex items-center gap-1"
                                        @click="sortSelector('type')"
                                    >
                                        Type
                                        <component
                                            :is="selectorSortIcon('type')"
                                            class="size-3"
                                        />
                                    </button>
                                </th>
                                <th class="p-3">
                                    <button
                                        class="inline-flex items-center gap-1"
                                        @click="sortSelector('details')"
                                    >
                                        Details
                                        <component
                                            :is="selectorSortIcon('details')"
                                            class="size-3"
                                        />
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in selectorRows"
                                :key="`${row.kind}-${row.id}`"
                                class="border-t"
                            >
                                <td class="p-3">
                                    <input
                                        type="checkbox"
                                        :checked="isSelected(row)"
                                        @change="
                                            toggleSelected(
                                                row,
                                                (
                                                    $event.target as HTMLInputElement
                                                ).checked,
                                            )
                                        "
                                    />
                                </td>
                                <td class="p-3 font-medium">
                                    {{ row.name }}
                                </td>
                                <td class="p-3">{{ row.type }}</td>
                                <td class="p-3">{{ row.details }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="space-y-2 md:hidden">
                    <label
                        v-for="row in selectorRows"
                        :key="`${row.kind}-${row.id}`"
                        class="flex gap-3 rounded border p-3"
                        ><input
                            type="checkbox"
                            :checked="isSelected(row)"
                            @change="
                                toggleSelected(
                                    row,
                                    ($event.target as HTMLInputElement).checked,
                                )
                            "
                        /><span
                            ><strong>{{ row.name }}</strong
                            ><br /><small
                                >{{ row.type }} · {{ row.details }}</small
                            ></span
                        ></label
                    >
                </div>
                <button
                    class="primary-button w-fit"
                    @click="selectorOpen = false"
                >
                    Done
                </button>
            </DialogContent>
        </Dialog>
    </main>
</template>
