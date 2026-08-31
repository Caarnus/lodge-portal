<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import PageHeader from "@/components/PageHeader.vue";
import WorkspaceTabs from "@/components/WorkspaceTabs.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { reactive } from "vue";

defineOptions({ layout: AppLayout });
const props = defineProps<{ lodge: any; roles: any[]; permissions: any[] }>();
const roleForm = useForm({ name: "", permission_ids: [] as number[] });
const roleDrafts = reactive(
    Object.fromEntries(
        props.roles.map((role) => [
            role.id,
            {
                name: role.name,
                permission_ids: role.permissions.map(
                    (permission: any) => permission.id,
                ),
            },
        ]),
    ),
);
const createRole = () =>
    roleForm.post(`/lodges/${props.lodge.id}/roles`, {
        onSuccess: () => roleForm.reset(),
    });
const updateRole = (role: any) =>
    router.put(
        `/lodges/${props.lodge.id}/roles/${role.id}`,
        roleDrafts[role.id],
    );
</script>

<template>
    <Head :title="`${lodge.name} role definitions`" />
    <main class="mx-auto w-full max-w-5xl p-4 sm:p-6 lg:p-8">
        <PageHeader title="Role definitions" :description="lodge.name">
            <template #actions
                ><Link
                    :href="`/lodges/${lodge.id}/role-assignments`"
                    class="primary-button"
                    >Manage role assignments</Link
                ></template
            >
        </PageHeader>
        <WorkspaceTabs
            :lodge="lodge"
            workspace="settings"
            active="roles"
            class="mt-6"
        />
        <section
            class="mt-6 rounded-lg border border-border/80 bg-card p-4 sm:p-5"
        >
            <h2 class="font-semibold">Create custom role</h2>
            <form class="mt-3" @submit.prevent="createRole">
                <label
                    >Role name<input
                        v-model="roleForm.name"
                        required
                        class="field-input mt-1"
                /></label>
                <fieldset class="mt-4">
                    <legend class="font-medium">
                        Permissions you may grant
                    </legend>
                    <label
                        v-for="permission in permissions"
                        :key="permission.id"
                        class="mt-2 flex gap-2"
                        ><input
                            v-model="roleForm.permission_ids"
                            type="checkbox"
                            :value="permission.id"
                        />
                        {{ permission.name }}</label
                    >
                </fieldset>
                <p
                    v-for="message in roleForm.errors"
                    :key="message"
                    class="mt-2 text-sm text-destructive"
                >
                    {{ message }}
                </p>
                <button class="primary-button mt-4">Create role</button>
            </form>
        </section>
        <section class="mt-6">
            <h2 class="font-semibold">Existing roles</h2>
            <div
                v-for="role in roles"
                :key="role.id"
                class="mt-3 rounded-lg border border-border/80 bg-card p-4"
            >
                <template v-if="role.is_system"
                    ><p class="font-medium">
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
                    </p></template
                >
                <form v-else @submit.prevent="updateRole(role)">
                    <label class="font-medium"
                        >Role name<input
                            v-model="roleDrafts[role.id].name"
                            required
                            class="field-input mt-1"
                    /></label>
                    <fieldset class="mt-3">
                        <legend class="text-sm font-medium">Permissions</legend>
                        <label
                            v-for="permission in permissions"
                            :key="permission.id"
                            class="mt-2 flex gap-2 text-sm"
                            ><input
                                v-model="roleDrafts[role.id].permission_ids"
                                type="checkbox"
                                :value="permission.id"
                            />
                            {{ permission.name }}</label
                        >
                    </fieldset>
                    <button class="secondary-button mt-3">Save role</button>
                </form>
            </div>
        </section>
    </main>
</template>
