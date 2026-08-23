<script setup lang="ts">
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from "@/components/ui/dialog";
import { router, useForm } from "@inertiajs/vue3";
import { reactive, ref, watch } from "vue";

const props = defineProps<{
    lodge: { id: number; name: string };
    canManageEvents: boolean;
    canManageRoles: boolean;
    eventCategories: any[];
    roles: any[];
    permissions: any[];
}>();

const categoriesOpen = ref(false);
const rolesOpen = ref(false);
const selectedCategoryIds = ref<number[]>([]);
const roleForm = useForm({ name: "", permission_ids: [] as number[] });
const roleDrafts = reactive<
    Record<number, { name: string; permission_ids: number[] }>
>({});

watch(
    () => props.eventCategories,
    (categories) => {
        selectedCategoryIds.value = categories
            .filter((category) => category.enabled)
            .map((category) => category.id);
    },
    { immediate: true },
);

watch(
    () => props.roles,
    (roles) => {
        roles.forEach((role) => {
            roleDrafts[role.id] = {
                name: role.name,
                permission_ids: role.permissions.map(
                    (permission: any) => permission.id,
                ),
            };
        });
    },
    { immediate: true },
);

const saveCategories = () =>
    router.put(
        `/lodges/${props.lodge.id}/event-categories`,
        { category_ids: selectedCategoryIds.value },
        { onSuccess: () => (categoriesOpen.value = false) },
    );

const createRole = () =>
    roleForm.post(`/lodges/${props.lodge.id}/roles`, {
        onSuccess: () => roleForm.reset(),
    });

const saveRole = (role: any) =>
    router.put(
        `/lodges/${props.lodge.id}/roles/${role.id}`,
        roleDrafts[role.id],
    );
</script>

<template>
    <section
        v-if="canManageEvents || canManageRoles"
        class="mt-10 border-t pt-6"
    >
        <h2 class="text-xl font-semibold">Management configuration</h2>
        <p class="mt-1 text-sm text-muted-foreground">
            Configure the lodge options used by management tools.
        </p>
        <div class="mt-4 flex flex-wrap gap-3">
            <button
                v-if="canManageEvents"
                type="button"
                class="secondary-button"
                @click="categoriesOpen = true"
            >
                Event categories
            </button>
            <button
                v-if="canManageRoles"
                type="button"
                class="secondary-button"
                @click="rolesOpen = true"
            >
                Role definitions
            </button>
        </div>
    </section>

    <Dialog :open="categoriesOpen" @update:open="categoriesOpen = $event">
        <DialogScrollContent class="max-w-xl">
            <DialogHeader>
                <DialogTitle>Event categories</DialogTitle>
                <DialogDescription>
                    Choose the categories {{ lodge.name }} can use for new
                    events.
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-3" @submit.prevent="saveCategories">
                <label
                    v-for="category in eventCategories.filter(
                        (item) => item.is_active,
                    )"
                    :key="category.id"
                    class="flex cursor-pointer items-start gap-3 rounded-lg border border-border bg-card p-4"
                >
                    <input
                        v-model="selectedCategoryIds"
                        :value="category.id"
                        type="checkbox"
                        class="mt-1"
                    />
                    <span>
                        <span class="block font-medium">{{
                            category.name
                        }}</span>
                        <span
                            v-if="category.description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ category.description }}
                        </span>
                    </span>
                </label>
                <DialogFooter>
                    <button type="submit" class="primary-button">
                        Save categories
                    </button>
                </DialogFooter>
            </form>
        </DialogScrollContent>
    </Dialog>

    <Dialog :open="rolesOpen" @update:open="rolesOpen = $event">
        <DialogScrollContent class="max-w-3xl">
            <DialogHeader>
                <DialogTitle>Role definitions</DialogTitle>
                <DialogDescription>
                    Define custom roles and their permissions for
                    {{ lodge.name }}.
                </DialogDescription>
            </DialogHeader>
            <section class="rounded-lg border border-border bg-card p-4">
                <h3 class="font-semibold">Create custom role</h3>
                <form class="mt-3 space-y-4" @submit.prevent="createRole">
                    <label class="field-label">
                        Role name
                        <input
                            v-model="roleForm.name"
                            required
                            class="field-input"
                        />
                    </label>
                    <fieldset>
                        <legend class="text-sm font-medium">
                            Permissions you may grant
                        </legend>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="permission in permissions"
                                :key="permission.id"
                                class="checkbox-field"
                            >
                                <input
                                    v-model="roleForm.permission_ids"
                                    type="checkbox"
                                    :value="permission.id"
                                />
                                <span>{{ permission.name }}</span>
                            </label>
                        </div>
                    </fieldset>
                    <p
                        v-for="message in roleForm.errors"
                        :key="message"
                        class="text-sm text-destructive"
                    >
                        {{ message }}
                    </p>
                    <button
                        :disabled="roleForm.processing"
                        class="primary-button"
                    >
                        Create role
                    </button>
                </form>
            </section>
            <section class="mt-6 space-y-3">
                <h3 class="font-semibold">Existing roles</h3>
                <article
                    v-for="role in roles"
                    :key="role.id"
                    class="rounded-lg border border-border bg-card p-4"
                >
                    <template v-if="role.is_system">
                        <p class="font-medium">
                            {{ role.name }}
                            <span class="text-xs text-muted-foreground"
                                >Built in</span
                            >
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                role.permissions
                                    .map((item: any) => item.name)
                                    .join(", ") || "No permissions"
                            }}
                        </p>
                    </template>
                    <form
                        v-else
                        class="space-y-3"
                        @submit.prevent="saveRole(role)"
                    >
                        <label class="field-label">
                            Role name
                            <input
                                v-model="roleDrafts[role.id].name"
                                required
                                class="field-input"
                            />
                        </label>
                        <fieldset>
                            <legend class="text-sm font-medium">
                                Permissions
                            </legend>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="permission in permissions"
                                    :key="permission.id"
                                    class="checkbox-field"
                                >
                                    <input
                                        v-model="
                                            roleDrafts[role.id].permission_ids
                                        "
                                        type="checkbox"
                                        :value="permission.id"
                                    />
                                    <span>{{ permission.name }}</span>
                                </label>
                            </div>
                        </fieldset>
                        <div class="flex justify-end">
                            <button type="submit" class="secondary-button">
                                Save role
                            </button>
                        </div>
                    </form>
                </article>
            </section>
        </DialogScrollContent>
    </Dialog>
</template>
