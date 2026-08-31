<script lang="ts" setup>
import InputError from "@/components/InputError.vue";
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import {router, useForm} from "@inertiajs/vue3";

defineOptions({layout: AppLayout});

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
        <PageHeader
            description="Platform filters only. Groups never grant access or membership."
            title="Lodge groups"
        />

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">Groups</h2>
            <form
                class="grid gap-3 rounded-lg border border-border/80 bg-card p-4 md:grid-cols-2"
                @submit.prevent="createGroup"
            >
                <label class="grid gap-1"
                >Type<select
                    v-model.number="groupForm.lodge_group_type_id"
                    class="field-input"
                    required
                >
                    <option disabled value="">Select type</option>
                    <option
                        v-for="type in props.types.filter(
                                (item) => item.is_active,
                            )"
                        :key="type.id"
                        :value="type.id"
                    >
                        {{ type.name }}
                    </option>
                </select></label
                ><label class="grid gap-1"
            >Name<input
                v-model="groupForm.name"
                class="field-input"
                required/></label
            ><label class="grid gap-1"
            >Slug<input
                v-model="groupForm.slug"
                class="field-input"
                required/></label
            ><label class="grid gap-1"
            >Description<input
                v-model="groupForm.description"
                class="field-input"/></label
            ><label class="flex items-center gap-2 text-sm"
            ><input v-model="groupForm.is_active" type="checkbox"/>
                Active</label
            ><label class="flex items-center gap-2 text-sm"
            ><input
                v-model="groupForm.has_public_landing_page"
                type="checkbox"
            />
                Public landing page</label
            >
                <button
                    :disabled="groupForm.processing"
                    class="primary-button md:col-span-2"
                >
                    Create group
                </button
                >
                <InputError
                    :message="Object.values(groupForm.errors)[0]"
                    class="md:col-span-2"
                />
            </form>

            <article
                v-for="group in props.groups"
                :key="group.id"
                class="space-y-4 rounded-lg border border-border/80 bg-card p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">
                        {{ group.lodge_count }} lodge{{
                            group.lodge_count === 1 ? "" : "s"
                        }}
                        <span v-if="group.archived_at"> · Archived</span>
                    </p>
                    <div class="flex gap-2">
                        <a
                            v-if="
                                group.has_public_landing_page &&
                                group.is_active &&
                                !group.archived_at
                            "
                            :href="`/groups/${group.slug}`"
                            class="secondary-button"
                            target="_blank"
                        >Preview public page</a
                        >
                        <button
                            v-if="group.archived_at"
                            class="secondary-button"
                            @click="restore(group)"
                        >
                            Restore
                        </button>
                        <button
                            v-else
                            class="secondary-button text-destructive hover:bg-destructive/10"
                            @click="archive(group)"
                        >
                            Archive
                        </button>
                    </div>
                </div>
                <form
                    v-if="!group.archived_at"
                    class="grid gap-3 md:grid-cols-2"
                    @submit.prevent="saveGroup(group)"
                >
                    <label class="grid gap-1"
                    >Type<select
                        v-model.number="group.lodge_group_type_id"
                        class="field-input"
                    >
                        <option
                            v-for="type in props.types.filter(
                                    (item) =>
                                        item.is_active ||
                                        item.id === group.lodge_group_type_id,
                                )"
                            :key="type.id"
                            :value="type.id"
                        >
                            {{
                                type.name
                            }}
                            <template v-if="!type.is_active">
                                (inactive)
                            </template
                            >
                        </option>
                    </select></label
                    ><label class="grid gap-1"
                >Name<input
                    v-model="group.name"
                    class="field-input"
                    required/></label
                ><label class="grid gap-1"
                >Slug<input
                    v-model="group.slug"
                    class="field-input"
                    required/></label
                ><label class="grid gap-1"
                >Description<input
                    v-model="group.description"
                    class="field-input"/></label
                ><label class="flex items-center gap-2 text-sm"
                ><input v-model="group.is_active" type="checkbox"/>
                    Active</label
                ><label class="flex items-center gap-2 text-sm"
                ><input
                    v-model="group.has_public_landing_page"
                    type="checkbox"
                />
                    Public landing page</label
                >
                    <button class="secondary-button md:col-span-2">
                        Save group
                    </button>
                </form>
                <form
                    v-if="!group.archived_at"
                    class="space-y-2 border-t pt-4"
                    @submit.prevent="saveLodges(group)"
                >
                    <p class="text-sm font-medium">Member lodges</p>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label
                            v-for="lodge in props.lodges"
                            :key="lodge.id"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                v-model="group.lodge_ids"
                                :value="lodge.id"
                                type="checkbox"
                            />
                            {{ lodge.name }} #{{
                                lodge.number
                            }}<span
                            v-if="lodge.status !== 'active'"
                            class="text-muted-foreground"
                        >({{ lodge.status }})</span
                        >
                        </label>
                    </div>
                    <button class="secondary-button">
                        Save lodge memberships
                    </button>
                </form>
            </article>
        </section>

        <section class="space-y-4 border-t pt-8">
            <h2 class="text-xl font-semibold">Group types</h2>
            <form
                class="grid gap-3 rounded-lg border border-border/80 bg-card p-4 md:grid-cols-[1fr_1fr_2fr_6rem_auto]"
                @submit.prevent="createType"
            >
                <input
                    v-model="typeForm.name"
                    class="field-input"
                    placeholder="Name"
                    required
                />
                <input
                    v-model="typeForm.key"
                    class="field-input"
                    placeholder="Stable key (optional)"
                />
                <input
                    v-model="typeForm.description"
                    class="field-input"
                    placeholder="Description"
                />
                <input
                    v-model.number="typeForm.sort_order"
                    class="field-input"
                    min="0"
                    placeholder="Order"
                    type="number"
                />
                <button :disabled="typeForm.processing" class="primary-button">
                    Add type
                </button>
                <InputError
                    :message="Object.values(typeForm.errors)[0]"
                    class="md:col-span-5"
                />
            </form>
            <form
                v-for="type in props.types"
                :key="type.id"
                class="grid gap-3 rounded-lg border border-border/80 bg-card p-4 md:grid-cols-[1fr_1fr_2fr_6rem_auto_auto_auto]"
                @submit.prevent="saveType(type)"
            >
                <input v-model="type.name" class="field-input" required/>
                <input
                    :value="type.key"
                    aria-label="Stable key"
                    class="field-input"
                    disabled
                />
                <input
                    v-model="type.description"
                    class="field-input"
                    placeholder="Description"
                />
                <input
                    v-model.number="type.sort_order"
                    aria-label="Sort order"
                    class="field-input"
                    min="0"
                    type="number"
                />
                <label class="flex items-center gap-2 text-sm"
                ><input
                    v-model="type.is_active"
                    type="checkbox"
                    @change="toggleType(type)"
                />
                    Active</label
                >
                <button class="secondary-button">Save</button>
                <button
                    class="secondary-button text-destructive hover:bg-destructive/10"
                    type="button"
                    @click="removeType(type)"
                >
                    Delete
                </button>
            </form>
        </section>
    </main>
</template>
