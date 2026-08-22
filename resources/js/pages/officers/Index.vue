<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
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
        <h1 class="text-2xl font-bold">Current officers</h1>
        <p class="text-sm text-slate-600">
            Assign a current member to each position for {{ lodge.name }}.
        </p>

        <div
            v-if="prompt && promptOpen"
            class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
            role="presentation"
            @click.self="promptOpen = false"
        >
            <div
                class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="officer-role-title"
            >
                <h2 id="officer-role-title" class="text-xl font-bold">
                    Review officer access
                </h2>
                <p class="mt-3 text-sm">
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
                <div class="mt-5 flex justify-end gap-2">
                    <button
                        class="rounded border px-4 py-2"
                        @click="promptOpen = false"
                    >
                        Not now</button
                    ><a
                        v-if="prompt.has_linked_user"
                        :href="`/lodges/${lodge.id}/role-assignments`"
                        class="rounded bg-slate-900 px-4 py-2 text-white"
                        >Manage access</a
                    >
                </div>
            </div>
        </div>

        <div class="mt-6 overflow-x-auto rounded-lg border">
            <div
                class="hidden min-w-[58rem] grid-cols-[minmax(10rem,1fr)_minmax(14rem,1.5fr)_repeat(3,minmax(7rem,.7fr))_5.5rem] gap-4 bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-700 md:grid"
            >
                <span>Position</span><span>Member</span><span>Public</span
                ><span>Public email</span><span>Public phone</span
                ><span class="sr-only">Actions</span>
            </div>
            <div
                v-for="position in positions"
                :key="position.id"
                class="grid gap-3 border-t p-4 first:border-t-0 md:min-w-[58rem] md:grid-cols-[minmax(10rem,1fr)_minmax(14rem,1.5fr)_repeat(3,minmax(7rem,.7fr))_5.5rem] md:items-center md:gap-4 md:first:border-t"
            >
                <p class="font-medium">{{ position.name }}</p>
                <select
                    v-model="drafts[position.id].membership_id"
                    :aria-label="`${position.name} member`"
                    class="w-full rounded border p-2"
                >
                    <option :value="null">Unassigned</option>
                    <option
                        v-for="membership in memberships"
                        :key="membership.id"
                        :value="membership.id"
                    >
                        {{ membership.person.display_name }}
                    </option>
                </select>
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
                        class="inline-flex size-10 items-center justify-center rounded-md hover:bg-slate-100 disabled:opacity-40"
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
                        class="inline-flex size-10 items-center justify-center rounded-md text-red-700 hover:bg-red-50"
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
