<script setup lang="ts">
import InputError from "@/components/InputError.vue";
import AppLayout from "@/layouts/AppLayout.vue";
import { router, useForm } from "@inertiajs/vue3";

defineOptions({ layout: AppLayout });

type GroupType = {
    id: number;
    key: string;
    name: string;
    description: string | null;
    sort_order: number;
    is_active: boolean;
};
type Lodge = { id: number; name: string; number: string; status: string };
type LodgeGroup = {
    id: number;
    lodge_group_type_id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    has_public_landing_page: boolean;
    archived_at: string | null;
    lodge_count: number;
    lodge_ids: number[];
    type: Pick<GroupType, "key" | "name" | "is_active">;
};

const props = defineProps<{
    groups: LodgeGroup[];
    types: GroupType[];
    lodges: Lodge[];
}>();

const groupForm = useForm({
    lodge_group_type_id: "",
    name: "",
    slug: "",
    description: "",
    is_active: true,
    has_public_landing_page: false,
});
const typeForm = useForm({
    key: "",
    name: "",
    description: "",
    sort_order: null as number | null,
    is_active: true,
});

const createGroup = () =>
    groupForm.post("/platform/lodge-groups", {
        onSuccess: () => groupForm.reset(),
    });
const createType = () =>
    typeForm.post("/platform/lodge-group-types", {
        onSuccess: () => typeForm.reset(),
    });
const saveGroup = (group: LodgeGroup) =>
    router.put(`/platform/lodge-groups/${group.id}`, {
        lodge_group_type_id: group.lodge_group_type_id,
        name: group.name,
        slug: group.slug,
        description: group.description,
        is_active: group.is_active,
        has_public_landing_page: group.has_public_landing_page,
    });
const saveLodges = (group: LodgeGroup) =>
    router.put(`/platform/lodge-groups/${group.id}/lodges`, {
        lodge_ids: group.lodge_ids,
    });
const archive = (group: LodgeGroup) => {
    if (window.confirm(`Archive ${group.name}? Membership history stays.`))
        router.patch(`/platform/lodge-groups/${group.id}/archive`);
};
const restore = (group: LodgeGroup) =>
    router.patch(`/platform/lodge-groups/${group.id}/restore`);
const saveType = (type: GroupType) =>
    router.put(`/platform/lodge-group-types/${type.id}`, type);
const toggleType = (type: GroupType) =>
    router.patch(`/platform/lodge-group-types/${type.id}/status`, {
        is_active: type.is_active,
    });
const removeType = (type: GroupType) => {
    if (window.confirm(`Delete unused type ${type.name}?`))
        router.delete(`/platform/lodge-group-types/${type.id}`);
};
</script>

<template>
    <main class="mx-auto w-full max-w-6xl space-y-10 p-4 sm:p-6 lg:p-8">
        <header>
            <h1 class="text-2xl font-bold sm:text-3xl">Lodge groups</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Platform filters only. Groups never grant access or membership.
            </p>
        </header>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Groups</h2>
            <form
                class="grid gap-3 rounded-lg border p-4 md:grid-cols-2"
                @submit.prevent="createGroup"
            >
                <label class="grid gap-1"
                    >Type<select
                        v-model.number="groupForm.lodge_group_type_id"
                        class="field-input"
                        required
                    >
                        <option value="" disabled>Select type</option>
                        <option
                            v-for="type in props.types.filter((item) => item.is_active)"
                            :key="type.id"
                            :value="type.id"
                        >
                            {{ type.name }}
                        </option>
                    </select></label
                ><label class="grid gap-1">Name<input v-model="groupForm.name" class="field-input" required /></label
                ><label class="grid gap-1">Slug<input v-model="groupForm.slug" class="field-input" required /></label
                ><label class="grid gap-1">Description<input v-model="groupForm.description" class="field-input" /></label
                ><label class="flex items-center gap-2 text-sm"><input v-model="groupForm.is_active" type="checkbox" /> Active</label
                ><label class="flex items-center gap-2 text-sm"><input v-model="groupForm.has_public_landing_page" type="checkbox" /> Public landing page</label
                ><button class="primary-button md:col-span-2" :disabled="groupForm.processing">Create group</button
                ><InputError class="md:col-span-2" :message="Object.values(groupForm.errors)[0]" />
            </form>

            <article
                v-for="group in props.groups"
                :key="group.id"
                class="space-y-4 rounded-lg border p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">
                        {{ group.lodge_count }} lodge{{ group.lodge_count === 1 ? "" : "s" }}
                        <span v-if="group.archived_at"> · Archived</span>
                    </p>
                    <div class="flex gap-2">
                        <a
                            v-if="group.has_public_landing_page && group.is_active && !group.archived_at"
                            :href="`/groups/${group.slug}`"
                            target="_blank"
                            class="rounded border px-3 py-2 text-sm"
                            >Preview public page</a
                        >
                        <button v-if="group.archived_at" class="rounded border px-3 py-2 text-sm" @click="restore(group)">Restore</button>
                        <button v-else class="rounded border px-3 py-2 text-sm text-red-700" @click="archive(group)">Archive</button>
                    </div>
                </div>
                <form v-if="!group.archived_at" class="grid gap-3 md:grid-cols-2" @submit.prevent="saveGroup(group)">
                    <label class="grid gap-1">Type<select v-model.number="group.lodge_group_type_id" class="field-input">
                        <option v-for="type in props.types.filter((item) => item.is_active || item.id === group.lodge_group_type_id)" :key="type.id" :value="type.id">{{ type.name }}<template v-if="!type.is_active"> (inactive)</template></option>
                    </select></label
                    ><label class="grid gap-1">Name<input v-model="group.name" class="field-input" required /></label
                    ><label class="grid gap-1">Slug<input v-model="group.slug" class="field-input" required /></label
                    ><label class="grid gap-1">Description<input v-model="group.description" class="field-input" /></label
                    ><label class="flex items-center gap-2 text-sm"><input v-model="group.is_active" type="checkbox" /> Active</label
                    ><label class="flex items-center gap-2 text-sm"><input v-model="group.has_public_landing_page" type="checkbox" /> Public landing page</label
                    ><button class="rounded border px-3 py-2 text-sm md:col-span-2">Save group</button>
                </form>
                <form v-if="!group.archived_at" class="space-y-2 border-t pt-4" @submit.prevent="saveLodges(group)">
                    <p class="text-sm font-medium">Member lodges</p>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label v-for="lodge in props.lodges" :key="lodge.id" class="flex items-center gap-2 text-sm">
                            <input v-model="group.lodge_ids" type="checkbox" :value="lodge.id" />
                            {{ lodge.name }} #{{ lodge.number }}<span v-if="lodge.status !== 'active'" class="text-muted-foreground">({{ lodge.status }})</span>
                        </label>
                    </div>
                    <button class="rounded border px-3 py-2 text-sm">Save lodge memberships</button>
                </form>
            </article>
        </section>

        <section class="space-y-4 border-t pt-8">
            <h2 class="text-xl font-semibold">Group types</h2>
            <form class="grid gap-3 rounded-lg border p-4 md:grid-cols-[1fr_1fr_2fr_6rem_auto]" @submit.prevent="createType">
                <input v-model="typeForm.name" placeholder="Name" class="field-input" required />
                <input v-model="typeForm.key" placeholder="Stable key (optional)" class="field-input" />
                <input v-model="typeForm.description" placeholder="Description" class="field-input" />
                <input v-model.number="typeForm.sort_order" type="number" min="0" placeholder="Order" class="field-input" />
                <button class="primary-button" :disabled="typeForm.processing">Add type</button>
                <InputError class="md:col-span-5" :message="Object.values(typeForm.errors)[0]" />
            </form>
            <form v-for="type in props.types" :key="type.id" class="grid gap-3 rounded-lg border p-4 md:grid-cols-[1fr_1fr_2fr_6rem_auto_auto_auto]" @submit.prevent="saveType(type)">
                <input v-model="type.name" class="field-input" required />
                <input :value="type.key" class="field-input" disabled aria-label="Stable key" />
                <input v-model="type.description" class="field-input" placeholder="Description" />
                <input v-model.number="type.sort_order" type="number" min="0" class="field-input" aria-label="Sort order" />
                <label class="flex items-center gap-2 text-sm"><input v-model="type.is_active" type="checkbox" @change="toggleType(type)" /> Active</label>
                <button class="rounded border px-3 py-2 text-sm">Save</button>
                <button type="button" class="rounded border px-3 py-2 text-sm text-red-700" @click="removeType(type)">Delete</button>
            </form>
        </section>
    </main>
</template>
