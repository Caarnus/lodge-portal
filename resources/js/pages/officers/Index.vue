<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import WorkspaceTabs from "@/components/WorkspaceTabs.vue";
import { SearchableSelect } from "@/components/ui/searchable-select";
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from "@/components/ui/dialog";
import { Head, router, usePage } from "@inertiajs/vue3";
import { Save, UserMinus } from "lucide-vue-next";
import Tooltip from "primevue/tooltip";
import { computed, reactive, ref, watch } from "vue";

defineOptions({ layout: AppLayout });
const vTooltip = Tooltip;
const props = defineProps<{
    lodge: any;
    assignments: any[];
    memberships: any[];
    positions: any[];
}>();
const page = usePage<any>();
const drafts = reactive<
    Record<
        number,
        {
            membership_id: number | null;
            is_public: boolean;
            show_email: boolean;
            show_phone: boolean;
        }
    >
>({});
const prompt = computed(() => page.props.flash?.officer_role_prompt);
const promptOpen = ref(Boolean(page.props.flash?.officer_role_prompt));
const assignmentFor = (positionId: number) =>
    props.assignments.find((item) => item.officer_position_id === positionId);
const memberOptions = computed(() =>
    props.memberships.map((membership) => ({
        value: membership.id,
        label: membership.person.display_name,
    })),
);

watch(
    () => props.assignments,
    () => {
        for (const position of props.positions) {
            const assignment = assignmentFor(position.id);
            drafts[position.id] = {
                membership_id: assignment?.membership_id ?? null,
                is_public: assignment?.is_public ?? true,
                show_email: assignment?.show_email ?? false,
                show_phone: assignment?.show_phone ?? false,
            };
        }
    },
    { immediate: true },
);
watch(prompt, (value) => {
    if (value) promptOpen.value = true;
});

const save = (position: any) => {
    const assignment = assignmentFor(position.id);
    const data = { ...drafts[position.id], officer_position_id: position.id };
    if (assignment)
        router.put(
            `/lodges/${props.lodge.id}/officers/${assignment.id}`,
            data,
            { preserveScroll: true },
        );
    else
        router.post(`/lodges/${props.lodge.id}/officers`, data, {
            preserveScroll: true,
        });
};
const remove = (position: any) => {
    const assignment = assignmentFor(position.id);
    if (assignment && confirm(`Remove the ${position.name} assignment?`)) {
        router.delete(`/lodges/${props.lodge.id}/officers/${assignment.id}`, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head :title="`${lodge.name} officers`" />
    <main class="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8">
        <PageHeader
            title="Current officers"
            :description="`Assign a current member to each position for ${lodge.name}.`"
        />
        <WorkspaceTabs
            :lodge="lodge"
            workspace="people"
            active="officers"
            class="mt-6"
        />

        <Dialog :open="promptOpen" @update:open="promptOpen = $event">
            <DialogScrollContent v-if="prompt" class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Review officer access</DialogTitle>
                    <DialogDescription>
                        Officer assignment and lodge access are managed
                        separately.
                    </DialogDescription>
                </DialogHeader>
                <p class="text-sm">
                    <template v-if="prompt.has_linked_user"
                        >The officer assignment was saved separately from
                        access. Review {{ prompt.person_name }}'s lodge roles
                        now.<span
                            v-if="
                                prompt.action === 'remove' &&
                                prompt.has_other_current_assignment
                            "
                        >
                            Another officer assignment remains, so retaining the
                            Officer role is recommended.</span
                        ></template
                    ><template v-else
                        >{{ prompt.person_name }} has no linked account. Invite
                        and link an account before assigning a role.</template
                    >
                </p>
                <DialogFooter>
                    <button
                        class="secondary-button"
                        @click="promptOpen = false"
                    >
                        Not now</button
                    ><a
                        v-if="prompt.has_linked_user"
                        :href="`/lodges/${lodge.id}/role-assignments`"
                        class="primary-button"
                        >Manage access</a
                    >
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <div
            class="mt-6 overflow-hidden rounded-lg border border-border/80 bg-card"
        >
            <div
                class="hidden grid-cols-[minmax(10rem,1fr)_minmax(14rem,1.5fr)_repeat(3,minmax(7rem,.7fr))_5.5rem] gap-4 bg-muted px-4 py-3 text-sm font-semibold text-muted-foreground md:grid"
            >
                <span>Position</span><span>Member</span><span>Public</span
                ><span>Public email</span><span>Public phone</span
                ><span class="sr-only">Actions</span>
            </div>
            <div
                v-for="position in positions"
                :key="position.id"
                class="grid gap-3 border-t border-border/60 p-4 transition-colors first:border-t-0 hover:bg-muted/35 md:grid-cols-[minmax(10rem,1fr)_minmax(14rem,1.5fr)_repeat(3,minmax(7rem,.7fr))_5.5rem] md:items-center md:gap-4"
            >
                <p class="font-medium">{{ position.name }}</p>
                <SearchableSelect
                    v-model="drafts[position.id].membership_id"
                    :options="memberOptions"
                    placeholder="Unassigned"
                    filter-placeholder="Filter members"
                    :ariaLabel="`${position.name} member`"
                    empty-label="No members match this filter."
                />
                <label class="flex items-center gap-2 text-sm"
                    ><input
                        v-model="drafts[position.id].is_public"
                        type="checkbox"
                    />
                    <span>Show officer</span></label
                >
                <label class="flex items-center gap-2 text-sm"
                    ><input
                        v-model="drafts[position.id].show_email"
                        type="checkbox"
                    />
                    <span>Show email</span></label
                >
                <label class="flex items-center gap-2 text-sm"
                    ><input
                        v-model="drafts[position.id].show_phone"
                        type="checkbox"
                    />
                    <span>Show phone</span></label
                >
                <div class="flex justify-end gap-1">
                    <button
                        type="button"
                        :disabled="!drafts[position.id].membership_id"
                        :aria-label="`Save ${position.name}`"
                        class="icon-button disabled:opacity-40"
                        v-tooltip.left="{
                            value: 'Save assignment',
                            showDelay: 2000,
                        }"
                        @click="save(position)"
                    >
                        <Save class="size-4" />
                    </button>
                    <button
                        v-if="assignmentFor(position.id)"
                        type="button"
                        :aria-label="`Remove ${position.name} assignment`"
                        class="icon-button text-destructive hover:bg-destructive/10"
                        v-tooltip.left="{
                            value: 'Remove assignment',
                            showDelay: 2000,
                        }"
                        @click="remove(position)"
                    >
                        <UserMinus class="size-4" />
                    </button>
                </div>
            </div>
        </div>
    </main>
</template>
